<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SelfSignup;
use App\Notifications\AccountAlreadyExists;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
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
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'nuevo@jumpweb.test',
            'phone' => '600111222',
            'password' => 'un-secreto-muy-largo-2026',
            'accept_privacy' => true,
            'accept_terms' => true,
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides */
    private function register(array $overrides = []): TestResponse
    {
        return $this->withHeader('Origin', (string) config('app.url'))
            ->postJson(self::ROOT.'/auth/register', $this->payload($overrides));
    }

    public function test_a_valid_signup_creates_the_account_with_its_role_and_consents(): void
    {
        $this->register()
            ->assertCreated()
            ->assertValidRequest()
            ->assertValidResponse(201)
            ->assertNoContent(201);

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertTrue($user->roles->contains('name', 'customer'));
        $this->assertSame(['privacy', 'terms'], $user->consents->pluck('type')->sort()->values()->all());
        $this->assertSame(Consent::CURRENT_VERSION, $user->consents->first()->version);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_marketing_consent_is_optional_and_recorded_when_given(): void
    {
        $this->register(['marketing' => true])->assertCreated();

        $user = User::where('email', 'nuevo@jumpweb.test')->firstOrFail();

        $this->assertTrue($user->marketing_opt_in);
        $this->assertContains('marketing', $user->consents->pluck('type')->all());
    }

    /** Las dos casillas legales son la prueba de aceptación del RGPD: sin ellas no hay alta. */
    public function test_the_legal_checkboxes_are_required(): void
    {
        $this->register(['accept_privacy' => false, 'accept_terms' => false])
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonStructure(['error' => ['fields' => ['accept_privacy', 'accept_terms']]]);

        $this->assertSame(0, User::where('email', 'nuevo@jumpweb.test')->count());
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
        Notification::assertSentTo($existing, AccountAlreadyExists::class);
    }

    /** Y si existe SIN verificar, se le reenvía la verificación para que complete su alta. */
    public function test_an_existing_unverified_email_gets_the_verification_again(): void
    {
        $existing = User::factory()->create(['email' => 'nuevo@jumpweb.test', 'email_verified_at' => null]);

        $this->register()->assertStatus(422);

        Notification::assertSentTo($existing, VerifyEmail::class);
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

    /** Alta suelta: no se inicia sesión y se envía la verificación. */
    public function test_a_standalone_signup_does_not_open_a_session(): void
    {
        $this->register()->assertCreated();

        $this->assertGuest();
        Notification::assertSentTo(User::where('email', 'nuevo@jumpweb.test')->firstOrFail(), VerifyEmail::class);
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

        Notification::assertSentTo($user, VerifyEmail::class);
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
        Notification::assertSentTo($user, VerifyEmail::class);

        Notification::fake();
        $this->postJson(self::ROOT.'/auth/email/resend', ['email' => $user->email])->assertAccepted();
        Notification::assertNothingSent();
    }

    /** El rol `customer` tiene que existir para que el alta lo asigne; si no, el test miente. */
    public function test_the_customer_role_exists_in_the_fixture(): void
    {
        $this->assertNotNull(Role::where('name', 'customer')->first());
    }
}
