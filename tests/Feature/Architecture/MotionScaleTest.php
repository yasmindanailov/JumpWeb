<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **EL MOVIMIENTO SALE DE LA ESCALA, no de un número suelto** (tanda 2d, `DECISIONES #222`).
 *
 * Medido antes de esta tanda: **239 declaraciones de movimiento, 53 duraciones y 20 curvas**, y
 * **200 de los 220 usos de curva eran `ease`** — o sea que el 90 % del movimiento no elegía curva,
 * la heredaba del navegador. No era una escala con ruido: **no había ninguna**, igual que pasó con
 * las sombras en `#196`.
 *
 * ▶ La escala no se extrajo de lo que había: es la del 2.º cliente (`Microanimaciones PJP`), que sí
 * la tiene escrita —cuatro curvas y siete duraciones, cada una con su uso—, traducida a ROLES del
 * producto. Cada valor responde a «¿para qué sirve este tiempo?».
 *
 * ⚠️ **Si esto se relaja, un cliente que retemple su movimiento deja de mover lo que se le escape**
 * — y no falla nada: simplemente se queda con el tacto del primero. Es la misma razón que sostiene
 * `ShapeScaleTest` y `RawColourIsNotATokenTest`.
 */
class MotionScaleTest extends TestCase
{
    /** Las CUATRO curvas y las SIETE duraciones, con el uso que las justifica. */
    private const ESCALA = [
        '--ease-entra' => 'lo que APARECE: se pasa de largo y vuelve',
        '--ease-cae' => 'el rebote GRANDE: uno por pantalla',
        '--ease-sale' => 'cierres, foco y todo el hover: rápida y sin opinión',
        '--ease-bucle' => 'solo esperas: un easing parece un fallo de red',
        '--dur-toque' => 'hover de icono y de enlace',
        '--dur-sale' => 'botones, cierres, salidas: la mitad de su entrada',
        '--dur-estado' => 'cambio de estado dentro de un componente',
        '--dur-entra' => 'entrada simple de una tarjeta o un tile',
        '--dur-cae' => 'cascada, sello y confirmación: el TECHO',
        '--dur-salto' => 'la única excepción al techo: el salto del hero',
        '--dur-espera' => 'ciclo de espera',
    ];

    /**
     * **Los bucles AMBIENTALES, que no compiten con la escala.**
     *
     * No son tiempos de respuesta a un gesto: son decoración que respira. El sistema del cliente
     * los llamaría ruido —«banners que respiran, iconos que laten»— pero retirarlos es una
     * decisión de PRODUCTO, no de mecanismo, así que aquí solo se declaran, cada uno con su token
     * para que una instalación pueda calmarlos sin reescribir `@keyframes`.
     */
    private const TOKENS_AMBIENTALES = [
        '--dur-invite' => 'el latido que invita a descubrir el CTA doble (2c·7)',
        '--dur-switch' => 'el ciclo del interruptor del titular del hero — `ui/toggle-on` · 6d (#262)',
    ];

    /**
     * **Las animaciones de DIBUJO: largas a propósito y no son bucles.**
     *
     * Un talón que se rasga o un abanico que se despliega están CONTANDO algo, y a 420 ms no se
     * entiende. Por eso no entran en el techo de la escala.
     *
     * ⚠️ **Los bucles se reconocen por un criterio OBJETIVO —llevan `infinite`—, no por su
     * nombre**; estas tres, en cambio, hay que enumerarlas. La primera versión del mapeo buscaba
     * palabras en el CONTEXTO y **saltó once declaraciones que sí había que convertir**:
     * `--shadow-float` mencionado en un comentario vecino salvó a `.lang-dd__panel`, y el título
     * «reveal on scroll» salvó a `.ride-card`. Era la conversión a medias que `#196` documentó.
     *
     * ▶ Y **esta lista solo puede ENCOGER**, como las de `ShapeScaleTest`.
     */
    private const ANIMACIONES_DE_DIBUJO = [
        'tear-once' => 'el talón del billete se rasga al pasar el cursor: es un dibujo contando algo',
        'e2-fan' => 'el abanico de entradas del icono `e2`',
        'e5-deal' => 'el cupón del icono `e5`',
    ];

