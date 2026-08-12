<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use App\Livewire\Auth\Register;
use App\Livewire\Tickets\Purchase;
use App\Notifications\AccountAlreadyExists;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Decisión #112 (2026-05-28) — Edge case: usuario intenta registrarse durante la compra
 * con un email que ya tenía cuenta. Anti-enumeración (#46) hace que vea la pantalla
 * genérica "verifica tu correo"; el email `AccountAlreadyExists` le sugiere iniciar
 * sesión. Estos tests blindan:
 *
 *   1. CTA del email apunta a `route('login')` (modal de login en la home), no a `/`.
 *   2. La pantalla `sent` SIEMPRE muestra el link "¿ya tienes cuenta? Inicia sesión"
 *      (visible en registro nuevo Y en duplicado → anti-enumeración preservada).
 *   3. En modo embedded (sidebar), el botón llama a `requestSwitchToLogin` (Livewire);
 *      en modo no-embedded, el botón llama a `$store.auth.open('login')` (Alpine).
 *   4. `Register::requestSwitchToLogin` emite el evento que Purchase escucha.
 *   5. `Purchase::onSwitchToLoginTab` reacciona poniendo step=5 + authMode=login.
 */
class DuplicateEmailEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_already_exists_email_cta_points_to_login_route(): void
    {
        Notification::fake();
        $existing = User::factory()->create(['email' => 'duped@test.local']); // verificado

        Livewire::test(Register::class)
            ->set('name', 'Bob')
            ->set('email', 'duped@test.local')
            ->set('phone', '600111222')
            ->set('password', 'super-secreto-no-comun-2026!')
            ->set('accept_privacy', true)
            ->set('accept_terms', true)
            ->call('register');

        // El email se envió al usuario existente (es lo que cierra el loop anti-takeover, #46).
        Notification::assertSentTo($existing, AccountAlreadyExists::class, function ($notification, array $channels) use ($existing) {
            $mail = $notification->toMail($existing);
            $data = $mail->toArray();
            // Decisión #112: el CTA apunta a la ruta `login` (que abre el modal en la home),
            // no a la home raíz. Sin este cambio, el usuario llegaba a `/` sin saber qué hacer.
            $this->assertSame(route('login'), $data['actionUrl']);
            // Texto del CTA traducido (no es un literal de marca como antes).
            $this->assertSame(__('account.exists_mail.action'), $data['actionText']);

            return true;
        });
    }

    public function test_sent_screen_renders_already_have_account_escape_link(): void
    {
        // Registro NUEVO (no duplicado): tras el submit válido, la pantalla `sent` debe
        // mostrar el link "¿Ya tienes cuenta? Inicia sesión" — visible SIEMPRE para que
        // un atacante no pueda inferir si el email existía (anti-enumeración #46).
        Notification::fake();

        $component = Livewire::test(Register::class)
            ->set('name', 'Alice')
            ->set('email', 'alice-new-'.uniqid().'@test.local')
            ->set('phone', '600999888')
            ->set('password', 'super-secreto-no-comun-2026!')
            ->set('accept_privacy', true)
            ->set('accept_terms', true)
            ->call('register');

        $component
            ->assertSet('sent', true)
            ->assertSee(__('account.verify.already_have_account'))
            ->assertSee(__('account.login.cta'));
    }

    public function test_duplicate_verified_email_shows_clear_feedback(): void
    {
        // [decisión clienta] La anti-enumeración se sustituyó por FEEDBACK CLARO: para el parque
        // prima la UX/conversión. Un email ya registrado (verificado) ahora se avisa en pantalla
        // (error en `email` → «ya tienes una cuenta, inicia sesión») en vez de la pantalla genérica.
        Notification::fake();
        User::factory()->create(['email' => 'existing@test.local']);

        Livewire::test(Register::class)
            ->set('name', 'Mallory')
            ->set('email', 'existing@test.local')
            ->set('phone', '600000000')
            ->set('password', 'super-secreto-no-comun-2026!')
            ->set('accept_privacy', true)
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasErrors(['email'])
            ->assertSet('sent', false);
    }

    public function test_request_switch_to_login_dispatches_event_when_embedded(): void
    {
        Livewire::test(Register::class, ['embedded' => true])
            ->set('sent', true)
            ->set('email', 'cualquier@test.local')
            ->call('requestSwitchToLogin')
            ->assertSet('sent', false)
            ->assertSet('email', '')
            ->assertDispatched('purchase:switch-to-login');
    }

    public function test_request_switch_to_login_is_noop_when_not_embedded(): void
    {
        // En modo no-embedded el switch lo hace Alpine ($store.auth.open). No queremos
        // que un POST malicioso a Livewire haga reset del componente del usuario por
        // sorpresa (defense in depth: el método no embedded simplemente no actúa).
        Livewire::test(Register::class, ['embedded' => false])
            ->set('sent', true)
            ->set('email', 'verify@test.local')
            ->call('requestSwitchToLogin')
            ->assertSet('sent', true)
            ->assertSet('email', 'verify@test.local')
            ->assertNotDispatched('purchase:switch-to-login');
    }

    public function test_purchase_listens_to_switch_to_login_event(): void
    {
        // Cuando el sidebar Purchase recibe el evento, debe volver al paso 5 con
        // authMode=login. La cesta sigue intacta en sesión, así el cliente puede
        // continuar la compra inmediatamente tras iniciar sesión.
        Livewire::test(Purchase::class)
            ->set('step', 7)
            ->set('authMode', 'register')
            ->dispatch('purchase:switch-to-login')
            ->assertSet('step', 5)
            ->assertSet('authMode', 'login');
    }
}
