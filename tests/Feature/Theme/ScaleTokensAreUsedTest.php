<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **LA ESCALA SE USA: ningún literal con token disponible** (auditoría de diseño M2, `DECISIONES #437`).
 *
 * Los tokens `--fs-*` (doce escalones) y `--sp-*` (diecisiete) existen para que `--fs-unit`/`--sp-unit`
 * muevan la web entera desde el paquete de una instalación. Medido antes de la tanda F: de los `font-size`
 * en px de las dos hojas, **245** tenían token del MISMO píxel y no lo usaban; de los `padding`/`gap`/
 * `margin`, **597** — el mando por instalación movía un tercio de la web. `scripts/escala-a-tokens.py`
 * hizo la sustitución mecánica (cero reflujo: la huella de maquetación de las doce vistas a 1280 y 390 es
 * idéntica antes y después), y esto impide que vuelvan.
 *
 * Lo que vigila, con el MISMO criterio que el guion: `font-size: Npx` con N en la escala `--fs-*`, y
 * `padding`/`margin`/`gap` cuyos escalones en px estén TODOS en la escala `--sp-*` (ceros aparte).
 * Fuera: `calc()`/`clamp()`/`var()`, negativos, decimales, `em`, `%`, los bloques `:root` (son la escala) y
 * los `@keyframes`. Los comentarios se blanquean conservando longitud (la trampa de `#193`).
 */
class ScaleTokensAreUsedTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /**
     * ⚠️ **El 9 SALE con la grieta 00 del cajón** (`#550`). Esta lista dice «para este píxel existe
     * token», y `--fs-9` se quedó con **cero usos** cuando sus tres consumidores —«Casi llena», la
     * chapa del catálogo y el precio del día— subieron al nivel Etiqueta (12), el suelo del sistema.
     * Se retiró del `:root` porque `SidebarTokenBudgetTest` no admite escalones muertos.
     *
     * ▶ **Y no se pierde la otra mitad de esta guarda**: un `font-size: 9px` literal ya lo prohíben
     * `SemanticFillTextTest` (suelo de 10 px en la web pública) y `SidebarBodySizeTest` (la escala,
     * dentro del cajón). *Eran tres guardas diciendo cosas distintas sobre el mismo token; la que
     * manda es la que mide el uso real.*
     */
    private const FS = [10, 11, 12, 13, 14, 15, 16, 17, 18, 20, 22];

    private const SP = [1, 2, 3, 4, 6, 7, 8, 10, 11, 12, 13, 14, 15, 16, 18, 20, 28];

    private const SPACE_PROPS = [
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'padding-inline', 'padding-block',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'margin-inline', 'margin-block',
        'gap', 'row-gap', 'column-gap',
    ];

    public function test_the_scan_sees_the_corpus(): void
    {
        $n = 0;
        foreach (self::SHEETS as $sheet) {
            $n += count($this->declarations($sheet));
        }
        $this->assertGreaterThan(3000, $n, 'el localizador de declaraciones se ha roto');
    }

    public function test_the_scale_tokens_exist_in_landing_root(): void
    {
        $root = (string) file_get_contents(base_path('public/css/landing.css'));
        foreach (self::FS as $n) {
            $this->assertStringContainsString("--fs-{$n}:", $root, "falta `--fs-{$n}` en la escala");
        }
        foreach (self::SP as $n) {
            $this->assertStringContainsString("--sp-{$n}:", $root, "falta `--sp-{$n}` en la escala");
        }
    }

    public function test_no_font_size_literal_has_an_unused_token(): void
    {
        $culpables = [];
        foreach (self::SHEETS as $sheet) {
            foreach ($this->declarations($sheet) as [$selector, $prop, $val]) {
                if ($prop !== 'font-size' || ! preg_match('/^(\d+)px$/', $val, $m)) {
                    continue;
                }
                if (in_array((int) $m[1], self::FS, true)) {
                    $culpables[] = "{$sheet} · {$selector} { font-size: {$val} } → var(--fs-{$m[1]})";
                }
            }
        }
        $this->assertSame([], $culpables, "`font-size` en px con token del mismo píxel (auditoría M2): usa la escala.\n  ".implode("\n  ", $culpables));
    }

    public function test_no_spacing_literal_has_an_unused_token(): void
    {
        $culpables = [];
        foreach (self::SHEETS as $sheet) {
            foreach ($this->declarations($sheet) as [$selector, $prop, $val]) {
                if (! in_array($prop, self::SPACE_PROPS, true) || preg_match('/var\(|calc\(|clamp\(|min\(|max\(|em|%|vw|vh|auto|!important/', $val)) {
                    continue;
                }
                $parts = preg_split('/\s+/', trim($val));
                $nums = array_filter($parts, fn (string $p): bool => $p !== '0');
                if ($nums === [] || array_filter($parts, fn (string $p): bool => ! preg_match('/^\d+px$|^0$/', $p)) !== []) {
                    continue;
                }
                $todos = true;
                foreach ($nums as $p) {
                    if (! in_array((int) $p, self::SP, true)) {
                        $todos = false;
                    }
                }
                if ($todos) {
                    $culpables[] = "{$sheet} · {$selector} { {$prop}: {$val} }";
                }
            }
        }
        $this->assertSame([], $culpables, "espacio en px con token del mismo píxel en todos sus escalones (auditoría M2): usa la escala.\n  ".implode("\n  ", $culpables));
    }

    /** @return list<array{0: string, 1: string, 2: string}> [selector, propiedad, valor] fuera de :root, @keyframes y comentarios */
    private function declarations(string $sheet): array
    {
        $css = (string) file_get_contents(base_path($sheet));
        $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
        $blind = (string) preg_replace_callback('/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*\s*\}/s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $blind);
        $blind = (string) preg_replace_callback('/(?:^|\n):root\s*\{[^{}]*\}/', fn (array $m): string => str_repeat(' ', strlen($m[0])), $blind);
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $blind, $matches, PREG_SET_ORDER);

        $out = [];
        foreach ($matches as $rule) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
            $selector = (string) preg_replace('/^@media[^{]*\{\s*/', '', $selector);
            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }
            $buffer = '';
            $depth = 0;
            $decls = [];
            foreach (str_split($rule[2]) as $char) {
                if ($char === '(') {
                    $depth++;
                } elseif ($char === ')') {
                    $depth--;
                }
                if ($char === ';' && $depth === 0) {
                    $decls[] = $buffer;
                    $buffer = '';

                    continue;
                }
                $buffer .= $char;
            }
            $decls[] = $buffer;
            foreach ($decls as $d) {
                if (! str_contains($d, ':')) {
                    continue;
                }
                [$prop, $val] = explode(':', $d, 2);
                $out[] = [$selector, trim(strtolower($prop)), trim((string) preg_replace('/\s+/', ' ', $val))];
            }
        }

        return $out;
    }
}
