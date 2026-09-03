<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL FOCO DE LOS CAMPOS TIENE ANILLO** (auditoría de diseño M7, `DECISIONES #434`).
 *
 * Tabulando de verdad a 1280, `/contacto` (cuatro campos y el textarea) y `/cumpleanos` (dos campos)
 * llegaban al foco con `outline: none` y lo único que cambiaba era el color de un borde de 1 px.
 * Era la mitad que quedaba del hallazgo nº 3 del auditor del propio cliente («los campos llevan
 * outline:none sin sustituto»): enlaces y botones ya llevaban anillo desde el Lote 6, los campos no.
 *
 * Lo que este fichero vigila:
 *  1. Que exista la regla `input/textarea/select:focus-visible { outline: var(--focus-outline) }`.
 *  2. Que NINGUNA regla `:focus` de campo vuelva a escribir `outline: none`: `site.css` va después
 *     que `landing.css` y a igual especificidad gana por orden — el anillo desaparecería sin que
 *     fallara nada, que es exactamente cómo estaba.
 *
 * ⚠️ Los comentarios se blanquean conservando longitud (la trampa de `#193`): un `outline: none`
 * citado en prosa no cuenta.
 */
class FieldFocusRingTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    public function test_the_scan_sees_the_corpus(): void
    {
        $this->assertGreaterThan(1500, count($this->rules()), 'el localizador de reglas se ha roto');
    }

    public function test_fields_get_the_token_ring_on_focus_visible(): void
    {
        $found = false;

        foreach ($this->rules() as $selector => $body) {
            $parts = array_map('trim', explode(',', $selector));
            if (! in_array('input:focus-visible', $parts, true)
                || ! in_array('textarea:focus-visible', $parts, true)
                || ! in_array('select:focus-visible', $parts, true)) {
                continue;
            }

            $found = true;
            $this->assertMatchesRegularExpression(
                '/outline:\s*var\(--focus-outline\)/',
                $body,
                'la regla de foco de los campos existe pero no pinta el anillo del token',
            );
        }

        $this->assertTrue(
            $found,
            'no hay ninguna regla `input:focus-visible, textarea:focus-visible, select:focus-visible` en '.
            'las hojas del producto: los campos vuelven a llegar al foco sin anillo (auditoría M7).',
        );
    }

    public function test_no_field_focus_rule_suppresses_the_outline(): void
    {
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/\b(input|textarea|select)\b[^,{]*:focus(?!-visible)/', $selector)) {
                continue;
            }
            if (preg_match('/outline:\s*(none|0)\b/', $body)) {
                $culpables[] = $selector;
            }
        }

        $this->assertSame(
            [],
            $culpables,
            'Estas reglas de foco de campo vuelven a poner `outline: none` y se llevan el anillo por orden '.
            "de cascada (auditoría M7):\n  ".implode("\n  ", $culpables)."\nDeja solo el cambio de borde.",
        );
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function rules(): array
    {
        $out = [];

        foreach (self::SHEETS as $sheet) {
            $css = (string) file_get_contents(base_path($sheet));
            $blind = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m): string => str_repeat(' ', strlen($m[0])),
                $css,
            );

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
