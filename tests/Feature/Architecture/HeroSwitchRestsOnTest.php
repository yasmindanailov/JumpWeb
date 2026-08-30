<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **EL INTERRUPTOR DEL TITULAR PARA, Y PARA ENCENDIDO** (`DECISIONES #280`).
 *
 * `[DECIDIDO owner, 2026-08-30]`: «que se pare tras unos ciclos». ⚠️⚠️ **Y no era cambiar
 * `infinite` por un número.** El ciclo del artboard `6d` va *apagado → salto → encendido → vuelta a
 * apagado*, así que un número ENTERO de iteraciones termina en el fotograma apagado; y hasta esta
 * tanda el estado encendido —pista verde, bulbo desplazado, rótulo visible— **existía solo dentro
 * del bloque `@media (prefers-reduced-motion: reduce)`**, o sea que el reposo de la pieza estaba
 * escrito como una concesión de accesibilidad y fuera de ella no existía.
 *
 * ▶ **Medido antes de tocar nada**, acotando las iteraciones sobre el CSS de entonces: la pista
 * terminaba transparente con el borde gris **y el rótulo al 100 %**. No es «se queda apagado»: es
 * la palabra ON encendida sobre un interruptor apagado — el defecto contra el que `#254` ya había
 * avisado, por la puerta contraria.
 *
 * ▶ **Lo que esta guarda vigila es el MECANISMO, no los números.** No asevera «0.6», ni «38 %», ni
 * el nombre de un color: lee los `@keyframes` REALES, calcula dónde cae el corte y comprueba que
 * ahí el valor animado **es constante y coincide con el reposo declarado**. Mover una parada del
 * artboard, cambiar la fracción o devolver el reposo al bloque de accesibilidad la pone roja.
 * Es el mismo criterio que `#251` se pagó: *aseverar el texto literal ata la guarda a una
 * implementación*.
 */
class HeroSwitchRestsOnTest extends TestCase
{
    /**
     * Las tres piezas: selector, `@keyframes` y las propiedades que su ciclo mueve.
     *
     * ⚠️ La lista de propiedades no se adivina: es la que hay que comparar contra el reposo. Si un
     * día el ciclo moviera una cuarta, habría que añadirla aquí — y el caso
     * `test_the_cycle_moves_nothing_this_guard_ignores` se pone rojo si no se hace.
     */
    private const PIEZAS = [
        '.hero__switch-sw' => ['heroSwitchTrack', ['background', 'border-color']],
        '.hero__switch-knob' => ['heroSwitchKnob', ['transform', 'background']],
        '.hero__switch-on' => ['heroSwitchLabel', ['opacity']],
    ];

    public function test_the_switch_is_no_longer_an_endless_loop(): void
    {
        $css = $this->css();

        foreach (array_keys(self::PIEZAS) as $selector) {
            $this->assertStringNotContainsString(
                'infinite', $this->reglaBase($css, $selector),
                "`{$selector}` vuelve a ser un bucle sin fin.\n".
                "▶ `[DECIDIDO owner, 2026-08-30]`: el interruptor para tras `--switch-cycles` ciclos.\n".
                '⚠️ Y `#279` lo midió: entre esto y la invitación del CTA, el techo de «dos elementos '.
                'animándose en pantalla» que escribe su propio artboard estaba gastado de salida.',
            );
        }
    }

    public function test_the_number_of_cycles_belongs_to_the_installation(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/:root\s*\{[^{}]*--switch-cycles\s*:\s*\d/s', $css,
            "`--switch-cycles` tiene que estar declarada en `:root`.\n".
            '▶ Cuántas veces salta el interruptor es de la INSTALACIÓN: sin token en la raíz, '.
            '`client.css` no puede calmarlo sin reescribir la regla del producto.',
        );

        $this->assertCount(
            1, $this->declaraciones($css, '--switch-runs'),
            "`--switch-runs` tiene que declararse UNA vez.\n".
            '▶ Es de donde sale el punto de corte de las tres piezas: repartida en varias, dos '.
            'podrían parar en fotogramas distintos y el desfase se vería.',
        );

        foreach (array_keys(self::PIEZAS) as $selector) {
            $this->assertMatchesRegularExpression(
                '/animation-iteration-count\s*:\s*var\(\s*--switch-runs\s*\)/',
                $this->reglaBase($css, $selector),
                "`{$selector}` no lee el número de iteraciones de `--switch-runs`.",
            );
        }
    }

