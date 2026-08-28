<?php

namespace Tests\Feature\Site;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **LA COREOGRAFÍA DEL CIERRE PUBLICA DOS PROGRESOS, Y CONFUNDIRLOS ES UN FALLO MUDO**
 * (`docs/specs/tema-por-instalacion.md` §19).
 *
 * La tarjeta del hero del cierre crece de tarjeta a pantalla completa, y ese recorrido produce
 * **dos** números que NO son intercambiables:
 *
 * | | quién lo manda | qué mueve |
 * |---|---|---|
 * | `--cierre-q` | el progreso **CRUDO** del scroll | la retirada del armazón · el umbral de «ya llena» · el arranque del minijuego |
 * | `--cierre-p` | el mismo, **SUAVIZADO** (`0,22·q + 0,78·smoothstep(q)`) | la geometría: ancho, alto, izquierda y la talla del titular |
 *
 * Es la separación del mockup, donde son `q` y `e` y **solo `e` entra en los `lerp`**.
 *
 * ⚠️⚠️ **Usar el suavizado para la retirada fue un fallo REAL y no lo veía nada** (`#250`): la
 * página funcionaba, la suite iba verde y ninguna captura lo enseñaba, porque un fotograma suelto
 * parece correcto. Solo salió al comparar **fotograma a fotograma** contra la fórmula del artboard:
 * al **7 % del crecimiento** su armazón valía **0,19 de opacidad y el nuestro 0,55**, porque el
 * suavizado va por detrás del crudo justo en el tramo donde el armazón tiene que irse.
 * ▶ *Dos progresos con nombres parecidos son dos progresos que alguien intercambiará.*
 */
class CierreChoreographyTest extends TestCase
{
    use ReadsSiteStylesheets;

    private const JS = 'resources/js/app.js';

    /** **El instrumento ve las dos piezas antes de juzgarlas.** */
    public function test_the_scan_finds_both_halves_of_the_choreography(): void
    {
        $css = implode("\n", $this->siteSheets());

        $this->assertStringContainsString('--cierre-salida:', $css, 'no se encuentra la salida del armazón en las hojas del producto');
        $this->assertStringContainsString('--cierre-queda:', $css, 'no se encuentra la curva de retirada del armazón');
        $this->assertStringContainsString("--cierre-p', String(p)", $this->js(), 'no se encuentra la publicación del progreso suavizado');
        $this->assertStringContainsString("--cierre-q', String(q)", $this->js(), 'no se encuentra la publicación del progreso crudo');
    }

    /** **La retirada del armazón lee el progreso CRUDO.** */
    public function test_the_frame_retreat_reads_the_raw_progress(): void
    {
        $salida = $this->declaration('--cierre-salida');

        $this->assertStringContainsString('var(--cierre-q)', $salida, implode("\n", [
            '`--cierre-salida` ha dejado de leer el progreso CRUDO.',
            'La retirada del armazón la manda `q`, como en el mockup; con el suavizado el armazón se',
            'queda puesto medio recorrido de más y **no lo ve ningún test ni ninguna captura**:',
            'medido, al 7 % del crecimiento su opacidad era 0,55 donde el mockup pone 0,19.',
        ]));
        $this->assertStringNotContainsString('var(--cierre-p)', $salida, 'la retirada no puede leer el progreso suavizado');
    }

    /**
     * **Y es CÚBICA**, que es la curva del mockup (`v = ne · (1 − salida)³`).
     *
     * `calc()` solo multiplica números, así que el cubo se escribe como tres factores. Con dos
     * —o con uno— la retirada sigue funcionando y sigue sin fallar nada: solo va más lenta.
     */
    public function test_the_retreat_curve_is_cubic(): void
    {
        $queda = $this->declaration('--cierre-queda');

        $this->assertSame(
            3,
            substr_count($queda, '(1 - var(--cierre-salida))'),
            'la retirada del armazón tiene que ser CÚBICA —`(1 − salida)³`, la curva del mockup—: '.
            'con menos factores el armazón se va más despacio de lo que dibuja el artboard, y eso '.
            'no lo enseña ninguna captura porque cada fotograma suelto parece correcto.',
        );
    }

    /** **Los dos umbrales de estado también son del CRUDO.** */
    public function test_both_state_thresholds_read_the_raw_progress(): void
    {
        $js = $this->js();

        $this->assertMatchesRegularExpression(
            "/toggle\('cierre--live',\s*q\s*>=/",
            $js,
            '`cierre--live` —lo que retira el armazón del hit-testing— tiene que dispararse con el '.
            'progreso CRUDO: es cuando la retirada termina, y con el suavizado llega tarde.',
        );
        $this->assertMatchesRegularExpression(
            '/const abierto = q >= 0\.985/',
            $js,
            'el aviso de «la tarjeta ya llena la pantalla» —que enciende el minijuego— usa el umbral '.
            'del mockup (`q > 0.985`) sobre el progreso CRUDO.',
        );
    }

    /** **Y la GEOMETRÍA, al revés: lee el suavizado.** La otra mitad de la misma invariante. */
    public function test_the_geometry_reads_the_eased_progress(): void
    {
        $fija = $this->ruleBody('.reserve--fija .reserve__box');

        foreach (['left', 'width', 'height'] as $prop) {
            $this->assertMatchesRegularExpression(
                '/(?<![-\w])'.$prop.'\s*:[^;]*var\(--cierre-p\)/',
                $fija,
                "`{$prop}` de la tarjeta anclada tiene que interpolar con el progreso SUAVIZADO: es ".
                'lo único que el mockup mete en sus `lerp`.',
            );
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![-\w])'.$prop.'\s*:[^;]*var\(--cierre-q\)/',
                $fija,
                "`{$prop}` no puede interpolar con el progreso crudo: el crecimiento saldría lineal.",
            );
        }

        $this->assertStringContainsString(
            'var(--cierre-p)',
            $this->ruleBody('.reserve h2'),
            'la talla del titular crece con la tarjeta, así que lee el progreso SUAVIZADO',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    private function js(): string
    {
        return (string) file_get_contents(base_path(self::JS));
    }

    /** El valor declarado de una custom property, tal cual está escrito. */
    private function declaration(string $property): string
    {
        $css = implode("\n", $this->siteSheets());
        $this->assertSame(1, preg_match('/'.preg_quote($property, '/').':([^;}]+)/', $css, $m), "`{$property}` no está declarada");

        return $m[1];
    }

    private function ruleBody(string $selector): string
    {
        $bodies = array_map(
            fn (array $r) => $r['body'],
            array_filter($this->siteRules(), fn (array $r) => $r['selector'] === $selector),
        );

        $this->assertNotEmpty($bodies, "no se encuentra la regla `{$selector}`");

        return implode(' ', $bodies);
    }
}
