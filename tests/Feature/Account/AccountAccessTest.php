<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\User;
use App\Http\Sidebar\AccountDoor;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Las PUERTAS de «Mi cuenta»: quién entra y a dónde llega.**
 *
 * ⚠️ **Las vistas se retiraron en la tanda 3 y las rutas sobrevivieron** (`DECISIONES #120(u)`), así
 * que este fichero cambia de sujeto sin cambiar de sitio: antes comprobaba que la página se pintaba,
 * ahora que la puerta **sigue siendo zona privada** y que **abre el cajón donde toca**. Lo segundo es
 * lo que impide que la retirada convierta en un 404 útil-pero-mudo los 8 correos ya entregados.
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

    /**
     * ⚠️⚠️ **Las dos rutas ABREN EL CAJÓN en su zona, y cada una en la SUYA.**
     *
     * Es lo que sustituye a las páginas retiradas. Sin esto, un cliente que llega desde uno de los 8
     * correos ya entregados aterrizaría en la home sin que pasara nada — la familia de fallos de
     * `DECISIONES #117`, donde un camino «no falla y no hace nada».
     *
     * ⚠️ Se comprueban **los dos atributos**: `data-purchase-open` abre el panel y `data-account-zone`
     * dice en qué pantalla. Con solo el primero, el cliente acabaría en el catálogo de compra.
     */
    public function test_the_surviving_routes_open_the_drawer_on_their_own_zone(): void
    {
        $user = User::factory()->create();

        foreach (['/mi-cuenta' => 'home', '/mi-cuenta/pedidos' => 'orders'] as $path => $zone) {
            $html = (string) $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('data-purchase-open="1"', $html, "«{$path}» no abre el cajón");
            $this->assertStringContainsString('data-account-zone="'.$zone.'"', $html, "«{$path}» no abre en su zona");
        }
    }

    /**
     * ⚠️ **Y ninguna otra página emite zona.** Un `data-account-zone` colgado en toda la web abriría
     * el área de cliente en cada apertura del cajón, y quien viene a comprar no encontraría el
     * catálogo. Es la trampa que `SidebarEntry` pagó en 4.0a con el desenlace del pago.
     */
    public function test_no_other_page_carries_an_account_zone(): void
    {
        $this->seed(LandingContentSeeder::class);

        $html = (string) $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-account-zone=""', $html, 'la home emite una zona de cuenta');
    }

    /**
     * El mapa ruta→zona vive en un solo sitio, y sus zonas tienen que EXISTIR en el cajón.
     *
     * ⚠️ Una errata aquí no rompería nada visible —el cajón caería en el índice— y el cliente que
     * viene de un correo aterrizaría en otra pantalla sin que nadie lo notara.
     */
    public function test_every_door_points_at_a_zone_the_drawer_knows(): void
    {
        $navigation = (string) file_get_contents(base_path('resources/js/sidebar/account/navigation.js'));

        $this->assertNotSame([], AccountDoor::ZONE_BY_ROUTE, 'el mapa de puertas está vacío');

        foreach (AccountDoor::ZONE_BY_ROUTE as $route => $zone) {
            $this->assertTrue(
                route($route, [], false) !== '',
                "la ruta «{$route}» ya no existe: quita su entrada del mapa"
            );
            $this->assertStringContainsString(
                ": '{$zone}',", $navigation,
                "la puerta «{$route}» apunta a la zona «{$zone}», que el cajón no declara en `ZONES`"
            );
        }
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
