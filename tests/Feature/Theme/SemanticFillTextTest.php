<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL TEXTO SOBRE UN RELLENO SEMÁNTICO ES UN TOKEN, Y NADA BAJA DE 10 PX** (auditoría de diseño
 * C3, `DECISIONES #434`).
 *
 * «Incluido» pintaba `#fff` quemado sobre `--ok`: con el verde del 2.º cliente da 2,95 (su propia
 * tabla de contraste lo marca «Blanco sobre Verde ✕ NUNCA»). No hay luminancia en CSS, así que el
 * PAR lo declara quien declara el color: el producto pone `--on-ok/--on-err/--on-warn` sobre sus
 * verde y rojo oscuros y el paquete del cliente los redefine para los suyos. Un `color: #fff` junto a
 * un relleno semántico vuelve a romperlo sin que falle nada.
 *
 * Y el sub-rótulo del CTA del armazón daba 2,61 en las doce vistas: no era el gris de otra
 * superficie sino `--fg-mute` atenuado al 62 % por una regla compartida con el relleno de tinta.
 *
 * Lo que este fichero vigila:
 *  1. Que los tres tokens existan en el `:root` del producto.
 *  2. Que ninguna regla combine `background: var(--ok|--err|--warn)` con un blanco quemado.
 *  3. Que `.cta-ghost__s` lea el gris de PAPEL a opacidad 1.
 *  4. El suelo tipográfico de `design.md` §3: ningún `font-size` literal por debajo de 10 px en la
 *     web pública. ⚠️ La lista de excepciones es del CAJÓN (aparcado) y solo encoge.
 */
class SemanticFillTextTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /** Reglas del cajón SPA (aparcado, `[DECIDIDO owner, 2026-09-01]`) que aún bajan de 10 px. Solo encoge. */
    private const CAJON_BAJO_EL_SUELO = ['.bk-seg__label'];

    public function test_the_scan_sees_the_corpus(): void
    {
        $this->assertGreaterThan(1500, count($this->rules()), 'el localizador de reglas se ha roto');
    }

    public function test_the_on_tokens_are_declared_in_the_product_root(): void
    {
        $root = $this->rootOf('public/css/site.css');

        foreach (['--on-ok', '--on-err', '--on-warn'] as $token) {
            $this->assertStringContainsString(
                $token.':',
                $root,
                "`{$token}` ya no está en el `:root` de `site.css`: el texto sobre ese relleno vuelve a ser un literal.",
            );
        }
    }

    public function test_no_rule_burns_white_text_on_a_semantic_fill(): void
    {
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/background(?:-color)?:\s*var\(--(ok|err|warn)\)/', $body)) {
                continue;
            }
            if (preg_match('/(?<![-\w])color:\s*(#fff\b|#ffffff\b|white\b)/i', $body)) {
                $culpables[] = $selector;
            }
        }

        $this->assertSame(
            [],
            $culpables,
            "Texto blanco QUEMADO sobre un relleno semántico (con el verde del 2.º cliente da 2,95):\n  ".
            implode("\n  ", $culpables)."\nUsa `var(--on-ok)` / `var(--on-err)` / `var(--on-warn)`.",
        );
    }

    public function test_the_ghost_cta_sublabel_reads_the_paper_grey_at_full_opacity(): void
    {
        $rules = $this->rules();

        $this->assertArrayHasKey('.cta-ghost__s', $rules, 'la regla `.cta-ghost__s` ya no existe');
        $this->assertMatchesRegularExpression('/color:\s*var\(--paper-fg-mute\)/', $rules['.cta-ghost__s'], 'el sub-rótulo del fantasma ya no lee el gris de papel');
        $this->assertMatchesRegularExpression('/opacity:\s*1\b/', $rules['.cta-ghost__s'], 'el sub-rótulo vuelve a heredar la opacidad del relleno de tinta (2,61 sobre blanco)');
    }

    public function test_no_literal_font_size_below_ten_pixels_in_the_public_web(): void
    {
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $body, $m)) {
                continue;
            }
            foreach ($m[1] as $px) {
                if ((float) $px < 10 && ! in_array($selector, self::CAJON_BAJO_EL_SUELO, true)) {
                    $culpables[] = "{$selector} ({$px}px)";
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($culpables)),
            "`font-size` por debajo del suelo de 10 px de `design.md` §3:\n  ".implode("\n  ", $culpables),
        );
    }

    public function test_the_exception_list_still_has_a_subject(): void
    {
        $rules = $this->rules();
        foreach (self::CAJON_BAJO_EL_SUELO as $selector) {
            $this->assertArrayHasKey($selector, $rules, "`{$selector}` ya no existe: retíralo de la lista de excepciones, que solo encoge");
        }
    }

    private function rootOf(string $sheet): string
    {
        $css = (string) file_get_contents(base_path($sheet));
        $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
        preg_match_all('/(?:^|\n):root\s*\{([^{}]*)\}/', $blind, $m);

        return implode("\n", $m[1]);
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function rules(): array
    {
        $out = [];

        foreach (self::SHEETS as $sheet) {
            $css = (string) file_get_contents(base_path($sheet));
            $blind = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $blind, $matches, PREG_SET_ORDER);

            foreach ($matches as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }
                $out[$selector] = ($out[$selector] ?? '').' '.trim($rule[2]);
            }
        }

        return $out;
    }
}