    /**
     * **EL CASO CENTRAL: donde para es donde descansa.**
     *
     * Se calcula el fotograma del corte a partir de `--switch-runs`, se comprueba que en ese punto
     * el `@keyframes` está en un tramo CONSTANTE —no a mitad de una transición, donde el valor
     * dependería de la curva— y se compara con lo que declara la regla base.
     *
     * ⚠️ Si las dos cosas no coinciden, el interruptor **da un respingo al terminar** y no falla
     * nada: ni la suite, ni una captura, ni el `docs-check`. Solo se ve mirándolo 9 segundos.
     */
    public function test_it_stops_exactly_where_it_rests(): void
    {
        $css = $this->css();
        $corte = $this->corte($css);

        foreach (self::PIEZAS as $selector => [$nombre, $propiedades]) {
            $fotogramas = $this->keyframes($css, $nombre);
            $base = $this->reglaBase($css, $selector);

            foreach ($propiedades as $propiedad) {
                $paradas = $fotogramas[$propiedad] ?? [];

                $this->assertNotEmpty(
                    $paradas,
                    "`@keyframes {$nombre}` no mueve `{$propiedad}`: la guarda estaría comparando el aire.",
                );

                [$antes, $despues] = $this->tramo($paradas, $corte);

                $this->assertSame(
                    $this->normaliza($antes[1]), $this->normaliza($despues[1]),
                    sprintf(
                        'el corte cae en el %.1f %% del ciclo y ahí `%s` de `%s` está EN MOVIMIENTO '.
                        "(%s en el %s %%, %s en el %s %%).\n".
                        '▶ El corte tiene que caer dentro de un tramo constante: si cae en una '.
                        "transición, el valor depende de la curva y el reposo nunca va a coincidir.\n".
                        '⚠️ Mueve la fracción de `--switch-runs`, no los fotogramas del artboard.',
                        $corte, $propiedad, $nombre, $antes[1], $antes[0], $despues[1], $despues[0],
                    ),
                );

                $this->assertSame(
                    $this->normaliza($antes[1]), $this->normaliza($this->valorBase($base, $propiedad)),
                    sprintf(
                        "`%s` para con `%s: %s` pero descansa con `%s`.\n".
                        '▶ Al terminar la animación —`fill` es `none`— la pieza vuelve a su regla base '.
                        "de golpe: si los dos valores no son el mismo, eso es un respingo.\n".
                        '⚠️ Medido en navegador el 2026-08-30: con los dos iguales, **0 px de salto**.',
                        $selector, $propiedad, $antes[1], $this->valorBase($base, $propiedad) ?? '(nada)',
                    ),
                );
            }
        }
    }

    /**
     * **El reposo NO puede volver a vivir dentro del bloque de accesibilidad.**
     *
     * Es el defecto original, y su forma es engañosa: allí dentro el interruptor se ve perfecto
     * —encendido y quieto— así que cualquier revisión con movimiento reducido lo da por bueno.
     */
    public function test_the_resting_state_is_not_an_accessibility_concession(): void
    {
        $reducido = $this->bloqueDeMovimientoReducido($this->css());

        $this->assertNotSame('', trim($reducido), 'no hay bloque de movimiento reducido: la guarda no vigilaría nada.');

        // ⚠️ **La primera versión de esto salió ROJA con el producto sano**, y por una razón que
        // vale para cualquier lector de CSS: buscaba `selector … {` con un `[^{]*` en medio, así
        // que ante una lista `a,\n b,\n c { … }` se tragaba las tres y devolvía UNA. Se lee la
        // regla entera y se parte la lista por comas.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $reducido, $hit, PREG_SET_ORDER);

        $vistos = [];

        foreach ($hit as [, $lista, $cuerpo]) {
            $suyos = array_values(array_filter(
                array_map('trim', preg_split('/\s*,\s*/', trim($lista)) ?: []),
                fn (string $s): bool => str_starts_with($s, '.hero__switch-'),
            ));

            if ($suyos === []) {
                continue;
            }

            $selector = implode(', ', $suyos);
            $vistos = [...$vistos, ...$suyos];

            foreach (preg_split('/;/', $cuerpo) ?: [] as $declaracion) {
                if (trim($declaracion) === '') {
                    continue;
                }

                $propiedad = trim(explode(':', $declaracion, 2)[0]);

                $this->assertStringStartsWith(
                    'animation', $propiedad,
                    "con movimiento reducido, `{$selector}` declara `{$propiedad}`.\n".
                    "▶ Ahí dentro solo cabe lo que de verdad es una concesión: que no se mueva.\n".
                    '⚠️⚠️ El estado encendido vivió AQUÍ hasta `#280`, y por eso acotar las iteraciones '.
                    'dejaba el interruptor apagado con la palabra ON encima. Un reposo escrito dentro '.
                    'de una excepción no es el reposo de la pieza: es el de una minoría de visitantes.',
                );
            }
        }

        // ⚠️ Sin esto la guarda pasaría en verde con el bloque VACÍO, que es el otro extremo del
        // mismo fallo: la pieza seguiría animándose con movimiento reducido.
        $this->assertSame(
            array_keys(self::PIEZAS), $vistos,
            'el bloque de movimiento reducido no cubre exactamente las tres piezas del interruptor.',
        );
    }

