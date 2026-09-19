<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * F4 · T4 — **la hoja del paquete no se separa de sus fuentes** (`docs/specs/cajon-empaquetable.md` §4.3).
 *
 * `public/css/cajon.css` es un EXTRACTO generado por `scripts/hoja-del-cajon.py` desde las tres hojas que
 * carga el producto. Es la pieza que hace que una landing ajena vista el cajón escribiendo dos líneas en vez
 * de copiarse las 2.650 reglas de la landing.
 *
 * ⚠️⚠️ **El peligro de un fichero generado no es que salga mal: es que salga VIEJO.** Nadie lo nota —sigue
 * siendo CSS válido— y el cajón de las instalaciones se queda en el diseño de antes de ayer mientras el del
 * producto avanza. Por eso la hoja lleva el SELLO de sus fuentes en la cabecera y este test lo recalcula.
 *
 * Aquí se vigila lo que es cuestión de hechos. **Que se VEA igual no lo dice este fichero**, lo dice el
 * navegador: `scripts/huella-maquetacion.mjs --cajon` mide el subárbol del cajón con las hojas del producto y
 * con el paquete solo, en dos anchos. Esa medición no cabe en la suite (necesita Chromium y el sitio en pie).
 */
class HojaDelCajonTest extends TestCase
{
    /** Las fuentes, en el ORDEN en que las carga `layout.blade.php`. Es el mismo `FUENTES` del generador. */
    private const FUENTES = ['landing.css', 'spinner.css', 'site.css'];

    private const PAQUETE = 'public/css/cajon.css';

