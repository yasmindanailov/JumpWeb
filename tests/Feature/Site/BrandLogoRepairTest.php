<?php

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * **LAS DOS REPARACIONES DEL LOGOTIPO DE UNA INSTALACIÓN** (`DECISIONES #275`).
 *
 * El lockup del segundo cliente se compone en CSS —cuatro `-webkit-text-stroke` decrecientes y un
 * `text-shadow` de 6 pasos para la profundidad— y se entrega exportado a SVG. Esa exportación trae
 * **dos defectos de traducción**, y los dos los vio el owner antes que nadie:
 *
 *  1. **La profundidad viene ENGORDADA.** `text-shadow` **NO arrastra el `-webkit-text-stroke`**:
 *     su sombra son copias del glifo DESNUDO. El export les puso a los `<use>` de extrusión el
 *     mismo `stroke-width` que a la capa de color, así que cada copia salía media anchura de trazo
 *     más gorda por lado. Medido en navegador con control (mismo glifo con y sin trazo: 20,63 px
 *     contra 14,13, y 14,13 el de un glifo sin trazo), el faldón azul bajo las letras medía **7,1 px
 *     contra los 4,0 del mockup**, y **todas** las columnas del contorno lo tenían (1121 de 1121)
 *     frente a 852 de 1137 en el suyo.
 *  2. **La silueta se RESTÓ del trazado de las palabras.** La letra que el dibujo tapa viene mordida
 *     por su contorno: le faltaba el **16,8 %** del área. En reposo no se ve —el dibujo ocupa el
 *     hueco—, pero `brand-hop` hace volar la silueta y deja la letra mutilada a la vista.
 *
 * ⚠️⚠️ **`#274` midió las BANDAS del contorno, que eran idénticas** (cian 1,00 · tinta 1,50 ·
 * blanco 0,875 px en su lockup y en el nuestro), concluyó que el ojo veía algo que la aritmética no,
 * y las adelgazó al 65 %. No tocó la causa —la sombra— y **rompió una invariante del propio dibujo**:
 * al encoger la capa de color sin encoger la extrusión, el azul marino pasó a asomar 1,14 px por
 * FUERA del cian en todo el contorno. *Se midió lo que se veía, no lo que sobresalía.*
 *
 * ▶ **Estos casos NO leen el logotipo instalado, y es deliberado**: está gitignorado, así que un caso
 * que solo corre donde hay paquete de marca desestabiliza el contador de aserciones del `pre-push`
 * entre máquinas (la lección que `InlineBrandLogoTest` ya pagó). Se vigila el MECANISMO sobre una
 * anatomía sintética, que es lo que el producto puede prometer.
 */
class BrandLogoRepairTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/logo-repair-'.getmypid().'.svg';
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        @unlink($this->tmp.'.path');
        parent::tearDown();
    }

    /** Anatomía sintética: geometría del PRODUCTO, no de ningún cliente. */
    private function svg(?string $u1 = null, ?string $relleno = null): string
    {
        $u1 ??= 'M0 0L10 0L10 10ZM2 2L4 2L4 4ZM20 0L30 0L30 10ZM40 0L50 0L50 10ZM42 2L44 2L44 4Z';
        $laLetra = 'M40 0L50 0L50 10ZM42 2L44 2L44 4Z';
        $relleno ??= $laLetra;

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50">
        <defs><path id="u1" d="{$u1}"></path><path id="u2" d="M0 0L9 0L9 9Z"></path>
        <path id="fig" d="M1 1L5 1L5 5Z"></path>
        <linearGradient id="sombraTexto" x1="0" y1="1" x2="0" y2="0">
        <stop offset="0" stop-color="#061C2C" stop-opacity=".5"></stop>
        <stop offset=".15" stop-color="#061C2C" stop-opacity=".18"></stop>
        <stop offset=".33" stop-color="#061C2C" stop-opacity="0"></stop>
        </linearGradient></defs>
        <g>
          <use href="#u2" fill="url(#sombraTexto)"></use>
          <use href="#u1" fill="url(#sombraTexto)"></use>
          <use href="#u2" transform="translate(3 4)" fill="#05344c" stroke="#05344c" stroke-width="52"></use>
          <use href="#u2" transform="translate(1 2)" fill="#0a5a90" stroke="#0a5a90" stroke-width="52"></use>
          <use href="#u2" fill="#1AA9DE" stroke="#1AA9DE" stroke-width="52"></use>
          <use href="#u1" transform="translate(2 3)" fill="#05344c" stroke="#05344c" stroke-width="40.8"></use>
          <use href="#u1" fill="#1AA9DE" stroke="#1AA9DE" stroke-width="40.8"></use>
          <path d="{$relleno}" fill="#26AEE1"></path>
          <use href="#fig" transform="translate(1 2)" fill="#063c5a" stroke="#063c5a" stroke-width="8.3"></use>
          <use href="#fig" fill="#1AA9DE" stroke="#1AA9DE" stroke-width="8.3"></use>
        </g></svg>
        SVG;
    }

    /** @return array{0:int,1:string,2:string} código, salida, contenido del fichero después */
    private function correr(string $guion, array $args = []): array
    {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path("scripts/{$guion}"));
        foreach ([$this->tmp, ...$args] as $a) {
            $cmd .= ' '.escapeshellarg($a);
        }
        exec($cmd.' 2>&1', $salida, $codigo);

        return [$codigo, implode("\n", $salida), (string) @file_get_contents($this->tmp)];
    }

    // ══ 1 · LA SOMBRA ══════════════════════════════════════════════════════════════════════════

    /**
     * **La extrusión de las PALABRAS pierde el trazo, y solo ella.**
     *
     * Es la traducción fiel de `text-shadow`, que copia el glifo desnudo. La SILUETA no entra: en el
     * mockup su profundidad es un `drop-shadow` de una `<img>`, y **un `drop-shadow` sí sigue el alfa
     * completo del dibujo, contorno incluido** — ahí la extrusión con trazo es lo correcto.
     */
    public function test_only_the_word_depth_loses_its_stroke(): void
    {
        $color = '/<use href="#u[12]"(?![^>]*\btransform=)[^>]*\bstroke-width="[\d.]+"/';
        $antes = $this->svg();
        file_put_contents($this->tmp, $antes);
        [$codigo, $salida, $despues] = $this->correr('logo-sombra.php');

        $this->assertSame(0, $codigo, "el guion ha fallado:\n{$salida}");

        $this->assertSame(
            0,
            preg_match_all('/<use href="#u[12]"[^>]*\btransform="[^"]*"[^>]*\bstroke-width=/', $despues),
            "ha quedado extrusión de palabra CON trazo.\n".
            "▶ `text-shadow` no arrastra el `-webkit-text-stroke`: sus copias son el glifo DESNUDO.\n".
            '  Con trazo, el faldón azul sale casi al doble de largo que en el mockup.',
        );

        // ⚠️ Contra el ANTES, no contra un número escrito a mano: así la guarda no depende del
        // tamaño del ejemplar y sigue mordiendo si alguien amplía la anatomía sintética.
        $this->assertSame(
            preg_match_all($color, $antes),
            preg_match_all($color, $despues),
            'las capas de COLOR han perdido su trazo: son las tres bandas del contorno (cian, tinta '.
            'y blanco) y ya coinciden con el mockup al dígito. El guion no debe tocarlas.',
        );
        $this->assertGreaterThan(
            0, preg_match_all($color, $antes),
            'el ejemplar no tiene ninguna capa de color: la aserción de arriba no vigilaría nada.',
        );

        $this->assertSame(
            2,
            preg_match_all('/<use href="#fig"[^>]*\bstroke-width="[\d.]+"/', $despues),
            "la SILUETA ha perdido su trazo, y ahí sí hace falta.\n".
            '▶ Su profundidad en el mockup es un `drop-shadow` de una imagen, que sigue el alfa '.
            'completo del dibujo — contorno incluido.',
        );
    }

    /**
     * **Es idempotente, y eso no es cosmético.**
     *
     * El guion al que sustituye (`logo-contorno.php`, `#274`) MULTIPLICABA por un factor: pasarlo dos
     * veces dejaba los trazos al 42 % sin avisar. Y el logotipo no viaja en el despliegue, así que
     * alguien acabaría pasándolo dos veces en el servidor.
     */
    public function test_the_shadow_repair_is_idempotent(): void
    {
        file_put_contents($this->tmp, $this->svg());
        $this->correr('logo-sombra.php');
        $primera = (string) file_get_contents($this->tmp);

        [$codigo, , $segunda] = $this->correr('logo-sombra.php');

        $this->assertSame(0, $codigo);
        $this->assertSame($primera, $segunda, 'el segundo pase ha vuelto a cambiar el fichero.');
    }

    /**
     * **El velo del borde inferior baja de fuerza y cambia de caja.**
     *
     * El lockup lo pinta con `background-clip: text`, así que **su degradado se mide sobre la CAJA DE
     * LÍNEA**; la exportación lo tradujo a `objectBoundingBox`, que se mide sobre la **TINTA**. No
     * son la misma caja —la de línea baja hasta el hueco de los descendentes—, así que copiar sus
     * paradas pone al pie de las letras un valor que en el suyo ya había decaído.
     * ⚠️ Medido en el borde inferior de la «J»: **α 0,377 contra 0,112**, y el doble de largo.
     */
    public function test_the_inner_veil_is_toned_down(): void
    {
        file_put_contents($this->tmp, $this->svg());
        [$codigo, $salida, $despues] = $this->correr('logo-sombra.php', ['#301002']);

        $this->assertSame(0, $codigo, "el guion ha fallado:\n{$salida}");
        $this->assertStringNotContainsString(
            'stop-opacity=".5"', $despues,
            'el velo sigue arrancando en .5: es la parada de la CAJA DE LÍNEA puesta al pie de la TINTA.',
        );
        $this->assertStringContainsString('stop-opacity=".14"', $despues);
        $this->assertStringNotContainsString(
            'offset=".33"', $despues,
            'el velo sigue llegando al 33 % de la tinta: en el mockup se apaga mucho antes.',
        );
    }

    /**
     * **Y el TONO se separa por palabra: frío bajo la fría, cálido bajo la cálida.**
     *
     * El export dejó **uno solo, el frío, para las dos**. Un velo azul sobre letras amarillas y
     * naranjas no las oscurece: las **desatura**. Es lo que el owner describió como «pierde color
     * vivo».
     * ⚠️ El color cálido **no vive en el producto**: es un dato de la marca (`DECISIONES #1`), y sin
     * él el guion aplica solo la corrección de fuerza, que sí es general.
     */
    public function test_the_warm_veil_is_only_added_when_its_colour_is_given(): void
    {
        file_put_contents($this->tmp, $this->svg());
        [, , $sinColor] = $this->correr('logo-sombra.php');

        $this->assertStringNotContainsString(
            'sombraTextoCalido', $sinColor,
            'el guion se ha inventado un color de marca que nadie le ha dado.',
        );

        file_put_contents($this->tmp, $this->svg());
        [$codigo, $salida, $conColor] = $this->correr('logo-sombra.php', ['#301002']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('id="sombraTextoCalido"', $conColor);
        $this->assertStringContainsString('#301002', $conColor);
        $this->assertStringContainsString(
            '<use href="#u2" fill="url(#sombraTextoCalido)">', $conColor,
            'la palabra CÁLIDA sigue leyendo el velo frío.',
        );
        $this->assertStringContainsString(
            '<use href="#u1" fill="url(#sombraTexto)">', $conColor,
            'la palabra FRÍA ha pasado al velo cálido, y ahí el mockup usa el frío.',
        );
    }

    /** **Un color que no es un color no entra**: el argumento acaba dentro de un `stop-color`. */
    public function test_the_warm_veil_colour_is_validated(): void
    {
        file_put_contents($this->tmp, $this->svg());
        $antes = (string) file_get_contents($this->tmp);

        [$codigo, , $despues] = $this->correr('logo-sombra.php', ['"><script>alert(1)</script>']);

        $this->assertNotSame(0, $codigo, 'ha aceptado algo que no es un `#RRGGBB`.');
        $this->assertSame($antes, $despues);
    }

    // ══ 2 · LA LETRA ═══════════════════════════════════════════════════════════════════════════

    /**
     * **La letra se restituye en sus DOS apariciones.**
     *
     * ⚠️⚠️ La misma geometría vive en `#u1` —que alimenta contorno, tinta y blanco vía `<use>`— y en
     * el `<path>` del RELLENO de color. **Arreglar solo la primera deja la parte restituida en
     * BLANCO**, porque el relleno no llega hasta ahí. Se descubrió midiendo, con el arreglo ya dado
     * por bueno: la forma era correcta y el color no.
     */
    public function test_the_letter_is_restored_in_both_places(): void
    {
        file_put_contents($this->tmp, $this->svg());
        file_put_contents($this->tmp.'.path', 'M40 0L52 0L52 12ZM42 2L44 2L44 4Z');

        [$codigo, $salida, $despues] = $this->correr('logo-letra-a.php', [$this->tmp.'.path']);

        $this->assertSame(0, $codigo, "el guion ha fallado:\n{$salida}");
        $this->assertSame(
            2,
            substr_count($despues, 'M40 0L52 0L52 12ZM42 2L44 2L44 4Z'),
            'la letra no está en sus DOS apariciones: con una sola, la parte restituida sale BLANCA.',
        );
        $this->assertStringNotContainsString(
            'M40 0L50 0L50 10Z', $despues,
            'ha quedado la letra mordida en algún sitio.',
        );
        $this->assertStringContainsString(
            '<path id="u2" d="M0 0L9 0L9 9Z"', $despues,
            'el guion ha tocado la otra palabra.',
        );
    }

    /**
     * **Si no encuentra el relleno, NO toca nada.**
     *
     * Media reparación es peor que ninguna: deja la letra a medio pintar y **no falla nada**. Es
     * exactamente el modo en que este arreglo estuvo mal la primera vez.
     */
    public function test_the_letter_repair_refuses_a_half_job(): void
    {
        file_put_contents($this->tmp, $this->svg(relleno: 'M0 0L1 0L1 1Z'));   // el relleno ya no copia la letra
        file_put_contents($this->tmp.'.path', 'M40 0L52 0L52 12ZM42 2L44 2L44 4Z');
        $antes = (string) file_get_contents($this->tmp);

        [$codigo, $salida, $despues] = $this->correr('logo-letra-a.php', [$this->tmp.'.path']);

        $this->assertNotSame(0, $codigo, 'el guion ha seguido adelante sin el `<path>` de relleno.');
        $this->assertStringContainsString('BLANCO', $salida, 'el mensaje no dice por qué se para.');
        $this->assertSame($antes, $despues, 'ha escrito el fichero pese a abortar.');
    }

    /** **Y es idempotente**: el mismo motivo que su hermano, y aquí además el `--delete` del despliegue. */
    public function test_the_letter_repair_is_idempotent(): void
    {
        file_put_contents($this->tmp, $this->svg());
        file_put_contents($this->tmp.'.path', 'M40 0L52 0L52 12ZM42 2L44 2L44 4Z');
        $this->correr('logo-letra-a.php', [$this->tmp.'.path']);
        $primera = (string) file_get_contents($this->tmp);

        [$codigo, $salida, $segunda] = $this->correr('logo-letra-a.php', [$this->tmp.'.path']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame($primera, $segunda, 'el segundo pase ha vuelto a cambiar el fichero.');
    }

    // ══ 3 · LA GEOMETRÍA ES DEL CLIENTE, NO DEL PRODUCTO ═══════════════════════════════════════

    /**
     * **La letra restituida es del CLIENTE** (`DECISIONES #1`), así que sigue las tres piezas del
     * paquete de marca: no se versiona, se pasa al guion, y `deploy.sh` la excluye del `--delete`.
     *
     * ⚠️ Sin la primera, la geometría de un cliente acabaría en el repo del producto — la misma fuga
     * que `#139` cerró en el CSS. Sin la tercera, el primer despliegue se la lleva y la reparación
     * deja de poder rehacerse en el servidor.
     */
    public function test_the_restored_letter_is_a_brand_file(): void
    {
        $this->assertMatchesRegularExpression(
            '#^/public/img/client-logo-a\.path\s*$#m',
            (string) file_get_contents(base_path('.gitignore')),
            '`.gitignore` no ignora la letra restituida: es geometría de la marca de un cliente y '.
            'este repo es el PRODUCTO (`DECISIONES #1`).',
        );

        preg_match(
            '/RSYNC_EXCLUDES=\((.*?)^\)/ms',
            (string) file_get_contents(base_path('scripts/deploy.sh')),
            $bloque,
        );

        $this->assertNotEmpty($bloque, 'no se ha encontrado el array `RSYNC_EXCLUDES=( … )`.');

        $this->assertMatchesRegularExpression(
            "#^\s*--exclude='/public/img/client-logo-a\.path'#m",
            $bloque[1],
            '`deploy.sh` no la excluye del `rsync --delete` (o la línea está comentada, que es lo mismo).',
        );
    }

    /**
     * **La guarda de la guarda**: las dos aserciones de arriba caen con su mutación.
     *
     * Es la lección que `ClientThemePackageTest` pagó: una exclusión COMENTADA sigue conteniendo la
     * subcadena, así que un `assertStringContainsString` pasa en verde con el despliegue borrando el
     * fichero. Se comprueba sobre el texto, sin tocar nada real.
     */
    public function test_those_two_assertions_would_catch_a_commented_line(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '#^/public/img/client-logo-a\.path\s*$#m',
            "# /public/img/client-logo-a.path\n",
        );
        $this->assertDoesNotMatchRegularExpression(
            "#^\s*--exclude='/public/img/client-logo-a\.path'#m",
            "    # --exclude='/public/img/client-logo-a.path'\n",
        );
    }
}