    /** Las propiedades que el ciclo mueve son las que esta guarda compara: ni una más. */
    public function test_the_cycle_moves_nothing_this_guard_ignores(): void
    {
        $css = $this->css();

        foreach (self::PIEZAS as $selector => [$nombre, $propiedades]) {
            $this->assertSame(
                $propiedades, array_keys($this->keyframes($css, $nombre)),
                "`@keyframes {$nombre}` mueve otras propiedades que las declaradas en `PIEZAS`.\n".
                '▶ Una propiedad que el ciclo mueve y esta guarda no compara puede parar en un valor '.
                'distinto del reposo sin que nadie se entere.',
            );
        }
    }

    // ── El instrumento ────────────────────────────────────────────────────────────────────────

    /** El CSS con los comentarios BLANQUEADOS conservando la longitud, como en `MotionScaleTest`. */
    private function css(): string
    {
        return (string) preg_replace_callback(
            '#/\*.*?\*/#s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            (string) file_get_contents(public_path('css/site.css')),
        );
    }

    /** El porcentaje del ciclo en el que se corta la última iteración, sacado de `--switch-runs`. */
    private function corte(string $css): float
    {
        $declaracion = $this->declaraciones($css, '--switch-runs')[0]
            ?? $this->fail('no se declara `--switch-runs`.');

        // `calc(var(--switch-cycles) - 1 + 0.6)`: la fracción es lo que sobra de la parte entera.
        preg_match_all('/(?<![\w.])(\d+(?:\.\d+)?)(?![\w.])/', $declaracion, $m);

        $fraccion = 0.0;

        foreach ($m[1] as $numero) {
            $fraccion += fmod((float) $numero, 1.0);
        }

        $this->assertGreaterThan(0.0, $fraccion,
            "`--switch-runs` es un número ENTERO de ciclos ({$declaracion}).\n".
            '▶ Un ciclo entero termina en el fotograma APAGADO: hay que truncar la última iteración '.
            'dentro del tramo encendido para que el final coincida con el reposo.');
        $this->assertLessThan(1.0, $fraccion, "`--switch-runs` no deja una fracción legible ({$declaracion}).");

        return $fraccion * 100;
    }

    /** Los valores de una `custom property`, tal como se declaran. */
    private function declaraciones(string $css, string $token): array
    {
        preg_match_all('/'.preg_quote($token, '/').'\s*:\s*([^;{}]+)/', $css, $m);

        return array_map('trim', $m[1]);
    }

    /** El cuerpo de las reglas de un selector FUERA de cualquier `@media`. */
    private function reglaBase(string $css, string $selector): string
    {
        $fuera = (string) preg_replace_callback(
            '/@media[^{]*\{(?:[^{}]|\{[^{}]*\})*\}/s',
            fn (array $m): string => str_repeat(' ', strlen($m[0])),
            $css,
        );

        preg_match_all(
            '/(?:^|[{}])\s*'.preg_quote($selector, '/').'\s*\{([^{}]*)\}/m',
            $fuera, $hit,
        );

        $this->assertNotEmpty($hit[1], "no hay ninguna regla base `{$selector}`: la guarda no vigilaría nada.");

        return implode(' ', $hit[1]);
    }

    /** El bloque de `prefers-reduced-motion` que toca al interruptor. */
    private function bloqueDeMovimientoReducido(string $css): string
    {
        preg_match_all(
            '/@media[^{]*prefers-reduced-motion:\s*reduce[^{]*\{((?:[^{}]|\{[^{}]*\})*)\}/s',
            $css, $m,
        );

        $suyos = array_filter($m[1] ?? [], fn (string $b): bool => str_contains($b, '.hero__switch-'));

        return implode("\n", $suyos);
    }

