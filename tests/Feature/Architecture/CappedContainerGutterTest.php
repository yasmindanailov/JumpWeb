<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **UN SANGRADO EN PORCENTAJE MIDE AL PADRE — y dentro de una caja ACOTADA eso miente**
 * (`docs/specs/tema-por-instalacion.md` §17).
 *
 * `--wrap-gutter` vale `max(40px, calc((100% - 1380px) / 2))`. Ese `100%` es un porcentaje, así
 * que se resuelve contra el **contenedor** del elemento, nunca contra el elemento. En algo que
 * ocupa todo el ancho —el `.nav`, el escenario del hero— eso significa exactamente lo que dice:
 * «40 px, o lo que haga falta para centrar una columna de 1380». Para eso existe y ahí está bien.
 *
 * ▶ **En un elemento que YA está acotado por su propio `max-width` significa otra cosa**: el
 * sangrado sigue creciendo con la ventana mientras la caja no puede, así que la columna se
 * estrangula. Y esto es lo importante: **a 1280 px no se nota**, que es donde corrieron todas las
 * sondas de este carril. Medido el 2026-08-28 con el defecto vivo:
 *
 *   · la tarjeta del hero del cierre — 1160 px a 1280 · **700 a 1920** · **160 a 2560**;
 *   · la columna del menú a pantalla completa — 1160 a 1280 · **700 a 1920** · **60 a 2560**.
 *
 * Ninguna de las dos fallaba, ninguna avisaba, y la del menú llevaba así desde que se escribió
 * sin que la viera nadie. Lo destapó el OJO del owner sobre la primera, en su pantalla.
 *
 * ⚠️ **Esta guarda no prohíbe `--wrap-gutter`: prohíbe MEZCLARLO con un tope propio.** Un
 * contenedor acotado se sangra con `--col-gutter`, que es una longitud fija y por tanto significa
 * lo mismo en cualquier pantalla.
 */
class CappedContainerGutterTest extends TestCase
{
    use ReadsSiteStylesheets;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El barrido ve el corpus, incluso dentro de un `@media`.** Sin este caso, una guarda que
     * no encontrara nada pasaría en verde por estar ciega — que es como han nacido ciegas tres
     * guardas de este carril (`DECISIONES #211`, `#214`, `#228`).
     */
    public function test_the_scan_sees_the_corpus_including_inside_at_rules(): void
    {
        $rules = $this->siteRules();

        $this->assertGreaterThan(500, count($rules), 'el barrido de reglas se ha quedado corto: es el instrumento, no la hoja');

        $selectors = array_map(fn (array $r) => $r['selector'], $rules);

        foreach (['.reserve', '.menu__inner', '.reserve__body'] as $needle) {
            $this->assertNotEmpty(
                array_filter($selectors, fn (string $s) => $s === $needle),
                "el barrido no encuentra `{$needle}`, que es una de las reglas que esta guarda existe para vigilar",
            );
        }

        // Y ve DENTRO de un `@media`: `.menu__inner` se redeclara en el corte de 1080.
        $this->assertGreaterThanOrEqual(
            2,
            count(array_filter($selectors, fn (string $s) => $s === '.menu__inner')),
            'el barrido no desciende a las reglas de dentro de un `@media`',
        );

        // El token que la guarda protege existe y es una LONGITUD, no un porcentaje.
        $this->assertMatchesRegularExpression(
            '/--col-gutter:\s*\d+(\.\d+)?px\s*;/',
            implode("\n", $this->siteSheets()),
            '`--col-gutter` tiene que ser una longitud fija: si se declara en porcentaje vuelve a '.
            'medir al padre y esta guarda deja de significar nada',
        );
    }

