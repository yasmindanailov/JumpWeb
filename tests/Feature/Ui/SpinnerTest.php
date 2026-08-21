<?php

namespace Tests\Feature\Ui;

use App\Livewire\Auth\Login;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sistema de feedback de carga (spinner). Ver docs/UI-SPINNER.md.
 *
 * Pruebas de RENDERIZADO: que el spinner / velo se inyecta donde toca, con el
 * objetivo (wire:target) correcto y de forma accesible. El comportamiento en vivo
 * de wire:loading lo aporta Livewire y se valida en pantalla.
 */
class SpinnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_spinner_component_is_accessible_by_default(): void
    {
        $html = Blade::render('<x-ui.spinner size="lg" />');

        $this->assertStringContainsString('jj-spinner', $html);
        $this->assertStringContainsString('jj-spinner--lg', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('jj-spinner__sr', $html); // texto para lector de pantalla
    }

    public function test_decorative_spinner_is_hidden_from_screen_readers(): void
    {
        $html = Blade::render('<x-ui.spinner size="xs" :decorative="true" />');

        $this->assertStringContainsString('jj-spinner--xs', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('role="status"', $html); // el contexto ya da el texto
    }

    public function test_loading_overlay_is_local_by_default_and_fullscreen_when_fixed(): void
    {
        $local = Blade::render('<x-ui.loading-overlay />');
        $this->assertStringContainsString('jj-loading', $local);
        $this->assertStringContainsString('jj-spinner', $local);
        $this->assertStringNotContainsString('jj-spinner-overlay', $local);

        $fixed = Blade::render('<x-ui.loading-overlay :fixed="true" />');
        $this->assertStringContainsString('jj-spinner-overlay', $fixed);
    }

    public function test_layout_links_the_spinner_stylesheet(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('css/spinner.css', false);
    }

    public function test_login_button_shows_a_spinner_targeting_the_login_action(): void
    {
        Livewire::test(Login::class)
            ->assertSeeHtml('wire:target="login"')
            ->assertSeeHtml('jj-spinner');
    }

    /**
     * ⚠️ **El cajón se abre antes de que exista su motor, y ese hueco se ve.**
     *
     * El entry de la SPA se trae con `import()` en la primera apertura, así que entre el clic y el
     * primer pintado de Vue hay una descarga. El velo `.jj-loading` que emite la propia SPA **no
     * puede cubrirla**: vive DENTRO de la app que aún no ha montado. Sin este marcado el cajón se
     * abre en blanco con la caché fría — que es justo lo que pasó al retirar el motor Livewire, cuyo
     * `placeholder()` del `lazy` tapaba el hueco (4.7·2b·3, `DECISIONES #112(h)`).
     *
     * Va DENTRO de `#sidecart-spa` y no hace falta apagarlo: **Vue limpia el contenedor al montar**
     * (`app.mount()` → `container.textContent = ''`, verificado en el runtime instalado). Si el chunk
     * no carga, lo vacía el `catch` de `bootSpaEngine()`.
     *
     * ⚠️ Se asevera **dentro del hueco**, no en la página: `.purchase-loading` suelto pasaría aunque
     * el velo estuviera fuera del punto de montaje, donde Vue nunca lo retiraría y se quedaría
     * pegado bajo el cajón para siempre.
     */
    public function test_the_spa_mount_point_carries_a_loading_veil_until_vue_takes_over(): void
    {
        $this->seed(LandingContentSeeder::class);

        $html = $this->get('/')->assertOk()->getContent();

        // ⚠️ Con DOM, no con una expresión regular: la primera versión de este caso usaba un regex
        // desde `id="sidecart-spa"` hasta `</div></div>`, y **al mutarlo se vio que no medía nada** —
        // sacando el velo FUERA del hueco seguía en verde, porque el regex se comía el nodo siguiente.
        $dom = new \DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $host = $dom->getElementById('sidecart-spa');
        $this->assertNotNull($host, 'no se ha encontrado el hueco de montaje del cajón');

        $inner = '';
        foreach ($host->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        $this->assertStringContainsString('purchase-loading', $inner, 'el velo tiene que estar DENTRO del hueco: es Vue quien lo retira al montar');
        $this->assertStringContainsString('jj-spinner', $inner);
        $this->assertStringContainsString(__('ui.loading'), $inner);
    }

    /*
     * ⚠️ **Aquí vivían los dos casos del cajón, y los dos se fueron con `Tickets\Purchase`**
     * (4.7·2b·3). No son iguales, y la diferencia importa:
     *
     * · `test_purchase_panel_has_a_loading_veil_targeting_panel_actions` — el velo del panel. Su
     *   sujeto **tiene sucesor y está fijado**: el cajón SPA emite `.jj-loading` como PRIMER hijo de
     *   `.purchase`, y `SidebarDomContractTest` lo ancla ahí en dos casos y declara el orden de
     *   bloques `jj-loading → bk-progress → purchase__scroll → bk-foot`. Lo que no sobrevive es el
     *   `wire:target`, que era vocabulario de Livewire.
     *
     * · `test_lazy_placeholder_renders_the_brand_spinner` — el placeholder del `lazy`. Este **NO
     *   tiene sucesor, y al medirlo apareció un HUECO REAL**: `bootSpaEngine()` abre el cajón y hace
     *   `await import()` del chunk del motor, y durante esa espera no se pinta nada — `spaLoading` es
     *   solo una guarda de reentrada y no llega a ninguna vista—. El velo `.jj-loading` no puede
     *   taparlo porque vive DENTRO de la app Vue que aún no ha montado. Con la caché fría el cajón se
     *   abre vacío. Está anotado en `DEUDA.md`; se cierra pintando el velo dentro de `#sidecart-spa`,
     *   que Vue reemplaza al montar.
     */
}
