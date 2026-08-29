<?php

namespace Tests\Feature\Site;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * **LA ANATOMÍA DEL SET DE ICONOS** (`docs/specs/tema-por-instalacion.md` §22, `#257`).
 *
 * El set del producto dejó de ser una colección de dibujos sueltos para ser un SISTEMA con reglas
 * escritas, que son las del artboard `Iconos PJP`:
 *
 * | §01 ANATOMÍA | lienzo de 24, área viva 20, masa mínima 3 |
 * | §05 TAMAÑOS  | 24 es la talla de trabajo |
 * | §06 REGLAS   | ✕ línea fina (trazo < 3) · ✕ duotono dentro del glifo |
 *
 * ⚠️⚠️ **Lo que esto caza es un icono pegado de otra librería.** Un glifo de Heroicons o Lucide se
 * ve «bien» suelto y rompe el idioma en cuanto se pone al lado de los otros: otra rejilla, trazo de
 * 1.5 y a veces un color quemado. No falla nada, no lo enseña ningún test de conducta y solo se nota
 * mirando la web entera a la vez — que es exactamente cómo se llegó a tener **siete lienzos
 * distintos** antes de `#257`.
 *
 * ⚠️ **Y el color en crudo no es solo estética: es white-label.** Un `#1AA9DE` dentro de un glifo lo
 * saca del tema — ese icono se queda con el color del segundo cliente en la instalación del tercero,
 * y no hay token que lo arregle (`landing-white-label.md` §4.5).
 */
class IconSetAnatomyTest extends TestCase
{
    private const DIR = 'views/components/icons';

    /**
     * **Los MARCADORES DE CATÁLOGO, que viven en otra escala a propósito.**
     *
     * No son glifos de interfaz: son las ILUSTRACIONES que marcan un producto (`ProductIcon::CHOICES`)
     * y el propio artboard las separa —§06 prohíbe la «pegatina por debajo de 40», o sea que reconoce
     * una segunda escala—. Meterlas en la rejilla de 24 las volvería ilegibles a su tamaño de uso.
     *
     * ⚠️ **Esta lista solo ENCOGE.** Un icono nuevo de interfaz no se añade aquí: se dibuja en 24.
     *
     * @var list<string>
     */
    private const OTRA_ESCALA = [
        'ic-b1', 'ic-b7', 'ic-e2', 'ic-e5', 'socks', 'ticket-tear-off', 'clipboard-check',
    ];

    /**
     * **Los TRES que siguen hablando el idioma anterior**, porque el artboard no los dibuja.
     *
     * `#257` trajo 43 dibujos del set del cliente y estos tres no tienen equivalente allí, así que
     * se dejaron intactos antes que inventarlos: redibujar a ojo un glifo de marca es lo que en
     * `#211` salió con un **29 % de píxeles distintos**. Son líneas finas (`fill="none"` con trazo
     * 1.7–2) y por eso desentonan; la salida es pedírselos al diseñador, no improvisarlos.
     *
     * ⚠️ **Esta lista solo ENCOGE**, como la de `ShapeScaleTest`.
     *
     * @var list<string>
     */
    private const TRAZO_FINO_HEREDADO = ['devices', 'cookie', 'chevron-down'];

    /**
     * **La guarda de la guarda: que el recorrido VEA el set entero.**
     *
     * Un descubrimiento que se queda corto pasa igual de verde con menos sujetos —es el fallo de
     * `DECISIONES #113`, que este repo ya pagó—, así que se ancla en piezas concretas de las tres
     * familias en vez de en un número, que envejece a la primera.
     */
    public function test_the_scan_sees_the_whole_set(): void
    {
        $nombres = array_keys($this->iconos());

        foreach (['menu', 'qr', 'settings', 'ic-b1', 'devices'] as $ancla) {
            $this->assertContains($ancla, $nombres, "el recorrido del set ha dejado de ver `{$ancla}`");
        }

        $this->assertGreaterThanOrEqual(
            50, count($nombres),
            'el recorrido ve menos de 50 iconos: el set tiene 57 y un descubrimiento corto pasa en '.
            'verde sin mirar lo que falta.'
        );
    }

    /** **Ningún glifo teclea un color.** Sin excepciones: un color quemado no lo puede tematizar nadie. */
    public function test_no_icon_paints_with_a_hard_coded_colour(): void
    {
        $culpables = [];

        foreach ($this->iconos() as $nombre => $svg) {
            if (preg_match('/#[0-9a-fA-F]{3,8}\b|\brgba?\(|\bhsla?\(/', $svg, $m) === 1) {
                $culpables[] = $nombre.' → '.$m[0];
            }
        }

        $this->assertSame([], $culpables,
            "Hay iconos con un color TECLEADO dentro del dibujo.\n".
            "▶ El set pinta con `currentColor` y nada más: el color lo pone el texto que acompaña.\n".
            '▶ Un color quemado saca ese glifo del tema, y en la instalación del siguiente cliente '.
            'se queda con el color del anterior.'
        );
    }

