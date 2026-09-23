<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\SelfSignup;
use App\Domain\Identity\Services\TermsAcceptance;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Turnstile;
use App\Notifications\AccountAlreadyExists;
use App\Notifications\VerifyEmailAddress;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 3c — `POST /api/v1/auth/register` y `auth/email/resend`.
 *
 * Lo que se comprueba no es que se cree una fila: eso ya lo prueban los tests del modal. Es que la
 * API **hereda** las cuatro capas de defensa del alta y las dos decisiones de producto —decir que
 * un correo ya existe, y el pay-first— en vez de tener su propia versión de ninguna.
 */
class AuthRegistrationTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();
        $this->clearLimits('nuevo@jumpweb.test');
    }

    private function clearLimits(string ...$emails): void
    {
        RateLimiter::clear('register:127.0.0.1');
        RateLimiter::clear('verify-resend:127.0.0.1');

        foreach ($emails as $email) {
            RateLimiter::clear('register-email:'.SelfSignup::emailHash($email));
            RateLimiter::clear('verify-resend-email:'.SelfSignup::emailHash($email));
        }
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        // ⚠️ **Sin casillas legales desde la T8·c** (`#350`): la privacidad es un aviso, las
        // condiciones se aceptan al contratar y el marketing vive en el interruptor de la cuenta.
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'nuevo@jumpweb.test',
            'phone' => '600111222',
            'password' => 'un-secreto-muy-largo-2026',
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function register(array $overrides = []): TestResponse
    {
        return $this->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::ROOT.'/auth/register', $this->payload($overrides));
    }

    public function test_a_valid_signup_creates_the_account_with_its_role_and_the_privacy_consent(): void
    {
        $this->register()
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201)
            ->assertNoContent(201);

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertTrue($user->roles->contains('name', 'customer'));
        // ⚠️⚠️ **Privacidad SÍ, condiciones NO** (T8·c, `#350`). La privacidad se INFORMA (art. 13) y
        // su fila es la constancia del art. 5.2; las condiciones se ACEPTAN, y eso ocurre en el
        // momento del contrato. La lista es EXACTA a propósito: ver el caso de abajo para qué pasa si
        // alguien devuelve aquí la fila `terms`.
        $this->assertSame(['privacy'], $user->consents->pluck('type')->sort()->values()->all());
        $this->assertSame(Consent::CURRENT_VERSION, $user->consents->first()->version);
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertNull($user->terms_accepted_at, 'el alta no acepta condiciones: eso es del checkout');
        Notification::assertSentTo($user, VerifyEmailAddress::class);
    }

    /**
     * ❗❗❗ **EL caso de la T8·c, y el que hace que la tanda no sea cosmética.**
     *
     * Quitar la casilla de la pantalla no basta: mientras el alta siguiera escribiendo una fila
     * `terms`, la **regla de gracia** de {@see TermsAcceptance::statusFor()} la empataría con la v1 —
     * porque `Consent::CURRENT_VERSION` es una fecha (`2026-05-23`) y no un `vN·xx`— y el checkout
     * **no le pediría nada nunca** a ninguna cuenta nueva. Medido antes de tocar el código:
     * `pendingFor()` devolvía `false` para una cuenta recién creada.
     *
     * ⚠️ **Con su CONTROL**: la gracia sigue viva para quien SÍ aceptó antes del versionado, que es
     * la única razón por la que existe. Sin ese control, este caso pasaría igual habiendo roto la
     * regla entera.
     */
    public function test_a_brand_new_account_still_owes_the_terms_and_the_grace_rule_survives(): void
    {
        app(LegalDocumentPublisher::class)->publish('condiciones', [
            'es' => ['title' => 'Condiciones', 'body' => [['h' => 'Uno', 'p' => 'Texto.']]],
        ]);

        $this->register()->assertCreated();
        $nueva = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertTrue(
            app(TermsAcceptance::class)->pendingFor($nueva),
            'una cuenta recién creada tiene que pasar por las condiciones al contratar'
        );

        // CONTROL: la cuenta ANTERIOR al versionado, con su fila de fecha. A ésa no se le vuelve a
        // pedir — es el `[DECIDIDO owner]` de `#348`, y sigue en pie.
        $vieja = User::factory()->create(['email' => 'de-siempre@jumpweb.test']);
        $vieja->consents()->create([
            'type' => Consent::TYPE_TERMS,
            'accepted_at' => now()->subYear(),
            'ip' => '127.0.0.1',
            'version' => Consent::CURRENT_VERSION,
        ]);

        $this->assertFalse(
            app(TermsAcceptance::class)->pendingFor($vieja->fresh()),
            'la regla de gracia se ha roto: a quien ya aceptó no se le vuelve a pedir la v1'
        );
    }

    /**
     * `[DECIDIDO owner, 2026-09-02]` (T8·c): **el alta no pide marketing**. Se ofrece en «Mi cuenta →
     * Privacidad», donde además se puede retirar con un clic — que es lo que exige el art. 7.3 y lo
     * que una casilla del alta no daba.
     */
    public function test_the_signup_records_no_marketing_consent(): void
    {
        $this->register()->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertFalse((bool) $user->marketing_opt_in);
        $this->assertNotContains('marketing', $user->consents->pluck('type')->all());
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $this->register(['password' => 'corta'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['password']]]);
    }

    /**
     * Decisión de producto de la clienta, replicada a propósito (`DECISIONES #31`): si el correo ya
     * tiene cuenta verificada **se le dice**, y al titular real le llega un aviso por correo. Si la
     * API lo ocultara, la misma persona vería respuestas distintas según por dónde entrase.
     */
    public function test_an_existing_verified_email_is_reported_and_the_owner_is_warned(): void
    {
        $existing = User::factory()->create(['email' => 'nuevo@jumpweb.test', 'email_verified_at' => now()]);

        $this->register()
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonStructure(['error' => ['fields' => ['email']]]);

        $this->assertSame(1, User::where('email', 'nuevo@jumpweb.test')->count(), 'no puede duplicar la cuenta');
        // ⚠️⚠️ **Y el CTA de ese correo apunta a `login`, no a la home.** Se comprueba aquí desde la
        // auditoría de A8 (`specs/auth-en-cajon.md` §8): vivía en `DuplicateEmailEdgeCaseTest`, que
        // conducía el modal de Livewire, y **era su ÚNICO guardián** —el caso de al lado solo
        // aseveraba que el correo se envía, no a dónde lleva—. El correo y su ruta sobreviven al
        // modal, así que el caso se re-apunta en vez de irse con él (`CONVENCIONES §3.quater`).
        // ▶ La decisión es de `#112`: sin ella el titular llegaba a `/` sin saber qué hacer. Y la
        // ruta sigue existiendo porque `login` es hoy una PUERTA que abre el cajón en su zona.
        Notification::assertSentTo($existing, AccountAlreadyExists::class, function ($notification) use ($existing) {
            $mail = $notification->toMail($existing)->toArray();

            // Con la UTM del correo pegada (T1c de la analítica, `EmailUtm`): el destino sigue siendo `login`.
            $this->assertSame(EmailUtm::tag(route('login'), 'account_already_exists'), $mail['actionUrl'], 'el CTA del correo ya no lleva a identificarse');
            $this->assertSame(__('account.exists_mail.action'), $mail['actionText']);

            return true;
        });
    }

    /** Y si existe SIN verificar, se le reenvía la verificación para que complete su alta. */
    public function test_an_existing_unverified_email_gets_the_verification_again(): void
    {
        $existing = User::factory()->create(['email' => 'nuevo@jumpweb.test', 'email_verified_at' => null]);

        $this->register()->assertStatus(422);

        Notification::assertSentTo($existing, VerifyEmailAddress::class);
        $this->assertSame(1, User::where('email', 'nuevo@jumpweb.test')->count());
    }

    /**
     * El señuelo: no se crea nada y la respuesta es **indistinguible** de un alta buena. Si el bot
     * pudiera notar la diferencia, el honeypot dejaría de servir para lo único que sirve.
     */
    public function test_the_honeypot_pretends_success_without_creating_anything(): void
    {
        $this->register(['website' => 'https://spam.example'])
            ->assertCreated()
            ->assertValidResponse(201);

        $this->assertSame(0, User::where('email', 'nuevo@jumpweb.test')->count());
        Notification::assertNothingSent();
    }

    /**
     * ⚠️⚠️ **Y son indistinguibles BYTE A BYTE, que es más fuerte que «los dos responden 201».**
     *
     * El caso de arriba dice que el señuelo finge éxito; éste dice que **un bot no puede notar la
     * diferencia**, que es lo único para lo que sirve un señuelo. Lo que separa un alta real de una
     * fingida es solo si hay sesión después — y por eso `register.js::runRegister()` tiene que
     * preguntar por `GET /me` en vez de leer la respuesta.
     *
     * ⚠️ **Este caso no se puede medir mutando UNA rama** (`CONVENCIONES §3.quater`, trampa 4): las
     * dos respuestas son iguales **por diseño**, así que un mutante de un solo lado es equivalente y
     * su verde no dice nada. Se mide haciendo que **difieran**.
     *
     * ▶ Vivía en `SidebarRegisterParityTest` y se mudó aquí el 2026-08-23
     * (`specs/auth-en-cajon.md` §4.7.bis): no comparaba dos motores —no monta Livewire por ningún
     * lado—, es una propiedad de `POST /auth/register` y su sujeto es `INVARIANTES` SEC-06.
     */
    public function test_the_honeypot_is_indistinguishable_from_a_real_signup(): void
    {
        $real = $this->register(['email' => 'persona@jumpweb.test', 'context' => 'purchase']);

        auth()->logout();

        $bot = $this->register([
            'email' => 'bot@jumpweb.test', 'website' => 'soy-un-bot', 'context' => 'purchase',
        ]);

        $this->assertSame($real->getStatusCode(), $bot->getStatusCode(), 'el estado tiene que ser el mismo');
        $this->assertSame($real->getContent(), $bot->getContent(), 'y el cuerpo también, o el señuelo no sirve');

        $this->assertFalse(auth()->check(), 'el señuelo no identifica a nadie');
        $this->assertNull(User::where('email', 'bot@jumpweb.test')->first(), 'ni crea cuenta');
        $this->assertNotNull(User::where('email', 'persona@jumpweb.test')->first(), 'el alta real sí');
    }

    /**
     * ⚠️ **El señuelo VACÍO es el caso normal, y hacía fallar el alta entera.**
     *
     * Un cliente legítimo manda `website: ""` —el campo existe en el formulario y viaja siempre—.
     * `ConvertEmptyStringsToNull` lo convierte en `null`, `sometimes` lo veía presente y `string` lo
     * rechazaba: **422 sobre un campo que el usuario no ve** y que ni siquiera es suyo. Los casos de
     * este fichero lo esquivaban por los dos únicos caminos posibles —mandarlo relleno u omitirlo—, y
     * lo destapó el primer cliente real del endpoint, el cajón SPA.
     *
     * Se comprueban las dos formas de «vacío» que puede mandar un cliente.
     */
    public function test_an_empty_honeypot_is_what_a_legitimate_client_sends(): void
    {
        $this->register(['website' => ''])
            ->assertCreated()
            ->assertValidResponse(201);

        $this->assertSame(1, User::where('email', 'nuevo@jumpweb.test')->count());

        $this->register(['email' => 'otra@jumpweb.test', 'website' => null])
            ->assertCreated();

        $this->assertSame(1, User::where('email', 'otra@jumpweb.test')->count());
    }

    /** Lo mismo para el token del anti-bot: sin widget en pantalla, un cliente manda la cadena vacía. */
    public function test_an_empty_turnstile_token_does_not_break_a_signup_without_anti_bot(): void
    {
        $this->register(['turnstile_token' => ''])
            ->assertCreated()
            ->assertValidResponse(201);

        $this->assertSame(1, User::where('email', 'nuevo@jumpweb.test')->count());
    }

    /**
     * ⚠️ **La rama del anti-bot ACTIVO no la cubría nadie**, y es la que el cajón acaba de estrenar
     * (4.4b·2). Hasta ahora el único caso de token corría con el anti-bot APAGADO, o sea que el
     * `Turnstile::verify()` ni se llamaba.
     *
     * El modo de fallo que fija es el más caro de diagnosticar del sistema: un widget que no llega a
     * pintarse manda el token vacío, y `Turnstile::verify('')` **corta antes del POST a Cloudflare y
     * antes de su `Log::warning`** — así que el alta se rechaza sin dejar rastro NI en los logs del
     * servidor NI en el panel de Cloudflare. Lo único observable es este 422.
     */
    public function test_with_the_anti_bot_active_an_empty_token_is_rejected_and_creates_nobody(): void
    {
        $this->enableAntiBot();

        $this->register(['turnstile_token' => ''])
            ->assertStatus(422)
            ->assertValidResponse(422);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@jumpweb.test']);
    }

    /**
     * El camino feliz con anti-bot. ⚠️ El `Http::fake` NO es decorado: `TestCase::setUp()` activa
     * `Http::preventStrayRequests()`, y con un token NO vacío `Turnstile::verify()` sí sale a la red.
     * Sin el doble, esto moriría por «stray request» y el rojo hablaría de otra cosa.
     */
    public function test_with_the_anti_bot_active_a_verified_token_lets_the_signup_through(): void
    {
        $this->enableAntiBot();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->register(['turnstile_token' => 'un-token-que-cloudflare-acepta'])
            ->assertCreated()
            ->assertValidResponse(201);

        $this->assertSame(1, User::where('email', 'nuevo@jumpweb.test')->count());
    }

    /** Deja el anti-bot COMPLETO (las dos claves) y purga los dos memos estáticos. */
    private function enableAntiBot(): void
    {
        Setting::updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site', 'group' => 'security']);
        Setting::updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secreto', 'group' => 'security']);

        Setting::flushMemo();
        Turnstile::flushCache();

        $this->assertTrue(Turnstile::enabled(), 'el anti-bot no quedó activo: el caso no probaría nada');
    }

    public function test_signups_are_rate_limited_per_ip(): void
    {
        foreach (range(1, SelfSignup::MAX_PER_IP) as $i) {
            $this->register(['email' => "alta{$i}@jumpweb.test"])->assertCreated();
        }

        $blocked = $this->register(['email' => 'una-mas@jumpweb.test'])
            ->assertStatus(429)
            ->assertValidResponse(429);

        $this->assertGreaterThan(0, $blocked->json('error.params.retry_after'));
        $this->assertSame(0, User::where('email', 'una-mas@jumpweb.test')->count());
    }

    /**
     * Límite por CORREO destinatario: impide que alguien con IPs rotativas llene el buzón de un
     * tercero con verificaciones o con avisos de «ya tienes cuenta». Al superarlo se responde como
     * un éxito y **no se envía nada** — igual que el señuelo, y por el mismo motivo.
     */
    public function test_the_per_email_limit_silently_stops_sending(): void
    {
        $victim = 'victima@jumpweb.test';
        $this->clearLimits($victim);
        User::factory()->create(['email' => $victim, 'email_verified_at' => now()]);

        foreach (range(1, SelfSignup::MAX_PER_EMAIL) as $ignored) {
            RateLimiter::clear('register:127.0.0.1');
            $this->register(['email' => $victim])->assertStatus(422);
        }

        RateLimiter::clear('register:127.0.0.1');
        Notification::fake();

        $this->register(['email' => $victim])->assertCreated();
        Notification::assertNothingSent();
    }

    // ── Contexto del alta ─────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **`#331` DA LA VUELTA A ESTE CASO** (`[DECIDIDO owner, 2026-09-01]`: «al registrarse,
     * directamente el usuario entra a su cuenta»).
     *
     * Decía que el alta suelta **no** abre sesión, y ese era el problema: terminaba en una pantalla
     * de «revisa tu correo» que es un callejón. Sin sesión no hay QR, y el QR es lo que identifica al
     * cliente en la puerta — así que quien se registra en el móvil delante del mostrador se quedaba
     * sin lo único que había ido a buscar.
     *
     * ▶ **Lo que NO cambia, y por eso el caso sobrevive**: la verificación se sigue enviando, y el
     * correo sigue SIN verificar. Lo que se le pide ahora se le pide DENTRO de su cuenta, con el
     * botón de reenviar al lado (`account.verify.pending_notice`).
     */
    public function test_a_standalone_signup_opens_a_session_and_still_sends_the_verification(): void
    {
        $this->register()->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at, 'entrar no es verificar: el buzón sigue sin demostrarse');
        Notification::assertSentTo($user, VerifyEmailAddress::class);
    }

    /**
     * ⚠️⚠️ **EL CONTROL: un HONEYPOT no identifica a nadie.** La sesión se abre porque hubo cuenta, no
     * porque llegara una petición — si el señuelo pudiera dejar sesión, sería una cuenta gratis.
     */
    public function test_a_honeypot_signup_leaves_no_session(): void
    {
        $this->register(['website' => 'soy-un-bot'])->assertCreated();

        $this->assertGuest();
        $this->assertNull(User::where('email', 'nuevo@jumpweb.test')->first(), 'el señuelo no crea cuenta');
    }

    /**
     * Alta dentro de la compra («pay-first», decisión de la clienta del 2026-06-14): se inicia
     * sesión para poder seguir al pago y **no** se manda verificación — la sustituye el pago, y un
     * bot no paga.
     */
    public function test_a_purchase_signup_opens_a_session_and_skips_the_verification_email(): void
    {
        $this->register(['context' => 'purchase'])->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at, 'pay-first no verifica: eso lo hace el pago');
        Notification::assertNothingSent();
    }

    /**
     * El alta en la compra deja sesión abierta, así que exige un origen *stateful*. Sin él se
     * rechaza ANTES de crear nada — lo encontró un `curl` contra el servidor real, no la suite: los
     * tests mandan siempre el encabezado y ejercitan la rama buena. Antes de la guarda, la cuenta
     * se creaba y el 500 llegaba después, dejando al cliente sin saber si tenía cuenta.
     */
    public function test_a_purchase_signup_without_a_stateful_origin_is_rejected_before_creating_anything(): void
    {
        $this->postJson(self::ROOT.'/auth/register', $this->payload(['context' => 'purchase']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'bad_request');

        $this->assertSame(0, User::where('email', 'nuevo@jumpweb.test')->count());
    }

    /** El alta SUELTA no necesita sesión y sí funciona sin ese encabezado. */
    public function test_a_standalone_signup_works_without_a_stateful_origin(): void
    {
        $this->postJson(self::ROOT.'/auth/register', $this->payload())->assertCreated();

        $this->assertSame(1, User::where('email', 'nuevo@jumpweb.test')->count());
    }

    public function test_an_unknown_context_is_rejected(): void
    {
        $this->register(['context' => 'admin'])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['fields' => ['context']]]);
    }

    // ── Reenvío de la verificación ────────────────────────────────────────────────────────────

    public function test_the_verification_can_be_resent_without_a_session(): void
    {
        $user = User::factory()->create(['email' => 'nuevo@jumpweb.test', 'email_verified_at' => null]);

        $this->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::ROOT.'/auth/email/resend', ['email' => $user->email])
            ->assertAccepted()
            ->assertValidRequest()
            ->assertValidResponse(202);

        Notification::assertSentTo($user, VerifyEmailAddress::class);
    }

    /**
     * Responde 202 exista o no la cuenta: si el cooldown o la inexistencia se notaran, esto sería
     * un oráculo de correos registrados.
     */
    public function test_resending_answers_the_same_for_an_unknown_email(): void
    {
        $this->postJson(self::ROOT.'/auth/email/resend', ['email' => 'no-existe@jumpweb.test'])
            ->assertAccepted();

        Notification::assertNothingSent();
    }

    /** Y no se le puede reenviar a quien ya verificó: no hay nada que verificar. */
    public function test_resending_does_nothing_for_a_verified_account(): void
    {
        $user = User::factory()->create(['email' => 'nuevo@jumpweb.test', 'email_verified_at' => now()]);

        $this->postJson(self::ROOT.'/auth/email/resend', ['email' => $user->email])->assertAccepted();

        Notification::assertNothingSent();
    }

    /** El cooldown por IP tampoco se nota en la respuesta, pero sí deja de enviar. */
    public function test_the_resend_cooldown_is_silent(): void
    {
        $user = User::factory()->create(['email' => 'nuevo@jumpweb.test', 'email_verified_at' => null]);

        $this->postJson(self::ROOT.'/auth/email/resend', ['email' => $user->email])->assertAccepted();
        Notification::assertSentTo($user, VerifyEmailAddress::class);

        Notification::fake();
        $this->postJson(self::ROOT.'/auth/email/resend', ['email' => $user->email])->assertAccepted();
        Notification::assertNothingSent();
    }

    // ─── Fase 6 · waiver: la casilla SEPARADA del alta (`specs/waiver-probatorio.md` §4.4) ───

    private function internalWaiver(): LegalDocumentVersion
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);

        return app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /**
     * `[DECIDIDO owner, 2026-08-26]` (spec §7·5, `#179`): el alta con la casilla NO firma al crear la
     * cuenta — deja la aceptación pendiente y la firma se registra al VERIFICAR el correo, con el
     * canal del alta. Hasta entonces la firma nacía con `email_verified_at = null` (revisión `#169`).
     */
    public function test_accepting_the_waiver_at_signup_defers_the_signature_until_the_email_is_verified(): void
    {
        $document = $this->internalWaiver();

        $this->register(['accept_waiver' => true, 'waiver_document_id' => $document->id])
            ->assertCreated()
            ->assertValidRequest();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();
        $this->assertSame(0, WaiverSignature::where('user_id', $user->id)->count(), 'sin correo verificado NO hay firma');
        $this->assertSame($document->id, $user->waiver_pending_document_id);
        $this->assertSame('web', $user->waiver_pending_channel);
        $this->assertNull($user->waiver_accepted_at);

        $this->verifyEmail($user);

        $user->refresh();
        $signature = WaiverSignature::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($document->id, $signature->legal_document_version_id);
        $this->assertSame('web', $signature->channel, 'el canal es el del ALTA, no el del clic de verificación');
        $this->assertSame('Ana Pérez', $signature->holder_name);
        $this->assertTrue($signature->verifyHash());
        $this->assertSame(['privacy', 'waiver'], $user->consents->pluck('type')->sort()->values()->all());
        $this->assertNotNull($user->waiver_accepted_at);
        $this->assertNull($user->waiver_pending_document_id, 'la pendiente se limpia al firmar');
        $this->assertDatabaseHas('audit_logs', ['action' => 'waiver.signed']);
    }

    /** Abre el enlace firmado del correo, como hace la persona; el controlador abre sesión y aquí se cierra. */
    private function verifyEmail(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1((string) $user->email)]);
        $this->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        auth()->logout();
    }

    /** Si el texto cambió entre el alta y la verificación, la aceptación pendiente se DESCARTA: nada se firma sin releer. */
    public function test_a_stale_pending_acceptance_is_dropped_at_verification(): void
    {
        $old = $this->internalWaiver();
        $this->register(['accept_waiver' => true, 'waiver_document_id' => $old->id])->assertCreated();
        app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Texto nuevo.']]],
        ]);
        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->verifyEmail($user);

        $user->refresh();
        $this->assertSame(0, WaiverSignature::where('user_id', $user->id)->count());
        $this->assertNull($user->waiver_pending_document_id, 'la pendiente caducada no se queda colgada');
        $this->assertNull($user->waiver_accepted_at);
    }

    /** Desmarcada por defecto, y fuera del modo interno OPCIONAL: sin la casilla el alta es la de siempre, sin waiver. */
    public function test_without_the_checkbox_the_signup_leaves_no_waiver(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'externo', 'group' => 'waiver']);

        $this->register()->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();
        $this->assertSame(0, WaiverSignature::where('user_id', $user->id)->count());
        $this->assertNull($user->waiver_accepted_at);
    }

    /** §4.4 — el texto cambió entre servirlo y aceptarlo: NO se crea la cuenta con un texto viejo. */
    public function test_a_stale_document_rejects_the_signup_before_creating_anything(): void
    {
        $old = $this->internalWaiver();
        app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Texto nuevo.']]],
        ]);

        $this->register(['accept_waiver' => true, 'waiver_document_id' => $old->id])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.fields.waiver_document_id.0', __('api.register.waiver_stale'));

        $this->assertNull(User::where('email', 'nuevo@jumpweb.test')->first());
    }

    public function test_accepting_without_saying_which_text_is_rejected(): void
    {
        $this->internalWaiver();

        $this->register(['accept_waiver' => true])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.waiver_document_id.0', __('api.register.waiver_document_required'));

        $this->assertNull(User::where('email', 'nuevo@jumpweb.test')->first());
    }

    public function test_outside_internal_mode_the_checkbox_is_refused(): void
    {
        $document = app(LegalDocumentPublisher::class)->publish('waiver', [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first(); // sin `waiver.mode`: externo

        $this->register(['accept_waiver' => true, 'waiver_document_id' => $document->id])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.waiver_document_id.0', __('api.register.waiver_not_internal'));

        $this->assertNull(User::where('email', 'nuevo@jumpweb.test')->first());
    }

    /** El rol `customer` tiene que existir para que el alta lo asigne; si no, el test miente. */
    public function test_the_customer_role_exists_in_the_fixture(): void
    {
        $this->assertNotNull(Role::where('name', 'customer')->first());
    }

    /** `#169` §10.2·1 — el canal del alta sale de si la petición se sirvió CON sesión (cajón) o sin ella (app nativa). */
    public function test_a_stateless_signup_signs_through_the_api_channel(): void
    {
        $document = $this->internalWaiver();

        // Sin `Origin`: el grupo stateful de Sanctum no monta la sesión — así llega una app nativa.
        $this->postJson(self::ROOT.'/auth/register', $this->payload(['accept_waiver' => true, 'waiver_document_id' => $document->id]))
            ->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();
        $this->assertSame('api', $user->waiver_pending_channel);
        $this->verifyEmail($user);
        $this->assertSame('api', WaiverSignature::where('user_id', $user->id)->value('channel'));
    }

    /** Y una cabecera `Authorization` suelta ya no decide nada: con sesión sigue siendo «web». */
    public function test_a_junk_bearer_header_does_not_turn_a_web_signup_into_api(): void
    {
        $document = $this->internalWaiver();

        $this->withHeader('Authorization', 'Bearer basura')
            ->register(['accept_waiver' => true, 'waiver_document_id' => $document->id])
            ->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();
        $this->verifyEmail($user);
        $this->assertSame('web', WaiverSignature::where('user_id', $user->id)->value('channel'));
    }

    /** `[DECIDIDO owner]` (spec §7·7, `#178`): en modo interno con versión publicada, la casilla es OBLIGATORIA. */
    public function test_in_internal_mode_with_a_published_version_the_checkbox_is_required(): void
    {
        $this->internalWaiver();

        $this->register()
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.fields.accept_waiver.0', __('api.register.waiver_required'));
        $this->register(['accept_waiver' => false])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.accept_waiver.0', __('api.register.waiver_required'));

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@jumpweb.test']);
    }

    /** Sin texto que aceptar (interno sin versión publicada) la casilla no puede ser obligatoria: no existe. */
    public function test_in_internal_mode_without_a_published_version_the_checkbox_is_not_required(): void
    {
        Setting::updateOrCreate(['key' => 'waiver.mode'], ['value' => 'interno', 'group' => 'waiver']);

        $this->register()->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();
        $this->assertSame(0, WaiverSignature::where('user_id', $user->id)->count());
    }

    /** S-1 (`#181`): la firma lleva la IP/UA de la ACEPTACIÓN (el alta), no de la petición que verifica. */
    public function test_the_signature_carries_the_user_agent_of_the_acceptance_not_of_the_verification_click(): void
    {
        $document = $this->internalWaiver();

        $this->withHeader('User-Agent', 'Alta/1.0 (navegador de la persona)')
            ->register(['accept_waiver' => true, 'waiver_document_id' => $document->id])
            ->assertCreated();
        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();
        $this->assertSame('Alta/1.0 (navegador de la persona)', $user->waiver_pending_user_agent);
        $this->assertNotNull($user->waiver_pending_ip);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1((string) $user->email)]);
        $this->withHeader('User-Agent', 'Verificador/2.0 (otra máquina)')->get($url)->assertRedirect();
        auth()->logout();

        $signature = WaiverSignature::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Alta/1.0 (navegador de la persona)', $signature->user_agent);
        $this->assertNull($user->fresh()->waiver_pending_user_agent);
    }

    /** S-1 (`#181`): si `Verified` se emite DENTRO de una transacción (el cobro), la firma espera al commit. */
    public function test_the_pending_signature_waits_for_the_commit_of_the_transaction_that_verifies(): void
    {
        $document = $this->internalWaiver();
        $this->register(['accept_waiver' => true, 'waiver_document_id' => $document->id])->assertCreated();
        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        DB::beginTransaction();
        $user->markEmailAsVerified();
        event(new Verified($user));
        $this->assertSame(0, WaiverSignature::where('user_id', $user->id)->count(), 'dentro de la transacción no se firma');
        DB::commit();

        $this->assertSame(1, WaiverSignature::where('user_id', $user->id)->count(), 'tras el commit, sí');
    }
}
