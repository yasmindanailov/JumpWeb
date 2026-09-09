<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **LA ESCALA TIPOGRÁFICA — diez niveles con nombre, y cada uno vale lo que el sistema dice**
 * (`docs/specs/rediseno-desde-canvas.md` §5.3 · `DECISIONES #474`, carril de diseño, Fase 1 · T1g).
 *
 * El sistema del 2.º cliente nombra el texto por su PAPEL —Display XL, Entradilla, Cuerpo S…— con
 * una talla móvil y una de escritorio cada uno. El producto tenía en su lugar **un token por
 * píxel** (`--fs-9`…`--fs-22`), que es lo que el propio canvas ficha como su grieta «00 bis»:
 * *«las dos escalas no son escalas: son un token por píxel»*. Una lista de tallas no impide que
 * alguien estrene el escalón 19; una escala con nombres sí, porque el 19 no tiene papel.
 *
 * ▶ **Lo que vigila, y por qué se puede vigilar SIN NAVEGADOR.** Cada nivel es un `clamp` que
 * interpola entre las dos tallas del canvas sobre la recta 390 → 1024 (`reticula.referencia` y
 * `reticula.puntos.escritorio`). Eso lo convierte en una propiedad ARITMÉTICA: a 390 px el nivel
 * tiene que valer exactamente su talla móvil y a 1024 la de escritorio. Aquí se evalúa el `clamp`
 * a los dos anchos y se compara con la tabla del canvas, que vive abajo como constante.
 *
 * ⚠️ **Por qué la aritmética y no una captura**: cuando se escribió esto **no había navegador
 * instalado** —ni en el contenedor ni en el host— y `TouchTargetTest` ya avisa en su propio
 * docblock de que no mide píxeles. Una guarda que no puede ejecutarse no es una red. La pasada de
 * navegador está planificada una sola vez al final de la Fase 1 (spec §5.ter); ésta cubre mientras
 * tanto lo único que se puede afirmar con certeza: que los valores declarados son los del sistema.
 *
 * ⚠️⚠️ **Los diez nacen SIN CONSUMIDOR y está decidido** (`[DECIDIDO owner]`). Al medirlo resultó
 * que ninguna regla del producto coincide con su nivel en talla Y en papel a la vez, así que no
 * había una sola sustitución que no afirmara un rol falso o moviera geometría a ciegas. **No es el
 * caso que `#287` prohíbe ni el `barra: 1400` que `#473` rechazó**: aquella duración no tenía
 * consumidor *ni lo tendrá*, y estos diez lo tienen fechado en la Fase 2 — la escala va antes de
 * vestir las secciones, igual que fueron antes la columna, el aire, los radios y el táctil.
 * ▶ Por eso esta guarda vigila la ESCALA (que exista y valga lo que debe) y no su uso: el uso lo
 * vigilará la Fase 2, cuando lo haya.
 */
class TypeScaleTest extends TestCase
{
    use ReadsSiteStylesheets;

    /**
     * La tabla del canvas (`tokens-pjp.js` v1.10, `tipo.escala`): token => [móvil, escritorio].
     *
     * ⚠️ **Esto es la FUENTE, no una copia de conveniencia.** Si el canvas publica otra talla, se
     * cambia aquí y el CSS detrás — nunca al revés.
     */
    private const NIVELES = [
        '--fs-display-xl' => [42, 76],
        '--fs-display-l' => [34, 52],
        '--fs-title' => [26, 36],
        '--fs-subtitle' => [22, 26],
        '--fs-lede' => [18, 21],
        '--fs-body' => [16, 17],
        '--fs-body-s' => [15, 15],
        '--fs-button' => [16, 16],
        '--fs-label' => [12, 12],
        '--fs-slogan' => [24, 30],
    ];

    /** Los dos anchos que el canvas declara como extremos de la recta. */
    private const MOVIL = 390;

    private const ESCRITORIO = 1024;

    /** El rango que el canvas declara aceptar: fuera de él manda el `clamp`, no el tramo. */
    private const MIN_ACEPTADO = 320;

    private const MAX_ACEPTADO = 1920;

