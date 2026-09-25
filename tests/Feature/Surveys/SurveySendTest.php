<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Notifications\SurveyInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * **El correo de la encuesta del día siguiente** (`docs/specs/encuestas.md` §4.3, T3; `DECISIONES #740`):
 * `surveys:send-external` manda UNA vez, a quien acreditó su visita AYER y puede recibirla, desde las 10:00 del
 * parque; la fila es la marca (idempotente por construcción); el correo es de servicio y lleva la baja.
 */
class SurveySendTest extends TestCase
{
    use RefreshDatabase;

    private const YESTERDAY = '2026-09-25';

    protected function setUp(): void
    {
        parent::setUp();
        // ⚠️ 12:30 en Madrid son las 10:30 en UTC: pasa de las 10:00 sea cual sea la zona con la que el reloj del
        // parque resuelva `now()` en la suite, y «ayer» sigue siendo el 25 en las dos.
        $this->travelTo(Carbon::parse('2026-09-26 12:30:00', 'Europe/Madrid'));
        Notification::fake();
    }

    private function survey(array $overrides = []): Survey
    {
        return Survey::create(array_merge([
            'key' => 'que-tal-ayer',
            'name' => ['es' => '¿Qué tal ayer?', 'en' => 'How was it?'],
            'intro' => ['es' => 'Dos preguntas rápidas.', 'en' => 'Two quick questions.'],
            'kind' => Survey::KIND_EXTERNAL,
            'active' => true,
            'questions' => [
                ['key' => 'ambiente', 'type' => 'scale', 'required' => true, 'label' => ['es' => 'Ambiente', 'en' => 'Atmosphere']],
                ['key' => 'comentario', 'type' => 'text', 'label' => ['es' => 'Algo más', 'en' => 'Anything else']],
            ],
        ], $overrides));
    }

    /** Un cliente con el correo verificado y, salvo que se diga, la visita de AYER acreditada. */
    private function visitor(string $email, array $overrides = [], ?string $visitedOn = self::YESTERDAY): User
    {
        $user = User::factory()->create(array_merge(['email' => $email, 'email_verified_at' => now(), 'locale' => 'es'], $overrides));
        if ($visitedOn !== null) {
            app(GateVisits::class)->register($user, null, Carbon::parse($visitedOn));
        }

        return $user;
    }

