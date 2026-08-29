<?php

namespace Tests\Feature\Ui;

use App\Livewire\Auth\ResetPassword;
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
 *
 * ⚠️ **Y desde `DECISIONES #143`, el CONTRATO de su hoja** (§B de este fichero). `spinner.css` tiene
 * dos mitades y la línea entre ellas es lo que permite que una instalación traiga su propio dibujo:
 * el contrato arriba, el dibujo del primer cliente abajo. Sin guarda, esa línea se borra sola —
 * basta con que alguien meta una regla de geometría en el sitio equivocado.
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

    /**
     * **Un botón de Livewire pinta su spinner apuntando a SU acción.**
     *
     * ⚠️ **Conducía `Auth\Login` y se re-apuntó el 2026-08-23** (`DECISIONES #122`): aquel componente
     * se retiró con el modal, pero el sujeto de este caso —que el `wire:target` señale la acción que
     * de verdad tarda— **sobrevive intacto**. `Auth\ResetPassword` sigue siendo una página, se llega a
     * ella desde un correo y tiene exactamente la misma forma, así que es el sucesor natural
     * (`CONVENCIONES §3.quater`: se clasifica por el sujeto, no por el fichero).
     *
     * ⚠️ El `wire:target` importa y no es decoración: sin él, `wire:loading` se dispara con
     * **cualquier** petición del componente y el botón parpadearía en operaciones que no son la suya.
     */
    public function test_a_livewire_button_shows_a_spinner_targeting_its_own_action(): void
    {
        Livewire::test(ResetPassword::class, ['token' => 'tok', 'email' => 'cliente@jumpweb.test'])
            ->assertSeeHtml('wire:target="resetPassword"')
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

    // ═════════════════════════════════════════════════════════════════════════════════════════════
    // §B · EL CONTRATO DE LA HOJA — lo que hace SUSTITUIBLE el dibujo (`DECISIONES #143`)
    //
    // `specs/landing-white-label.md` §4.5.2 decía que cambiar el dibujo del spinner «es sustituir un
    // fichero» y que «no hay que construir nada». Medido: el dibujo NO es un fichero — son dos
    // pseudo-elementos y un `@keyframes` dentro de `spinner.css`, y `UI-SPINNER.md` §3 decía además
    // que ese fichero «no se modifica». No había punto de sustitución ninguno.
    // ═════════════════════════════════════════════════════════════════════════════════════════════

    /**
     * Las dos mitades de `spinner.css`, partidas por el marcador del contrato.
     *
     * ⚠️ Se parte por un MARCADOR de máquina (`>>> SPINNER:CONTRACT >>>`) y no por el rótulo
     * legible. La primera versión de este método buscaba «§A · CONTRATO» y encontraba la **prosa de
     * la cabecera**, que describe las dos mitades antes de que existan: partía el fichero por donde
     * no era y las tres aserciones de abajo medían un texto que no era CSS. Un rótulo que un humano
     * puede escribir dos veces no sirve de frontera.
     *
     * @return array{0: string, 1: string}
     */
    private function stylesheetHalves(): array
    {
        $css = (string) file_get_contents(public_path('css/spinner.css'));

        $contractAt = strpos($css, '>>> SPINNER:CONTRACT >>>');
        $drawingAt = strpos($css, '>>> SPINNER:DRAWING >>>');

        $this->assertNotFalse($contractAt, 'ha desaparecido el marcador de §A: la hoja ya no declara su contrato');
        $this->assertNotFalse($drawingAt, 'ha desaparecido el marcador de §B: la hoja ya no declara dónde empieza el dibujo sustituible');
        $this->assertGreaterThan($contractAt, $drawingAt, 'el dibujo (§B) tiene que ir DESPUÉS del contrato (§A)');
        $this->assertSame(1, substr_count($css, '>>> SPINNER:CONTRACT >>>'), 'el marcador de §A está duplicado: la frontera deja de ser una');
        $this->assertSame(1, substr_count($css, '>>> SPINNER:DRAWING >>>'), 'el marcador de §B está duplicado: la frontera deja de ser una');

        // ⚠️ El marcador vive DENTRO de un comentario, así que cortar justo ahí deja cada mitad
        // empezando a media prosa: sin su `/*` de apertura, el `preg_replace` de `rulesOnly()` ya no
        // la reconoce y el rótulo se lee como si fuera un selector. Se avanza hasta su cierre.
        $contractAt = (int) strpos($css, '*/', $contractAt) + 2;
        $drawingEnd = (int) strpos($css, '*/', $drawingAt) + 2;

        return [substr($css, $contractAt, $drawingAt - $contractAt), substr($css, $drawingEnd)];
    }

    /** Un trozo de hoja sin comentarios — lo que el navegador aplica de verdad. */
    private function rulesOnly(string $css): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /**
     * **El dibujo vive ENTERO por debajo de la línea de sustitución.**
     *
     * Es la aserción que sostiene el mecanismo: lo que quede en §A no lo puede cambiar una
     * instalación sin pisar el contrato. Una regla de geometría colada arriba no rompe nada hoy y
     * deja el spinner medio sustituible mañana — el peor de los dos estados, porque parece que
     * funciona.
     */
    public function test_the_drawing_lives_entirely_below_the_substitution_line(): void
    {
        [$contract, $drawing] = $this->stylesheetHalves();

        foreach (['.jj-spinner::after', '.jj-spinner::before', '@keyframes jjSpinnerHop'] as $piece) {
            $this->assertStringContainsString(
                $piece, $drawing,
                "«{$piece}» es DIBUJO y tiene que vivir en §B, que es lo que una instalación redefine.",
            );
        }

        // En §A solo puede haber pseudo-elementos dentro de la garantía de reducir movimiento, que
        // es contrato: se comprueba que ninguna REGLA de §A los pinte.
        preg_match_all('/([^{}]*\.jj-spinner::(?:before|after)[^{}]*)\{([^{}]*)\}/', $this->rulesOnly($contract), $rules, PREG_SET_ORDER);

        $this->assertNotEmpty($rules, 'no se ve la regla de reducir movimiento en §A: ¿ha cambiado el contrato?');

        foreach ($rules as $rule) {
            $this->assertMatchesRegularExpression(
                '/^\s*animation:\s*none\s*!important;?\s*$/', $rule[2],
                'una regla de §A pinta un pseudo-elemento del spinner: '.trim($rule[1])." { {$rule[2]} }\n".
                '▶ Los pseudo-elementos son EL punto de sustitución. Todo lo que los dibuje va en §B.',
            );
        }
    }

    /**
     * **El contrato conserva sus piezas**: los tres tokens, los cinco tamaños, el texto para lector
     * de pantalla y el velo. Son las 31 referencias que hay repartidas por 9 ficheros.
     */
    public function test_the_contract_keeps_every_piece_its_consumers_rely_on(): void
    {
        [$contract] = $this->stylesheetHalves();

        foreach ([
            '--jj-spinner-size', '--jj-spinner-color', '--jj-spinner-speed',
            '.jj-spinner--xs', '.jj-spinner--sm', '.jj-spinner--md', '.jj-spinner--lg', '.jj-spinner--xl',
            '.jj-spinner__sr', '.jj-spinner-overlay', '.jj-spinner-with-label',
        ] as $piece) {
            $this->assertStringContainsString(
                $piece, $contract,
                "el contrato ha perdido «{$piece}», y sus consumidores lo esperan.",
            );
        }
    }

    /**
     * ⚠️⚠️ **La garantía de «reducir movimiento» cubre CUALQUIER dibujo, no solo el del producto.**
     *
     * Hasta el 2026-08-25 la regla era `.jj-spinner::before { animation: none }`, y `::before` es
     * exactamente la única pieza que anima el dibujo del PRIMER cliente. En cuanto una instalación
     * traiga un dibujo que anime `::after` o el propio elemento, quien pidió reducir movimiento
     * seguiría viéndolo girar: no falla, no avisa, y no se ve desde aquí.
     *
     * Un contrato de accesibilidad que solo cubre el dibujo de quien lo escribió no es un contrato.
     * El `!important` es deliberado: el paquete de un cliente carga DESPUÉS y no debe poder ganarlo
     * por descuido.
     */
    public function test_reduced_motion_covers_any_drawing_and_not_just_the_products(): void
    {
        [$contract] = $this->stylesheetHalves();

        preg_match('/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{(.*?)\n\}/s', $contract, $block);

        $this->assertNotEmpty(
            $block,
            'el contrato ya no lleva la regla de `prefers-reduced-motion`: la accesibilidad del '.
            'spinner pasaría a depender de qué dibujo traiga cada instalación.',
        );

        foreach (['.jj-spinner,', '.jj-spinner::before,', '.jj-spinner::after'] as $selector) {
            $this->assertStringContainsString(
                $selector, $block[1],
                "la garantía de reducir movimiento ya no cubre «{$selector}».\n".
                '▶ Cubrir solo `::before` es cubrir solo el dibujo del primer cliente.',
            );
        }

        $this->assertStringContainsString(
            'animation: none !important', $block[1],
            'la garantía ha perdido el `!important`: `client.css` carga DESPUÉS y una instalación '.
            'podría reactivar la animación sin querer.',
        );
    }

    /**
     * **El dibujo se puede GANAR desde `client.css`.**
     *
     * La sustitución se apoya en el orden de carga (lo asevera `ClientThemePackageTest`) **y** en que
     * el producto no se dé especificidad de más: con `.jj-spinner.jj-spinner::before` el paquete del
     * cliente perdería aunque cargara después, y el síntoma sería «he redefinido el spinner y no
     * pasa nada» — indistinguible de un fichero que no carga.
     */
    public function test_the_drawing_does_not_outrank_a_client_override(): void
    {
        [, $drawing] = $this->stylesheetHalves();

        // Sin comentarios y sin el cuerpo de los `@media`/`@keyframes`, que no son selectores.
        $rules = (string) preg_replace('/@(?:media|keyframes|supports)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $this->rulesOnly($drawing));

        preg_match_all('/([^{}]+)\{/', $rules, $selectors);
        // ⚠️ **Una LISTA de selectores son varios selectores, y hay que juzgarlos por separado.**
        // Sin partir por comas, `.jj-spinner::before, .jj-spinner::after { … }` —una regla
        // perfectamente legítima, y la que agrupa los dos puntos de los extremos desde `#259`— se
        // leía como un único selector rarísimo y el caso salía rojo con el dibujo sano.
        $found = array_values(array_filter(array_map(
            'trim',
            preg_split('/\s*,\s*/', implode(',', array_map('trim', $selectors[1]))) ?: [],
        )));

        $this->assertNotEmpty($found, 'no se ve ninguna regla en §B: el dibujo ha desaparecido o el escaneo está roto');

        foreach ($found as $selector) {
            // ⚠️⚠️ **Lo que se vigila es la ESPECIFICIDAD, no la lista de nombres.** Desde `#259` el
            // dibujo son TRES piezas —«tres botes»—: los dos pseudo-elementos y el propio elemento,
            // que pinta el punto del medio con su fondo. `.jj-spinner` a secas tiene MENOS
            // especificidad que `.jj-spinner::before`, así que el paquete de una instalación lo
            // sigue ganando por orden de cascada, que es la garantía que este caso protege.
            // ▶ Lo que sigue prohibido es exactamente lo de antes: un descendiente, una clase
            // compuesta o cualquier cosa que suba la especificidad por encima de lo que el cliente
            // puede escribir.
            $this->assertMatchesRegularExpression(
                '/^\.jj-spinner(?:::(?:before|after))?$/', $selector,
                "el dibujo usa el selector «{$selector}», que no es el mínimo.\n".
                '▶ Una instalación redefine `.jj-spinner`, `::before` y `::after`. Cualquier '.
                "especificidad extra aquí hace que su paquete cargue y NO pinte.\n".
                '▶ Si hace falta un selector nuevo, documenta en §B qué tiene que escribir el cliente.',
            );
        }
    }

    /**
     * **El dibujo pinta con el token de color, no con un color escrito a mano.**
     *
     * `--jj-spinner-color` vale `currentColor` por defecto, que es lo que hace que el spinner de un
     * botón herede el color del botón. Un literal ahí lo desengancharía de los 31 sitios que lo usan.
     */
    public function test_the_drawing_paints_with_the_colour_token(): void
    {
        [, $drawing] = $this->stylesheetHalves();

        // Sin comentarios: el rótulo de §B nombra `--jj-spinner-color` en prosa.
        $rules = (string) preg_replace('#/\*.*?\*/#s', '', $drawing);

        $this->assertSame(
            0, preg_match_all('/#[0-9a-fA-F]{3,8}\b|\brgba?\(\s*\d/', $rules),
            'el dibujo del spinner lleva un color escrito a mano. Tiene que salir de '.
            '`var(--jj-spinner-color)`, que por defecto es `currentColor`.',
        );

        $this->assertGreaterThanOrEqual(
            2, substr_count($rules, 'var(--jj-spinner-color)'),
            'las dos piezas del dibujo tienen que tomar su color del token.',
        );
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
