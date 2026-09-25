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
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Livewire\Admin\Puerta\ValidarRegistro;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La encuesta INTERNA en la puerta** (`docs/specs/encuestas.md` §4.2, T2; `DECISIONES #740`): se ofrece SOLO
 * con la visita de hoy acreditada, una encuesta interna viva y sin respuesta de este cliente; el operador
 * pregunta y marca; lo marcado se tipa y se valida en el SERVIDOR; una respuesta por cliente y encuesta; «No
 * preguntar» también es una respuesta; al libro va solo el HECHO, y el rastro apunta al cliente.
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

    private function fact(string $name): ?AnalyticsEvent
    {
        return AnalyticsEvent::query()->where('name', $name)->first();
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

        // La oferta no escribe nada de la encuesta: ni fila, ni hecho, ni rastro. Eso lo hace el operador.
        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertNull($this->fact('survey_answered'));
        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'puerta.survey_%')->count());

        // Un segundo escaneo el mismo día no duplica la visita, y vuelve a ofrecer (sigue sin respuesta).
        $this->open($this->staff(), $token)->assertSet('survey.state', 'offer');
        $this->assertSame(1, CustomerVisit::query()->count());
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'visit_checked_in')->count());
    }

    /** Buscar por correo abre la ficha pero NO acredita (puede ser una consulta): sin visita no hay oferta. */
    public function test_a_typed_lookup_offers_nothing_until_the_visit_is_registered(): void
    {
        [$holder] = $this->customer();
        $this->survey();

        $page = Livewire::actingAs($this->staff())->test(ValidarRegistro::class)->set('input', 'ana@example.com')->call('search');
        $page->assertSet('profile.holder_name', 'Ana Titular')
            ->assertSet('profile.via', 'lookup')
            ->assertSet('profile.visit_registered_today', false)
            ->assertSet('survey', null)
            ->assertDontSee('data-gate-survey', false);
        $this->assertSame(0, CustomerVisit::query()->count());

        $page->call('registerVisit');

        $page->assertSet('profile.visit_registered_today', true)->assertSet('survey.state', 'offer');
        $this->assertSame($holder->id, (int) $page->get('profileUserId'));
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

    public function test_answering_writes_one_typed_row_the_fact_and_the_audit_and_closes_the_offer(): void
    {
        [$holder, $token] = $this->customer();
        $survey = $this->survey();
        $staff = $this->staff();

        $page = $this->openWithVisit($staff, $token)->call('openSurvey');
        $page->assertSet('survey.state', 'open')
            ->assertSee('data-gate-survey-form', false)
            ->assertSee('data-gate-question="ambiente"', false)
            ->assertSee('¿Cómo nos conociste?')
            ->assertSee('data-gate-survey-save', false);

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

        $row = SurveyResponse::query()->sole();
        $this->assertSame($survey->id, (int) $row->survey_id);
        $this->assertSame($holder->id, (int) $row->user_id);
        $this->assertSame(SurveyResponse::CHANNEL_INTERNAL, $row->channel);
        $this->assertSame($staff->id, (int) $row->answered_by);
        $this->assertSame(self::TODAY, $row->visited_on?->toDateString(), 'la respuesta queda atada a la VISITA de hoy');
        $this->assertSame('es', $row->locale);
        $this->assertNotNull($row->answered_at);
        $this->assertNull($row->declined_at);
        $this->assertSame(
            ['ambiente' => 4, 'como' => 'google', 'zonas' => ['jump', 'kids'], 'volveria' => false, 'comentario' => 'Genial, volveremos.'],
            $row->answers,
            'las respuestas se guardan TIPADAS por su pregunta (entero, booleano, lista, texto recortado), no como llegaron',
        );

        // El libro: el HECHO con la clave y el canal, atado al cliente (régimen del contrato); ninguna respuesta.
        $fact = $this->fact('survey_answered');
        $this->assertNotNull($fact);
        $this->assertSame(['survey' => 'visita-de-hoy', 'channel' => 'internal'], $fact->props);
        $this->assertSame($holder->id, (int) $fact->user_id);
        $this->assertStringNotContainsString('Genial', json_encode(AnalyticsEvent::query()->get(), JSON_UNESCAPED_UNICODE), 'ninguna respuesta pisa el libro (`RGPD-07`)');

        // El rastro: target el cliente, quién preguntó, qué encuesta y qué fila.
        $audit = AuditLog::query()->where('action', 'puerta.survey_answered')->sole();
        $this->assertSame($holder->id, (int) $audit->target_id);
        $this->assertSame($staff->id, (int) $audit->user_id);
        $this->assertSame(['survey' => 'visita-de-hoy', 'response_id' => $row->id], $audit->payload);

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

        $row = SurveyResponse::query()->sole();
        $this->assertNotNull($row->declined_at);
        $this->assertNull($row->answered_at);
        $this->assertNull($row->answers);
        $this->assertSame($holder->id, (int) $row->user_id);
        $this->assertSame(self::TODAY, $row->visited_on?->toDateString());
        $this->assertSame($staff->id, (int) $row->answered_by);

        $fact = $this->fact('survey_declined');
        $this->assertNotNull($fact);
        $this->assertSame(['survey' => 'visita-de-hoy', 'channel' => 'internal'], $fact->props);
        $this->assertNull($this->fact('survey_answered'));
        $this->assertSame(1, AuditLog::query()->where('action', 'puerta.survey_declined')->where('target_id', $holder->id)->count());

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

        $this->assertSame(0, SurveyResponse::query()->count());
        $this->assertSame(0, AnalyticsEvent::query()->where('name', 'like', 'survey_%')->count());
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
        $this->assertSame(0, SurveyResponse::query()->count(), 'una ficha caducada no escribe: se vuelve a ofrecer en la siguiente búsqueda');
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
        app(SurveyResponses::class)->answer($survey, $holder->id, SurveyResponse::CHANNEL_INTERNAL, ['ambiente' => 5, 'como' => 'google'], 'es', self::TODAY);

        $page->call('answerSurvey')->assertHasNoErrors()->assertSet('survey.state', 'answered');

        $row = SurveyResponse::query()->sole();
        $this->assertSame(['ambiente' => 5, 'como' => 'google'], $row->answers, 'la primera respuesta manda: la segunda no la pisa');
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'survey_answered')->count(), 'un solo hecho por respuesta');
        $this->assertSame(0, AuditLog::query()->where('action', 'puerta.survey_answered')->count(), 'sin escritura no hay rastro que inventar');
    }
}