    /** **La rejilla es 24**, salvo los marcadores de catálogo, que están en otra escala a propósito. */
    public function test_every_ui_glyph_lives_on_the_24_grid(): void
    {
        $fuera = [];

        foreach ($this->iconos() as $nombre => $svg) {
            if (in_array($nombre, self::OTRA_ESCALA, true)) {
                continue;
            }

            if (! preg_match('/viewBox="0 0 24 24"/', $svg)) {
                preg_match('/viewBox="([^"]*)"/', $svg, $m);
                $fuera[] = $nombre.' → '.($m[1] ?? 'sin viewBox');
            }
        }

        $this->assertSame([], $fuera,
            "Hay glifos de interfaz fuera de la rejilla de 24.\n".
            "▶ Es la anatomía del set (§01 del artboard: lienzo 24, área viva 20, margen 2), y lo que\n".
            "  permite que dos iconos distintos pesen lo mismo puestos uno al lado del otro.\n".
            '▶ Si de verdad es una ilustración de catálogo y no un glifo, va en `OTRA_ESCALA` con su motivo.'
        );
    }

    /**
     * **Nada de línea fina DONDE LA FORMA LA DIBUJA EL TRAZO** (§06: «✕ línea fina, trazo por debajo de 3»).
     *
     * ⚠️⚠️ **El matiz es lo que hace útil esta guarda, y sale del propio artboard, que se «salta» su
     * regla**: `ui/check` declara `stroke-width="1.4"`. No es una línea fina — es una MASA
     * (`fill="currentColor"`) con un trazo hilo que le redondea las juntas. La prohibición es para el
     * icono que se dibuja *con* el trazo, así que la condición se mira solo donde hay `fill="none"`.
     * Sin ese matiz la guarda sería roja con el set del cliente puesto tal cual, que es la definición
     * de una guarda que mide otra cosa.
     */
    public function test_no_hairline_where_the_stroke_is_the_drawing(): void
    {
        $iconos = $this->iconos();

        // ⚠️ **CONTROL del matiz.** Si `check` dejara de ser una masa con trazo hilo, este caso
        // pasaría a comprobar una discriminación que ya no ejercita nadie, y el día que alguien
        // «arregle» la guarda quitando la condición de `fill="none"` no habría nada que lo cazara.
        $this->assertMatchesRegularExpression(
            '/<svg[^>]*fill="currentColor"[^>]*stroke-width="1\.4"/', $iconos['check'] ?? '',
            '`check` ha dejado de ser el control de esta guarda: era la MASA con trazo hilo que '.
            'demuestra que la regla mira solo donde el trazo dibuja la forma.'
        );
        $this->assertNotContains('check', self::TRAZO_FINO_HEREDADO,
            '`check` no puede estar en la lista de excepciones: es masa, no línea fina. Si está ahí, '.
            'la guarda se ha relajado para tapar un falso positivo en vez de arreglarlo.'
        );

        $finos = [];

        foreach ($iconos as $nombre => $svg) {
            if (in_array($nombre, self::TRAZO_FINO_HEREDADO, true)) {
                continue;
            }

            // Solo los elementos —o el `<svg>`— cuya forma la dibuja el trazo: `fill="none"`.
            foreach ($this->etiquetasSinRelleno($svg) as $etiqueta) {
                $grosor = $this->grosorEfectivo($etiqueta, $svg);

                if ($grosor !== null && $grosor < 3.0) {
                    $finos[] = $nombre.' → '.$grosor;
                }
            }
        }

        $this->assertSame([], $finos,
            "Hay dibujos hechos con TRAZO por debajo de 3.\n".
            "▶ §06 del artboard lo prohíbe con su motivo: a 20 px un icono de línea desaparece y una\n".
            "  silueta no. La masa mínima del set es 3.\n".
            '▶ Si es un glifo heredado que el artboard no dibuja, va en `TRAZO_FINO_HEREDADO`.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Cada icono del set, RENDERIZADO. Se mira lo que se sirve y no el fuente: los valores por
     * defecto viven en `$attributes->merge()` y en el fuente no se leen.
     *
     * @return array<string, string>
     */
    private function iconos(): array
    {
        $out = [];

        foreach (glob(resource_path(self::DIR.'/*.blade.php')) ?: [] as $fichero) {
            $nombre = basename($fichero, '.blade.php');
            $out[$nombre] = Blade::render("<x-icons.{$nombre} />");
        }

        $this->assertNotEmpty($out, 'no se ha encontrado ningún icono del set');

        return $out;
    }

    /**
     * Las etiquetas de apertura que declaran `fill="none"` —el `<svg>` incluido—, que son las que
     * dibujan con el trazo.
     *
     * @return list<string>
     */
    private function etiquetasSinRelleno(string $svg): array
    {
        preg_match_all('/<[a-zA-Z]+\b[^>]*fill="none"[^>]*>/', $svg, $m);

        return $m[0];
    }

    /**
     * El grosor que de verdad se aplica a una etiqueta: el suyo si lo declara, y si no el que
     * hereda del `<svg>`.
     *
     * ⚠️⚠️ **La frontera de palabra no es cosmética**: sin `(?<![-\w])`, un patrón de `width`
     * casa dentro de `stroke-width`. En esta misma tanda pasó DOS veces —en el extractor del
     * artboard y en `SidebarDrawerPolishTest`, que se puso rojo con el producto sano—.
     */
    private function grosorEfectivo(string $etiqueta, string $svg): ?float
    {
        if (preg_match('/(?<![-\w])stroke-width="([0-9.]+)"/', $etiqueta, $m) === 1) {
            return (float) $m[1];
        }

        // Sin trazo declarado en ninguna parte no hay línea que juzgar: es una forma sin pintar.
        if (! str_contains($etiqueta, 'stroke=') && ! preg_match('/<svg\b[^>]*stroke="/', $svg)) {
            return null;
        }

        if (preg_match('/<svg\b[^>]*(?<![-\w])stroke-width="([0-9.]+)"/', $svg, $m) === 1) {
            return (float) $m[1];
        }

        return null;
    }
}
