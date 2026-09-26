<?php

namespace Tests\Feature\Architecture;

use App\Http\Instancia\InstanceViews;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **NINGUNA CLASE DE LA LANDING SE QUEDA SIN CONSUMIDOR SIN QUE ALGUIEN LO VEA** (F5 · T2c, `#667`).
 *
 * ❗❗❗ **Nace porque la guarda que había solo miraba las clases de FACHADA**, y esa es exactamente la
 * deuda que `#665` describió: *«la deuda no es la lista, es lo que la lista no mira»*. Medido al
 * escribirla: de las **1.540** clases declaradas en `landing.css` y `site.css`, **128 no las pintaba
 * nadie** —36 de la sección de cumpleaños vieja, 22 del cajón Livewire que la Fase 4 retiró, 15 del
 * post-form, y así— y la guarda de fachada veía **una**.
 *
 * ⚠️⚠️ **Esto es un TRINQUETE, no un absoluto**: la lista de abajo es la deuda declarada y **solo
 * puede ENCOGER**, como la línea base de Larastan (`#625`) y la de ESLint (`#629`). Lo que no puede
 * pasar es que CREZCA sin que nadie lo decida.
 *
 * ❗❗ **Y hay TRES formas de componer una clase en esta casa, no una.** Un censo que busque el nombre
 * literal da huérfanas que están vivas — medido: la primera pasada dio 161 y sobraban 33. Las tres:
 *   1. concatenación en JS/Vue — `:class="'orders__status--' + row.status"`;
 *   2. concatenación en PHP — `'jj-spinner--'.$size`;
 *   3. **interpolación Blade dentro del atributo** — `class="visit__dot--{{ $heroStatus['face'] }}"`.
 * La 3 costó dos pasadas: `visit__dot--open` aparecía en el DOM servido y `visit__dot--later` no,
 * porque **a esa hora el parque estaba cerrado**. *Una medida que solo ve la rama de hoy no ve las
 * otras.*
 *
 * ⚠️ Las clases que hoy solo pinta una vista de la INSTANCIA no son huérfanas: están declaradas en
 * `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` y las excluye este mismo escaneo.
 */
class LandingCssHasNoOrphansTest extends TestCase
{
    /*
     * ⚠️ Las hojas las lee el trait COMPARTIDO y no este fichero, que es lo que esta casa decidió
     * cuando tres guardas se escribían el mismo recorrido: *cuando algo está en dos sitios, la salida
     * no es retirarlo de uno, es que haya UNA definición.* De regalo vienen sus tres trampas ya
     * pagadas: los comentarios BLANQUEADOS conservando longitud —la que esta tanda volvió a pagar
     * en el guion de poda—, `client.css` fuera (es de una instalación) y `cajon.css` fuera (es una
     * COPIA generada de estas mismas reglas, y contarla duplicaría cada huérfana).
     */
    use ReadsSiteStylesheets;

