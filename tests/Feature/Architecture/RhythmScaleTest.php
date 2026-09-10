<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **EL RITMO VERTICAL — el aire sale de un token, y los pares se declaran juntos**
 * (`docs/specs/rediseno-desde-canvas.md` §5.3 · `#470`).
 *
 * El sistema de diseño piensa el ritmo como **aire ENTRE piezas** y con **un valor por
 * superficie** (`ritmo.seccion` y `ritmo.seccionMovil`). El CSS pensaba en relleno por sección y
 * con dos literales, así que el aire real era el doble del número escrito y nadie podía cambiarlo
 * sin tocar cuatro reglas.
 *
 * ⚠️⚠️ **Lo que esta guarda existe para impedir es un fallo que NO ROMPE NADA**: que un paquete de
 * instalación sobreescriba la mitad del par. Con `--sec-air` puesto y `--sec-air-mobile` sin poner,
 * el escritorio se mueve y el móvil se queda en el valor del producto — y no falla, ni avisa, ni se
 * ve en la máquina de quien lo escribió si mira a 1280. Es la familia de `#434` («el par lo declara
 * quien declara el color: no se deriva») y la de `#314` (una regla de móvil que no hace nada).
 *
 * ⚠️ La comprobación del paquete es CONDICIONAL a propósito, por lo mismo que en `ShapeScaleTest`:
 * su sujeto es `public/css/client.css`, que está gitignorado (`DECISIONES #1`, y la trampa de
 * `#468`). Sin paquete no hay par que comprobar. No la deja muerta: en este proyecto **el CI es el
 * gate local**, y la máquina que empuja tiene su paquete puesto.
 */
class RhythmScaleTest extends TestCase
{
    use ReadsSiteStylesheets;

    /**
     * Los dos escalones del aire de sección existen en el producto y son un PAR.
     *
     * Que estén los dos es lo que permite que un paquete cambie uno sin heredar el otro por
     * accidente — y es la condición para que la guarda de abajo signifique algo.
     */
    public function test_the_section_air_is_a_pair_of_tokens_in_the_product(): void
    {
        $producto = $this->productSheets();

        foreach (['--sec-air', '--sec-air-mobile'] as $token) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').'\s*:\s*\d+px/',
                $producto,
                "`{$token}` no está declarado en las hojas VERSIONADAS del producto. Sin él, la ".
                'regla que lo usa cae al valor inicial y la sección pierde su aire en silencio.',
            );
        }
    }

    /** El aire de `.section` sale del token: ningún literal vertical vuelve a esa regla. */
    public function test_the_section_rule_reads_the_token_and_not_a_literal(): void
    {
        $producto = $this->productSheets();

        preg_match_all('/^\.section\s*\{([^}]*)\}/m', $producto, $m);

        $this->assertNotEmpty($m[1], 'no se ha encontrado ninguna regla `.section`: el escáner mira al vacío.');

        foreach ($m[1] as $cuerpo) {
            if (! str_contains($cuerpo, 'padding')) {
                continue;
            }

            $this->assertStringContainsString(
                'var(--sec-air',
                $cuerpo,
                'una regla `.section` escribe su relleno vertical a mano: «'.trim($cuerpo).'». El '.
                'aire vive en `--sec-air` / `--sec-air-mobile` y solo ahí — un literal aquí es un '.
                'segundo ritmo que nadie decidió.',
            );
        }
    }

    /**
     * **DOS SECCIONES SE SEPARAN CON AIRE, NUNCA CON UNA LÍNEA** (`#486`, lo vio el owner: *«quita
     * la barra fina de debajo del cta, donde empieza la siguiente sección»*).
     *
     * Las reglas duras del sistema lo escriben con esas palabras: *«Aire entre secciones 144 en
     * escritorio y 96 en móvil, **uniforme de arriba abajo**»*. No declaran ningún divisor, y
     * ninguno de los cinco artboards de sección dibuja uno.
     *
     * ⚠️⚠️ **Es un caso INVERTIDO y hace falta porque el filete no rompía nada.** Estuvo ahí desde el
     * diseño anterior como `.section + .section { border-top: 1px solid var(--line) }`, se veía en
     * las siete costuras de la portada y **ninguna guarda lo miraba**: un divisor de más no falla, no
     * avisa y solo se nota comparando con el dibujo. El día que alguien lo devuelva «para separar
     * mejor», esto se pone rojo y le obliga a decir por qué.
     *
     * ⚠️ Se mira el SELECTOR ADYACENTE, que es el mecanismo por el que un divisor entre secciones se
     * escribe de verdad: un `border-top` en `.section` a secas lo pondría también en la primera, que
     * es un defecto distinto y se ve al instante.
     *
     * ⚠️⚠️ **La primera versión de este caso solo aseveraba DENTRO de un bucle y salió «risky» con el
     * producto sano**: al retirar la regla, el bucle se quedó vacío y el caso dejó de vigilar sin
     * ponerse rojo. Es literalmente la trampa que `#482` dejó escrita — *un caso que solo asevera
     * dentro de un bucle deja de vigilar en cuanto el bucle se vacía, y lo hace en verde*. Ahora la
     * aserción es una y corre siempre.
     */
    public function test_two_sections_are_separated_by_air_and_never_by_a_line(): void
    {
        $producto = $this->productSheets();

        // Guarda de la guarda: si el escáner no ve la hoja, lo de abajo pasa mirando el vacío.
        $this->assertStringContainsString('.section {', $producto, 'no se encuentra la regla `.section`: el escáner mira al vacío.');

        preg_match_all('/\.section\s*\+\s*\.section[^{]*\{([^}]*)\}/m', $producto, $m);

        $conDivisor = array_values(array_filter(
            $m[1],
            static fn (string $cuerpo): bool => str_contains($cuerpo, 'border'),
        ));

        $this->assertSame(
            [], $conDivisor,
            'Ha vuelto un divisor entre secciones: «.section + .section { '.implode(' | ', $conDivisor)." }».\n".
            "▶ El sistema separa secciones **solo con aire** —144 en escritorio y 96 en móvil,\n".
            "  uniforme de arriba abajo— y no declara ningún filete. El de antes se retiró en\n".
            "  `#486` porque el owner lo vio bajo el CTA de la sección 05.\n".
            '▶ Si de verdad hace falta, dilo aquí con su motivo y quita esta comprobación.',
        );
    }

    /**
     * **Si el paquete de instalación toca un escalón del par, toca los dos.**
     *
     * Éste es el caso que motiva el fichero. Ver el aviso del docblock de la clase.
     */
    public function test_an_installation_package_that_overrides_one_step_overrides_both(): void
    {
        $paquete = base_path('public/css/client.css');

        if (! is_file($paquete)) {
            $this->markTestSkipped(
                'sin paquete de instalación: en un clon limpio no existe `public/css/client.css` '.
                'y no hay par que comprobar (`#468`).',
            );
        }

        $css = file_get_contents($paquete) ?: '';

        $escritorio = str_contains($css, '--sec-air:');
        $movil = str_contains($css, '--sec-air-mobile:');

        $this->assertSame(
            $escritorio,
            $movil,
            'el paquete de instalación declara solo la mitad del aire de sección ('.
            ($escritorio ? '`--sec-air` sin `--sec-air-mobile`' : '`--sec-air-mobile` sin `--sec-air`').
            '). El escalón que falta se queda con el valor del PRODUCTO, así que esa instalación '.
            'tiene dos ritmos distintos según el ancho — y no falla, ni avisa.',
        );
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
            if (str_ends_with($sheet, 'client.css')) {
                continue;
            }

            $out .= "\n".$css;
        }

        return $out;
    }
}
