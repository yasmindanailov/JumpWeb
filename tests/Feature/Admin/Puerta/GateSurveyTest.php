<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\PuertaSettings;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Domain\Platform\Services\Surveys\SurveySeals;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La encuesta INTERNA en la puerta** (`docs/specs/encuestas.md` §4.2 y §4.7; `DECISIONES #740` y `#754`) y, desde la P1b
 * de la Puerta nueva (`docs/specs/puerta-nueva.md` §4.4; `#817`·3), **PREGUNTA A PREGUNTA y solo en verde**: se ofrece SOLO
 * con la visita de hoy acreditada, una encuesta interna viva y sin participación de este cliente; la tarjeta nace preguntando
 * la primera; cada toque se TIPA y se VALIDA en el servidor contra SU pregunta; el primero escribe la participación y la
 * respuesta ANÓNIMA y sellada, y los siguientes la completan; «Ahora no» sin nada contestado es el «no preguntar» de siempre
 * y, con algo, cierra con lo contestado. Una por cliente y encuesta; la franja, nunca la hora; al libro, nada; un rastro que
 * dice a quién sin decir qué pasó.
 */
class GateSurveyTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-25';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->travelTo(Carbon::parse(self::TODAY.' 11:00:00', 'Europe/Madrid'));
    }

    // ─── Fixture ──────────────────────────────────────────────────────────────

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);
        RateLimiter::clear("puerta:validate:user:{$u->id}");
        RateLimiter::clear("puerta:lookup:user:{$u->id}");

        return $u;
    }

    private function staffWithoutProfile(): User
    {
        $u = $this->staff();
        $u->roles->first()->permissions()->detach(Permission::where('name', 'puerta.profile')->value('id'));

        return $u;
    }

    /**
     * @return array{0: User, 1: string} el cliente y su carné en claro. Con su descargo (el modo de las pruebas es el
     *                                   externo: lo dice su sello), así que el veredicto es VERDE y la tarjeta se pinta.
     */
    private function customer(bool $conDescargo = true): array
    {
        $holder = User::factory()->create(['name' => 'Ana Titular', 'email' => 'ana@example.com', 'email_verified_at' => now(), 'waiver_accepted_at' => $conDescargo ? now() : null]);
        $holder->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return [$holder, (string) app(CustomerCards::class)->ensureFor($holder)->plainToken()];
    }

    /** Los cinco tipos de pregunta, en este orden; dos de ellas obligatorias. */
    private function survey(array $overrides = []): Survey
    {
        return Survey::create(array_merge([
            'key' => 'visita-de-hoy',
            'name' => ['es' => 'Tu visita de hoy', 'en' => 'Your visit today'],
            'intro' => ['es' => 'Dos minutos, con la persona delante.'],
            'kind' => Survey::KIND_INTERNAL,
            'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'required' => true, 'label' => ['es' => '¿Qué tal el ambiente?']],
                ['key' => 'como', 'type' => 'choice', 'required' => true, 'label' => ['es' => '¿Cómo nos conociste?'], 'options' => [
                    ['key' => 'google', 'label' => ['es' => 'Google']],
                    ['key' => 'amigos', 'label' => ['es' => 'Amigos']],
                ]],
                ['key' => 'zonas', 'type' => 'multi', 'label' => ['es' => '¿Qué zonas usasteis?'], 'options' => [
                    ['key' => 'jump', 'label' => ['es' => 'Jump']],
                    ['key' => 'kids', 'label' => ['es' => 'Kids']],
                ]],
                ['key' => 'volveria', 'type' => 'yesno', 'label' => ['es' => '¿Volverías?']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más']],
            ],
        ], $overrides));
    }

    /** La ficha abierta por CARNÉ, como en el mostrador: el escaneo acredita la visita (`#741`). */
    private function open(User $staff, string $token): Testable
    {
        return Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search');
    }

    private function closedAudits(): int
    {
        return AuditLog::query()->where('action', 'like', 'puerta.survey_%')->count();
    }

    // ─── Cuándo se ofrece ─────────────────────────────────────────────────────

    public function test_without_a_live_internal_survey_nothing_is_offered_even_with_the_visit_registered(): void
    {
        [, $token] = $this->customer();
        // Una externa viva y una interna APAGADA: ninguna de las dos es una oferta para la puerta.
        $this->survey(['key' => 'externa', 'kind' => Survey::KIND_EXTERNAL]);
        $this->survey(['key' => 'apagada', 'active' => false]);

        $page = $this->open($this->staff(), $token);

        $page->assertSet('profile.visit_registered_today', true)
            ->assertSet('survey', null)
            ->assertDontSee('data-gate-survey', false);
    }

    /** `#741`: el ESCANEO acredita la visita y la tarjeta sale en el mismo gesto, ya preguntando la primera (la P1b). */
    public function test_a_scan_accredits_the_visit_and_asks_the_first_question_in_the_same_gesture(): void
    {
        [$holder, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token);

        $page->assertSet('profile.via', 'card')
            ->assertSet('profile.visit_registered_today', true)
            ->assertSet('survey.state', 'asking')
            ->assertSet('survey.step', 0)
            ->assertSet('survey.key', 'visita-de-hoy')
            ->assertSee('data-gate-survey="asking"', false)
            ->assertSee('Pregúntale.')
            ->assertSee('data-gate-question="ambiente"', false)
            ->assertSee('¿Qué tal el ambiente?')
            ->assertDontSee('¿Cómo nos conociste?')
            ->assertSee('data-gate-survey-skip', false)
            // La intro del panel y el aviso del anonimato, sobre la PRIMERA pregunta (D7). Tecleados a mano (`#734`).
            ->assertSee('Dos minutos, con la persona delante.')
            ->assertSee('data-gate-survey-notice', false)
            ->assertSee('Es anónima: nadie en el parque verá tu nombre junto a tus respuestas, y a los 90 días se separan de ti del todo.');
        $this->assertSame(1, CustomerVisit::query()->where('user_id', $holder->id)->count());
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'visit_checked_in')->count());

        // La oferta no escribe nada de la encuesta: ni participación, ni respuesta, ni rastro. Eso lo hace el primer toque.
        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertSame(0, $this->closedAudits());

        // Un segundo escaneo el mismo día no duplica la visita, y vuelve a preguntar (sigue sin respuesta).
        $this->open($this->staff(), $token)->assertSet('survey.state', 'asking');
        $this->assertSame(1, CustomerVisit::query()->count());
    }

    /** `#756`: buscar por correo o móvil TAMBIÉN acredita la visita (con su origen, `lookup`), y la encuesta se ofrece. */
    public function test_a_typed_lookup_registers_the_visit_as_lookup_and_offers_the_survey(): void
    {
        [$holder] = $this->customer();
        $this->survey();

        $page = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', 'ana@example.com')->call('search');
        $page->assertSet('profile.via', 'lookup')
            ->assertSet('profile.visit_registered_today', true)
            ->assertSet('survey.state', 'asking');

        $visit = CustomerVisit::query()->sole();
        $this->assertSame($holder->id, (int) $visit->user_id);
        $this->assertSame('lookup', $visit->source);

        $page->call('registerVisit');
        Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', 'ana@example.com')->call('search');
        $this->assertSame(1, CustomerVisit::query()->count());
    }

    public function test_without_the_profile_permission_there_is_no_sheet_and_no_survey(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staffWithoutProfile(), $token);

        $page->assertSet('profile', null)->assertSet('survey', null);
        $page->call('answerQuestion', 'ambiente', '4')->assertStatus(403);
    }

    /**
     * ▶ **Solo en VERDE** (el mockup): sin el descargo, el veredicto es ámbar y la tarjeta no se PINTA; el servidor la sigue
     * ofreciendo, pero nada se escribe hasta el primer toque.
     */
    public function test_without_the_waiver_the_card_is_not_painted_and_nothing_is_written(): void
    {
        [, $token] = $this->customer(conDescargo: false);
        $this->survey();

        $page = $this->open($this->staff(), $token);

        $page->assertSet('survey.state', 'asking')->assertDontSee('data-gate-survey', false)->assertSee('Falta firmar el descargo');
        $this->assertSame(0, SurveyParticipation::query()->count());
    }

    // ─── Contestar, pregunta a pregunta ───────────────────────────────────────

    public function test_each_tap_answers_one_question_the_first_opens_the_row_and_the_rest_complete_it(): void
    {
        [$holder, $token] = $this->customer();
        $survey = $this->survey();
        $staff = $this->staff();

        $page = $this->open($staff, $token);

        // Primer toque: la escala, con su CADENA, como llega de un botón. Abre la participación y la respuesta.
        $page->call('answerQuestion', 'ambiente', '4')
            ->assertHasNoErrors()
            ->assertSet('survey.state', 'asking')
            ->assertSet('survey.step', 1)
            ->assertSee('¿Cómo nos conociste?')
            ->assertDontSee('data-gate-survey-notice', false);

        $asked = SurveyParticipation::query()->sole();
        $this->assertSame($holder->id, (int) $asked->user_id);
        $this->assertSame($staff->id, (int) $asked->asked_by);
        $this->assertSame(self::TODAY, $asked->asked_on->toDateString());
        $row = SurveyResponse::query()->sole();
        $this->assertSame(['ambiente' => 4], $row->answers);
        $this->assertSame(SurveyResponse::BAND_MORNING, $row->band);
        $this->assertSame($staff->id, (int) $row->asked_by);
        $this->assertFalse($row->declined);
        $this->assertCount(1, app(SurveySeals::class)->sealedFor($holder->id), 'contestada = sellada');
        $this->assertSame(1, $this->closedAudits(), 'el rastro, UNO, al nacer la participación');

        // Los siguientes completan la MISMA fila. El «no» de `volveria` es a propósito: «0» no puede guardarse como sí.
        $page->call('answerQuestion', 'como', 'google')
            ->set('surveyAnswers.zonas', ['jump', 'kids'])
            ->call('answerQuestion', 'zonas')
            ->call('answerQuestion', 'volveria', '0')
            ->set('surveyAnswers.comentario', '  Genial, volveremos.  ')
            ->call('answerQuestion', 'comentario')
            ->assertHasNoErrors()
            ->assertSet('survey.state', 'answered')
            ->assertSee('data-gate-survey="answered"', false)
            ->assertSee('Guardado.');

        $this->assertSame(1, SurveyParticipation::query()->count());
        $this->assertSame(
            ['ambiente' => 4, 'como' => 'google', 'zonas' => ['jump', 'kids'], 'volveria' => false, 'comentario' => 'Genial, volveremos.'],
            SurveyResponse::query()->sole()->answers,
            'una sola fila, completada toque a toque y TIPADA por su pregunta',
        );
        $this->assertSame(1, $this->closedAudits(), 'completar no deja más rastros');
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'like', 'survey_%')->count(), 'al libro, nada (`#754`)');

        // Y no se vuelve a ofrecer.
        $this->open($staff, $token)->assertSet('survey', null);
    }

    /** «Siguiente» sin nada marcado SALTA la pregunta: en la puerta una obligatoria no frena (`#817`·3). */
    public function test_an_empty_next_skips_the_question_without_writing(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token)->call('answerQuestion', 'ambiente', '5')->call('answerQuestion', 'como', 'amigos');
        $page->call('answerQuestion', 'zonas')->assertHasNoErrors()->assertSet('survey.step', 3);

        $this->assertSame(['ambiente' => 5, 'como' => 'amigos'], SurveyResponse::query()->sole()->answers);
    }

    public function test_now_not_without_any_answer_is_the_old_decline(): void
    {
        [$holder, $token] = $this->customer();
        $this->survey();
        $staff = $this->staff();

        $page = $this->open($staff, $token)->call('skipSurvey');

        $page->assertSet('survey.state', 'declined')->assertSee('data-gate-survey="declined"', false);
        $this->assertSame($holder->id, (int) SurveyParticipation::query()->sole()->user_id);
        $row = SurveyResponse::query()->sole();
        $this->assertTrue($row->declined);
        $this->assertNull($row->answers);
        $this->assertNull(DB::table('survey_responses')->value('seal'), 'declinada, sin sello');
        $audit = AuditLog::query()->where('action', 'like', 'puerta.survey_%')->sole();
        $this->assertSame('puerta.survey_closed', $audit->action);
        $this->assertSame(['survey' => 'visita-de-hoy'], $audit->payload, 'el mismo rastro que al contestar: sin desenlace');

        $this->open($staff, $token)->assertSet('survey', null);
    }

    public function test_now_not_after_some_answers_closes_with_what_was_answered(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $this->open($this->staff(), $token)->call('answerQuestion', 'ambiente', '3')->call('skipSurvey')
            ->assertSet('survey.state', 'answered');

        $row = SurveyResponse::query()->sole();
        $this->assertFalse($row->declined);
        $this->assertSame(['ambiente' => 3], $row->answers, '«deja el resto»: lo contestado se queda');
    }

    public function test_an_impossible_value_is_refused_and_nothing_is_written(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token);
        $page->call('answerQuestion', 'ambiente', '6')
            ->assertHasErrors(['surveyAnswers.ambiente'])
            ->assertSet('survey.step', 0)
            ->assertSee('data-gate-survey-error="ambiente"', false);
        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, $this->closedAudits());

        $page->call('answerQuestion', 'ambiente', '5')->call('answerQuestion', 'como', 'tiktok')->assertHasErrors(['surveyAnswers.como']);
        $page->call('answerQuestion', 'como', 'google')->call('answerQuestion', 'zonas')->call('answerQuestion', 'volveria', '1');
        $page->set('surveyAnswers.comentario', str_repeat('a', 301))->call('answerQuestion', 'comentario')->assertHasErrors(['surveyAnswers.comentario']);

        $this->assertSame(['ambiente' => 5, 'como' => 'google', 'volveria' => true], SurveyResponse::query()->sole()->answers);
    }

    /** El navegador no decide: una clave que la encuesta GUARDADA no tiene no escribe nada, y lo contestado no se pisa. */
    public function test_the_browser_copy_decides_nothing(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token);
        $page->set('survey.questions.0.key', 'inventada')->call('answerQuestion', 'inventada', '4')->assertSet('survey.step', 0);
        $this->assertSame(0, SurveyResponse::query()->count());

        $page->call('answerQuestion', 'ambiente', '4')->call('answerQuestion', 'ambiente', '1');
        $this->assertSame(['ambiente' => 4], SurveyResponse::query()->sole()->answers, 'lo contestado no se pisa');
    }

    // ─── Los límites: la ficha caduca, la encuesta se apaga, otra tablet ya contestó ────────────────

    public function test_the_survey_dies_with_the_sheet_and_writes_nothing(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token);
        $this->travel(PuertaSettings::profileTtlMinutes() + 1)->minutes();

        $page->call('answerQuestion', 'ambiente', '5')->assertSet('profile', null)->assertSet('survey', null);
        $this->assertSame(0, SurveyParticipation::query()->count(), 'una ficha caducada no escribe: se vuelve a ofrecer en la siguiente búsqueda');
    }

    public function test_a_survey_switched_off_between_the_offer_and_the_answer_is_not_written(): void
    {
        [, $token] = $this->customer();
        $survey = $this->survey();

        $page = $this->open($this->staff(), $token);
        $survey->update(['active' => false]);

        $page->call('answerQuestion', 'ambiente', '5')->assertSet('survey', null);
        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    public function test_a_stale_tablet_cannot_write_a_second_response_for_the_same_customer(): void
    {
        [$holder, $token] = $this->customer();
        $survey = $this->survey();

        $page = $this->open($this->staff(), $token);

        // Otra tablet (u otro operador) contesta por este cliente mientras ésta sigue abierta.
        $this->assertTrue(app(SurveyResponses::class)->answerInPerson($survey, $holder->id, ['ambiente' => 5, 'como' => 'google'], null));

        $page->call('answerQuestion', 'ambiente', '2')->assertHasNoErrors()->assertSet('survey.state', 'answered');

        $this->assertSame(1, SurveyParticipation::query()->count());
        $this->assertSame(['ambiente' => 5, 'como' => 'google'], SurveyResponse::query()->sole()->answers, 'la primera respuesta manda');
        $this->assertSame(0, $this->closedAudits(), 'sin escritura no hay rastro que inventar');
    }

    /** La FRANJA sale de la hora del PARQUE (hasta las 13:00, hasta las 16:00, y después), nunca de la hora exacta. */
    public function test_the_response_keeps_the_band_of_the_park_and_never_the_hour(): void
    {
        [, $token] = $this->customer();
        $this->survey();
        // Las 16:00 en Madrid son las 14:00 en UTC: la franja tiene que salir de la hora del PARQUE (tarde), no de la UTC.
        $this->travelTo(Carbon::parse(self::TODAY.' 16:00:00', 'Europe/Madrid'));

        $this->open($this->staff(), $token)->call('answerQuestion', 'ambiente', '3')->assertHasNoErrors();

        $this->assertSame(SurveyResponse::BAND_AFTERNOON, SurveyResponse::query()->sole()->band);
    }
}
