<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **LA PIEL DE LA TARJETA: dos niveles, y solo dos** (T10 del idioma visual, `DECISIONES #323`).
 *
 * La auditoría de diseño del 2026-09-01 midió que la web tenía DOS lenguajes de tarjeta sin regla:
 * la pegatina (borde de tinta + sombra dura) en atracciones, Visítanos y los requisitos de normas,
 * y una tarjeta BLANDA (hairline, sin sombra, hover que levitaba con borde cian) justo en la
 * tarifa —la superficie donde se decide la compra— y en `/normas`. `[DECIDIDO owner]`, con las dos
 * pieles renderizadas sobre la página real: **pegatina = lo que se elige o se compra; sin sombra =
 * apoyo**. Lo que este fichero vigila:
 *
 *  1. **Que las tarjetas de PRIMER nivel compartan la receta** —borde `var(--paper-fg)` y
 *     `box-shadow: var(--shadow-float)`— y que ninguna la abandone en silencio (una tarjeta que
 *     vuelva a `--line` no falla: solo deja de parecer de la misma web).
 *  2. **Que las de SEGUNDO nivel NO lleven la sombra dura**: si `.socks-note` amanece en pegatina,
 *     la jerarquía que distingue «elige esto» de «ten esto en cuenta» desaparece — y el adelanto de
 *     normas de la portada (`.rules-peek__item`, borde de tinta SIN sombra, `#309`) es exactamente
 *     ese segundo nivel a propósito.
 *  3. **Que ninguna pegatina LEVITE**: la tarjeta responde con la sombra o no responde (`#303`);
 *     `.price` y `.rule` levitaban 6 y 3 px con `--shadow-lift` y borde cian, y eso se retiró.
 *  4. **Que la destacada conserve el borde de tinta** sobre el color de zona: `border-color:
 *     transparent` en `.price--feat` era el único sitio donde una pegatina perdía su contorno.
 *
 * ⚠️ Los comentarios del CSS se blanquean conservando longitud (la trampa de `#193`), y el
 * localizador de reglas es el de `ActionFillTest`: un cuerpo se parte por `;` a profundidad cero,
 * o un `color-mix(…, transparent)` se corta por su propia coma.
 */
class CardSkinTest extends TestCase
{
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /** Primer nivel: lo que se elige o se compra. Cada una con su sujeto. */
    private const PEGATINA = [
        '.price' => 'la tarjeta de tarifa (portada y /precios) — T10',
        // ⚠️ `.ride-card` se fue de aquí en `#482`: la tarjeta de atracción vivía en el carrusel
        // de la portada, que se retiró con la sección 03. *Una entrada sin sujeto es una guarda
        // que pasa sin mirar nada*, y esta lista lo dice en su propia guarda-de-la-guarda.
        '.visit-card' => 'las tres tarjetas de Visítanos (#307)',
        '.rules-must__card' => 'los dos requisitos de la sección de normas (#309)',
        '.rule' => 'las tarjetas de /normas — T10',
    ];