    public function test_the_scale_is_declared_once_and_reads_a_role(): void
    {
        $raiz = $this->hojas();

        foreach (self::ESCALA as $token => $uso) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($token, '/').'\s*:/', $raiz,
                "falta el token `{$token}` ({$uso}).\n".
                '▶ Sin declarar, `var()` cae a nada y la transición desaparece EN SILENCIO.',
            );
        }
    }

    /**
     * **Ninguna duración escrita a mano fuera de la escala.**
     *
     * ⚠️ **Incluye las `custom properties`, y ése era el escondite**: `--cta-pair-swap: 0.46s` y
     * `--cta-pair-in: 0.14s` vivían dentro de una variable, así que un inventario de `transition`
     * no las veía. Un literal metido en un token sigue siendo un literal.
     */
    public function test_no_duration_is_written_by_hand(): void
    {
        $sueltas = [];

        foreach ($this->declaraciones() as [$fichero, $selector, $valor]) {
            if ($this->esAmbiental($valor)) {
                continue;
            }

            foreach ($this->duraciones($valor) as $ms) {
                if ($ms === 0.0) {
                    continue;   // un retardo de cero es «sin retardo», no una duración
                }

                $sueltas[] = "{$fichero}  {$selector}  →  {$ms} ms";
            }
        }

        $this->assertSame([], $sueltas, implode("\n", [
            'Hay movimiento con la duración escrita a mano:',
            ...array_map(fn (string $s): string => '  '.$s, $sueltas),
            '',
            '▶ Elige el ROL, no el número:',
            ...array_map(fn (string $t, string $u): string => "  · {$u}  →  var({$t})",
                array_keys(self::ESCALA), array_values(self::ESCALA)),
            '',
            '⚠️ Si de verdad es un bucle AMBIENTAL, lleva `infinite` y se salta solo.',
        ]));
    }

    /**
     * **Ninguna curva escrita a mano fuera de las cuatro.**
     *
     * ⚠️ **`ease` cuenta**: es la curva por defecto del navegador, y era el 90 % de lo que había.
     * Heredarla no es elegirla — y el resultado es que el movimiento de la web no lo decide nadie.
     */
    public function test_no_curve_is_written_by_hand(): void
    {
        $sueltas = [];

        foreach ($this->declaraciones() as [$fichero, $selector, $valor]) {
            if ($this->esAmbiental($valor)) {
                continue;
            }

            if (preg_match_all('/cubic-bezier\([^)]*\)|steps\([^)]*\)|(?<![-\w])(?:linear|ease-in-out|ease-in|ease-out|ease)(?![-\w])/', $valor, $m)) {
                foreach ($m[0] as $curva) {
                    $sueltas[] = "{$fichero}  {$selector}  →  {$curva}";
                }
            }
        }

        $this->assertSame([], $sueltas, implode("\n", [
            'Hay movimiento con la curva escrita a mano:',
            ...array_map(fn (string $s): string => '  '.$s, $sueltas),
            '',
            '▶ Son CUATRO, y cada una tiene contrato:',
            '  · lo que aparece, se pasa y vuelve   →  var(--ease-entra)',
            '  · el rebote grande, uno por pantalla →  var(--ease-cae)',
            '  · cierres, foco y TODO el hover      →  var(--ease-sale)',
            '  · solo esperas                       →  var(--ease-bucle)',
        ]));
    }

    /**
     * **Y las curvas leen los tokens, no los repiten.**
     *
     * Un `cubic-bezier` idéntico al de un token, escrito a mano, pasa las dos guardas de arriba si
     * alguien lo mete dentro de otra `custom property` — y deja de seguir al paquete de la
     * instalación sin que nada falle.
     */
    public function test_the_scale_values_are_not_repeated_elsewhere(): void
    {
        $raiz = $this->hojas();
        $curvas = ['cubic-bezier(0.34, 1.56, 0.64, 1)', 'cubic-bezier(0.2, 1.56, 0.25, 1)', 'cubic-bezier(0.4, 0, 0.2, 1)'];

        foreach ($curvas as $curva) {
            $veces = substr_count(preg_replace('/\s+/', ' ', $raiz), $curva);

            $this->assertLessThanOrEqual(
                1, $veces,
                "`{$curva}` aparece {$veces} veces: solo puede estar en su token.\n".
                '▶ Repetida, deja de seguir al paquete de la instalación y nada falla.',
            );
        }
    }

    /** Las hojas del producto, con los comentarios blanqueados. */
    private function hojas(): string
    {
        $out = '';

        foreach (glob(public_path('css/*.css')) ?: [] as $ruta) {
            if (str_ends_with($ruta, 'client.css')) {
                continue;   // el paquete de un cliente está hecho de literales, y es correcto
            }

            $out .= preg_replace('#/\*.*?\*/#s', ' ', (string) file_get_contents($ruta))."\n";
        }

        return (string) $out;
    }

    /** @return list<array{0:string,1:string,2:string}> fichero · selector · valor */
    private function declaraciones(): array
    {
        $out = [];

        foreach (glob(public_path('css/*.css')) ?: [] as $ruta) {
            if (str_ends_with($ruta, 'client.css')) {
                continue;
            }

            // ⚠️ Los comentarios se BLANQUEAN conservando la longitud: así los offsets siguen
            // valiendo para encontrar el selector, que es lo que hace útil el mensaje de error.
            $crudo = (string) file_get_contents($ruta);
            $sinComentarios = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m): string => str_repeat(' ', strlen($m[0])),
                $crudo,
            );

            $patron = '/(?<![-\w])(?:transition|animation)(?:-duration|-timing-function|-delay)?\s*:\s*([^;{}]+)/';

            if (! preg_match_all($patron, $sinComentarios, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[1] as $hit) {
                [$valor, $donde] = $hit;
                $abre = strrpos(substr($sinComentarios, 0, $donde), '{');
                $selector = $abre === false ? '?' : trim(substr($sinComentarios, 0, $abre));
                $selector = trim(substr($selector, (int) strrpos($selector, '}')), " \t\n}");
                $out[] = [basename($ruta), preg_replace('/\s+/', ' ', substr($selector, -60)) ?? '?', $valor];
            }

            // Y las `custom properties`, que son donde se esconde un literal (`--cta-pair-swap`).
            if (preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;{}]+)/', $sinComentarios, $cp)) {
                foreach ($cp[2] as $i => $valor) {
                    if (isset(self::ESCALA[$cp[1][$i]]) || isset(self::TOKENS_AMBIENTALES[$cp[1][$i]])) {
                        continue;   // los once del sistema SON la escala; los ambientales, su excepción
                    }

                    // ⚠️ **El FALLBACK de un `var()` no cuenta, y hay que decir por qué**: es el
                    // suelo para cuando el token no está declarado —por ejemplo, una hoja servida
                    // aislada en un test—, no una duración de diseño. Con el token presente nunca
                    // se usa. Es el mismo criterio con el que el proyecto tolera un fallback en
                    // `var(--X, …)` y prohíbe el literal suelto.
                    $sinFallback = (string) preg_replace('/var\(\s*--[a-z0-9-]+\s*,[^)]*\)/', 'var(--x)', $valor);

                    if (preg_match('/(?<![\w.-])\d*\.?\d+m?s(?![\w-])/', $sinFallback)) {
                        $out[] = [basename($ruta), $cp[1][$i].' (custom property)', $valor];
                    }
                }
            }
        }

        return $out;
    }

    /** @return list<float> en milisegundos */
    private function duraciones(string $valor): array
    {
        if (! preg_match_all('/(?<![\w.-])(\d*\.?\d+)(m?s)(?![\w-])/', $valor, $m, PREG_SET_ORDER)) {
            return [];
        }

        return array_map(fn (array $x): float => (float) $x[1] * ($x[2] === 's' ? 1000 : 1), $m);
    }

    private function esAmbiental(string $valor): bool
    {
        if (preg_match('/(?<![-\w])infinite(?![-\w])/', $valor)) {
            return true;
        }

        foreach (array_keys(self::ANIMACIONES_DE_DIBUJO) as $nombre) {
            if (str_contains($valor, $nombre)) {
                return true;
            }
        }

        foreach (array_keys(self::TOKENS_AMBIENTALES) as $token) {
            if (str_contains($valor, $token)) {
                return true;
            }
        }

        return false;
    }
}
