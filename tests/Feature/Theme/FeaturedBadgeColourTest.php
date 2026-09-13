<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **LA ETIQUETA DESTACADA ES AMARILLA EN TODAS PARTES** (`DECISIONES #585`, `[DECIDIDO owner, 2026-09-13]`).
 *
 * `ticket_types.badge` («Etiqueta destacada» en el panel) se pinta como chip en cinco sitios de la web
 * y en el cajón, y todos leen el rol del MARCADOR —`--marker` con su tinta `--on-marker`—, que es el
 * amarillo del sistema de color.
 *
 * ⚠️ Se vigila el ROL y no el amarillo: el valor lo pone el paquete de cada instalación, y sin paquete
 * `--marker` es transparente y la palabra se lee en la tinta de su superficie (`#480`).
 * ⚠️ Y el texto va en `--on-marker`, nunca en `--fg`: dentro de una tarjeta de tinta `--fg` es CLARO, y
 * sobre el amarillo daría 1,49 (`#523`).
 */
class FeaturedBadgeColourTest extends TestCase
{
    /** Hoja => los chips de la etiqueta destacada que declara. */
    private const CHIPS = [
        'public/css/landing.css' => ['.rate-card__badge', '.rate-table__badge', '.party-card__badge', '.party-compare__badge', '.svc-ed2__badge'],
        'public/css/site.css' => ['.catalog__badge'],
    ];

    public function test_every_featured_badge_reads_the_marker_role(): void
    {
        foreach (self::CHIPS as $hoja => $selectores) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(base_path($hoja)));
            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $reglas, PREG_SET_ORDER);

            foreach ($selectores as $selector) {
                $cuerpos = [];
                foreach ($reglas as [, $lista, $cuerpo]) {
                    if (in_array($selector, array_map('trim', explode(',', $lista)), true)) {
                        $cuerpos[] = $cuerpo;
                    }
                }

                $this->assertNotEmpty($cuerpos, "`{$selector}` no tiene regla en {$hoja}: el caso miraría el vacío.");

                $todo = implode(';', $cuerpos);
                $this->assertMatchesRegularExpression('/background:\s*var\(--marker\b/', $todo, "`{$selector}` no rellena con el marcador.");
                $this->assertMatchesRegularExpression('/(?<![-\w])color:\s*var\(--on-marker\b/', $todo, "`{$selector}` no pinta su texto con la tinta del marcador.");
            }
        }
    }
}