    /** Segundo nivel: apoyo. Borde, pero SIN la sombra dura. */
    private const APOYO = [
        '.socks-note' => 'la nota de calcetines bajo las tarifas',
        '.rules-peek__item' => 'el adelanto de normas de la portada (#309): borde de tinta sin sombra, a propósito',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_scan_sees_the_corpus(): void
    {
        $rules = $this->rules();

        $this->assertGreaterThan(
            1500, count($rules),
            'el escaneo ve '.count($rules).' reglas en las dos hojas del producto y son miles: el '.
            'localizador se ha roto y todo lo de abajo pasaría sin mirar.',
        );

        foreach (array_merge(array_keys(self::PEGATINA), array_keys(self::APOYO)) as $selector) {
            $this->assertArrayHasKey(
                $selector, $rules,
                "la tarjeta `{$selector}` ya no existe en el CSS: si se fue con su sujeto, retírala de ".
                'la lista; si la renombraron, apúntala al nombre nuevo. Una entrada sin sujeto es una '.
                'guarda que pasa sin mirar nada.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · Las pegatinas comparten la receta
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_first_level_cards_share_the_sticker_recipe(): void
    {
        $rules = $this->rules();
        $offenders = [];

        foreach (self::PEGATINA as $selector => $subject) {
            $body = $rules[$selector];

            if (! $this->has($body, 'box-shadow', 'var(--shadow-float)')) {
                $offenders[] = "{$selector} ({$subject}) → sin `box-shadow: var(--shadow-float)`";
            }

            if (! $this->borderIsInk($body)) {
                $offenders[] = "{$selector} ({$subject}) → el borde no es de tinta (`var(--paper-fg)`)";
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Estas tarjetas de PRIMER nivel han abandonado la receta de la pegatina:'],
            array_map(fn (string $o): string => '  · '.$o, $offenders),
            ['',
                '▶ Pegatina = borde `1px solid var(--paper-fg)` + `box-shadow: var(--shadow-float)`.',
                '  Es LA tarjeta del sitio; una que vuelva a hairline no falla, solo deja de parecer',
                '  de la misma web (el hallazgo T2 de la auditoría, otra vez).'],
        )));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · El apoyo no lleva la sombra dura
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_second_level_cards_do_not_carry_the_hard_shadow(): void
    {
        $rules = $this->rules();

        foreach (self::APOYO as $selector => $subject) {
            $this->assertFalse(
                $this->has($rules[$selector], 'box-shadow', 'var(--shadow-float)'),
                "`{$selector}` ({$subject}) lleva la sombra dura: es una tarjeta de APOYO y la ".
                'sombra es lo que distingue «elige esto» de «ten esto en cuenta». Si de verdad ha '.
                'pasado a primer nivel, muévela a `PEGATINA` con su porqué.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · Ninguna pegatina levita
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_no_sticker_levitates_on_hover(): void
    {
        $rules = $this->rules();

        /*
         * ⚠️⚠️ **ESTE CASO SALIÓ «RISKY» EN `#482`, Y EL DEFECTO ERA SUYO, NO DE LA TANDA.** Al
         * retirarse `.ride-card` —la única pegatina que declaraba `:hover`— el bucle de abajo se
         * quedó sin una sola vuelta y PHPUnit avisó de que no aseveraba nada. *Un caso que solo
         * asevera dentro de un bucle deja de vigilar en cuanto el bucle se queda vacío, y lo hace en
         * verde.*
         * ▶ Se aserta primero el HECHO: hoy ninguna pegatina responde al puntero. El día que alguna
         * declare un `:hover`, esta aserción se pone roja y obliga a mirarlo — y a partir de ahí el
         * bucle vuelve a tener sujeto.
         */
        $conHover = array_values(array_filter(
            array_keys(self::PEGATINA),
            static fn (string $selector): bool => isset($rules[$selector.':hover']),
        ));

        $this->assertSame(
            [], $conHover,
            'una pegatina ha estrenado `:hover`: '.implode(', ', $conHover)."\n".
            "▶ No es un error por sí mismo, pero hay que decidirlo: la pegatina responde con la\n".
            "  SOMBRA o no responde (`#303`), y la que no es enlace no responde (`#307`/`#295`).\n".
            '  Añádela aquí a sabiendas y el bucle de abajo vigilará que no levite.',
        );

        foreach (array_keys(self::PEGATINA) as $selector) {
            $hover = $rules[$selector.':hover'] ?? [];

            foreach ($hover as [$property, $value]) {
                $this->assertNotSame(
                    'transform', $property,
                    "`{$selector}:hover` vuelve a mover la tarjeta (`transform: {$value}`). La pegatina ".
                    'responde con la sombra o no responde (`#303`); la que no es enlace, no responde '.
                    '(`#307`/`#295`). Levitar era el hover heredado que la T10 retiró.',
                );

                $this->assertNotSame(
                    'var(--shadow-lift)', $value,
                    "`{$selector}:hover` vuelve a la sombra difusa `--shadow-lift`: una pegatina no se eleva.",
                );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  4 · La destacada conserva el contorno
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_featured_price_card_keeps_its_ink_border(): void
    {
        $feat = $this->rules()['.price--feat'] ?? null;

        $this->assertNotNull($feat, 'la regla `.price--feat` ya no existe: si la destacada se fue, retira este caso con ella');

        $this->assertFalse(
            $this->has($feat, 'border-color', 'transparent'),
            'la tarifa destacada vuelve a borrar su contorno (`border-color: transparent`): la pegatina '.
            'sobre color de zona conserva el borde de tinta, como el toggle activo de cumpleaños.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Herramientas
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @param list<array{0: string, 1: string}> $body */
    private function has(array $body, string $property, string $value): bool
    {
        foreach ($body as [$p, $v]) {
            if ($p === $property && $v === $value) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{0: string, 1: string}> $body */
    private function borderIsInk(array $body): bool
    {
        foreach ($body as [$p, $v]) {
            if (in_array($p, ['border', 'border-color'], true) && str_contains($v, 'var(--paper-fg)')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Las reglas de las hojas del producto: selector normalizado → lista de `[propiedad, valor]`.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
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

                foreach ($this->declarations($rule[2]) as $declaration) {
                    $out[$selector][] = $declaration;
                }
            }
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string}> */
    private function declarations(string $body): array
    {
        $out = [];
        $buffer = '';
        $depth = 0;

        foreach (str_split($body) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ';' && $depth === 0) {
                $out = $this->push($out, $buffer);
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        return $this->push($out, $buffer);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $out
     * @return list<array{0: string, 1: string}>
     */
    private function push(array $out, string $buffer): array
    {
        if (str_contains($buffer, ':')) {
            [$property, $value] = explode(':', $buffer, 2);
            $out[] = [trim(strtolower($property)), trim((string) preg_replace('/\s+/', ' ', $value))];
        }

        return $out;
    }
}
