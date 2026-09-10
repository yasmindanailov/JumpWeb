<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL HOVER RESPONDE, NO SALTA — en TODA la web pública** (auditoría de diseño M1, m1 y m2,
 * `[DECIDIDO owner, 2026-09-03]` D5, `DECISIONES #435`).
 *
 * `#217` §9.4 (el CTA del armazón no salta), `#321` (el `.btn` no salta) y `#323` (ninguna pegatina
 * levita) fijaron la física, pero solo `.btn` tenía guarda: fuera de ella sobrevivían once controles
 * que se movían al pasar el ratón (el CTA del pack con TRES efectos a la vez, la hamburguesa, las
 * flechas del paso a paso, el lanzador de ofertas, los CTA de servicios…). El owner decidió retirarlos
 * todos y conservar UN gesto: el «flota» del logotipo (`#217` §10), que es un DESCENDIENTE del hover
 * (`.nav__brand:hover .nav__brand-logo`) y por eso este fichero no lo alcanza.
 *
 * Lo que vigila:
 *  1. Ninguna regla cuyo sujeto es el PROPIO elemento en `:hover` mueve o escala con `transform`.
 *     ⚠️ Los descendientes (`:hover svg`, `:hover .ico`) sí pueden: el icono que se desliza es la
 *     respuesta, no el salto. La lista de excepciones es del CAJÓN SPA (aparcado) y **solo encoge**.
 *  2. Ningún `transition: all` en las dos hojas (m1): `MotionScaleTest` mira duraciones y curvas, no
 *     la propiedad, y con `all` se anima lo que nadie decidió.
 *  3. Ninguna regla `:hover` o `:focus-visible` cambia `padding`/`margin` (m2): animar layout en una
 *     interacción desplaza el elemento bajo el puntero o bajo el foco.
 *
 * ⚠️ Los comentarios se blanquean conservando longitud (la trampa de `#193`): un `transform` citado
 * en prosa no cuenta.
 */
class HoverDoesNotJumpTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /**
     * **La pegatina que se APRIETA** (`#478`, `[DECIDIDO owner]`: «el efecto al poner el ratón encima
     * y darle clic no es como en el mockup»).
     *
     * ⚠️⚠️ **No es el hover que `#435` retiró, y la diferencia es la dirección.** Aquellos 19
     * **levitaban**: subían, y un elemento que sube se aparta del puntero que lo está señalando.
     * Éste hace lo contrario — se **hunde** 3 px y su sombra se acorta a 2, así que **la suma sigue
     * siendo 5 y el keyline no se mueve de sitio**. Es la física de apretar algo, y es lo que el
     * artboard dibuja.
     * ▶ Va aquí y no en `CAJON_QUE_SALTA` porque aquella lista es de deuda del cajón aparcado y solo
     * encoge; ésta es una conducta **querida** del sistema de diseño.
     */
    private const PEGATINA_QUE_SE_APRIETA = [
        '.zone-card:hover',
        '.zone-card:active',
        // ⚠️ La tarjeta de pack de la sección 04 (`#483`) usa **los MISMOS tokens** que la de
        // zona —`--press-shift` y los dos escalones de sombra—, no una segunda física: es la
        // misma pegatina apretándose en otra sección.
        '.party-card:hover',
        '.party-card:active',
    ];

    /** El cajón SPA (aparcado, `[DECIDIDO owner, 2026-09-01]`): sus botones siguen subiendo 2 px. Solo encoge. */
    private const CAJON_QUE_SALTA = [
        '.bk-cta:hover',
        '.cartbar:hover',
        '.acct__btn--primary:hover',
        '.acct__btn--ghost:hover',
        '.acc-tile:hover',
    ];

    public function test_the_scan_sees_the_corpus(): void
    {
        $rules = $this->rules();
        $this->assertGreaterThan(1500, count($rules), 'el localizador de reglas se ha roto');
        foreach (self::PEGATINA_QUE_SE_APRIETA as $selector) {
            $this->assertTrue(
                $this->selectorExists($selector, $rules),
                "`{$selector}` ya no existe: retíralo de la excepción, o estará tapando un hover que "
                .'nadie declaró.',
            );
        }
        foreach (self::CAJON_QUE_SALTA as $selector) {
            $this->assertTrue(
                $this->selectorExists($selector, $rules),
                "`{$selector}` ya no salta o ya no existe: retíralo de la lista de excepciones, que solo encoge.",
            );
        }
    }

    public function test_no_element_moves_or_scales_on_its_own_hover(): void
    {
        $culpables = [];

        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/transform\s*:\s*[^;]*\b(translate|scale)/', $body)) {
                continue;
            }
            foreach (array_map('trim', explode(',', $selector)) as $part) {
                if (! preg_match('/:hover$/', $part)
                    || in_array($part, self::CAJON_QUE_SALTA, true)
                    || in_array($part, self::PEGATINA_QUE_SE_APRIETA, true)) {
                    continue;
                }
                $culpables[] = $part;
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($culpables)),
            'Estos controles vuelven a SALTAR o a crecer al pasar el ratón (auditoría M1; el sistema decidió que '.
            "el hover responde con color o sombra, `#217`/`#321`/`#323`):\n  ".implode("\n  ", $culpables),
        );
    }

    public function test_no_transition_all(): void
    {
        $culpables = [];
        foreach ($this->rules() as $selector => $body) {
            if (preg_match('/transition\s*:\s*all\b/', $body)) {
                $culpables[] = $selector;
            }
        }
        $this->assertSame([], $culpables, "`transition: all` anima lo que nadie decidió (auditoría m1):\n  ".implode("\n  ", $culpables));
    }

    public function test_no_interaction_animates_padding_or_margin(): void
    {
        $culpables = [];
        foreach ($this->rules() as $selector => $body) {
            if (! preg_match('/:(hover|focus-visible|focus)\b/', $selector)) {
                continue;
            }
            if (preg_match('/(?<![-\w])(padding|margin)(-[a-z]+)?\s*:/', $body)) {
                $culpables[] = $selector;
            }
        }
        $this->assertSame(
            [],
            $culpables,
            "Una interacción cambia `padding`/`margin` y desplaza el elemento (auditoría m2):\n  ".implode("\n  ", $culpables),
        );
    }

    /** @param  array<string, string>  $rules */
    private function selectorExists(string $needle, array $rules): bool
    {
        foreach (array_keys($rules) as $selector) {
            if (in_array($needle, array_map('trim', explode(',', $selector)), true)) {
                return true;
            }
        }

        return false;
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
