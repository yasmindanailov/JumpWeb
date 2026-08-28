<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **La pantalla de PUERTA es un KIOSCO, no una página que también cabe** (`DECISIONES #232`).
 *
 * `[DECIDIDO owner, 2026-08-28]`: la puerta tiene **tablet propia, fija en un soporte y en
 * horizontal**. Eso cambia lo que hay que optimizar: no «que quepa», sino que **la respuesta y la
 * acción se vean sin desplazar** y que se acierte con el dedo, de pie y a un brazo de distancia.
 *
 * ⚠️ **Medido antes** (iPad horizontal 1080×810, con ficha abierta): el contenido medía **1.298 px
 * de alto contra 1.080 de pantalla** y la columna se quedaba en **768 px** desperdiciando un
 * tercio del ancho. El CSS del panel **no tenía ni una regla entre 640 y 1280 px** — el rango
 * exacto de una tablet. Después, por caso real: «no registrado» **810** (cabe), «registrado, falta
 * firmar» **854** (44 px, nada) y «exención de versión anterior» **1.073** (lleva un párrafo más).
 *
 * ❗ **Lo que esta guarda NO puede ver.** Que una pantalla «se vea bien» no lo mide un test de PHP:
 * eso se comprueba en navegador y las cifras de arriba salen de un sondeo headless. Lo que sí se
 * puede fijar aquí son las **decisiones**, para que nadie las deshaga sin enterarse — y una de
 * ellas es de privacidad, no de estética.
 */
class GateKioskTest extends TestCase
{
    private const THEME = 'resources/css/filament/admin/theme.css';

    private function css(): string
    {
        return (string) file_get_contents(base_path(self::THEME));
    }

    /** El bloque de kiosco: todo lo que hay entre `@media (min-width: 64rem)` y su cierre. */
    private function kioskBlock(): string
    {
        $css = $this->css();
        $start = strpos($css, '@media (min-width: 64rem)');

        $this->assertNotFalse(
            $start,
            'No existe el bloque de KIOSCO de la puerta. El corte es 64rem (1024 px) porque cubre '
            .'iPad horizontal (1080), Air (1194), Pro (1366) y el escritorio.',
        );

        // Cierre del bloque, contando llaves desde la primera.
        $open = strpos($css, '{', $start);
        $depth = 0;
        $end = $open;

        for ($i = $open, $len = strlen($css); $i < $len; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        return substr($css, $start, $end - $start + 1);
    }

    /**
     * El ancho deja de ser el de un documento (48rem) y pasa a ser el de una pantalla. Sin esto,
     * un iPad horizontal enseña una columna de 768 px con un tercio de pantalla en blanco.
     */
    public function test_the_kiosk_widens_the_shell_beyond_the_document_width(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.gate-shell\s*\{[^}]*max-width:\s*(\d+)rem/',
            $this->kioskBlock(),
            'El kiosco no ensancha `.gate-shell`: en tablet horizontal se seguiría viendo la '
            .'columna de 48rem con el resto vacío.',
        );

        preg_match('/\.gate-shell\s*\{[^}]*max-width:\s*(\d+)rem/', $this->kioskBlock(), $m);

        $this->assertGreaterThan(48, (int) $m[1], 'El ancho del kiosco no puede ser el del documento.');
    }

    /**
     * ▶ **Entre dos clientes no puede haber ningún gesto** (`#234`, `[DECIDIDO owner]`).
     *
     * El lector de QR es un teclado: si el campo se queda con lo del cliente anterior, hay que
     * borrarlo a mano antes de cada escaneo. Por eso `search()` lo vacía y devuelve el foco en
     * cuanto la entrada es válida.
     *
     * ⚠️ **Son DOS mitades y ninguna sirve sola**: el `dispatch()` del componente y el
     * `x-on:…window` de la vista. Si se retira una, la otra queda muda **y nada falla**: la
     * pantalla sigue respondiendo, solo que el cursor deja de volver — y eso no lo nota nadie
     * hasta que hay cola. Se aseveran las dos.
     */
    public function test_the_field_clears_and_takes_focus_back_after_a_search(): void
    {
        $component = (string) file_get_contents(base_path('app/Livewire/Admin/Puerta/ValidarRegistro.php'));
        $view = (string) file_get_contents(base_path('resources/views/livewire/admin/puerta/validar.blade.php'));

        $this->assertStringContainsString(
            "\$this->input = '';",
            $component,
            '`search()` ya no vacía el campo: el empleado tiene que borrar a mano lo del cliente anterior.',
        );

        $this->assertStringContainsString(
            "\$this->dispatch('gate-input-cleared')",
            $component,
            'Falta el aviso al navegador: sin él el campo se vacía pero el cursor no vuelve, y el '
            .'lector de QR escribe en ningún sitio.',
        );

        $this->assertStringContainsString(
            'x-on:gate-input-cleared.window="$el.focus()"',
            $view,
            'El campo ya no escucha el aviso: el `dispatch()` del componente quedó mudo.',
        );
    }

