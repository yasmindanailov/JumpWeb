<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **LA COLUMNA DEL SITIO SE ESCRIBE UNA VEZ** (`docs/specs/tema-por-instalacion.md` §18).
 *
 * `[DECIDIDO owner, 2026-08-28]`: la columna del PRODUCTO es **1176 px de contenido** con un
 * sangrado de 32 · 24 · 16, o sea la caja de 1240 que dibujan todas sus secciones.
 *
 * ⚠️⚠️ **Y una instalación PUEDE traer la suya** (`#472`). Hasta entonces no podía, y no por
 * decisión: esta guarda contaba las declaraciones sobre TODAS las hojas —la del cliente incluida—
 * así que un paquete que declarara su columna ponía la suite roja. El ancho de la columna es
 * sistema visual del cliente, no arquitectura del producto: el 2.º la tiene en **1120** y el 3.º
 * tendrá otra. Lo que se sigue vigilando es lo que de verdad importa: **una sola declaración por
 * capa** y **cero anchos de columna escritos a mano**, que es de donde salieron las dos columnas
 * de `#238`.
 *
 * El sitio necesita expresar ese mismo número de **tres formas distintas**, y ahí está el peligro:
 *
 * | forma | quién | para qué |
 * |---|---|---|
 * | `width` | `.wrap` | las secciones, el pie y las páginas de contenido |
 * | SANGRADO | `--wrap-gutter` | lo que va a sangre completa y tiene que alinearse: el `.nav`, el hero |
 * | caja EXTERIOR | `--hero-w-end`, `.reserve`, `.menu__inner` | los que se sangran por dentro |
 *
 * ▶ **Tres expresiones del mismo número escritas a mano son tres columnas esperando a separarse**,
 * y este producto ya lo vivió: hasta `#238` el hero acababa en 1240 (el número del mockup) y las
 * secciones iban a 1380, sin que nadie lo hubiera decidido. Por eso el ancho vive en `--col-max` y
 * las tres formas se derivan de él.
 *
 * ⚠️ **Esto NO prohíbe los `max-width` pequeños.** Un párrafo a 480 px o una tarjeta a 640 no son
 * la columna: son MEDIDAS tipográficas, y tienen que poder escribirse. Lo que se vigila es el
 * tramo en el que un número deja de ser una medida y pasa a ser la columna del sitio.
 */
class ColumnIsDeclaredOnceTest extends TestCase
{
    use ReadsSiteStylesheets;

    /**
     * A partir de aquí un ancho ya no es una medida tipográfica: es la columna.
     *
     * La medida más ancha del producto es la rejilla de zonas a 920 px; el escalón más estrecho de
     * la columna, en la ventana más pequeña que se sirve (360 px), es 328. Entre 920 y 1000 no hay
     * nada, así que el corte no roza a nadie por ninguno de los dos lados.
     */
    private const COLUMN_FLOOR = 1000;

