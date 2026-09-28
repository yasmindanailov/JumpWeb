<?php

namespace Tests\Feature\Instancia;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * **El QR de una dirección para las páginas de la instancia** (`<x-qr>`, T6c·4b de `isla-y-landing-nueva.md` §4.19: la
 * hoja para dirección de colegios). Un mecanismo del producto: la vista da la dirección y recibe un SVG del servidor, con
 * nombre para quien no lo ve; la dirección no sale como texto en el marcado (se incrusta sin escapar sin riesgo).
 */
class QrComponentTest extends TestCase
{
    public function test_it_paints_the_qr_of_the_address_as_a_named_svg_image_without_the_address_as_text(): void
    {
        $direccion = 'https://parque.test/colegios?c=60_395_2026-10-20_10%3A00#calcula';
        $html = Blade::render('<x-qr :data="$d" label="QR de Colegios" class="mio" />', ['d' => $direccion]);

        $this->assertMatchesRegularExpression('#^<span [^>]*>\s*<svg[^>]*>.*</svg>\s*</span>$#s', trim($html));
        foreach (['role="img"', 'class="mio"', 'aria-label="QR de Colegios"'] as $atributo) {
            $this->assertStringContainsString($atributo, strstr($html, '<svg', true), $atributo);
        }
        $this->assertStringNotContainsString('parque.test', $html, 'la dirección no está como texto: son trazados');
        $this->assertNotSame($html, Blade::render('<x-qr :data="$d" label="QR de Colegios" class="mio" />', ['d' => 'https://parque.test/otra']), 'otra dirección, otro QR');
    }

    public function test_without_a_label_it_names_nothing(): void
    {
        $this->assertStringNotContainsString('aria-label', Blade::render('<x-qr data="https://parque.test/" />'));
    }
}
