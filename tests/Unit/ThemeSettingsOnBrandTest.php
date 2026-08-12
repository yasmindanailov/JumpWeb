<?php

namespace Tests\Unit;

use App\Support\ThemeSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `ThemeSettings::onBrand()` decide el texto/icono SOBRE el acento de marca (token `--on-brand`,
 * white-label, Lote 4): BLANCO por defecto (preferencia de la clienta) salvo que el acento sea
 * tan claro que el blanco no alcance AA-grande (3:1), donde usa texto oscuro `--fg`.
 * Lógica pura → `tests/Unit` sin BD (CONVENCIONES §3.ter).
 */
class ThemeSettingsOnBrandTest extends TestCase
{
    #[DataProvider('whiteTextAccents')]
    public function test_brand_and_dark_accents_get_white_text(string $hex): void
    {
        // Sobre la marca (naranja) y cualquier acento suficientemente oscuro → texto BLANCO.
        $this->assertSame('#FFFFFF', ThemeSettings::onBrand($hex));
    }

    public static function whiteTextAccents(): array
    {
        return [
            'naranja Jump (default)' => ['#FF5B22'],
            'azul marino' => ['#222244'],
            'granate' => ['#7A1020'],
            'casi negro (=--fg)' => ['#14130F'],
            'verde bosque' => ['#0B3D2E'],
        ];
    }

    #[DataProvider('lightAccents')]
    public function test_very_light_accents_get_dark_text(string $hex): void
    {
        // Acentos tan claros que el blanco no contrastaría (lima Kids, amarillo, blanco) → texto oscuro (literal = --fg).
        $this->assertSame('#14130F', ThemeSettings::onBrand($hex));
    }

    public static function lightAccents(): array
    {
        return [
            'lima Kids' => ['#C6FF3A'],
            'amarillo' => ['#FFE14A'],
            'rosa claro (kids-2)' => ['#FF77C7'],
            'blanco' => ['#FFFFFF'],
        ];
    }
}