    /** Lo que tiene que derivarse del token, con lo que cada uno expresa. */
    private const DERIVED = [
        '.wrap' => 'la columna de las secciones, el pie y las páginas de contenido',
        '--wrap-gutter' => 'la misma columna vista como sangrado, para lo que va a sangre completa',
        '--hero-w-end' => 'el ancho final de la tarjeta del hero de cabecera',
        '.reserve' => 'la caja exterior de la tarjeta del hero del cierre',
        '.menu__inner' => 'la caja exterior de la columna del menú a pantalla completa',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El barrido ve el corpus y NO confunde un punto de ruptura con un ancho.**
     *
     * Es la trampa concreta de esta guarda: `@media (max-width: 1080px)` contiene un `max-width`
     * con un literal de cuatro cifras, y hay **once** en estas hojas. Si el barrido los leyera como
     * declaraciones, la guarda saldría roja con el producto sano — o, peor, alguien la ablandaría
     * hasta dejarla ciega.
     */
    public function test_the_scan_sees_the_corpus_and_never_reads_a_breakpoint_as_a_width(): void
    {
        $rules = $this->siteRules();

        $this->assertGreaterThan(500, count($rules), 'el barrido de reglas se ha quedado corto: es el instrumento, no la hoja');

        $selectors = array_map(fn (array $r) => $r['selector'], $rules);
        $this->assertContains('.wrap', $selectors, 'el barrido no encuentra `.wrap`, que es la regla que esta guarda existe para vigilar');
        $this->assertContains(':root', $selectors, 'el barrido no encuentra `:root`, donde vive el token');

        // Ningún selector recogido es un at-rule: los `@media` se recorren por dentro.
        $this->assertSame([], array_values(array_filter($selectors, fn (string $s) => str_starts_with($s, '@'))));

        // Y hay puntos de ruptura de cuatro cifras de verdad, o el caso de arriba no probaría nada.
        $breakpoints = preg_match_all('/@media[^{]*\d{4}px/', implode("\n", $this->siteSheets()));
        $this->assertGreaterThanOrEqual(5, $breakpoints, 'sin puntos de ruptura de cuatro cifras, este caso no demuestra nada');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo vigilado
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **El ancho de la columna se escribe una vez, y es `--col-max`.** */
    public function test_the_column_width_is_written_in_exactly_one_place(): void
    {
        $enProducto = preg_match_all('/--col-max:\s*(\d+)px\s*;/', implode("\n", $this->siteSheets()), $m);

        $this->assertSame(1, $enProducto, '`--col-max` tiene que declararse exactamente una vez en las hojas del producto');
        $this->assertSame('1176', $m[1][0], 'la columna del producto son 1176 px de contenido; cambiarla es una decisión del owner, no un arreglo');

        /* El paquete de instalación PUEDE traer su columna —es sistema visual del cliente— pero
           una sola vez: dos declaraciones suyas son el mismo defecto de `#238` dentro del paquete,
           y la que gane dependerá del orden en que estén escritas. */
        $enPaquete = preg_match_all('/--col-max:\s*(\d+)px\s*;/', $this->installationPackage(), $mp);

        $this->assertLessThanOrEqual(
            1, $enPaquete,
            'el paquete de instalación declara `--col-max` '.$enPaquete.' veces: '.
            implode(' · ', $mp[1] ?? []).'. Traer la columna propia está permitido; traerla dos '.
            'veces es crear dos columnas dentro del mismo paquete.',
        );

        $offenders = [];

        foreach ($this->siteRules() as $rule) {
            if (! preg_match_all('/(?<![-\w])(?:max-|min-)?width\s*:\s*([^;}]+)/i', $rule['body'], $all, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($all as $decl) {
                if (! preg_match_all('/(\d+(?:\.\d+)?)px/', $decl[1], $nums)) {
                    continue;
                }

                foreach ($nums[1] as $n) {
                    if ((float) $n >= self::COLUMN_FLOOR) {
                        $offenders[] = "{$rule['sheet']} · {$rule['selector']} → ".trim($decl[0]);
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), implode("\n", [
            'Estas reglas escriben a mano un ancho del tamaño de la columna del sitio:',
            '  · '.implode("\n  · ", array_unique($offenders)),
            '',
            'El sitio expresa la MISMA columna de tres formas —ancho, sangrado y caja exterior— y',
            'escribir el número en cualquiera de ellas es crear una segunda columna que se separará',
            'de la primera sin que nadie lo decida. Pasó hasta `#238`: el hero acababa en 1240 y las',
            'secciones iban a 1380.',
            '',
            '▶ Derívalo de `--col-max`. Si de verdad es una medida y no la columna, estará por debajo',
            '  de '.self::COLUMN_FLOOR.' px — la más ancha del producto son 920.',
        ]));
    }

    /** **Y las tres formas de expresarla LEEN el token.** */
    public function test_everything_that_expresses_the_column_reads_the_token(): void
    {
        $sheets = implode("\n", $this->siteSheets());

        foreach (self::DERIVED as $who => $what) {
            $body = str_starts_with($who, '--')
                ? $this->declarationBody($sheets, $who)
                : implode(' ', array_map(
                    fn (array $r) => $r['body'],
                    array_filter($this->siteRules(), fn (array $r) => $r['selector'] === $who),
                ));

            $this->assertNotSame('', trim($body), "no se encuentra `{$who}` en las hojas del producto");
            $this->assertStringContainsString('var(--col-max)', $body, implode("\n", [
                "`{$who}` ya no lee `--col-max`, y es {$what}.",
                'Cuando una de las tres formas deja de derivarse, el sitio pasa a tener dos columnas',
                'y la diferencia solo se ve en las pantallas donde una de ellas manda.',
            ]));
        }
    }

    /** El valor declarado de una custom property, tal cual está escrito. */
    private function declarationBody(string $css, string $property): string
    {
        return preg_match('/'.preg_quote($property, '/').':([^;}]+)/', $css, $m) === 1 ? $m[1] : '';
    }

    /**
     * El paquete de instalación, leído DIRECTAMENTE del disco.
     *
     * ⚠️⚠️ **No sale de `siteSheets()` y esto costó una mutación que no mordía.** El trait **excluye
     * `client.css` a propósito** —lo dice su docblock: «no es del producto, y existe precisamente
     * para declarar valores literales»—, así que una aserción que lo buscara ahí estaría vigilando
     * una cadena vacía y pasaría siempre. *Un caso sin sujeto no vigila nada, y no se nota hasta
     * que se muta.*
     *
     * Devuelve cadena vacía si no hay paquete, que es lo correcto: en un clon limpio no existe
     * (`DECISIONES #1`) y entonces no hay nada que validar (`#468`).
     */
    private function installationPackage(): string
    {
        $ruta = base_path('public/css/client.css');

        return is_file($ruta) ? (string) file_get_contents($ruta) : '';
    }
}
