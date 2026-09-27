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
 * **La encuesta INTERNA en la puerta** (`docs/specs/encuestas.md` §4.2 y §4.7, T2 y T5; `DECISIONES #740` y `#754`): se
 * ofrece SOLO con la visita de hoy acreditada, una encuesta interna viva y sin participación de este cliente; el
 * operador lee el aviso, pregunta y marca; lo marcado se tipa y se valida en el SERVIDOR; una por cliente y encuesta;
 * «No preguntar» también es una fila. Desde `#754`, ANÓNIMA: la participación dice a quién se preguntó, la respuesta lo
 * que contestó SIN nadie (día, franja, empleado); al libro no va nada; el rastro dice a quién sin decir qué pasó.
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

    /** @return array{0: User, 1: string} el cliente y su carné en claro */
    private function customer(): array
    {
        $holder = User::factory()->create(['name' => 'Ana Titular', 'email' => 'ana@example.com', 'email_verified_at' => now()]);
        $holder->roles()->sync([Role::where('name', 'customer')->value('id')]);

        return [$holder, (string) app(CustomerCards::class)->ensureFor($holder)->plainToken()];
    }

    /** Los cinco tipos de pregunta, dos de ellas obligatorias. */
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

    /** La ficha abierta por CARNÉ, como en el mostrador. */
    private function open(User $staff, string $token): Testable
    {
        return Livewire::actingAs($staff)->test(ValidarRegistro::class)->set('input', $token)->call('search');
    }

    /** La ficha abierta y la visita de hoy acreditada: el momento en que la spec dice que se ofrece. */
    private function openWithVisit(User $staff, string $token): Testable
    {
        return $this->open($staff, $token)->call('registerVisit');
    }

    // ─── Cuándo se ofrece ─────────────────────────────────────────────────────

    public function test_without_a_live_internal_survey_nothing_is_offered_even_with_the_visit_registered(): void
    {
        [, $token] = $this->customer();
        // Una externa viva y una interna APAGADA: ninguna de las dos es una oferta para la puerta.
        $this->survey(['key' => 'externa', 'kind' => Survey::KIND_EXTERNAL]);
        $this->survey(['key' => 'apagada', 'active' => false]);

        $page = $this->openWithVisit($this->staff(), $token);

        $page->assertSet('profile.visit_registered_today', true)
            ->assertSet('survey', null)
            ->assertDontSee('data-gate-survey', false);
    }

    /** `#741`: el ESCANEO acredita la visita y la tarjeta sale en el mismo gesto, sin botón. */
    public function test_a_scan_accredits_the_visit_and_offers_the_survey_in_the_same_gesture(): void
    {
        [$holder, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token);

        $page->assertSet('profile.holder_name', 'Ana Titular')
            ->assertSet('profile.via', 'card')
            ->assertSet('profile.visit_registered_today', true)
            ->assertSet('survey.state', 'offer')
            ->assertSet('survey.key', 'visita-de-hoy')
            ->assertSet('survey.count', 5)
            ->assertSee('data-gate-survey="offer"', false)
            ->assertSee('Tu visita de hoy')
            ->assertSee('data-gate-survey-open', false)
            ->assertSee('data-gate-survey-decline', false);
        $this->assertSame(1, CustomerVisit::query()->where('user_id', $holder->id)->count());
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'visit_checked_in')->count());
        $this->assertSame($holder->id, (int) $page->get('profileUserId'));

        // La oferta no escribe nada de la encuesta: ni participación, ni respuesta, ni rastro. Eso lo hace el operador.
        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'puerta.survey_%')->count());

        // `#754`: el aviso del anonimato, para leerlo en voz alta, en la tarjeta de la oferta. Tecleado a mano (`#734`).
        $page->assertSee('data-gate-survey-notice', false)
            ->assertSee('Es anónima: nadie en el parque verá tu nombre junto a tus respuestas, y a los 90 días se separan de ti del todo.');

        // Un segundo escaneo el mismo día no duplica la visita, y vuelve a ofrecer (sigue sin respuesta).
        $this->open($this->staff(), $token)->assertSet('survey.state', 'offer');
        $this->assertSame(1, CustomerVisit::query()->count());
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'visit_checked_in')->count());
    }

    /**
     * `#756` (el owner, 27-09): buscar por correo o móvil TAMBIÉN acredita la visita, como el escaneo, y la encuesta se
     * ofrece en el mismo gesto. Hasta entonces (`#741`) no acreditaba («puede ser una consulta»): por eso la visita
     * guarda su ORIGEN, `lookup`, y el cuadro puede separarla del escaneo.
     */
    public function test_a_typed_lookup_registers_the_visit_as_lookup_and_offers_the_survey(): void
    {
        [$holder] = $this->customer();
        $this->survey();

        $page = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', 'ana@example.com')->call('search');
        $page->assertSet('profile.holder_name', 'Ana Titular')
            ->assertSet('profile.via', 'lookup')
            ->assertSet('profile.visit_registered_today', true)
            ->assertSet('survey.state', 'offer');

        $visit = CustomerVisit::query()->sole();
        $this->assertSame($holder->id, (int) $visit->user_id);
        $this->assertSame('lookup', $visit->source);

        // Otra búsqueda el mismo día no duplica, y `registerVisit()` sobre una ficha acreditada tampoco.
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
        $page->call('openSurvey')->assertStatus(403);
    }

    // ─── Contestar ────────────────────────────────────────────────────────────

    public function test_answering_writes_the_participation_and_an_anonymous_typed_response_and_closes_the_offer(): void
    {
        [$holder, $token] = $this->customer();
        $survey = $this->survey();
        $staff = $this->staff();

        $page = $this->openWithVisit($staff, $token)->call('openSurvey');
        $page->assertSet('survey.state', 'open')
            ->assertSee('data-gate-survey-form', false)
            ->assertSee('data-gate-question="ambiente"', false)
            ->assertSee('¿Cómo nos conociste?')
            ->assertSee('data-gate-survey-save', false)
            // `#754`: el aviso sigue a la vista con el formulario abierto, y junto a la pregunta de texto, «que no diga su nombre».
            ->assertSee('data-gate-survey-notice', false)
            ->assertSee('data-gate-survey-text-hint', false)
            ->assertSee('Si quiere seguir en el anonimato, que no diga su nombre ni datos personales.');

        // Lo que manda un formulario: CADENAS y listas de cadenas. El «no» de `volveria` es a propósito:
        // un servidor que guardara «lo que llega» dejaría una cadena, y uno que confundiera el «0» con
        // «verdadero» guardaría un sí.
        $page->set('surveyAnswers.ambiente', '4')
            ->set('surveyAnswers.como', 'google')
            ->set('surveyAnswers.zonas', ['jump', 'kids'])
            ->set('surveyAnswers.volveria', '0')
            ->set('surveyAnswers.comentario', '  Genial, volveremos.  ')
            ->call('answerSurvey')
            ->assertHasNoErrors()
            ->assertSet('survey.state', 'answered')
            ->assertSee('data-gate-survey="answered"', false)
            ->assertDontSee('data-gate-survey-form', false);

        // LA PARTICIPACIÓN: a quién se preguntó, quién y el día de la visita. Sin respuestas, sin hora, sin desenlace.
        $asked = SurveyParticipation::query()->sole();
        $this->assertSame($survey->id, (int) $asked->survey_id);
        $this->assertSame($holder->id, (int) $asked->user_id);
        $this->assertSame(SurveyResponse::CHANNEL_INTERNAL, $asked->channel);
        $this->assertSame($staff->id, (int) $asked->asked_by);
        $this->assertSame(self::TODAY, $asked->asked_on->toDateString());
        $this->assertNull($asked->sent_at);
        $this->assertNull($asked->token_hash);

        // LA RESPUESTA: sin nadie. El día, la FRANJA (las 11:00 del parque son la mañana), quién preguntó, lo grueso de
        // la visita (su primera vez; nada cobrado para hoy) y lo contestado TIPADO por su pregunta.
        $row = SurveyResponse::query()->sole();
        $this->assertSame($survey->id, (int) $row->survey_id);
        $this->assertSame(SurveyResponse::CHANNEL_INTERNAL, $row->channel);
        $this->assertSame(self::TODAY, $row->answered_on->toDateString());
        $this->assertSame(SurveyResponse::BAND_MORNING, $row->band);
        $this->assertSame($staff->id, (int) $row->asked_by);
        $this->assertTrue($row->first_visit);
        $this->assertSame(SurveyResponse::KIND_OTHER, $row->visit_kind);
        $this->assertFalse($row->declined);
        $this->assertSame(
            ['ambiente' => 4, 'como' => 'google', 'zonas' => ['jump', 'kids'], 'volveria' => false, 'comentario' => 'Genial, volveremos.'],
            $row->answers,
            'las respuestas se guardan TIPADAS por su pregunta (entero, booleano, lista, texto recortado), no como llegaron',
        );
        // Contestada = sellada: el sello (cifrado) es lo único que sabe de quién es, y solo `SurveySeals` lo lee.
        $this->assertCount(1, app(SurveySeals::class)->sealedFor($holder->id));

        // El libro: NADA de la respuesta (`#754`: su hora exacta y el cliente unirían el desenlace; `RGPD-07`).
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'like', 'survey_%')->count());

        // El rastro: UNO, `puerta.survey_closed` — a quién (target) y quién preguntó (autor), la encuesta, y NADA más:
        // ni el desenlace ni la fila (con ellos y la hora, la respuesta anónima tendría dueño).
        $audit = AuditLog::query()->where('action', 'like', 'puerta.survey_%')->sole();
        $this->assertSame('puerta.survey_closed', $audit->action);
        $this->assertSame($holder->id, (int) $audit->target_id);
        $this->assertSame($staff->id, (int) $audit->user_id);
        $this->assertSame(['survey' => 'visita-de-hoy'], $audit->payload);

        // Y no se vuelve a ofrecer: la siguiente ficha del mismo cliente (con la visita ya acreditada) no la trae.
        $again = $this->open($staff, $token);
        $again->assertSet('profile.visit_registered_today', true)->assertSet('survey', null);
    }

    public function test_declining_is_also_an_answer_and_is_not_offered_again(): void
    {
        [$holder, $token] = $this->customer();
        $this->survey();
        $staff = $this->staff();

        $page = $this->openWithVisit($staff, $token)->call('declineSurvey');

        $page->assertSet('survey.state', 'declined')->assertSee('data-gate-survey="declined"', false);

        $asked = SurveyParticipation::query()->sole();
        $this->assertSame($holder->id, (int) $asked->user_id);
        $this->assertSame(self::TODAY, $asked->asked_on->toDateString());
        $this->assertSame($staff->id, (int) $asked->asked_by);

        // «No preguntar» es una respuesta anónima `declined`, sin lo contestado y SIN SELLO: «volvió quien puntuó» no
        // pregunta por quien no puntuó.
        $row = SurveyResponse::query()->sole();
        $this->assertTrue($row->declined);
        $this->assertNull($row->answers);
        $this->assertSame(self::TODAY, $row->answered_on->toDateString());
        $this->assertSame($staff->id, (int) $row->asked_by);
        $this->assertNull(DB::table('survey_responses')->value('seal'));

        // El MISMO rastro que al contestar: el registro de actividad no distingue las dos salidas.
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'like', 'survey_%')->count());
        $audit = AuditLog::query()->where('action', 'like', 'puerta.survey_%')->sole();
        $this->assertSame('puerta.survey_closed', $audit->action);
        $this->assertSame($holder->id, (int) $audit->target_id);
        $this->assertSame(['survey' => 'visita-de-hoy'], $audit->payload);

        $this->open($staff, $token)->assertSet('survey', null);
    }

    public function test_a_required_question_left_blank_or_an_impossible_value_is_refused_and_nothing_is_written(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->openWithVisit($this->staff(), $token)->call('openSurvey');

        // «6» no está en la escala y `como` (obligatoria) no viene: dos errores, cero filas.
        $page->set('surveyAnswers.ambiente', '6')
            ->call('answerSurvey')
            ->assertHasErrors(['surveyAnswers.ambiente', 'surveyAnswers.como'])
            ->assertHasNoErrors(['surveyAnswers.zonas', 'surveyAnswers.volveria', 'surveyAnswers.comentario'])
            ->assertSet('survey.state', 'open')
            ->assertSee('data-gate-survey-error="ambiente"', false)
            ->assertSee(__('admin.puerta.validar.profile.survey_error_required'));

        // Un texto más largo de lo que la pregunta acepta, y una opción que no existe.
        $page->set('surveyAnswers.ambiente', '5')
            ->set('surveyAnswers.como', 'tiktok')
            ->set('surveyAnswers.comentario', str_repeat('a', 301))
            ->call('answerSurvey')
            ->assertHasErrors(['surveyAnswers.como', 'surveyAnswers.comentario'])
            ->assertHasNoErrors(['surveyAnswers.ambiente']);

        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'puerta.survey_%')->count());

        // Y con las dos obligatorias basta: lo opcional puede quedarse en blanco.
        $page->set('surveyAnswers.como', 'amigos')
            ->set('surveyAnswers.comentario', '')
            ->call('answerSurvey')
            ->assertHasNoErrors()
            ->assertSet('survey.state', 'answered');
        $this->assertSame(['ambiente' => 5, 'como' => 'amigos'], SurveyResponse::query()->sole()->answers);
    }

    // ─── Los límites: la ficha caduca, la encuesta se apaga, otra tablet ya contestó ────────────────

    public function test_the_open_survey_dies_with_the_sheet_and_writes_nothing(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->openWithVisit($this->staff(), $token)->call('openSurvey')
            ->set('surveyAnswers.ambiente', '5')
            ->set('surveyAnswers.como', 'google');

        $this->travel(PuertaSettings::profileTtlMinutes() + 1)->minutes();

        $page->call('answerSurvey')->assertSet('profile', null)->assertSet('survey', null);
        $this->assertSame(0, SurveyParticipation::query()->count(), 'una ficha caducada no escribe: se vuelve a ofrecer en la siguiente búsqueda');
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    public function test_a_survey_switched_off_between_the_offer_and_the_answer_is_not_written(): void
    {
        [, $token] = $this->customer();
        $survey = $this->survey();

        $page = $this->openWithVisit($this->staff(), $token)->call('openSurvey')
            ->set('surveyAnswers.ambiente', '5')
            ->set('surveyAnswers.como', 'google');

        $survey->update(['active' => false]);

        $page->call('answerSurvey')->assertSet('survey', null);
        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    public function test_a_stale_tablet_cannot_write_a_second_response_for_the_same_customer(): void
    {
        [$holder, $token] = $this->customer();
        $survey = $this->survey();

        $page = $this->openWithVisit($this->staff(), $token)->call('openSurvey')
            ->set('surveyAnswers.ambiente', '2')
            ->set('surveyAnswers.como', 'amigos');

        // Otra tablet (u otro operador) contesta por este cliente mientras ésta sigue abierta.
        $this->assertTrue(app(SurveyResponses::class)->answerInPerson($survey, $holder->id, ['ambiente' => 5, 'como' => 'google'], null));

        $page->call('answerSurvey')->assertHasNoErrors()->assertSet('survey.state', 'answered');

        // La participación es el árbitro: la segunda escritura no deja ni otra participación ni OTRA RESPUESTA anónima
        // (que contaría dos veces a la misma persona en el cuadro).
        $this->assertSame(1, SurveyParticipation::query()->count());
        $row = SurveyResponse::query()->sole();
        $this->assertSame(['ambiente' => 5, 'como' => 'google'], $row->answers, 'la primera respuesta manda: la segunda no la pisa');
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'puerta.survey_%')->count(), 'sin escritura no hay rastro que inventar');
    }

    /** La FRANJA sale de la hora del PARQUE (hasta las 13:00, hasta las 16:00, y después), nunca de la hora exacta. */
    public function test_the_response_keeps_the_band_of_the_park_and_never_the_hour(): void
    {
        [, $token] = $this->customer();
        $this->survey();
        // Las 16:00 en Madrid son las 14:00 en UTC: la franja tiene que salir de la hora del PARQUE (tarde), no de la UTC.
        $this->travelTo(Carbon::parse(self::TODAY.' 16:00:00', 'Europe/Madrid'));

        $this->openWithVisit($this->staff(), $token)->call('openSurvey')
            ->set('surveyAnswers.ambiente', '3')
            ->set('surveyAnswers.como', 'google')
            ->call('answerSurvey')
            ->assertHasNoErrors();

        $this->assertSame(SurveyResponse::BAND_AFTERNOON, SurveyResponse::query()->sole()->band);
    }
}