    /**
     * ▶ **Volver desde el TPV y poder escanear sin tocar nada** (`#234`, `[DECIDIDO owner]`).
     *
     * ⚠️ Es un caso que `autofocus` **NO** cubre y por eso se aseveran los listeners: `autofocus`
     * actúa al CARGAR la página, y volver desde otro programa no la recarga —solo devuelve el foco
     * a la ventana—, así que el cursor se quedaba fuera y había que pinchar el campo antes de cada
     * escaneo. Verificado en navegador: al abrir ✓, tras pinchar otra cosa ✗ (caso de control), al
     * volver de otro programa ✓, al volver a la pestaña ✓.
     *
     * ⚠️ `preventScroll` es parte de la decisión, no un detalle: si el empleado estaba leyendo la
     * ficha más abajo, recuperar el cursor no puede arrastrarle de vuelta arriba.
     */
    public function test_the_field_takes_focus_back_when_the_window_returns(): void
    {
        $view = (string) file_get_contents(base_path('resources/views/livewire/admin/puerta/validar.blade.php'));

        $this->assertStringContainsString(
            'x-on:focus.window="$el.focus({ preventScroll: true })"',
            $view,
            'Al volver desde otro programa el cursor ya no vuelve al campo: hay que pinchar antes '
            .'de cada escaneo, que es justo lo que se quitó.',
        );

        $this->assertStringContainsString(
            'x-on:visibilitychange.document',
            $view,
            'Falta el caso de volver a la PESTAÑA, que no siempre dispara `focus` en la ventana.',
        );

        $this->assertStringContainsString(
            'x-init="$el.focus()"',
            $view,
            'El foco al abrir la pantalla vuelve a depender solo de que el navegador honre '
            .'`autofocus`.',
        );

        $this->assertStringContainsString(
            'preventScroll',
            $view,
            'Recuperar el cursor arrastra la página arriba: si el empleado estaba leyendo la ficha, '
            .'le mueve el sitio.',
        );
    }