    /**
     * Un `@keyframes` como `propiedad => [[porcentaje, valor], …]`, ordenado.
     *
     * ⚠️ Una parada puede llevar varios porcentajes (`38%, 82% { … }`) y cada uno vale por sí solo:
     * son los extremos del tramo, y confundirlos con uno haría creer que un tramo constante es una
     * transición.
     */
    private function keyframes(string $css, string $nombre): array
    {
        preg_match(
            '/@keyframes\s+'.preg_quote($nombre, '/').'\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s',
            $css, $m,
        );

        $this->assertNotEmpty($m[1] ?? null, "no existe `@keyframes {$nombre}`.");

        preg_match_all('/([\d.%,\s]+?)\s*\{([^{}]*)\}/s', $m[1], $bloques, PREG_SET_ORDER);

        $out = [];

        foreach ($bloques as [, $paradas, $cuerpo]) {
            foreach (preg_split('/\s*,\s*/', trim($paradas)) ?: [] as $parada) {
                foreach (preg_split('/;/', $cuerpo) ?: [] as $declaracion) {
                    if (! str_contains($declaracion, ':')) {
                        continue;
                    }

                    [$propiedad, $valor] = explode(':', $declaracion, 2);
                    $out[trim($propiedad)][] = [(float) rtrim(trim($parada), '%'), trim($valor)];
                }
            }
        }

        foreach ($out as &$paradas) {
            usort($paradas, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        }

        return $out;
    }

    /** Las dos paradas que rodean a un punto del ciclo. */
    private function tramo(array $paradas, float $punto): array
    {
        $antes = $paradas[0];
        $despues = $paradas[count($paradas) - 1];

        foreach ($paradas as $parada) {
            if ($parada[0] <= $punto) {
                $antes = $parada;
            }

            if ($parada[0] >= $punto) {
                $despues = $parada;
                break;
            }
        }

        return [$antes, $despues];
    }

    /**
     * El valor de una propiedad en el cuerpo de una regla, mirando también dentro de los atajos.
     *
     * ⚠️ `border-color` no se escribe: viene dentro de `border: <ancho> solid <color>`, y el ancho
     * es un `calc()` con espacios dentro. Partir por espacios a pelo daría `0.225)` como color.
     */
    private function valorBase(string $cuerpo, string $propiedad): ?string
    {
        if (preg_match('/(?<![-\w])'.preg_quote($propiedad, '/').'\s*:\s*([^;{}]+)/', $cuerpo, $m)) {
            return trim($m[1]);
        }

        if ($propiedad === 'border-color' && preg_match('/(?<![-\w])border\s*:\s*([^;{}]+)/', $cuerpo, $m)) {
            $partes = $this->partesDeNivelCero(trim($m[1]));

            return end($partes) ?: null;
        }

        return null;
    }

    /** Parte un valor CSS por los espacios de NIVEL CERO, sin entrar en los paréntesis. */
    private function partesDeNivelCero(string $valor): array
    {
        $partes = [];
        $actual = '';
        $hondo = 0;

        foreach (str_split($valor) as $letra) {
            $hondo += (int) ($letra === '(') - (int) ($letra === ')');

            if ($hondo === 0 && preg_match('/\s/', $letra)) {
                if ($actual !== '') {
                    $partes[] = $actual;
                    $actual = '';
                }

                continue;
            }

            $actual .= $letra;
        }

        if ($actual !== '') {
            $partes[] = $actual;
        }

        return $partes;
    }

    /**
     * Normaliza un valor para compararlo: espacios y **transformaciones IDENTIDAD**.
     *
     * ⚠️ En el tramo encendido el artboard escribe `translateX(…) scaleX(1)` —devuelve el bulbo a
     * su ancho tras el estirón— y la regla base no necesita decirlo. Son la MISMA matriz: medido en
     * navegador, las dos dan `matrix(1, 0, 0, 1, 48.0908, 0)`. Comparar el texto en crudo pondría
     * roja la guarda con el producto sano.
     */
    private function normaliza(?string $valor): string
    {
        $valor = (string) preg_replace('/\s+/', ' ', trim((string) $valor));
        $valor = (string) preg_replace('/\s*(?:scaleX|scaleY|scale)\(\s*1\s*\)/', '', $valor);
        $valor = (string) preg_replace('/\s*translate[XY]?\(\s*0(?:px)?\s*\)/', '', $valor);

        return trim($valor) === '' ? 'none' : trim($valor);
    }
}