    public function test_it_mails_yesterdays_verified_visitors_once_with_a_token_and_leaves_the_fact(): void
    {
        $survey = $this->survey();
        $ana = $this->visitor('ana@example.com', ['locale' => 'en']);

        $this->assertSame(0, Artisan::call('surveys:send-external'));

        $row = SurveyResponse::query()->sole();
        $this->assertSame($ana->id, (int) $row->user_id);
        $this->assertSame($survey->id, (int) $row->survey_id);
        $this->assertSame(SurveyResponse::CHANNEL_EXTERNAL, $row->channel);
        $this->assertSame(self::YESTERDAY, $row->visited_on?->toDateString(), 'la respuesta queda atada a la visita de AYER');
        $this->assertSame('en', $row->locale, 'el idioma del correo es el del cliente, y la página lo hereda');
        $this->assertNotNull($row->sent_at);
        $this->assertNull($row->answered_at);
        $this->assertMatchesRegularExpression(SurveyResponses::TOKEN_RE, (string) $row->token);

        Notification::assertSentTo($ana, SurveyInvitation::class, static fn (SurveyInvitation $n): bool => $n->response->is($row) && $n->survey->is($survey));

        $fact = AnalyticsEvent::query()->where('name', 'survey_sent')->sole();
        $this->assertSame(['survey' => 'que-tal-ayer', 'channel' => 'external'], $fact->props);
        $this->assertSame($ana->id, (int) $fact->user_id);

        // La pasada siguiente (corre cada hora) no manda dos veces: la fila es la marca.
        Artisan::call('surveys:send-external');
        $this->assertSame(1, SurveyResponse::query()->count());
        Notification::assertSentToTimes($ana, SurveyInvitation::class, 1);
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'survey_sent')->count());
    }

    public function test_who_is_left_out_and_why(): void
    {
        $survey = $this->survey();
        $other = $this->survey(['key' => 'otra', 'active' => false]);
        $ana = $this->visitor('ana@example.com');
        $baja = $this->visitor('baja@example.com', ['surveys_opt_out' => true]);
        $anteayer = $this->visitor('anteayer@example.com', [], '2026-09-24');
        $sinVerificar = $this->visitor('sin@example.com', ['email_verified_at' => null]);
        $anonima = $this->visitor('anon-9@'.User::ANONYMIZED_EMAIL_DOMAIN);
        $reciente = $this->visitor('reciente@example.com');
        SurveyResponse::create(['survey_id' => $other->id, 'user_id' => $reciente->id, 'channel' => 'external', 'sent_at' => now()->subDays(10), 'token' => Str::random(40)]);
        $enLaPuerta = $this->visitor('puerta@example.com');
        SurveyResponse::create(['survey_id' => $survey->id, 'user_id' => $enLaPuerta->id, 'channel' => 'internal', 'declined_at' => now()->subDay()]);
        // `#742`: quien CONTESTÓ la interna en esa visita ya dio su opinión; quien dijo «no preguntar» recibe el correo.
        $internal = $this->survey(['key' => 'visita', 'kind' => Survey::KIND_INTERNAL]);
        $contesto = $this->visitor('contesto@example.com');
        SurveyResponse::create(['survey_id' => $internal->id, 'user_id' => $contesto->id, 'channel' => 'internal', 'visited_on' => self::YESTERDAY, 'answered_at' => self::YESTERDAY.' 11:00:00', 'answers' => ['ambiente' => 4]]);
        $declino = $this->visitor('declino@example.com');
        SurveyResponse::create(['survey_id' => $internal->id, 'user_id' => $declino->id, 'channel' => 'internal', 'visited_on' => self::YESTERDAY, 'declined_at' => self::YESTERDAY.' 11:00:00']);

        Artisan::call('surveys:send-external');

        Notification::assertSentTo($ana, SurveyInvitation::class);
        Notification::assertSentTo($declino, SurveyInvitation::class);
        foreach ([$baja, $anteayer, $sinVerificar, $anonima, $reciente, $enLaPuerta, $contesto] as $user) {
            Notification::assertNotSentTo($user, SurveyInvitation::class);
        }
        $this->assertSame(2, SurveyResponse::query()->where('survey_id', $survey->id)->where('channel', 'external')->count());

        // Con el plazo a cero, quien recibió otra encuesta hace diez días sí entra: el plazo es un ajuste.
        Setting::updateOrCreate(['key' => 'surveys.cooldown_days'], ['value' => '0', 'group' => 'puerta']);
        Setting::flushMemo();
        Artisan::call('surveys:send-external');
        Notification::assertSentTo($reciente, SurveyInvitation::class);
    }

    public function test_it_waits_for_ten_in_the_park_and_force_overrides(): void
    {
        $this->survey();
        $ana = $this->visitor('ana@example.com');
        // 08:15 en Madrid son las 06:15 en UTC: antes de las 10:00 en las dos zonas.
        $this->travelTo(Carbon::parse('2026-09-26 08:15:00', 'Europe/Madrid'));

        Artisan::call('surveys:send-external');
        Notification::assertNothingSent();
        $this->assertSame(0, SurveyResponse::query()->count());

        Artisan::call('surveys:send-external', ['--force' => true]);
        Notification::assertSentTo($ana, SurveyInvitation::class);
    }

    public function test_dry_run_counts_without_writing_or_mailing(): void
    {
        $this->survey();
        $this->visitor('ana@example.com');

        Artisan::call('surveys:send-external', ['--dry-run' => true]);

        $this->assertStringContainsString('Mandaría 1', Artisan::output());
        $this->assertSame(0, SurveyResponse::query()->count());
        Notification::assertNothingSent();
    }

    public function test_without_a_live_external_survey_nothing_is_sent(): void
    {
        $this->survey(['kind' => Survey::KIND_INTERNAL]);
        $this->visitor('ana@example.com');

        Artisan::call('surveys:send-external');

        Notification::assertNothingSent();
        $this->assertSame(0, SurveyResponse::query()->count());
    }

    /** El correo: en el idioma del cliente, con el botón a SU página y la baja, y sin una línea comercial. */
    public function test_the_mail_speaks_the_customers_language_carries_the_button_and_the_opt_out_and_sells_nothing(): void
    {
        $survey = $this->survey();
        $ana = $this->visitor('ana@example.com', ['locale' => 'en']);
        $response = app(SurveyResponses::class)->send($survey, $ana->id, self::YESTERDAY, 'en');
        $this->assertNotNull($response);

        app()->setLocale('en');
        $mail = (new SurveyInvitation($survey, $response))->toMail($ana);
        $html = (string) $mail->render();

        $this->assertStringContainsString('How was', (string) $mail->subject);
        $this->assertStringContainsString('Two quick questions.', $html, 'la primera línea es la intro de la encuesta, en su idioma');
        $this->assertStringContainsString(route('survey.show', ['token' => $response->token]), $html);
        $this->assertStringContainsString(route('survey.optout', ['token' => $response->token]), $html);
        foreach (['oferta', 'descuento', 'offer', 'discount', 'promo'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $html, 'un correo de SERVICIO no lleva una línea comercial (§7·4)');
        }
    }

    /** La `intro` del panel solo si existe en el idioma del cliente: si no, la frase de la casa en SU idioma. */
    public function test_an_intro_written_only_in_spanish_does_not_leak_into_an_english_mail(): void
    {
        $survey = $this->survey(['intro' => ['es' => 'Dos preguntas rápidas.']]);
        $ana = $this->visitor('ana@example.com', ['locale' => 'en']);
        $response = app(SurveyResponses::class)->send($survey, $ana->id, self::YESTERDAY, 'en');
        $this->assertNotNull($response);

        app()->setLocale('en');
        $html = (string) (new SurveyInvitation($survey, $response))->toMail($ana)->render();

        $this->assertStringNotContainsString('Dos preguntas rápidas.', $html);
        $this->assertStringContainsString('You visited', $html);
    }
}
