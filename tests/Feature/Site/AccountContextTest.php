<?php

namespace Tests\Feature\Site;

use App\Domain\Identity\Models\User;
use App\Livewire\Site\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Saludo del sidebar de compra como componente Livewire (2026-06-14). Antes era Blade estático y no
 * se actualizaba tras el login embebido (#69, sin recarga) → quedaba en «Hola, saltador/a». Ahora
 * escucha `logged-in` y refleja la sesión real en vivo.
 */
class AccountContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_generic_greeting(): void
    {
        Livewire::test(AccountContext::class)
            ->assertSee(__('account.sidecart.guest_hello'))
            ->assertSee(__('account.nav.login'));
    }

    public function test_authenticated_user_sees_personal_greeting(): void
    {
        $user = User::factory()->create(['name' => 'Ana Pérez']);

        Livewire::actingAs($user)->test(AccountContext::class)
            ->assertSee('Ana')                                  // «Hola, Ana»
            ->assertSee(__('account.nav.sign_out'))
            ->assertDontSee(__('account.sidecart.guest_hello'));
    }

    public function test_responds_to_logged_in_event_with_session(): void
    {
        // El método del #[On('logged-in')] re-renderiza con la sesión iniciada (saludo fidedigno
        // sin recargar — clave para el flujo pay-first del sidebar).
        $user = User::factory()->create(['name' => 'Ana Pérez']);

        Livewire::actingAs($user)->test(AccountContext::class)
            ->call('refresh')
            ->assertSee('Ana')
            ->assertDontSee(__('account.sidecart.guest_hello'));
    }

    /**
     * ⚠️ **RESCATADO de `SidebarV2Test` al borrar el motor Livewire** (4.7·2b·3, `DECISIONES #111`).
     * Ese fichero murió entero con el componente, pero este caso NO hablaba de él: es el ÚNICO sitio
     * de la suite que asevera el tag del bloque de cuenta y el puente de la clase de modo del panel
     * —`:class="'is-' + $store.purchase.mode"`—, que vive en `layout.blade.php` y SOBREVIVE al
     * borrado. Dejarlo morir habría quitado dos guardas del layout sin que nada se pusiera rojo.
     */
    public function test_account_context_includes_the_minimised_tag(): void
    {
        // Invitado: la página de entradas renderiza el layout con el bloque de cuenta y su tag, y el
        // panel enlaza su clase de modo a `$store.purchase.mode` (puente del sidebar v2).
        $this->get(route('entradas'))
            ->assertOk()
            ->assertSee(__('account.sidecart.tag'))
            ->assertSee("'is-' + \$store.purchase.mode", false);
    }
}
