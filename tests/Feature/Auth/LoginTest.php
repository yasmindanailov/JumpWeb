<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\User;
use App\Livewire\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        // La factory crea la contraseña 'password'.
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_embedded_login_dispatches_event_and_does_not_redirect(): void
    {
        // Embebido en el sidebar de compra (#69): al entrar avisa con un evento, sin redirigir.
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(Login::class, ['embedded' => true])
            ->set('email', 'ana@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertDispatched('logged-in');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_case_insensitive_on_email(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'ANA@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_shows_generic_error(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'incorrecta')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_shows_the_same_generic_error(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'desconocido@example.com')
            ->set('password', 'loquesea')
            ->call('login')
            ->assertHasErrors('email'); // mismo error que con contraseña mala (no revela existencia)

        $this->assertGuest();
    }

    public function test_required_fields_are_validated(): void
    {
        Livewire::test(Login::class)
            ->set('email', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)
                ->set('email', 'ana@example.com')
                ->set('password', 'mal')
                ->call('login')
                ->assertHasErrors('email');
        }

        // El 6.º intento queda bloqueado aunque la contraseña sea correcta. El error de bloqueo
        // va a la clave `_global` (banner) para diferenciarlo de "credenciales incorrectas" del
        // campo `email` — L-02 / auditoría 2026-05-26.
        Livewire::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('_global')
            ->assertHasNoErrors('email');

        $this->assertGuest();
    }

    public function test_login_blocks_password_spraying_from_one_ip_across_many_accounts(): void
    {
        // Auditoría Fase 1 (A5): la clave compuesta email|ip NO frena el spraying (muchas cuentas, 1 IP:
        // ninguna llega a 5 fallos). El 2.º limitador por IP-sola sí: tras 30 fallos desde la misma IP
        // con emails DISTINTOS, el siguiente intento queda bloqueado aunque la cuenta y el password sean
        // válidos.
        User::factory()->create(['email' => 'ana@example.com']);

        for ($i = 0; $i < 30; $i++) {
            Livewire::test(Login::class)
                ->set('email', "spray{$i}@example.com") // email distinto → la clave compuesta nunca acumula
                ->set('password', 'mal')
                ->call('login')
                ->assertHasErrors('email');
        }

        Livewire::test(Login::class)
            ->set('email', 'ana@example.com')
            ->set('password', 'password') // credenciales VÁLIDAS, pero la IP ya está bloqueada
            ->call('login')
            ->assertHasErrors('_global');

        $this->assertGuest();
    }

    public function test_closing_modal_resets_credentials_and_clears_errors(): void
    {
        // Reproduce el bug reportado: el error de "credenciales" no debe persistir al reabrir.
        Livewire::test(Login::class)
            ->set('email', 'desconocido@example.com')
            ->set('password', 'mal')
            ->call('login')
            ->assertHasErrors('email')
            ->dispatch('auth-modal-closed')
            ->assertHasNoErrors()
            ->assertSet('email', '')
            ->assertSet('password', '');
    }

    public function test_login_clears_cart_from_a_different_previous_user(): void
    {
        // Auditoría 2026-05-26 (hallazgo D): dispositivo compartido. Alice tenía sesión y dejó
        // una cesta. Bob hace login SIN logout previo de Alice. La cesta de Alice debe descartarse.
        $alice = User::factory()->create(['email' => 'alice@example.com']);
        $bob = User::factory()->create(['email' => 'bob@example.com']);

        // Estado previo de sesión: cesta + marcador del dueño = Alice.
        session()->put('purchase.cart', [['ticket_type_id' => 1, 'date' => '2026-06-01', 'time' => '10:00:00', 'qty' => 2]]);
        session()->put('purchase.user_id', $alice->id);

        Livewire::test(Login::class)
            ->set('email', 'bob@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertNull(session('purchase.cart')); // descartada
        $this->assertSame($bob->id, session('purchase.user_id'));
    }

    public function test_login_preserves_guest_cart_for_the_same_person(): void
    {
        // Caso normal: guest añade al carrito → se loguea para pagar → conserva la cesta.
        $alice = User::factory()->create(['email' => 'alice@example.com']);

        // Guest tenía cesta, sin marcador de dueño.
        $cart = [['ticket_type_id' => 1, 'date' => '2026-06-01', 'time' => '10:00:00', 'qty' => 2]];
        session()->put('purchase.cart', $cart);

        Livewire::test(Login::class)
            ->set('email', 'alice@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertSame($cart, session('purchase.cart')); // se conserva
        $this->assertSame($alice->id, session('purchase.user_id'));
    }
}
