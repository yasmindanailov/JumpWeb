<?php

namespace Tests\Feature\Surveys;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyParticipation;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use App\Notifications\SurveyInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **El correo de la encuesta del día siguiente** (`docs/specs/encuestas.md` §4.3 y §4.7, T3 y T5; `DECISIONES #740` y
 * `#754`): `surveys:send-external` manda UNA vez, a quien acreditó su visita AYER y puede recibirla, desde las 10:00 del
 * parque; la participación es la marca (idempotente por construcción) y guarda el HASH del token, que en claro solo
 * viaja en el correo; el correo es de servicio, lleva el aviso del anonimato y la baja.
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

    /** Contestar (o declinar) en la puerta AYER: la respuesta nace con el día y la franja del reloj, así que se viaja. */
    private function atTheGateYesterday(callable $write): void
    {
        $now = now();
        $this->travelTo(Carbon::parse(self::YESTERDAY.' 11:00:00', 'Europe/Madrid'));
        $write(app(SurveyResponses::class));
        $this->travelTo($now);
    }

    public function test_it_mails_yesterdays_verified_visitors_once_with_a_token_that_the_database_only_keeps_hashed(): void
    {
        $survey = $this->survey();
        $ana = $this->visitor('ana@example.com', ['locale' => 'en']);

        $this->assertSame(0, Artisan::call('surveys:send-external'));

        $token = null;
        Notification::assertSentTo($ana, SurveyInvitation::class, static function (SurveyInvitation $n) use ($survey, &$token): bool {
            $token = $n->token;

            return $n->survey->is($survey) && preg_match(SurveyResponses::TOKEN_RE, $n->token) === 1;
        });
        $this->assertIsString($token);

        $asked = SurveyParticipation::query()->sole();
        $this->assertSame($ana->id, (int) $asked->user_id);
        $this->assertSame($survey->id, (int) $asked->survey_id);
        $this->assertSame('external', $asked->channel);
        $this->assertSame(self::YESTERDAY, $asked->asked_on->toDateString(), 'el día de la VISITA que la originó');
        $this->assertSame('en', $asked->locale, 'el idioma del correo es el del cliente, y la página lo hereda');
        $this->assertNotNull($asked->sent_at);
        // El token EN CLARO no está en la base: solo su HMAC con la etiqueta de búsqueda.
        $this->assertSame(SurveyResponses::tokenHash($token, SurveyResponses::HASH_LOOKUP), $asked->token_hash);
        $this->assertStringNotContainsString($token, (string) json_encode(DB::table('survey_participations')->get()));

        $fact = AnalyticsEvent::query()->where('name', 'survey_sent')->sole();
        $this->assertSame(['survey' => 'que-tal-ayer', 'channel' => 'external'], $fact->props);
        $this->assertSame($ana->id, (int) $fact->user_id, 'mandar es de la participación, que sí tiene persona');

        // La pasada siguiente (corre cada hora) no manda dos veces: la participación es la marca.
        Artisan::call('surveys:send-external');
        $this->assertSame(1, SurveyParticipation::query()->count());
        Notification::assertSentToTimes($ana, SurveyInvitation::class, 1);
        $this->assertSame(1, AnalyticsEvent::query()->where('name', 'survey_sent')->count());
    }

    public function test_who_is_left_out_and_why(): void
    {
        $survey = $this->survey();
        $other = $this->survey(['key' => 'otra', 'active' => false]);
        $internal = $this->survey(['key' => 'visita', 'kind' => Survey::KIND_INTERNAL]);
        $ana = $this->visitor('ana@example.com');
        $baja = $this->visitor('baja@example.com', ['surveys_opt_out' => true]);
        $anteayer = $this->visitor('anteayer@example.com', [], '2026-09-24');
        $sinVerificar = $this->visitor('sin@example.com', ['email_verified_at' => null]);
        $anonima = $this->visitor('anon-9@'.User::ANONYMIZED_EMAIL_DOMAIN);
        $reciente = $this->visitor('reciente@example.com');
        SurveyParticipation::create(['survey_id' => $other->id, 'user_id' => $reciente->id, 'channel' => 'external', 'asked_on' => '2026-09-15', 'sent_at' => now()->subDays(10)]);
        $yaPreguntado = $this->visitor('ya@example.com');
        SurveyParticipation::create(['survey_id' => $survey->id, 'user_id' => $yaPreguntado->id, 'channel' => 'external', 'asked_on' => '2026-08-01', 'sent_at' => now()->subDays(56)]);
        // `#742`: quien CONTESTÓ la interna en esa visita ya dio su opinión; quien dijo «no preguntar» recibe el correo.
        // Desde `#754` la participación no guarda el desenlace: lo sabe el SELLO de la respuesta contestada.
        $contesto = $this->visitor('contesto@example.com');
        $declino = $this->visitor('declino@example.com');
        $this->atTheGateYesterday(static function (SurveyResponses $responses) use ($internal, $contesto, $declino): void {
            $responses->answerInPerson($internal, $contesto->id, ['ambiente' => 4], null);
            $responses->declineInPerson($internal, $declino->id, null);
        });

        Artisan::call('surveys:send-external');

        Notification::assertSentTo($ana, SurveyInvitation::class);
        Notification::assertSentTo($declino, SurveyInvitation::class);
        foreach ([$baja, $anteayer, $sinVerificar, $anonima, $reciente, $yaPreguntado, $contesto] as $user) {
            Notification::assertNotSentTo($user, SurveyInvitation::class);
        }
        $this->assertSame(2, SurveyParticipation::query()->where('survey_id', $survey->id)->whereNotNull('user_id')->where('asked_on', self::YESTERDAY)->count());

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
        $this->assertSame(0, SurveyParticipation::query()->count());

        Artisan::call('surveys:send-external', ['--force' => true]);
        Notification::assertSentTo($ana, SurveyInvitation::class);
    }

    public function test_dry_run_counts_without_writing_or_mailing(): void
    {
        $this->survey();
        $this->visitor('ana@example.com');

        Artisan::call('surveys:send-external', ['--dry-run' => true]);

        $this->assertStringContainsString('Mandaría 1', Artisan::output());
        $this->assertSame(0, SurveyParticipation::query()->count());
        Notification::assertNothingSent();
    }

    public function test_without_a_live_external_survey_nothing_is_sent(): void
    {
        $this->survey(['kind' => Survey::KIND_INTERNAL]);
        $this->visitor('ana@example.com');

        Artisan::call('surveys:send-external');

        Notification::assertNothingSent();
        $this->assertSame(0, SurveyParticipation::query()->count());
    }

    /** El correo: en el idioma del cliente, con el botón a SU página, el aviso del anonimato y la baja, y sin vender nada. */
    public function test_the_mail_speaks_the_customers_language_carries_the_button_the_notice_and_the_opt_out_and_sells_nothing(): void
    {
        $survey = $this->survey();
        $ana = $this->visitor('ana@example.com', ['locale' => 'en']);
        $token = app(SurveyResponses::class)->send($survey, $ana->id, self::YESTERDAY, 'en');
        $this->assertIsString($token);

        app()->setLocale('en');
        $mail = (new SurveyInvitation($survey, $token))->toMail($ana);
        $html = (string) $mail->render();

        $this->assertStringContainsString('How was', (string) $mail->subject);
        $this->assertStringContainsString('Two quick questions.', $html, 'la primera línea es la intro de la encuesta, en su idioma');
        $this->assertStringContainsString(route('survey.show', ['token' => $token]), $html);
        $this->assertStringContainsString(route('survey.optout', ['token' => $token]), $html);
        foreach (['oferta', 'descuento', 'offer', 'discount', 'promo'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase($forbidden, $html, 'un correo de SERVICIO no lleva una línea comercial (§7·4)');
        }
    }

    /**
     * `#754`: EL AVISO DEL ANONIMATO va en el correo, en el idioma del cliente, detrás de la intro. Texto del PRODUCTO:
     * tecleado aquí a mano en los tres idiomas (`#734`: con `__()` la aserción pasaría con la clave vacía).
     */
    public function test_the_mail_carries_the_anonymity_notice_in_the_three_languages(): void
    {
        $survey = $this->survey();
        $notices = [
            'es' => 'Nadie en el parque verá tu nombre junto a tus respuestas, y a los 90 días se separan de ti del todo.',
            'en' => 'No one at the park will see your name next to your answers, and after 90 days they are separated from you entirely.',
            'fr' => 'Personne au parc ne verra ton nom à côté de tes réponses, et au bout de 90 jours elles sont entièrement détachées de toi.',
        ];

        foreach ($notices as $locale => $notice) {
            $user = $this->visitor($locale.'@example.com', ['locale' => $locale]);
            app()->setLocale($locale);
            $html = html_entity_decode((string) (new SurveyInvitation($survey, str_repeat('a', 40)))->toMail($user)->render(), ENT_QUOTES);

            $this->assertStringContainsString($notice, $html, "el aviso en {$locale}");
        }
    }

    /** La `intro` del panel solo si existe en el idioma del cliente: si no, la frase de la casa en SU idioma. */
    public function test_an_intro_written_only_in_spanish_does_not_leak_into_an_english_mail(): void
    {
        $survey = $this->survey(['intro' => ['es' => 'Dos preguntas rápidas.']]);
        $ana = $this->visitor('ana@example.com', ['locale' => 'en']);
        $token = app(SurveyResponses::class)->send($survey, $ana->id, self::YESTERDAY, 'en');
        $this->assertIsString($token);

        app()->setLocale('en');
        $html = (string) (new SurveyInvitation($survey, $token))->toMail($ana)->render();

        $this->assertStringNotContainsString('Dos preguntas rápidas.', $html);
        $this->assertStringContainsString('You visited', $html);
    }
}
