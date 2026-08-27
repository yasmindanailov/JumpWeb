<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **EL COLOR DE UNA ZONA NO VIAJA POR EL NOMBRE DE UNA CLASE** (`DECISIONES #138`).
 *
 * ⚠️⚠️ Nació de un defecto VIVO, no de una simetría. El color llegaba por dos caminos: la tarjeta lo
 * tomaba de `zones.color` —el suyo— y la pestaña de una regla `.zone-tab--{accent}` que leía
 * `--kids-1`, o sea **el color de la primera zona con ese acento**. Medido sobre la BD de desarrollo:
 * las zonas `cap` y `cap2` tenían `accent=kids` y `color=#FF5B22`, así que su tarjeta salía naranja y
 * su pestaña lima. El mismo sitio, dos colores, y **nada fallaba**.
 *
 * ▶ Y la otra mitad era peor para un producto white-label: **esas reglas solo existían para `jump` y
 * `kids`**, los acentos del primer cliente. Una instalación con otras zonas perdía el tinte en
 * silencio — sin error, solo una landing sosa.
 *
 * Esta guarda **prohíbe el MECANISMO**, no persigue el síntoma: hermana de `LedgerSingleSourceTest`
 * y `DayLabelSingleSourceTest`, que nacieron de la misma clase de divergencia.
 */
class ZoneAccentIsNotAClassNameTest extends TestCase
{
    /** Plantillas que pintan zonas. Si nace una nueva, entra aquí y se somete a la misma regla. */
    private const TEMPLATES = 'resources/views';

    /**
     * **Ninguna plantilla mete el acento dentro de un nombre de clase.**
     *
     * `class="zone-tab zone-tab--{{ $zone->accent }}"` obliga a que exista una regla CSS por cada
     * acento posible, y los acentos son DATOS: la lista no se puede conocer al escribir el CSS.
     */
    public function test_no_template_puts_the_accent_inside_a_class_name(): void
    {
        $hallazgos = [];

        foreach ($this->bladeFiles(base_path(self::TEMPLATES)) as $ruta) {
            $codigo = (string) file_get_contents($ruta);

            // `--{{ $zone->accent }}` / `--{{ $zone['accent'] ... }}` pegado a un nombre de clase.
            if (preg_match('/--\{\{[^}]*accent[^}]*\}\}/', $codigo, $m)) {
                $hallazgos[] = str_replace(base_path().'/', '', $ruta).' · «'.trim($m[0]).'»';
            }
        }

        $this->assertSame([], $hallazgos, implode("\n", array_merge(
            ['Una plantilla vuelve a meter el acento en el nombre de una clase:'],
            array_map(fn (string $h): string => '  · '.$h, $hallazgos),
            ['',
                '▶ Los acentos son DATOS: no se puede escribir una regla CSS por cada uno.',
                '▶ Pinta el color EN LÍNEA con `ThemeSettings::zoneStyle()`, que compone',
                '  `--zone-1`/`--zone-2`/`--on-brand` una sola vez para todas las superficies.'],
        )));
    }

    /**
     * **La guarda de la guarda**: el patrón caza su propio ejemplo.
     *
     * Sin esto, una expresión mal escrita dejaría el test verde para siempre sin mirar nada — el modo
     * de fallo de toda guarda por `grep`, y uno que este repo ya pagó dos veces.
     */
    public function test_the_forbidden_pattern_actually_matches(): void
    {
        foreach ([
            '<button class="zone-tab zone-tab--{{ $zone->accent }}">',
            "<div class=\"svc-rates__panel svc-rates__panel--{{ \$zone['accent'] ?? '' }}\">",
        ] as $muestra) {
            $this->assertMatchesRegularExpression(
                '/--\{\{[^}]*accent[^}]*\}\}/', $muestra,
                'el patrón prohibido ha dejado de cazar su propio ejemplo: la guarda es decorativa',
            );
        }
    }

    /**
     * ⚠️ **Las referencias a la paleta del PRIMER CLIENTE solo pueden ENCOGER.**
     *
     * `--jump-*` y `--kids-*` son los acentos de las dos zonas del cliente origen, escritos en el CSS
     * del producto. Cada uno que quede es un sitio donde una instalación nueva hereda una marca que
     * no es la suya. La lista de abajo es lo que falta por retirar, con su motivo; **quitar una
     * entrada está bien, añadirla no**.
     *
     * ✅ **VACÍA desde el 2026-08-25** (`DECISIONES #139`): el diseñador de invitaciones era el último
     * y ya no nombra ninguna zona. A partir de aquí la guarda es absoluta — cualquier reaparición
     * cae, sin excepciones que negociar.
     *
     * @var array<string,string>
     */
    private const PALETA_HEREDADA_PENDIENTE = [];

    public function test_the_first_clients_palette_only_shrinks(): void
    {
        $conReferencias = [];

        foreach (glob(base_path('public/css/*.css')) ?: [] as $ruta) {
            // ⚠️ **La hoja de una INSTALACIÓN queda fuera, y no es un descuido.** `client.css`
            // no es del producto: existe precisamente para que un cliente declare sus valores
            // —literales incluidos, que es de lo que está hecho un paquete de tema— y juzgarla
            // con las reglas del producto sería prohibirle hacer aquello para lo que existe.
            // ▶ Y además la hacía MENTIR al gate: una guarda que asevera por hoja cambiaba el
            // recuento de aserciones según si la máquina tenía o no un paquete instalado, así
            // que el `pre-push` bloqueaba en una máquina o en la otra. Medido el 2026-08-28 al
            // montar el paquete del segundo cliente. Mismo criterio que `SidebarStyleWiringTest`,
            // que enumera las hojas del producto en vez de barrer la carpeta.
            if (basename($ruta) === 'client.css') {
                continue;
            }

            // ⚠️ Se miran las REGLAS, no los comentarios. Sin esto la guarda saltaba con la nota que
            // explica por qué esos tokens se retiraron: una guarda que se dispara con su propia
            // documentación obliga a borrar el porqué, y acaba silenciada.
            $codigo = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($ruta));
            $n = preg_match_all('/var\(--(?:jump|kids)-\d/', $codigo);

            if ($n > 0) {
                $conReferencias[str_replace(base_path().'/', '', $ruta)] = $n;
            }
        }

        $pendientes = array_keys(self::PALETA_HEREDADA_PENDIENTE);
        $nuevos = array_diff(array_keys($conReferencias), $pendientes);

        $this->assertSame([], array_values($nuevos), implode("\n", [
            'Un CSS vuelve a nombrar la paleta del primer cliente (`--jump-*` / `--kids-*`):',
            '  · '.implode("\n  · ", $nuevos),
            '',
            '▶ Una instalación nueva heredaría una marca que no es la suya, sin que nada falle.',
            '▶ Usa `--zone-1`/`--zone-2`, que el servidor pinta por zona.',
        ]));

        // Y al revés: si un fichero de la lista ya no tiene referencias, la entrada sobra.
        foreach ($pendientes as $ruta) {
            $this->assertArrayHasKey(
                $ruta, $conReferencias,
                "`$ruta` ya no nombra la paleta del primer cliente: borra su entrada de ".
                '`PALETA_HEREDADA_PENDIENTE`. La lista solo encoge, y una entrada muerta la convierte en decorativa.',
            );
        }
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(string $dir): array
    {
        $rutas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $rutas[] = $file->getPathname();
            }
        }

        return $rutas;
    }
}
