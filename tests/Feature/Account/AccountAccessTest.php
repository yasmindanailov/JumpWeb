<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\User;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4.5a — Acceso a la zona privada (middleware `auth` + `verified`, regla 6 de SEGURIDAD).
 */
class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/mi-cuenta')->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/mi-cuenta')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_can_view_the_account_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/mi-cuenta')
            ->assertOk()
            // ⚠️⚠️ **Anclado en su ESTRUCTURA, y no por gusto: este caso llevaba INERTE desde
            // `#231 p8`.** `landing.footer.account_link` es literalmente el mismo texto —«Mi
            // cuenta»— y el footer va en esta misma página, así que tanto `assertSee` como
            // `assertSeeText` pasaban por el ENLACE DEL PIE aunque el `<h1>` no existiera. Medido
            // por mutación el 2026-08-22: con el título borrado, el caso seguía verde.
            // Es la colisión de subcadena que avisa `TESTING.md` §2.ter — «si el texto es prefijo
            // (o gemelo) de otro de la misma página, ancla además en algo estructural».
            ->assertSee('<h1 class="page__title">'.__('account.account.title').'</h1>', false);
    }

    public function test_authenticated_nav_shows_account_chip_and_reservations_link(): void
    {
        // Rediseño #221: la cuenta del nav pasó de un dropdown «Mis pedidos» a un chip
        // «Hola, nombre» + icono que abre el sidebar; «Mis reservas» (→ pedidos) vive en el
        // bloque de cuenta del sidecart.
        $this->seed(LandingContentSeeder::class);
        $user = User::factory()->create(['name' => 'Mara', 'locale' => 'es']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Hola, Mara')                      // chip de cuenta en el nav
            ->assertSee('nav__acct', false)                // el chip abre el sidebar
            ->assertSeeText(__('tickets.my_reservations'))     // «Mis reservas» en el bloque del sidecart
            ->assertSee(route('account.orders'), false);   // enlaza a su página de pedidos
    }

    public function test_guest_nav_does_not_show_the_account_menu(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertDontSee(route('account.orders'), false);
    }
}
