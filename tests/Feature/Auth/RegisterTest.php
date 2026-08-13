<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\SelfSignup;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use App\Livewire\Auth\Register;
use App\Notifications\AccountAlreadyExists;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // El chequeo anti-filtración (HIBP) no debe llamar a la red en tests.
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });

        RateLimiter::clear('register:127.0.0.1');
        RateLimiter::clear('verify-resend:127.0.0.1');
        $this->clearEmailRateLimits('ana@example.com');
    }

    /**
     * Limpia los rate limiters por EMAIL (register-email + verify-resend-email) para los
     * tests que iteran sobre el mismo email — el cooldown server-side los bloquearía.
     */
    private function clearEmailRateLimits(string ...$emails): void
    {
        foreach ($emails as $email) {
            $hash = SelfSignup::emailHash($email);
            RateLimiter::clear('register-email:'.$hash);
            RateLimiter::clear('verify-resend-email:'.$hash);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'phone' => '600123123',
            'password' => 'una-frase-larga-y-segura',
            'accept_privacy' => true,
            'accept_terms' => true,
            'marketing' => false,
        ], $overrides);
    }

    public function test_valid_registration_creates_user_consents_role_and_sends_email(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register')
            ->assertHasNoErrors()
            ->assertSet('sent', true); // se queda en la pantalla de confirmación (no redirige)

        $user = User::where('email', 'ana@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
        $this->assertNull($user->email_verified_at);
        // #216: el waiver salió del flujo de registro → solo privacy + terms (sin waiver).
        $this->assertSame(2, $user->consents()->count());
        $this->assertEqualsCanonicalizing(
            ['privacy', 'terms'],
            $user->consents()->pluck('type')->all(),
        );
        $this->assertNull($user->waiver_accepted_at, 'el registro ya no firma el waiver');

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertGuest(); // no inicia sesión hasta verificar
    }

    public function test_marketing_optin_adds_marketing_consent(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->set($this->valid(['marketing' => true]))
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'ana@example.com')->first();
        $this->assertTrue($user->marketing_opt_in);
        $this->assertSame(3, $user->consents()->count()); // privacy + terms + marketing (#216: sin waiver)
        $this->assertContains('marketing', $user->consents()->pluck('type')->all());
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(Register::class)
            ->set($this->valid(['name' => '', 'email' => '', 'phone' => '', 'password' => '']))
            ->call('register')
            ->assertHasErrors(['name', 'email', 'phone', 'password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_legal_checkboxes_are_required(): void
    {
        Livewire::test(Register::class)
            ->set($this->valid(['accept_privacy' => false, 'accept_terms' => false]))
            ->call('register')
            ->assertHasErrors(['accept_privacy', 'accept_terms']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_must_have_minimum_length(): void
    {
        Livewire::test(Register::class)
            ->set($this->valid(['password' => 'corta']))
            ->call('register')
            ->assertHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_verified_email_shows_clear_feedback_and_does_not_duplicate(): void
    {
        // [decisión clienta] Feedback CLARO en vez de anti-enumeración: si el email ya existe y está
        // verificado, se avisa en pantalla (error en `email`) y se alerta al titular por correo.
        Notification::fake();
        $existing = User::factory()->create(['email' => 'ana@example.com']); // verificado

        Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register')
            ->assertHasErrors(['email']);

        $this->assertSame(1, User::where('email', 'ana@example.com')->count());
        Notification::assertSentTo($existing, AccountAlreadyExists::class);
        Notification::assertNotSentTo($existing, VerifyEmail::class);
    }

    public function test_existing_unverified_email_resends_verification(): void
    {
        Notification::fake();
        $existing = User::factory()->unverified()->create(['email' => 'ana@example.com']);

        Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register')
            ->assertHasErrors(['email']); // feedback claro + reenvío de verificación

        $this->assertSame(1, User::where('email', 'ana@example.com')->count());
        Notification::assertSentTo($existing, VerifyEmail::class);
        Notification::assertNotSentTo($existing, AccountAlreadyExists::class);
    }

    public function test_honeypot_blocks_account_creation_but_shows_generic_screen(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->set($this->valid(['website' => 'http://spam.example']))
            ->call('register')
            ->assertSet('sent', true); // pantalla genérica (el bot no nota nada)

        $this->assertDatabaseCount('users', 0); // pero NO se crea cuenta
        Notification::assertNothingSent();
    }

    public function test_rate_limit_blocks_after_too_many_attempts(): void
    {
        Notification::fake();

        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Register::class)
                ->set($this->valid(['email' => "user{$i}@example.com"]))
                ->call('register')
                ->assertHasNoErrors();
        }

        Livewire::test(Register::class)
            ->set($this->valid(['email' => 'user6@example.com']))
            ->call('register')
            ->assertHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'user6@example.com']);
    }

    public function test_resend_sends_verification_again_and_decrements_counter(): void
    {
        Notification::fake();

        $component = Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register')
            ->assertSet('sent', true)
            ->assertSet('resendsLeft', 4);

        $user = User::where('email', 'ana@example.com')->first();
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        RateLimiter::clear('verify-resend:127.0.0.1');
        $this->clearEmailRateLimits('ana@example.com');
        $component->call('resend')->assertSet('resendsLeft', 3);

        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_resend_respects_the_total_limit(): void
    {
        Notification::fake();

        $component = Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register');

        for ($i = 0; $i < 6; $i++) {
            RateLimiter::clear('verify-resend:127.0.0.1');
            $this->clearEmailRateLimits('ana@example.com');
            $component->call('resend');
        }

        $component->assertSet('resendsLeft', 0);
        $user = User::where('email', 'ana@example.com')->first();
        // 1 inicial + 4 reenvíos permitidos (los siguientes se ignoran) = 5
        Notification::assertSentToTimes($user, VerifyEmail::class, 5);
    }

    public function test_resend_does_nothing_before_registration(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->call('resend')
            ->assertSet('resendsLeft', 4);

        Notification::assertNothingSent();
    }

    public function test_closing_modal_resets_all_state(): void
    {
        Notification::fake();

        // Privacidad (tablet compartida): al cerrar, no debe quedar nada del cliente anterior.
        Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register')
            ->assertSet('sent', true)
            ->dispatch('auth-modal-closed')
            ->assertSet('sent', false)
            ->assertSet('email', '')
            ->assertSet('name', '')
            ->assertSet('phone', '')
            ->assertSet('password', '')
            ->assertSet('resendsLeft', 4);
    }

    public function test_closing_modal_clears_validation_errors(): void
    {
        // El mensaje de error no debe persistir al reabrir el modal.
        Livewire::test(Register::class)
            ->set($this->valid(['email' => 'no-es-email', 'accept_privacy' => false]))
            ->call('register')
            ->assertHasErrors()
            ->dispatch('auth-modal-closed')
            ->assertHasNoErrors()
            ->assertSet('email', '');
    }

    public function test_per_email_rate_limit_silently_skips_after_three_attempts(): void
    {
        // Protección anti-DoS al buzón víctima: tras 3 intentos por el MISMO email
        // (incluso con IPs distintas, simulado limpiando el rate IP), no se manda
        // nada — pero la pantalla genérica "sent" se muestra igual (anti-enumeración).
        Notification::fake();
        $existing = User::factory()->unverified()->create(['email' => 'ana@example.com']);

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear('register:127.0.0.1'); // simula IP rotativa
            Livewire::test(Register::class)
                ->set($this->valid(['email' => 'ana@example.com']))
                ->call('register')
                ->assertHasErrors(['email']); // existente sin verificar → feedback claro + reenvío
        }
        Notification::assertSentToTimes($existing, VerifyEmail::class, 3);

        // El 4.º intento por el mismo email salta el rate-limit por email (antes del check de
        // existencia): cae a la pantalla genérica anti-DoS y NO envía nada más.
        RateLimiter::clear('register:127.0.0.1');
        Livewire::test(Register::class)
            ->set($this->valid(['email' => 'ana@example.com']))
            ->call('register')
            ->assertSet('sent', true);

        Notification::assertSentToTimes($existing, VerifyEmail::class, 3); // no sube
    }

    public function test_resend_email_cooldown_skips_second_send_but_decrements_counter(): void
    {
        // Cooldown por email víctima (1/min): el PRIMER resend sí envía; el segundo en menos de
        // un minuto NO envía pero sí gasta una unidad del límite del modal (anti-loophole: no se
        // puede gastar el límite total del modal sin que el envío real esté topado).
        Notification::fake();

        $component = Livewire::test(Register::class)
            ->set($this->valid())
            ->call('register')
            ->assertSet('resendsLeft', 4);

        $user = User::where('email', 'ana@example.com')->first();
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        // Primer resend: rate por email aún en 0/1 → envía. resendsLeft 4→3.
        RateLimiter::clear('verify-resend:127.0.0.1');
        $component->call('resend')->assertSet('resendsLeft', 3);
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);

        // Segundo resend: rate por email ya en 1/1 (sin limpiarlo) → bloquea. resendsLeft 3→2.
        RateLimiter::clear('verify-resend:127.0.0.1');
        $component->call('resend')->assertSet('resendsLeft', 2);
        Notification::assertSentToTimes($user, VerifyEmail::class, 2); // no sube
    }

    public function test_database_rollback_when_consent_creation_fails(): void
    {
        // Atomicidad: si la creación de un consent falla, el usuario y los consents previos
        // deben revertirse (no quedar a medias). Forzamos el fallo dropeando la tabla `consents`.
        Notification::fake();

        DB::statement('DROP TABLE consents');

        try {
            Livewire::test(Register::class)
                ->set($this->valid())
                ->call('register');
            $this->fail('Se esperaba una excepción al crear consents.');
        } catch (\Throwable) {
            // esperado
        }

        $this->assertSame(0, User::where('email', 'ana@example.com')->count()); // user revertido
        Notification::assertNothingSent();
    }

    public function test_embedded_register_renders_turnstile_robustly_for_livewire_morphs(): void
    {
        // Regresión de PRODUCCIÓN: el registro embebido en el flujo de compra llega al DOM por un
        // MORPH de Livewire. Un <script src=api.js> PLANO no se ejecuta al inyectarse por morph, así
        // que el widget nunca se dibujaba, el token llegaba vacío y `verify('')` lanzaba "no eres un
        // robot" sin correo ni log. El fix carga api.js con @assets (Livewire lo inyecta también en
        // morphs) y lo renderiza con @script (turnstile.render) sobre un nodo wire:ignore.
        // Este test fija el contrato del MARKUP correcto (no podemos ejecutar JS de navegador aquí).
        Setting::updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key', 'group' => 'security']);
        Setting::updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key', 'group' => 'security']);
        $this->resetTurnstileCache();

        try {
            $html = Livewire::test(Register::class, ['embedded' => true])->html();

            // Render vía Alpine (x-init corre en nodos morphados) sobre un nodo wire:ignore que
            // sobrevive a los re-render de validación. La sitekey se inyecta al componente Alpine.
            $this->assertStringContainsString('wire:ignore', $html);
            $this->assertStringContainsString("turnstileField('site-key')", $html);

            // El patrón ROTO no debe volver: ni <script> plano de api.js en el cuerpo (un <script>
            // inyectado por morph no se ejecuta), ni el auto-render por data-callback (no detecta
            // nodos inyectados por morph). La carga de api.js vive en app.js (createElement).
            $this->assertStringNotContainsString('data-callback', $html);
            $this->assertStringNotContainsString('turnstile/v0/api.js', $html);
        } finally {
            $this->resetTurnstileCache(); // no envenenar la memoización estática de tests posteriores
        }
    }

    /** Resetea la memoización estática de Turnstile (persiste entre tests del mismo proceso). */
    private function resetTurnstileCache(): void
    {
        $ref = new \ReflectionProperty(Turnstile::class, 'cache');
        $ref->setValue(null, null);
    }
}