    /**
     * Clases que un `.vue` emite y que la hoja del paquete NO viste, toleradas con su motivo.
     *
     * **La lista solo encoge.** Mismo trato que `ALLOWED_ORPHANS` en `ArmazonCssHasNoOrphansTest`.
     *
     * @var array<string, string>
     */
    private const TOLERADAS = [
        // (vacía)
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La hoja existe, está generada y el escáner la lee.**
     *
     * Sin esto, todo lo de abajo puede estar verde por no encontrar nada que mirar.
     */
    public function test_the_package_sheet_is_generated_and_readable(): void
    {
        $hoja = $this->paquete();

        $this->assertStringContainsString(
            'GENERADO por scripts/hoja-del-cajon.py', $hoja,
            'la hoja del paquete no lleva la cabecera del generador: o se editó a mano, o ya no se genera',
        );

        $this->assertGreaterThan(
            500, count($this->selectores($hoja)),
            'el escáner ve menos de 500 selectores en la hoja del paquete y hay ~780: no la está leyendo entera',
        );

        // Por NOMBRE, no por umbral: un contador no distingue «leo poco» de «leo otra cosa».
        foreach (['sidecart__panel', 'acct__btn', 'tabset__tab', 'jj-spinner'] as $clase) {
            $this->assertTrue(
                $this->vestida($clase, $hoja),
                "el escáner no ve `.{$clase}` en la hoja del paquete, que sí la declara",
            );
        }

        $this->assertFalse(
            $this->vestida('sidecart__zzz-no-existe', $hoja),
            'el localizador casa con cualquier cosa',
        );

        // ⚠️ **Y no da por vestido un bloque porque exista un elemento suyo.** Sin esta estrictez el
        // trinquete aprobaría un paquete al que le falte `.tabset` mientras lleve `.tabset__tab` — y la
        // regla que falta sería justo la que reparte los dos botones 50/50. No se puede comprobar por
        // mutación (relajar el localizador solo lo hace más permisivo, y un test más permisivo sigue
        // verde), así que se comprueba aquí, con el caso escrito.
        $this->assertFalse(
            $this->vestida('tabset', '.tabset__tab { color: red }'),
            'el localizador da por vestido `.tabset` porque existe `.tabset__tab`',
        );
        $this->assertTrue($this->vestida('tabset', '.tabset { display: flex }'), 'el localizador no ve la regla exacta');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La hoja se regeneró después del último cambio en sus fuentes.**
     *
     * El sello son los primeros 12 hex del SHA-1 de cada fuente, escritos en la cabecera al generar.
     */
    public function test_the_package_sheet_is_not_stale(): void
    {
        $hoja = $this->paquete();

        $this->assertSame(
            1, preg_match('/^ \* FUENTES (.+)$/m', $hoja, $m),
            'la hoja del paquete no lleva la línea `FUENTES` con el sello de sus fuentes',
        );

        $viejas = [];

        foreach (self::FUENTES as $nombre) {
            $sello = substr(sha1((string) file_get_contents(base_path('public/css/'.$nombre))), 0, 12);

            if (! str_contains($m[1], $nombre.':'.$sello)) {
                $viejas[] = $nombre;
            }
        }

        $this->assertSame([], $viejas, implode("\n", [
            'La hoja del paquete se generó con OTRA versión de: '.implode(', ', $viejas).'.',
            '',
            'No falla nada hoy —sigue siendo CSS válido— y por eso hace falta este test: lo que se queda',
            'atrás es el cajón de las instalaciones, que se vestiría con el diseño de antes.',
            '',
            '▶ `python3 scripts/hoja-del-cajon.py` para ver qué cambiaría, y `--aplicar` para escribirlo.',
        ]));
    }

    /**
     * **Ninguna lectura de token se queda sin valor ni respaldo.**
     *
     * En la hoja del producto un `var(--x)` sin definir lo tapa el resto del documento. En el paquete no hay
     * resto: la página es de otro. Una lectura desnuda ahí es una regla que **no pinta**, y no la ve nadie
     * hasta que un cliente abre el cajón.
     */
    public function test_no_token_is_read_without_a_value_or_a_fallback(): void
    {
        $hoja = $this->sinComentarios($this->paquete());

        preg_match_all('/^\s*(--[\w-]+)\s*:/m', $hoja, $definidos);
        $definidos = array_flip($definidos[1]);

        preg_match_all('/var\(\s*(--[\w-]+)\s*(.)/', $hoja, $lecturas, PREG_SET_ORDER);

        $desnudas = [];

        foreach ($lecturas as [, $token, $siguiente]) {
            if (! isset($definidos[$token]) && $siguiente !== ',') {
                $desnudas[$token] = true;
            }
        }

        $this->assertSame([], array_keys($desnudas), implode("\n", [
            'Estos tokens se leen en la hoja del paquete sin definirse en ella y sin respaldo:',
            '  '.implode(', ', array_keys($desnudas)),
            '',
            'En una página ajena eso es una regla que no pinta. O la fuente los define, o la lectura',
            'lleva su `var(--x, respaldo)`. Los ocho que hoy vienen de fuera —los `--*-brand` de',
            '`client.css`, el `--i` de la cascada— todos traen respaldo.',
        ]));
    }

    /**
     * **Toda clase que el cajón EMITE y que el producto viste, la viste también el paquete** (§4.3).
     *
     * Es el trinquete que pide la spec. El caso que evita es concreto y ya pasó una vez durante la T4: las
     * pestañas `.tabset` de Entrar / Crear cuenta tenían sus reglas en `landing.css`, que el generador no
     * miraba, y salían **sin vestir** en una página ajena. Aquí lo habría dicho la suite.
     */
    public function test_every_class_the_drawer_emits_is_dressed_by_the_package_sheet(): void
    {
        $producto = $this->sinComentarios(implode("\n", array_map(
            fn (string $n) => (string) file_get_contents(base_path('public/css/'.$n)),
            self::FUENTES,
        )));
        $paquete = $this->paquete();

        $faltan = [];

        foreach ($this->clasesQueEmiteElCajon() as $clase) {
            if (isset(self::TOLERADAS[$clase])) {
                continue;
            }

            if ($this->vestida($clase, $producto) && ! $this->vestida($clase, $paquete)) {
                $faltan[] = $clase;
            }
        }

        sort($faltan);

        $this->assertSame([], $faltan, implode("\n", [
            'Estas clases las emite un `.vue` del cajón, el producto las viste y la hoja del paquete NO:',
            '  .'.implode("\n  .", $faltan),
            '',
            'En la landing del producto se ven bien; en una página ajena salen desnudas, que es',
            'justamente lo que el paquete existe para evitar.',
            '',
            '▶ Casi siempre se arregla regenerando: `python3 scripts/hoja-del-cajon.py --aplicar`.',
        ]));
    }

    /**
     * **Las excepciones declaradas siguen teniendo sujeto.**
     *
     * Una excepción cuya clase ya no existe no es inofensiva: tapa a la siguiente que se llame igual.
     */
    public function test_every_declared_exception_still_has_a_subject(): void
    {
        $emitidas = array_flip($this->clasesQueEmiteElCajon());

        $sinSujeto = array_values(array_filter(
            array_keys(self::TOLERADAS),
            fn (string $clase) => ! isset($emitidas[$clase]),
        ));

        $this->assertSame(
            [], $sinSujeto,
            '`TOLERADAS` perdona clases que ningún `.vue` emite ya: '.implode(', ', $sinSujeto),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    private function paquete(): string
    {
        $ruta = base_path(self::PAQUETE);

        $this->assertFileExists($ruta, 'falta `'.self::PAQUETE.'`: se genera con `scripts/hoja-del-cajon.py --aplicar`');

        return (string) file_get_contents($ruta);
    }

    /**
     * Las clases literales de los `class="…"` de los `.vue` del cajón.
     *
     * ⚠️ Solo las literales, y es a propósito: una clase compuesta en JS (`'zone-' + n`) no se puede leer
     * sin ejecutar el componente, y este trinquete prefiere no ver una a inventarse diez.
     *
     * @return list<string>
     */
    private function clasesQueEmiteElCajon(): array
    {
        $encontradas = [];

        foreach ($this->ficheros(resource_path('js/sidebar'), 'vue') as $fichero) {
            $fuente = $this->sinComentarios((string) file_get_contents($fichero));

            if (preg_match_all('/class="([^"]+)"/', $fuente, $listas)) {
                foreach ($listas[1] as $lista) {
                    foreach (preg_split('/\s+/', trim($lista)) ?: [] as $clase) {
                        if (preg_match('/^[a-z][a-z0-9-]*(__[a-z0-9-]+)?(--[a-z0-9-]+)?$/', $clase) === 1) {
                            $encontradas[$clase] = true;
                        }
                    }
                }
            }
        }

        return array_keys($encontradas);
    }

    /**
     * ¿La hoja tiene alguna regla cuyo selector nombre esta clase?
     *
     * ⚠️ Aquí las clases son NOMBRES ENTEROS (`tabset`, `tabset__tab`), no bloques BEM como en el generador,
     * así que la mirada negativa es la estricta: `.tabset` no puede darse por vestida porque exista
     * `.tabset__tab`. Con la del generador —que deja pasar el `__` a propósito— este trinquete aprobaría un
     * paquete al que le falte justo la regla del contenedor, que es la que reparte los dos botones 50/50.
     */
    private function vestida(string $clase, string $css): bool
    {
        return preg_match('/\.'.preg_quote($clase, '/').'(?![a-zA-Z0-9_-])/', $css) === 1;
    }

    /** @return list<string> */
    private function selectores(string $css): array
    {
        preg_match_all('/(?:^|\})([^{}]*)\{/s', $this->sinComentarios($css), $m);

        return array_values(array_filter(array_map('trim', $m[1])));
    }

    /**
     * ⚠️ Los comentarios se BLANQUEAN conservando la longitud, no se borran: un `/* … *\/` pegado al selector
     * de la línea de arriba dejaría el localizador ciego a esa regla. Misma cautela que `ShapeScaleTest`.
     */
    private function sinComentarios(string $fuente): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/|<!--.*?-->#s',
            fn (array $m) => str_repeat(' ', strlen($m[0])),
            $fuente,
        );
    }

    /** @return list<string> */
    private function ficheros(string $raiz, string $extension): array
    {
        $salida = [];

        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz));

        foreach ($iterador as $fichero) {
            if ($fichero->isFile() && $fichero->getExtension() === $extension) {
                $salida[] = $fichero->getPathname();
            }
        }

        sort($salida);

        return $salida;
    }
}