    /**
     * ⚠️ El buscador pegado arriba **se retiró a propósito** (`#234`): resolvía «empezar de nuevo
     * obliga a bajar del todo», y ese problema desapareció cuando el campo pasó a vaciarse y
     * recuperar el foco solo. La solución buena hizo innecesaria a la anterior — y volver a
     * pegarlo sería reintroducir ruido en pantalla para un problema que ya no existe.
     */
    public function test_the_search_box_is_not_sticky(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\.gate-search\s*\{[^}]*position:\s*sticky/',
            $this->css(),
            'El buscador ha vuelto a quedarse pegado arriba. Se retiró en #234: el campo ya se '
            .'vacía y recupera el foco solo, así que no hace falta tenerlo siempre delante.',
        );
    }

    /**
     * Las tarjetas de la ficha, y su reparto en dos columnas de la misma altura (`#234`).
     *
     * ⚠️ **«Visita» no está por una decisión, no por un olvido**: se retira hasta que exista
     * JumpPoints, que es lo único que da sentido a acreditar una visita. Su maquinaria sigue
     * entera —`registerVisit()` y `GateVisitsTest`—, así que si alguien la reintroduce tiene que
     * ser a propósito y revisando el reparto de columnas, que hoy cuadra porque son cuatro.
     */
    public function test_the_profile_shows_exactly_the_four_cards_decided(): void
    {
        $view = (string) file_get_contents(base_path('resources/views/livewire/admin/puerta/validar.blade.php'));

        foreach (['profile.today', 'profile.window', 'profile.waiver_section', 'profile.minors'] as $section) {
            $this->assertStringContainsString(
                "admin.puerta.validar.{$section}",
                $view,
                "Falta la tarjeta «{$section}» de la ficha de puerta.",
            );
        }

        $this->assertStringNotContainsString(
            'profile.card_section',
            $view,
            'Ha vuelto la tarjeta del QR del cliente. Se retiró en #234: son muy pocos los casos '
            .'con problema de carné y ocupaba una columna entera.',
        );

        $this->assertStringNotContainsString(
            'profile.visit_section',
            $view,
            'Ha vuelto la tarjeta de «Visita». Se retiró en #234 hasta que exista JumpPoints. Si '
            .'es a propósito, hay que revisar también el reparto de columnas del kiosco: cuadra '
            .'con CUATRO tarjetas, no con cinco.',
        );
    }

    /**
     * Las dos columnas del kiosco arrancan a la MISMA altura: a la izquierda las tarjetas con
     * lista, a la derecha las de un dato. Antes «Exención» empezaba a la altura de «Otros días».
     */
    public function test_the_two_columns_start_at_the_same_height(): void
    {
        $css = $this->css();
        $view = (string) file_get_contents(base_path('resources/views/livewire/admin/puerta/validar.blade.php'));

        // Cada columna es su PROPIA pila. Con una rejilla, las dos comparten la altura de cada
        // fila: medido, «Hoy» (98 px) vivía en la fila de «Exención» (187) y dejaba 89 px en
        // blanco debajo antes de «Otros días» — un hueco que no era ningún margen y que ninguna
        // propiedad de rejilla arregla, porque la fila es la fila.
        $this->assertSame(
            2,
            substr_count($view, '<div class="gate-col"'),
            'La ficha ha dejado de tener DOS pilas. Si vuelve a ser una rejilla de tarjetas sueltas, '
            .'las columnas vuelven a compartir altura de fila y reaparecen los huecos.',
        );

        $this->assertMatchesRegularExpression(
            '/\.gate-col\s*\{[^}]*display:\s*flex[^}]*flex-direction:\s*column/s',
            $css,
            'Las columnas de la ficha han dejado de ser pilas.',
        );

        $this->assertMatchesRegularExpression(
            '/\.gate-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1\.8fr\)/',
            $this->kioskBlock(),
            'El kiosco ha dejado de dar más ancho a la columna de las listas.',
        );
    }

    /**
     * ⚠️⚠️ **«Nueva búsqueda» es un control de PRIVACIDAD, no una comodidad**: además de vaciar el
     * campo, quita de la pantalla la ficha del cliente anterior. En una tablet fija en el mostrador
     * eso es lo único que impide que los datos de quien acaba de pasar se queden a la vista del
     * siguiente de la cola.
     *
     * Es justo el control que un cambio de «vamos a ganar altura» retiraría primero, así que se
     * fija aquí: **nada puede ocultarlo**.
     */
    public function test_the_privacy_reset_is_never_hidden(): void
    {
        $css = $this->css();

        $this->assertDoesNotMatchRegularExpression(
            '/\.gate-foot\s*\{[^}]*display:\s*none/',
            $css,
            '«Nueva búsqueda» se ha ocultado. No es chrome: es lo que borra de la pantalla la ficha '
            .'del cliente anterior. Si hace falta altura, sale de otro sitio.',
        );

        $this->assertStringContainsString(
            '.gate-foot {',
            $css,
            'Ha desaparecido el pie de la puerta, donde vive «Nueva búsqueda».',
        );
    }

    /**
     * 44 px es el mínimo táctil de Apple (Material dice 48). Medido en la tablet: los controles de
     * esta pantalla salían de **32–36 px**.
     *
     * ⚠️ La regla va FUERA de cualquier `@media` a propósito: un ratón nunca falló por un botón
     * grande, y así no depende de acertar el ancho del dispositivo — que es exactamente lo que
     * falló aquí, donde no había ni una regla entre 640 y 1280 px.
     */
    public function test_touch_targets_are_at_least_44px_at_every_width(): void
    {
        $css = $this->css();
        $kiosk = $this->kioskBlock();

        $this->assertMatchesRegularExpression(
            '/\.gate\s+button[^{]*\{[^}]*min-height:\s*44px/s',
            $css,
            'Los botones de la puerta han perdido su alto mínimo táctil de 44 px.',
        );

        $this->assertStringNotContainsString(
            'min-height: 44px',
            $kiosk,
            'El mínimo táctil se ha metido DENTRO del bloque de kiosco: entonces solo aplica por '
            .'encima de 1024 px, y la tablet en vertical —o cualquier móvil— se queda sin él.',
        );
    }
}
