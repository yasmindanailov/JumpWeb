<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **UN BOTÓN QUE ABRE ALGO TIENE QUE ABRIRLO, Y LO QUE ABRE NACE CERRADO** (2026-09-01).
 *
 * ⚠️⚠️ **Nace de un fallo REAL que lo vio el OWNER usando el cajón, no una suite.** El botón «Más
 * info» de cada complemento (`.addons__moreinfo`) se emitía **sin `@click`**, y la lista de
 * ventajas (`.addons__features`) se pintaba **sin condición de estado**. En CSS tampoco había un
 * `display: none` que la ocultara. Resultado: **la ficha salía siempre desplegada y el botón era
 * decoración**. Con diez complementos en un pack de cumpleaños, eso es toda la carta abierta de
 * golpe encima del paso de la hora.
 *
 * ⚠️⚠️ **Y NINGUNA guarda podía verlo, ni siquiera las que miran este fichero.**
 * `SidebarStyleWiringTest` pregunta si cada clase emitida tiene una REGLA —y `.addons__features` la
 * tiene, bien puesta—; el contrato de árbol compara ESTRUCTURA, y un `<ul>` visible es el mismo nodo
 * que uno oculto. *Nadie preguntaba si el control hace lo que su rótulo promete.*
 *
 * ⚠️ **Peor: el comentario de `addon-chip.blade.php` AFIRMABA que esto funcionaba** — decía que la
 * landing usaba «el mismo patrón que el sidebar de compra … + toggle Alpine». La landing sí lo tenía;
 * el cajón nunca. *Un comentario que describe la paridad con otra pantalla no es prueba de que esa
 * pantalla la cumpla.*
 *
 * ▶ Se vigila con análisis del MARCADO y no con una captura, porque es exactamente lo que vuelve sin
 * que nadie lo note: alguien añade un desplegable copiando el de al lado y se trae el botón muerto.
 */
class DrawerDisclosureIsWiredTest extends TestCase
{
    private const PASO = 'resources/js/sidebar/steps/TimeStep.vue';

    /**
     * El componente **sin comentarios**.
     *
     * ⚠️⚠️ **Sin esto la guarda se caza a sí misma, y pasó al escribirla.** El comentario que explica
     * el defecto CITA el marcado roto —`<button class="addons__moreinfo">` sin `@click`— y el
     * escáner lo leyó como un tercer botón: **caso rojo con el producto sano**.
     * ▶ Es la trampa de `#293` por el otro lado: allí un motivo copiado a mano no aparecía buscando
     * su nombre; aquí un nombre citado en prosa aparece como si fuera código. *Todo escáner de
     * marcado tiene que decidir qué hace con los comentarios, y decidirlo a propósito.*
     */
    private function fuente(): string
    {
        $src = (string) file_get_contents(base_path(self::PASO));

        $src = (string) preg_replace('#/\*.*?\*/#s', '', $src);      // bloques JS y JSDoc
        $src = (string) preg_replace('#<!--.*?-->#s', '', $src);     // comentarios de plantilla

        return $src;
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El fichero existe y contiene las dos piezas.**
     *
     * ⚠️ Sin este caso, renombrar el componente o la clase dejaría los de abajo buscando en una
     * cadena que ya no contiene nada — y **no encontrar una infracción en un fichero vacío no es
     * cumplirla**. Es el fallo que este proyecto ha cometido cuatro veces.
     */
    public function test_the_scan_actually_reads_the_step(): void
    {
        $src = $this->fuente();

        $this->assertNotSame('', trim($src), 'el paso de la hora no se está leyendo');
        $this->assertStringContainsString('addons__moreinfo', $src, 'ya no hay botón «Más info» que vigilar');
        $this->assertStringContainsString('addons__features', $src, 'ya no hay lista de ventajas que vigilar');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Todo botón «Más info» lleva su manejador.**
     *
     * Se mira la etiqueta ENTERA del `<button>`, no la línea: el atributo puede ir en otra por el
     * formato. Un botón sin `@click` no falla, no avisa y no hace nada — que es como estuvo.
     */
    public function test_every_more_info_button_has_a_click_handler(): void
    {
        preg_match_all('/<button\b[^>]*addons__moreinfo[^>]*>/s', $this->fuente(), $m);

        $this->assertNotEmpty($m[0], 'no se encuentra ningún botón «Más info»: el patrón ya no casa');

        foreach ($m[0] as $i => $etiqueta) {
            $this->assertMatchesRegularExpression(
                '/@click\s*=/', $etiqueta,
                'el botón «Más info» n.º '.($i + 1)." NO tiene `@click`.\n".
                "▶ Un control cuyo rótulo promete abrir algo y no lo abre es peor que no estar: el\n".
                '  usuario lo pulsa y concluye que la web está rota. Pasó, y lo vio el owner.',
            );
        }
    }

    /**
     * **Y la lista de ventajas nace CERRADA: se pinta bajo condición de estado.**
     *
     * ⚠️ Las dos mitades hacen falta. Con el manejador puesto pero la lista sin condición, el botón
     * alternaría un estado que no pinta nada y la ficha seguiría siempre abierta — que es
     * exactamente el síntoma que se arregló.
     */
    public function test_the_features_list_is_gated_by_state(): void
    {
        preg_match_all('/<ul\b[^>]*addons__features[^>]*>/s', $this->fuente(), $m);

        $this->assertNotEmpty($m[0], 'no se encuentra ninguna lista de ventajas: el patrón ya no casa');

        foreach ($m[0] as $i => $etiqueta) {
            $this->assertMatchesRegularExpression(
                '/\bv-(?:if|show)\s*=\s*"[^"]*isExpanded\(/', $etiqueta,
                'la lista de ventajas n.º '.($i + 1)." se pinta SIN condición de estado.\n".
                "▶ Entonces sale siempre desplegada y el botón que la «abre» no cambia nada. Con diez\n".
                '  complementos en un pack, eso es la carta entera abierta encima del paso de la hora.',
            );
        }
    }

    /**
     * **El estado nace VACÍO, o sea todo plegado.**
     *
     * ⚠️ Sin esto, arrancar con todo expandido cumpliría los dos casos de arriba y devolvería el
     * mismo defecto por otra puerta: el botón funcionaría, pero la ficha seguiría abierta al entrar.
     */
    public function test_nothing_starts_expanded(): void
    {
        $this->assertMatchesRegularExpression(
            '/const\s+expanded\s*=\s*ref\(\s*new\s+Set\(\s*\)\s*\)/', $this->fuente(),
            "el conjunto de desplegados no nace vacío.\n".
            '▶ El botón dice «Más info»: lo que hay debajo tiene que estar plegado hasta que se pulse.',
        );
    }
}
