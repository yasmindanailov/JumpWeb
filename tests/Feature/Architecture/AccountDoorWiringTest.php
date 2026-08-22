<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La PUERTA del área de cliente está cableada de punta a punta** (`specs/area-cliente.md` §4.6).
 *
 * El bloque de cuenta del panel es Livewire y vive FUERA del motor Vue —es hermano de su punto de
 * montaje—, así que su botón «Mis reservas» tiene que cruzar tres piezas para llegar a la sección:
 *
 *   marcado Blade (`x-on:click`) → `$store.purchase.followAccountLink()` (Alpine, `app.js`) →
 *   `spaHandle.showAccount()` (el motor, `sidebar/index.js`) → `stores/section.js`
 *
 * ⚠️⚠️ **Este test es del mismo tipo que `SidebarIntentWiringTest`, y por el mismo motivo medido**
 * (`DECISIONES #117`): allí la cadena estaba construida hasta el penúltimo eslabón y **nadie llamaba
 * al último**, así que los enlaces profundos «no fallaban, no hacían nada» durante meses. Los tests
 * unitarios de cada pieza pasaban: un test verde de una función no dice nada sobre si alguien la
 * llama. Aquí se comprueba que **cada eslabón tiene consumidor**, no cómo se comporta.
 *
 * ⚠️ Y una cuarta cosa que sí es conducta y no puede perderse: **el `href` sobrevive**. Entre que el
 * panel se abre y el chunk del motor termina de cargar hay una ventana real en la que `spaHandle` es
 * `null`; sin `href`, un clic ahí no haría nada. Con él, el cliente acaba en la página de siempre.
 */
class AccountDoorWiringTest extends TestCase
{
    use RefreshDatabase;

    private const BLADE = 'resources/views/livewire/site/account-context.blade.php';

    private const ALPINE = 'resources/js/app.js';

    private const ENTRY = 'resources/js/sidebar/index.js';

    /** El marcado del bloque de cuenta llama al puente de Alpine, y con la zona correcta. */
    public function test_the_account_block_asks_the_drawer_for_the_orders_zone(): void
    {
        $blade = $this->source(self::BLADE);

        $this->assertStringContainsString(
            "followAccountLink(\$event, 'orders')", $blade,
            'El botón «Mis reservas» ha dejado de pedirle la zona al cajón. Sin esto vuelve a navegar '.
            'a la página, que es exactamente lo que el área de cliente existe para sustituir.'
        );
    }

    /**
     * ⚠️ **Y el `href` sigue ahí.** No es decorativo: es lo único que responde mientras el motor
     * carga, y lo que hace que el clic central o «abrir en pestaña nueva» funcionen.
     */
    public function test_the_link_still_degrades_to_the_page_when_the_engine_is_not_there(): void
    {
        $this->assertStringContainsString(
            "href=\"{{ route('account.orders') }}\"", $this->source(self::BLADE),
            'Se ha perdido el `href` del botón de cuenta. Con el motor a medio cargar, un clic ahí '.
            'ya no hace NADA — y eso no falla, no avisa y no se ve.'
        );
    }

    /** El puente de Alpine existe y **previene la navegación solo cuando el motor se hace cargo**. */
    public function test_the_alpine_bridge_exists_and_only_swallows_the_click_when_it_can(): void
    {
        $alpine = $this->source(self::ALPINE);

        $this->assertStringContainsString('followAccountLink(event, zone)', $alpine, 'no existe el puente');

        $this->assertStringContainsString(
            'if (! zone || ! this.spaHandle) return;', $alpine,
            'El puente ha dejado de comprobar que el motor esté. Sin esa guarda, se traga el clic y '.
            'no lleva a ninguna parte mientras el chunk carga.'
        );

        $this->assertStringContainsString(
            'this.spaHandle.showAccount(zone)', $alpine,
            'El puente ya no llama al motor: la cadena se rompe en el penúltimo eslabón, que es '.
            'literalmente lo que pasó con la costura de intención en `#117`.'
        );
    }

    /** Y el motor lo publica. Sin esto, el puente llamaría a un método que no existe. */
    public function test_the_engine_publishes_the_entry_point(): void
    {
        $this->assertStringContainsString(
            'showAccount: (zone) =>', $this->source(self::ENTRY),
            'El motor ha dejado de publicar `showAccount`. El puente de Alpine llamaría a `undefined` '.
            'y el botón dejaría de responder sin que nada avise.'
        );
    }

    /**
     * **El extremo del servidor**: el bloque se sirve con su cableado en el HTML real.
     *
     * ⚠️ No basta con que el fichero Blade lo tenga: `account-context` es un componente Livewire que
     * podría dejar de renderizarse —o renderizar otra rama— sin que el fichero cambie. Esto mira lo
     * que el cliente RECIBE.
     */
    public function test_the_served_html_carries_the_wiring_for_a_signed_in_customer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $html = (string) $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('acct__btn--reservas', $html, 'no se está sirviendo el botón de cuenta');
        // ⚠️ Se asevera sobre el HTML **tal cual se sirve**, con sus comillas simples sin escapar:
        // Blade no escapa el contenido de un atributo escrito a mano, y dar por hecha una entidad
        // (`&#039;`) haría que este caso fallara por una razón que no es la que vigila.
        $this->assertStringContainsString("followAccountLink(\$event, 'orders')", $html,
            'el botón llega al navegador SIN su cableado: navegaría a la página en vez de abrir el área');
    }

    /** Y un invitado no lo recibe: su botón abre el modal, que es la puerta de hoy (§4.6). */
    public function test_a_guest_gets_no_wiring_because_there_is_nothing_to_open(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('followAccountLink', $html,
            'Un invitado no puede abrir el área de cliente: su botón sigue abriendo el modal de login.');
    }

    private function source(string $relative): string
    {
        $path = base_path($relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
