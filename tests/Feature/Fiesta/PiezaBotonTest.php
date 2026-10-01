<?php

namespace Tests\Feature\Fiesta;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * EL BOTÓN DEL SISTEMA (`x-pieza.boton`, `.pz-boton`), como el `Button` del diseño en dos cosas que no se ven y se notan,
 * medidas el 01-10 en la pasada ligera de la isla (`#768`, `fiesta-sistema-nuevo.md` §4.18):
 *  · lleva SIEMPRE `position: relative` y `overflow: hidden` (el diseño, desde el zip tercero, por el brillo de «llega»), no
 *    solo mientras llega: con el recorte de las esquinas, Chromium pinta el contenido de otra forma (sin él, ±1 de color en
 *    el trazo de los iconos: la pieza `botones` del banco, 393 px a 390; con él, 0);
 *  · el icono y el texto van DENTRO de un `span` interior `inline-flex`: centrado en el alto del interlineado, el icono cae
 *    en otra fracción de píxel y redondea distinto (el teléfono de «Llamar», en y = 784,50 sin él y 784,48 en el diseño: un
 *    píxel más abajo al pintarlo).
 */
class PiezaBotonTest extends TestCase
{
    public function test_the_button_always_clips_and_wraps_its_content_like_the_design(): void
    {
        $hoja = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('js/fiesta/fiesta.css')));

        $this->assertSame(1, preg_match('#(?:^|\})\s*\.pz-boton\s*\{([^}]*)\}#', $hoja, $regla), 'la regla base del botón vive en la hoja de la fiesta');
        $this->assertStringContainsString('position: relative', $regla[1], 'siempre, como el `Button` del diseño');
        $this->assertStringContainsString('overflow: hidden', $regla[1], 'siempre, no solo mientras llega');

        $this->assertSame(1, preg_match('#\.pz-boton__dentro\s*\{([^}]*)\}#', $hoja, $dentro), 'la regla del span interior');
        foreach (['display: inline-flex', 'align-items: center', 'justify-content: center', 'gap: inherit', 'min-width: 0'] as $declaracion) {
            $this->assertStringContainsString($declaracion, $dentro[1], 'como el span interior del diseño: '.$declaracion);
        }

        foreach ([
            // `{{ '' }}` tras cerrar la ranura: pegado, `</x-slot:izquierda>Llamar` compila a `@endslotLlamar` (la trampa de la casa).
            'botón' => '<x-pieza.boton variant="quiet"><x-slot:izquierda><x-lucide name="phone" :size="17" /></x-slot:izquierda>{{ \'\' }}Llamar</x-pieza.boton>',
            'enlace' => '<x-pieza.boton variant="quiet" href="tel:600111222"><x-slot:izquierda><x-lucide name="phone" :size="17" /></x-slot:izquierda>{{ \'\' }}Llamar</x-pieza.boton>',
        ] as $caso => $plantilla) {
            $this->assertMatchesRegularExpression('#<(button|a)[^>]*class="pz-boton[^"]*"[^>]*><span class="pz-boton__dentro"><span[^>]*>.*?</svg>\s*</span>Llamar</span></\1>#s', Blade::render($plantilla), $caso.': el icono y el texto, dentro');
        }
    }
}
