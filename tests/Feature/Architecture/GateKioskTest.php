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
     * ▶ La decisión de uso, no de estética: en un kiosco la acción más repetida es «el siguiente»,
     * y sin esto empezar de nuevo obliga a bajar del todo. Además, el lector de QR escribe en ese
     * campo: si no está en pantalla, hay que buscarlo antes de cada escaneo.
     */
    public function test_the_search_box_stays_in_view(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.gate-search\s*\{[^}]*position:\s*sticky/',
            $this->kioskBlock(),
            'El buscador de la puerta ha dejado de quedarse a la vista.',
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