    /**
     * **El clasificador reconoce sus propios ejemplos.** Un caso que dice «esto está acotado» y
     * otro que dice «esto va a sangre», escritos a mano, para que la regla no se pueda ablandar
     * sin que se note.
     */
    public function test_the_classifier_catches_its_own_examples(): void
    {
        $this->assertTrue($this->isCapped('max-width: 1240px; margin-inline: auto;'));
        $this->assertTrue($this->isCapped('width: min(1380px, 100% - 80px);'));
        $this->assertTrue($this->isCapped('padding: 0 12px; max-width : 40rem'));

        $this->assertFalse($this->isCapped('padding-inline: var(--wrap-gutter); display: flex;'));
        $this->assertFalse($this->isCapped('max-width: 100%;'));
        $this->assertFalse($this->isCapped('max-width: none;'));
        // El escenario del hero interpola su tope contra la ventana: no es una columna acotada.
        $this->assertFalse($this->isCapped('max-width: calc(100vw + (var(--hero-w-end) - 100vw) * var(--hero-p));'));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo vigilado
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ninguna regla mezcla un tope propio con el sangrado de sangre completa.**
     */
    public function test_no_capped_container_uses_the_full_bleed_gutter(): void
    {
        $offenders = [];

        foreach ($this->siteRules() as $rule) {
            if (! str_contains($rule['body'], 'var(--wrap-gutter)')) {
                continue;
            }

            if ($this->isCapped($rule['body'])) {
                $offenders[] = "{$rule['sheet']} · {$rule['selector']}";
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'Estas reglas acotan su propio ancho Y se sangran con `--wrap-gutter`:',
            '  · '.implode("\n  · ", $offenders),
            '',
            'El `100%` de `--wrap-gutter` mide el CONTENEDOR, no el elemento, así que dentro de una',
            'caja acotada el sangrado crece con la ventana mientras la caja no puede: la columna se',
            'estrangula y no lo ve nadie hasta que alguien abre la página en una pantalla ancha.',
            'Medido a 2560 px con este defecto vivo: 160 px de tarjeta de cierre y 60 de menú.',
            '',
            '▶ Usa `--col-gutter` (longitud fija). `--wrap-gutter` es para elementos a sangre completa.',
        ]));
    }

    /**
     * **La tarjeta del cierre reserva el hueco del minijuego UNA sola vez.**
     *
     * El lienzo es `absolute` y va pegado al canto inferior, así que su sitio lo aparta el
     * `padding-bottom` de `.reserve__box` — como en el mockup. Había una segunda reserva sobre
     * `.reserve__body` que volvía a apartar la tira entera más un respiro: **220 px de aire muerto
     * a 1280 px** (762 de alto medidos contra los 542 del mockup), y con la tarjeta anclada eso
     * tapaba el pie casi entero. Dos reservas del mismo hueco no se ven por separado; se ven
     * sumadas, y parecen «el bloque es alto».
     */
    public function test_the_closing_card_reserves_the_game_strip_exactly_once(): void
    {
        foreach ($this->siteRules() as $rule) {
            if ($rule['selector'] !== '.reserve__body') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '/(?<![-\w])padding(-[a-z]+)?\s*:/',
                $rule['body'],
                "`.reserve__body` ({$rule['sheet']}) ha vuelto a declarar relleno. El hueco del lienzo ".
                'lo aparta el `padding-bottom` de `.reserve__box`, que es donde lo pone el mockup: '.
                'apartarlo también aquí lo reserva DOS veces y el alto de la tarjeta se dispara.',
            );
        }

        // Y la reserva que sí tiene que existir sigue ahí, o la guarda de arriba se cumpliría
        // sola con la tira pisando el texto.
        $box = array_values(array_filter($this->siteRules(), fn (array $r) => $r['selector'] === '.reserve__box'
            && preg_match('/(?<![-\w])padding\s*:/', $r['body']) === 1));

        $this->assertNotEmpty($box, '`.reserve__box` ha perdido el relleno que aparta el sitio del lienzo del minijuego');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** ¿El bloque acota su propio ancho a una columna? */
    private function isCapped(string $body): bool
    {
        if (preg_match('/(?<![-\\w])(max-)?width\\s*:\\s*([^;}]+)/i', $body, $m) !== 1) {
            return false;
        }

        $value = strtolower(trim($m[2]));

        // `100%`, `auto` y `none` no acotan nada; `100vw` y las interpolaciones contra la ventana
        // tampoco — el escenario del hero es el caso real.
        if (str_contains($value, 'vw') || in_array($value, ['100%', 'auto', 'none', 'inherit'], true)) {
            return false;
        }

        // Una caja que se acota LEYENDO la columna del sitio también está acotada: es justo el
        // caso de la tarjeta del cierre y de la columna del menú.
        return preg_match('/\\d+(\\.\\d+)?(px|rem|em|ch)/', $value) === 1
            || str_contains($value, 'var(--col-max)');
    }
}