    /**
     * **LA DEUDA DECLARADA — solo puede ENCOGER** (`#667`).
     *
     * Cada entrada dice por qué sigue ahí. Una clase que deja de ser huérfana **tiene que salir de
     * esta lista** (lo exige el caso de abajo): una línea base que se queda con entradas resueltas
     * deja de medir la deuda y pasa a esconderla.
     *
     * @var array<string, string> clase => por qué no se retira
     */
    private const DEUDA = [
        // ⚠️⚠️ **DECISIÓN DEL OWNER, y por eso no se poda** (`#226`, `#253`): al vaciar el hero se
        // dejó su CSS «aparcado a propósito, sin retirar, porque retirarlo convertiría una decisión
        // provisional en un desmantelamiento». El marcado no existe —`HomePageTest` asevera su
        // ausencia— pero las reglas esperan a que el owner decida si vuelven.
        'hero__act' => 'los botones propios del hero, retirados en `#254` · CSS aparcado por decisión del owner (`#226`)',
        'hero__act--alt' => 'ídem `#226`',
        'hero__act--buy' => 'ídem `#226`',
        'hero__act-s' => 'ídem `#226`',
        'hero__acts' => 'ídem `#226`',
        'hero__chip' => 'el chip de estado del hero, retirado en `#226` · CSS aparcado por decisión del owner',
        'hero__chip--onvideo' => 'ídem `#226`',
        'hero__chip-dot' => 'ídem `#226`',
        'hero__chip-pin' => 'ídem `#226`',
        // ❗❗ **Una VARIANTE DE SISTEMA no es una regla muerta, y esto lo cazó el propio sistema**: la
        // poda de `#667` se llevó `.tag--tinta` y `TagSystemTest::test_the_system_is_declared` la
        // devolvió en el mismo minuto —declara las cuatro etiquetas juntas, tengan consumidor o no—.
        // *Un sistema declara sus variantes antes de que existan sus consumidores.*
        'tag--tinta' => 'variante del sistema de etiquetas, declarada y vigilada por `TagSystemTest`',
        // ⚠️ Éstas comparten REGLA con una clase viva, así que retirarlas es reescribir una regla que
        // sigue en uso: se van cuando se toque esa regla, no antes.
        'word' => 'comparte regla con una clase viva; se retira al tocar esa regla',
        // ⚠️ `guestform__progress` vivía aquí («comparte regla con una viva del post-form») y SE RESOLVIÓ: la regla
        // entera se fue con el post-form viejo (`fiesta-sistema-nuevo.md` T4, 26-09). La deuda encogió sola.
        'orders__line--addon' => 'comparte regla con `.orders__line`, que el cajón sí pinta',
        'orders__line--cancelled' => 'ídem `.orders__line`',
        'orders__line--finished' => 'ídem `.orders__line`',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **El escaneo lee hojas y fuentes de verdad**: en el vacío no hay huérfanas. */
    public function test_the_scan_reads_real_sheets_and_sources(): void
    {
        $this->assertGreaterThan(1000, count($this->clasesDeclaradas()),
            'se están leyendo muy pocas clases: ¿han cambiado de sitio las hojas?');
        $this->assertGreaterThan(500_000, strlen($this->fuentes()),
            'el corpus de consumidores es sospechosamente corto');
        // Y que el detector de composición vea las tres formas (si deja de verlas, sobran huérfanas).
        $this->assertGreaterThan(20, count($this->prefijosCompuestos()),
            'el detector de clases COMPUESTAS no encuentra prefijos: daría por huérfanas las vivas');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El trinquete
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **Ninguna clase nueva se queda sin consumidor.** */
    public function test_no_landing_class_is_orphaned_beyond_the_declared_debt(): void
    {
        $nuevas = array_values(array_diff($this->huerfanas(), array_keys(self::DEUDA)));

        $this->assertSame([], $nuevas, implode("\n", [
            'Estas clases se declaran en `public/css/` y NO las pinta nadie:',
            '  .'.implode("\n  .", $nuevas),
            '',
            'Tres salidas, y la primera es casi siempre la buena:',
            '  1. retirarlas de la hoja — una regla sin consumidor no falla, no avisa y engaña al leer;',
            '  2. si su pantalla vive en la INSTANCIA, declararlas en',
            '     `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` con la vista que las pinta;',
            '  3. si hay un motivo para conservarlas, a `DEUDA` de este fichero CON ese motivo escrito.',
            '',
            '⚠️ Si la clase SÍ se pinta y aparece aquí, mira cómo: este escaneo conoce tres formas de',
            '   componerla (concatenación JS, concatenación PHP e interpolación Blade). Si hay una cuarta,',
            '   el que hay que arreglar es el detector.',
        ]));
    }

    /**
     * **LA DEUDA SOLO ENCOGE**: una entrada que ya no es huérfana sale de la lista.
     *
     * ⚠️ Sin este caso, la línea base se queda con entradas resueltas y **deja de medir la deuda para
     * esconderla** — que es justo lo que le pasó a la guarda de fachada.
     */
    public function test_the_declared_debt_only_shrinks(): void
    {
        $resueltas = array_values(array_diff(array_keys(self::DEUDA), $this->huerfanas()));

        $this->assertSame([], $resueltas, implode("\n", [
            'Estas clases están en `DEUDA` y ya NO son huérfanas: '.implode(', ', $resueltas),
            '▶ Sácalas de la lista en este mismo cambio. Una línea base con entradas resueltas miente',
            '  sobre cuánta deuda queda, y es lo que deja crecer a la de al lado sin que nadie lo vea.',
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function huerfanas(): array
    {
        $fuentes = $this->fuentes();
        $prefijos = $this->prefijosCompuestos();
        $deLaInstancia = array_keys(InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA);

        $out = [];

        foreach ($this->clasesDeclaradas() as $clase) {
            if (in_array($clase, $deLaInstancia, true)) {
                continue;
            }

            if (preg_match('/(?<![\w-])'.preg_quote($clase, '/').'(?![\w-])/', $fuentes) === 1) {
                continue;
            }

            foreach ($prefijos as $prefijo) {
                if ($prefijo !== $clase && str_starts_with($clase, $prefijo)) {
                    continue 2;
                }
            }

            $out[] = $clase;
        }

        sort($out);

        return $out;
    }

    /** @return list<string> */
    private function clasesDeclaradas(): array
    {
        $out = [];

        foreach ($this->siteRules() as $regla) {
            preg_match_all('/\.(-?[_a-zA-Z][\w-]*)/', $regla['selector'], $clases);
            foreach ($clases[1] as $c) {
                $out[$c] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Los prefijos que alguien COMPONE. Las tres formas, y el orden importa poco porque se compara
     * por prefijo: lo que importa es no dejarse ninguna fuera.
     *
     * @return list<string>
     */
    private function prefijosCompuestos(): array
    {
        $txt = $this->fuentes();
        $out = [];

        foreach ([
            "/'([a-z][\\w-]*(?:--|__|-))'\\s*\\+/",   // 1 · JS: `'orders__status--' + row.status`
            '/`([a-z][\\w-]*(?:--|__|-))\\$\\{/',     //     y plantilla JS: `` `is-${mode}` ``
            "/'([a-z][\\w-]*(?:--|__|-))'\\s*\\./",   // 2 · PHP: `'jj-spinner--'.\$size`
            '/([a-z][\\w-]*(?:--|__|-))\\{\\{/',      // 3 · Blade: `visit__dot--{{ \$face }}`
        ] as $patron) {
            preg_match_all($patron, $txt, $m);
            foreach ($m[1] as $p) {
                $out[$p] = true;
            }
        }

        return array_keys($out);
    }

    /** Todo lo que puede pintar una clase: las vistas, el cajón, el dominio. */
    private function fuentes(): string
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $txt = '';

        foreach ([resource_path('views'), resource_path('js'), app_path()] as $raiz) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $f) {
                if (! in_array($f->getExtension(), ['php', 'js', 'vue'], true)) {
                    continue;
                }
                $txt .= "\n".$this->sinComentarios((string) file_get_contents($f->getPathname()));
            }
        }

        return $cache = $txt;
    }

    /**
     * ⚠️⚠️ **Los comentarios se quitan, y no es cosmética.** Este repo NOMBRA clases en prosa todo el
     * rato (`.trio--page`, `.rev__card`…), así que sin quitarlos «tiene consumidor» sale cierto para
     * cualquier clase que alguien mencionó al explicar por qué la retiraba.
     */
    private function sinComentarios(string $txt): string
    {
        $txt = (string) preg_replace('/\{\{--.*?--\}\}/s', ' ', $txt);
        $txt = (string) preg_replace('#/\*.*?\*/#s', ' ', $txt);
        $txt = (string) preg_replace('#^\s*//.*$#m', ' ', $txt);

        return (string) preg_replace('#^\s*\*.*$#m', ' ', $txt);
    }
}