    /**
     * **CONTROL del instrumento**: el escáner encuentra los diez niveles.
     *
     * Sin esto, un cambio de nombre o un `:root` movido dejaría todas las comprobaciones de abajo
     * recorriendo una lista vacía y pasando en verde. Es la trampa que este repo ha pagado ya
     * varias veces (`#295`, `#302`), y la que hace que un cero signifique algo.
     */
    public function test_the_scanner_finds_every_level(): void
    {
        $css = $this->productSheets();

        foreach (array_keys(self::NIVELES) as $token) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').'\s*:/',
                $css,
                "`{$token}` no está declarado en las hojas versionadas del producto: la escala del ".
                'sistema está incompleta y las demás comprobaciones no tendrían sujeto.',
            );
        }
    }

    /**
     * **Cada nivel vale su talla del canvas en los dos extremos de la recta.**
     *
     * Es la propiedad que sostiene toda la tanda: el `clamp` puede escribirse de muchas formas y
     * casi todas dan un número plausible; solo una da 42 a 390 px y 76 a 1024.
     */
    public function test_every_level_matches_the_canvas_at_both_ends(): void
    {
        $css = $this->productSheets();

        foreach (self::NIVELES as $token => [$movil, $escritorio]) {
            $valor = $this->declaredValue($css, $token);

            $this->assertEqualsWithDelta(
                $movil,
                $this->evaluate($valor, self::MOVIL),
                0.01,
                "`{$token}` no vale {$movil}px a ".self::MOVIL.'px de ancho, que es la talla móvil '.
                "que declara el canvas. Valor escrito: «{$valor}».",
            );

            $this->assertEqualsWithDelta(
                $escritorio,
                $this->evaluate($valor, self::ESCRITORIO),
                0.01,
                "`{$token}` no vale {$escritorio}px a ".self::ESCRITORIO.'px de ancho, que es la '.
                "talla de escritorio que declara el canvas. Valor escrito: «{$valor}».",
            );
        }
    }

    /**
     * **Fuera de la recta el nivel se QUEDA en su talla: ni encoge a 320 ni crece a 1920.**
     *
     * ⚠️⚠️ **Los extremos del `clamp` no los ve ninguna comprobación de dentro de la recta, y lo
     * dijo la mutación**: entre 390 y 1024 el valor lo decide el tramo interpolado, así que se puede
     * escribir un mínimo de 40 donde el canvas dice 42, o un máximo de 56 donde dice 52, y las tres
     * medidas de arriba salen verdes. Lo que cambia es lo que ve **un teléfono de 320 px y una
     * pantalla de 1920** — que es justo el rango que el canvas declara aceptar («se diseña a 390 y
     * se acepta de 320 a 1920»), y donde un Display XL desbocado se come la pantalla.
     * ▶ Ahí es donde los extremos son los únicos que mandan, y por eso se miden ahí.
     */
    public function test_every_level_is_capped_outside_the_line(): void
    {
        $css = $this->productSheets();

        foreach (self::NIVELES as $token => [$movil, $escritorio]) {
            $valor = $this->declaredValue($css, $token);

            $this->assertEqualsWithDelta(
                $movil,
                $this->evaluate($valor, self::MIN_ACEPTADO),
                0.01,
                "`{$token}` no se queda en {$movil}px a ".self::MIN_ACEPTADO.'px de ancho: por debajo '.
                'de la recta el nivel lo capa su mínimo, y el canvas acepta hasta 320.',
            );

            $this->assertEqualsWithDelta(
                $escritorio,
                $this->evaluate($valor, self::MAX_ACEPTADO),
                0.01,
                "`{$token}` no se queda en {$escritorio}px a ".self::MAX_ACEPTADO.'px de ancho: por '.
                'encima de la recta lo capa su máximo, o el texto sigue creciendo sin techo.',
            );
        }
    }

    /**
     * **A mitad de la recta, cada nivel vale la MEDIA de sus dos tallas.**
     *
     * ⚠️⚠️ **Sin esto, el tramo fluido no lo vigila nadie, y lo descubrió la mutación.** En los dos
     * extremos manda el `clamp`: si alguien estropea las constantes `A` y `B` de la interpolación,
     * a 390 el valor se capa contra el mínimo y a 1024 contra el máximo, así que la comprobación de
     * arriba **sale verde con el tramo intermedio torcido** — y ahí es donde viven casi todos los
     * anchos reales de un teléfono y de una tableta.
     * ▶ A 707 px (el punto medio de 390 → 1024) la interpolación lineal cae exactamente en la media
     * de las dos tallas, que es una propiedad que no depende de cómo se hayan escrito las
     * constantes.
     */
    public function test_every_level_interpolates_linearly_in_between(): void
    {
        $css = $this->productSheets();
        $medio = (int) ((self::MOVIL + self::ESCRITORIO) / 2);

        foreach (self::NIVELES as $token => [$movil, $escritorio]) {
            $this->assertEqualsWithDelta(
                ($movil + $escritorio) / 2,
                $this->evaluate($this->declaredValue($css, $token), $medio),
                0.02,
                "`{$token}` no interpola linealmente: a {$medio}px debería valer la media de ".
                "{$movil} y {$escritorio}. Los extremos pueden salir bien y el tramo de en medio ".
                'estar torcido, porque ahí el `clamp` ya no capa nada.',
            );
        }
    }

    /**
     * **Un nivel de talla única no lleva `clamp`.**
     *
     * Cuerpo S, Botón y Etiqueta valen lo mismo en las dos superficies. Un `clamp` de extremos
     * iguales daría el número correcto —así que ninguna otra comprobación lo vería— y a la vez
     * haría creer que ahí hay un tramo fluido que alguien puede «ajustar».
     */
    public function test_a_single_size_level_is_not_written_as_a_clamp(): void
    {
        $css = $this->productSheets();

        foreach (self::NIVELES as $token => [$movil, $escritorio]) {
            if ($movil !== $escritorio) {
                continue;
            }

            $this->assertStringNotContainsString(
                'clamp(',
                $this->declaredValue($css, $token),
                "`{$token}` vale {$movil}px en las dos superficies y está escrito con `clamp`: no ".
                'hay tramo que interpolar, y escribirlo así invita a tocar un fluido inexistente.',
            );
        }
    }

    /**
     * **Todos los niveles cuelgan de `--fs-unit`**, que es el mando de la instalación.
     *
     * La escala por píxel ya era multiplicativa por esta razón (`#437`). Si un nivel nuevo se
     * escribiera con un literal, esa instalación tendría una tipografía a la que su propio mando
     * no llega — y no falla: simplemente ese texto se queda quieto cuando todo lo demás se mueve.
     */
    public function test_every_level_hangs_from_the_installation_unit(): void
    {
        $css = $this->productSheets();

        foreach (array_keys(self::NIVELES) as $token) {
            $this->assertStringContainsString(
                'var(--fs-unit)',
                $this->declaredValue($css, $token),
                "`{$token}` no se deriva de `--fs-unit`: el mando tipográfico de la instalación no ".
                'llega a ese nivel y ese texto se queda quieto mientras el resto escala.',
            );
        }
    }

    /**
     * **La escala por PÍXEL sigue viva** (`[DECIDIDO owner]`: las dos conviven).
     *
     * Los `--fs-N` mueren superficie a superficie según se viste cada una, no de golpe. Retirarlos
     * antes de tiempo dejaría sin valor cientos de reglas — y un `calc()` sin su variable no es un
     * valor raro: es una declaración inválida que el navegador descarta.
     */
    public function test_the_per_pixel_scale_is_still_declared(): void
    {
        $css = $this->productSheets();

        foreach ([9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 20, 22] as $n) {
            $this->assertStringContainsString(
                "--fs-{$n}:",
                $css,
                "`--fs-{$n}` ha desaparecido. Las dos escalas conviven hasta que cada superficie se ".
                'vista (Fase 2 la web pública, Fase 4 el cajón, Fase 5 el post-form): retirar un '.
                'escalón antes deja sus reglas con un `calc()` sin variable, que el navegador tira.',
            );
        }
    }

    /** El valor declarado de un token en `:root`, tal cual se escribió. */
    private function declaredValue(string $css, string $token): string
    {
        preg_match('/'.preg_quote($token, '/').'\s*:\s*([^;]+);/', $css, $m);

        $this->assertNotEmpty($m, "no se ha podido leer el valor de `{$token}`: el localizador mira al vacío.");

        return trim($m[1]);
    }

    /**
     * Evalúa un valor de la escala a un ancho de viewport dado, en píxeles.
     *
     * Entiende las dos formas que la escala usa y ninguna más, a propósito: `calc(var(--fs-unit) *
     * N)` y `clamp(<mín>, calc(var(--fs-unit) * A + Bvw), <máx>)`. Cualquier otra forma revienta
     * aquí en vez de devolver un número plausible.
     */
    private function evaluate(string $valor, int $ancho): float
    {
        if (preg_match('/^calc\(var\(--fs-unit\)\s*\*\s*([\d.]+)\)$/', $valor, $m)) {
            return (float) $m[1];
        }

        $patron = '/^clamp\(\s*calc\(var\(--fs-unit\)\s*\*\s*([\d.]+)\)\s*,'
            .'\s*calc\(var\(--fs-unit\)\s*\*\s*([\d.]+)\s*\+\s*([\d.]+)vw\)\s*,'
            .'\s*calc\(var\(--fs-unit\)\s*\*\s*([\d.]+)\)\s*\)$/';

        $this->assertMatchesRegularExpression(
            $patron,
            $valor,
            "el valor «{$valor}» no tiene ninguna de las dos formas de la escala. Se evalúa lo que ".
            'se entiende: una forma nueva se declara aquí antes de escribirse en el CSS.',
        );

        preg_match($patron, $valor, $m);

        [$min, $a, $b, $max] = [(float) $m[1], (float) $m[2], (float) $m[3], (float) $m[4]];

        return max($min, min($a + $b * $ancho / 100, $max));
    }

    /**
     * Las hojas VERSIONADAS, concatenadas.
     *
     * ⚠️ Deja fuera `client.css` a propósito: es el paquete de un cliente y no viaja en el
     * producto, así que aseverar sobre él lo que se espera del producto es la trampa de `#468`.
     */
    private function productSheets(): string
    {
        $out = '';

        foreach ($this->siteSheets() as $sheet => $css) {
            if (str_ends_with((string) $sheet, 'client.css')) {
                continue;
            }

            $out .= "\n".$css;
        }

        return $out;
    }
}
