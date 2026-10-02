<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\GateVisits;
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
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La encuesta INTERNA en la puerta** (`docs/specs/encuestas.md` §4.2 y §4.7; `DECISIONES #740` y `#754`) y, desde la P1b
 * de la Puerta nueva (`docs/specs/puerta-nueva.md` §4.4; `#817`·3), **PREGUNTA A PREGUNTA y solo en verde**: se ofrece SOLO
 * con la visita de hoy acreditada, una encuesta interna viva y sin participación de este cliente; la tarjeta nace preguntando
 * la primera; cada toque se TIPA y se VALIDA en el servidor contra SU pregunta; el primero escribe la participación y la
 * respuesta ANÓNIMA y sellada, y los siguientes la completan; «Ahora no» sin nada contestado no escribe nada y la deja para
 * su próxima visita (`#819`) y, con algo, cierra con lo contestado. «Solo en su primera visita», si la encuesta lo dice
 * (`#819`). Una por cliente y encuesta; la franja, nunca la hora; al libro, nada; un rastro que dice a quién sin decir qué
 * pasó.
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
    private function customer(bool $conDescargo = true, string $email = 'ana@example.com'): array
    {
        $holder = User::factory()->create(['name' => 'Ana Titular', 'email' => $email, 'email_verified_at' => now(), 'waiver_accepted_at' => $conDescargo ? now() : null]);
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

        $page = $this->open($staff, $token)->assertSee('1 de 5');

        // Primer toque: la escala, con su CADENA, como llega de un botón. Abre la participación y la respuesta. Y es la MISMA
        // lectura: un toque dentro de la ficha no la rehace (la vista la usa de clave).
        $page->call('answerQuestion', 'ambiente', '4')
            ->assertHasNoErrors()
            ->assertSet('survey.state', 'asking')
            ->assertSet('survey.step', 1)
            ->assertSet('lectura', 1)
            ->assertSee('¿Cómo nos conociste?')
            ->assertSee('2 de 5')
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

        // Los siguientes completan la MISMA fila, cada valor CON SU TOQUE, como lo manda el navegador: la de varias, su lista
        // entera; la de texto, lo tecleado. El «no» de `volveria` es a propósito: «0» no puede guardarse como sí.
        $page->call('answerQuestion', 'como', 'google')
            ->call('answerQuestion', 'zonas', ['jump', 'kids'])
            ->call('answerQuestion', 'volveria', '0')
            ->call('answerQuestion', 'comentario', '  Genial, volveremos.  ')
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
        // La de varias SIN NADA marcado llega como lista vacía (`marcadas`); la de texto, como cadena vacía.
        $page->call('answerQuestion', 'zonas', [])->assertHasNoErrors()->assertSet('survey.step', 3);
        $page->call('answerQuestion', 'volveria', '1')->call('answerQuestion', 'comentario', '   ')->assertHasNoErrors()->assertSet('survey.state', 'answered');

        $this->assertSame(['ambiente' => 5, 'como' => 'amigos', 'volveria' => true], SurveyResponse::query()->sole()->answers);
    }

    /**
     * ▶ **La de VARIAS guarda EXACTAMENTE lo marcado** (el owner, 02-10: «la última pregunta selecciona todas las opciones a la
     * vez»). Desde `#741` la vista ataba las casillas a un `wire:model` sin lista inicial: un toque las marcaba TODAS y el
     * servidor recibía un `true` que no es una lista, así que no se guardaba NINGUNA —en silencio—. Las pruebas no lo veían
     * porque fijaban la lista en el servidor (`set()`), justo lo que el navegador nunca mandaba. Ahora la lista viaja con el
     * toque, y lo que no es una lista de opciones de la pregunta no se guarda.
     */
    public function test_a_multi_question_stores_exactly_what_was_marked(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token)->call('answerQuestion', 'ambiente', '5')->call('answerQuestion', 'como', 'amigos');
        $page->assertSee('data-gate-survey-toggle="jump"', false)->assertSee('data-gate-survey-toggle="kids"', false);

        // Lo que mandaba la vista vieja tras un toque: `true`. No es una lista: se salta, y nada de «todas».
        $page->call('answerQuestion', 'zonas', true)->assertHasNoErrors()->assertSet('survey.step', 3);
        $this->assertSame(['ambiente' => 5, 'como' => 'amigos'], SurveyResponse::query()->sole()->answers);

        // Una opción que la pregunta no tiene no se cuela entre las buenas.
        [, $otro] = $this->customer(email: 'bea@example.com');
        $page = $this->open($this->staff(), $otro)->call('answerQuestion', 'ambiente', '4')->call('answerQuestion', 'como', 'google');
        $page->call('answerQuestion', 'zonas', ['kids', 'piscina'])->assertHasErrors(['respuesta.zonas'])->assertSet('survey.step', 2);
        $page->call('answerQuestion', 'zonas', ['kids'])->assertHasNoErrors()->assertSet('survey.step', 3);

        $this->assertContains(['ambiente' => 4, 'como' => 'google', 'zonas' => ['kids']], SurveyResponse::query()->pluck('answers')->all());
    }

    /**
     * ▶ **«Ahora no» SIN nada contestado la deja para su PRÓXIMA visita** (`#819`, el owner; el mockup: «no guarda nada»): ni
     * participación, ni respuesta, ni rastro, y la tarjeta se va. Ese día no se le vuelve a ofrecer —tampoco en otra tablet—;
     * al día siguiente, sí. (Antes era el «no preguntar» de `#740`: una fila `declined` y no volvía a salir nunca.)
     */
    public function test_now_not_without_any_answer_writes_nothing_and_waits_for_the_next_visit(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $this->open($this->staff(), $token)->call('skipSurvey')
            ->assertHasNoErrors()
            ->assertSet('survey', null)
            ->assertSet('surveyId', null)
            ->assertDontSee('data-gate-survey', false)
            ->assertSee('data-gate-profile', false);

        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertSame(0, $this->closedAudits(), 'sin nada escrito no hay rastro');

        $this->open($this->staff(), $token)->assertSet('survey', null);
        $this->travel(5)->hours();
        $this->open($this->staff(), $token)->assertSet('survey', null);

        $this->travelTo(Carbon::parse(self::TODAY.' 11:00:00', 'Europe/Madrid')->addDay());
        $this->open($this->staff(), $token)->assertSet('survey.state', 'asking')->assertSet('survey.step', 0);
    }

    /**
     * ▶ **«Solo en su primera visita»** (`#819`): la oferta pregunta si hoy es su primera visita —ni visita acreditada ni día
     * cobrado ANTES de hoy; la de hoy, que el escaneo acredita, no cuenta—. A todos, como siempre.
     */
    public function test_a_first_visit_survey_is_offered_only_on_the_first_visit(): void
    {
        [, $carneNuevo] = $this->customer();
        [$habitual, $carneHabitual] = $this->customer(email: 'bea@example.com');
        app(GateVisits::class)->register($habitual, null, Carbon::parse(self::TODAY)->subDays(20));
        $survey = $this->survey(['audience' => Survey::AUDIENCE_FIRST_VISIT]);

        $this->open($this->staff(), $carneNuevo)->assertSet('survey.state', 'asking');
        $this->open($this->staff(), $carneHabitual)->assertSet('profile.visit_registered_today', true)->assertSet('survey', null);

        $survey->update(['audience' => Survey::AUDIENCE_ALL]);
        $this->open($this->staff(), $carneHabitual)->assertSet('survey.state', 'asking');
        $this->assertSame(0, SurveyParticipation::query()->count(), 'ofrecer no escribe nada');
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
            ->assertHasErrors(['respuesta.ambiente'])
            ->assertSet('survey.step', 0)
            ->assertSee('data-gate-survey-error="ambiente"', false);
        $this->assertSame(0, SurveyParticipation::query()->count());
        $this->assertSame(0, $this->closedAudits());

        $page->call('answerQuestion', 'ambiente', '5')->call('answerQuestion', 'como', 'tiktok')->assertHasErrors(['respuesta.como']);
        $page->call('answerQuestion', 'como', 'google')->call('answerQuestion', 'zonas')->call('answerQuestion', 'volveria', '1');
        $page->call('answerQuestion', 'comentario', str_repeat('a', 301))->assertHasErrors(['respuesta.comentario']);

        $this->assertSame(['ambiente' => 5, 'como' => 'google', 'volveria' => true], SurveyResponse::query()->sole()->answers);
    }

    /**
     * El navegador no decide: la tarjeta está BLOQUEADA (no la mueve ni cambia lo que se pregunta) y solo se contesta la
     * pregunta que está EN PANTALLA. Un toque doble o tardío —Livewire los encola y llegan cuando la tarjeta ya pasó— ni
     * rebobina la tarjeta ni contesta otra, y lo contestado no se pisa.
     */
    public function test_the_browser_copy_decides_nothing(): void
    {
        [, $token] = $this->customer();
        $this->survey();

        $page = $this->open($this->staff(), $token);
        foreach (['survey.step' => 3, 'survey.questions.0.key' => 'inventada', 'survey.state' => 'answered'] as $path => $value) {
            try {
                $page->set($path, $value);
                $this->fail("El navegador movió la tarjeta ({$path}).");
            } catch (CannotUpdateLockedPropertyException) {
            }
        }

        // Una pregunta que NO está en pantalla (la segunda, con la primera delante): nada se escribe y la tarjeta no se mueve.
        $page->call('answerQuestion', 'como', 'google')->assertSet('survey.step', 0);
        $this->assertSame(0, SurveyResponse::query()->count());

        // El doble toque: el segundo llega con la tarjeta ya en la segunda pregunta.
        $page->call('answerQuestion', 'ambiente', '4')->call('answerQuestion', 'ambiente', '1')->assertSet('survey.step', 1)->assertSee('¿Cómo nos conociste?');
        $this->assertSame(['ambiente' => 4], SurveyResponse::query()->sole()->answers, 'lo contestado no se pisa');
    }

    /** Si el panel QUITA una pregunta después de la oferta, la tarjeta la salta sin escribir: lo guardado manda. */
    public function test_a_question_removed_after_the_offer_is_skipped_without_writing(): void
    {
        [, $token] = $this->customer();
        $survey = $this->survey();

        $page = $this->open($this->staff(), $token)->call('answerQuestion', 'ambiente', '4');
        $survey->update(['questions' => array_values(array_filter($survey->questions, static fn (array $q): bool => $q['key'] !== 'como'))]);

        $page->call('answerQuestion', 'como', 'google')->assertHasNoErrors()->assertSet('survey.step', 2);
        $this->assertSame(['ambiente' => 4], SurveyResponse::query()->sole()->answers);
    }

    /**
     * ▶ **Un CARNÉ en una respuesta de texto es el lector escribiendo donde estaba el cursor** (un lector es un teclado):
     * es una lectura NUEVA y se abre su ficha; jamás se guarda como respuesta, ni solo ni pegado a lo que ya se había escrito
     * (un carné es una credencial). Un texto de verdad que empieza por «JW» se guarda tal cual.
     */
    public function test_a_card_read_into_a_text_answer_opens_that_card_and_is_never_stored(): void
    {
        [, $token] = $this->customer();
        [$bea, $carneDeBea] = $this->customer(email: 'bea@example.com');
        $this->survey();

        $page = $this->open($this->staff(), $token)
            ->call('answerQuestion', 'ambiente', '4')->call('answerQuestion', 'como', 'google')
            ->call('answerQuestion', 'zonas', ['jump'])->call('answerQuestion', 'volveria', '1');

        $page->call('answerQuestion', 'comentario', 'Muy bien todo '.$carneDeBea)
            ->assertHasNoErrors()
            ->assertSet('profileUserId', $bea->id)
            ->assertSet('profile.via', 'card')
            ->assertSet('lectura', 2);

        $answers = SurveyResponse::query()->get()->pluck('answers')->all();
        $this->assertSame([['ambiente' => 4, 'como' => 'google', 'zonas' => ['jump'], 'volveria' => true]], $answers, 'el carné no está en ninguna respuesta');

        // Un texto que solo PARECE un carné (sin su carácter de control) es una respuesta.
        [, $carla] = $this->customer(email: 'carla@example.com');
        $this->open($this->staff(), $carla)
            ->call('answerQuestion', 'ambiente', '5')->call('answerQuestion', 'como', 'amigos')
            ->call('answerQuestion', 'zonas', ['kids'])->call('answerQuestion', 'volveria', '1')
            ->call('answerQuestion', 'comentario', 'JW tenéis un parque genial')
            ->assertSet('survey.state', 'answered');
        $this->assertContains('JW tenéis un parque genial', SurveyResponse::query()->get()->pluck('answers.comentario')->filter()->all());
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
