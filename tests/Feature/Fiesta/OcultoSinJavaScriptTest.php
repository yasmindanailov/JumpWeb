<?php

namespace Tests\Feature\Fiesta;

use Tests\TestCase;

/**
 * **`hidden` ES OCULTO TAMBIÉN SIN JAVASCRIPT** (`specs/fiesta-sistema-nuevo.md` §4.12, 26-09).
 *
 * Las piezas de la fiesta llevan sus estilos EN LÍNEA (el port 1:1 del React del diseño), y un `display` en línea le gana
 * al `[hidden]` del navegador. La hoja solo lo reforzaba bajo `.js`: sin JavaScript se veían ocho nodos que nadie debía
 * ver (medido con una sonda de navegador), y el visor de «Ver el parque» —fijo, a pantalla entera, con su velo— tapaba la
 * invitación y el recibo enteros. Lo cazó la sonda de «Avísame de fechas» al pulsar su casilla sin JavaScript.
 *
 * ⚠️ El CSS no se calcula en PHP: esto afirma la REGLA y, de control, que la necesidad existe (el visor sigue llevando
 * `hidden` y `display` en línea). Lo que se ve lo mide la sonda (`storage/app/audit/sonda-hidden.mjs`).
 */
class OcultoSinJavaScriptTest extends TestCase
{
    public function test_hidden_wins_over_inline_display_with_or_without_javascript(): void
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(base_path('resources/js/fiesta/fiesta.css')));

        $this->assertMatchesRegularExpression(
            '/(?:^|\})\s*\[hidden\]\s*\{\s*display\s*:\s*none\s*!important\s*;?\s*\}/',
            $css,
            'la regla de `[hidden]` tiene que valer SIN `.js` delante: sin JavaScript, los `display` en línea le ganan al navegador',
        );

        // Control: por qué hace falta. Si el visor dejara de llevar su `display` en línea, este caso habría que revisarlo.
        $visor = (string) file_get_contents(resource_path('views/fiesta/invitacion/visor.blade.php'));
        $this->assertMatchesRegularExpression('/<div class="inv-visor"[^>]*\bhidden\b[^>]*style="[^"]*display: flex/', $visor);
    }
}
