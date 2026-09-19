<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsSiteStylesheets;
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
    // Solo por su lista de hojas que NO son del producto: esta guarda lee las hojas a su manera.
    use ReadsSiteStylesheets;

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
        '--dur-cinta' => 'la cinta `C3` de `/servicios` — «en la web se mueve despacio» (#293)',
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

    /**
     * **Las COREOGRAFÍAS: `@keyframes` cuyos tramos declaran su propia física** (`#265`).
     *
     * Una escala de cuatro curvas describe **cómo responde un control** —lo que aparece, lo que
     * cae, lo que se va, lo que espera—. No describe **la gravedad**: un cuerpo que salta sube
     * desacelerando y cae acelerando, y eso son dos curvas distintas dentro del mismo movimiento.
     * El salto del logotipo tiene **siete** tramos y el mockup declara una para cada uno.
     *
     * ⚠️⚠️ **La alternativa era peor de las dos maneras.** Con una sola curva de la escala pasó lo
     * que motivó esta ficha: `--ease-cae` tiene overshoot (1.56), así que **cada tramo se pasaba de
     * largo y volvía** — un salto cuyas posiciones ya describen dos rebotes, rebotando además
     * dentro de cada tramo. Y meter las siete en la escala la convertiría en once curvas, o sea en
     * ninguna.
     *
     * ▶ **Lo que la excepción NO permite**: una curva suelta en un `transition`, ni una curva
     * dentro de un `@keyframes` que no esté aquí. La lista es corta a propósito y **solo puede
     * encoger**, como las de `ShapeScaleTest`.
     */
    private const COREOGRAFIAS = [
        'brand-hop' => 'el salto de la silueta del logotipo: 7 tramos con la física del mockup',
        'brand-settle' => 'el asentamiento del lockup cuando la silueta aterriza',
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
    /**
     * `client.css` es el paquete de una INSTALACIÓN —hecho de literales a propósito— y `cajon.css` es una
     * COPIA generada de las hojas del producto para el cajón empaquetable (`#635`): contarla duplicaría cada
     * declaración y haría decir a esta guarda que la escala está en dos sitios. Misma lista que
     * `Tests\Support\ReadsSiteStylesheets`, que es donde está escrito el porqué largo.
     */
    private function noEsDelProducto(string $ruta): bool
    {
        return in_array(basename($ruta), self::HOJAS_QUE_NO_SON_DEL_PRODUCTO, true);
    }

    private function hojas(): string
    {
        $out = '';

        foreach (glob(public_path('css/*.css')) ?: [] as $ruta) {
            if ($this->noEsDelProducto($ruta)) {
                continue;
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
            if ($this->noEsDelProducto($ruta)) {
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

            $coreografias = $this->rangosDeCoreografia($sinComentarios);

            foreach ($m[1] as $hit) {
                [$valor, $donde] = $hit;

                // ⚠️ Una curva DENTRO de una coreografía es su física, no una elección de estilo.
                if ($this->dentroDeAlguno($donde, $coreografias)) {
                    continue;
                }

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

    /**
     * Los tramos `[inicio, fin]` que ocupa cada `@keyframes` de la lista de coreografías.
     *
     * ⚠️ Se emparejan las LLAVES, no se busca el siguiente `}`: un `@keyframes` contiene un bloque
     * por fotograma, así que el primer cierre está a dos líneas del principio y el rango saldría
     * ridículamente corto — con la guarda pasando en verde por no llegar a mirar nada.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function rangosDeCoreografia(string $css): array
    {
        $out = [];

        foreach (array_keys(self::COREOGRAFIAS) as $nombre) {
            if (! preg_match('/@keyframes\s+'.preg_quote($nombre, '/').'\s*\{/', $css, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $inicio = $m[0][1];
            $profundidad = 0;

            for ($i = $inicio + strlen($m[0][0]) - 1, $n = strlen($css); $i < $n; $i++) {
                if ($css[$i] === '{') {
                    $profundidad++;
                } elseif ($css[$i] === '}' && --$profundidad === 0) {
                    $out[] = [$inicio, $i];

                    break;
                }
            }
        }

        return $out;
    }

    /** @param  list<array{0: int, 1: int}>  $rangos */
    private function dentroDeAlguno(int $donde, array $rangos): bool
    {
        foreach ($rangos as [$desde, $hasta]) {
            if ($donde >= $desde && $donde <= $hasta) {
                return true;
            }
        }

        return false;
    }

    /**
     * **Las coreografías declaradas existen de verdad.**
     *
     * Sin esto, un nombre mal escrito o un `@keyframes` retirado dejarían la excepción apuntando al
     * vacío — y la guarda seguiría en verde, vigilando una lista de fantasmas.
     */
    public function test_every_declared_choreography_exists(): void
    {
        // ⚠️⚠️ **Los comentarios se BLANQUEAN, y sin eso la guarda es CIEGA**: comentar un bloque
        // es la forma habitual de desactivar CSS, y `@keyframes brand-hop { … }` dentro de un
        // comentario satisfacía este caso — o sea que la coreografía podía desaparecer entera con
        // la guarda escrita para evitarlo en verde. Es la trampa de `#252` («el nombre vivo dentro
        // de su propio comentario»), repetida.
        $css = implode("\n", array_map(
            fn (string $ruta): string => (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m): string => str_repeat(' ', strlen($m[0])),
                (string) file_get_contents($ruta),
            ),
            array_filter(glob(public_path('css/*.css')) ?: [], fn (string $r): bool => ! $this->noEsDelProducto($r)),
        ));

        foreach (self::COREOGRAFIAS as $nombre => $porque) {
            $this->assertMatchesRegularExpression(
                '/@keyframes\s+'.preg_quote($nombre, '/').'\s*\{/',
                $css,
                "la coreografía `{$nombre}` ({$porque}) no existe: la excepción apunta al vacío",
            );
        }
    }

    /**
     * ❗❗ **LA CASCADA DE FRANJAS: la coreografía que el artboard define con NÚMEROS** (`#277`).
     *
     * `Microanimaciones PJP` no describe esta pieza con adjetivos: dice de dónde cae, con qué curva,
     * cuánto dura, cuánto se aplasta al aterrizar y con cuánto desfase entra la siguiente. Es la
     * única del sistema con el recorrido escrito, así que es la única que se puede vigilar entera.
     *
     * ⚠️⚠️ **El DESFASE se deriva, no se escribe.** Su artboard pide 90 ms sobre los 420… y 90 no
     * está en su propia escala de siete duraciones. Escribirlo como literal lo escondería de las dos
     * guardas de arriba —un número dentro de una `custom property` no lo ve ningún inventario de
     * `transition`, que es la lección de `#222` §16.4—, así que se expresa como fracción del techo,
     * igual que el desfase del cargador (`--jj-desfase`, «120 ms sobre 900»).
     *
     * ⚠️ **Y con movimiento reducido la cascada NO desaparece: se queda en fundido.** Es la norma del
     * propio artboard —«se mantienen los cambios de opacidad»— y su bloque reducido deja `pjpcae` en
     * un `from{opacity:0} to{opacity:1}`. Quitarla del todo dejaría la lista apareciendo de golpe.
     */
    public function test_the_slot_cascade_keeps_its_contract(): void
    {
        $css = $this->hojas();

        $chip = $this->cuerpoDeRegla($css, '.purchase__chip');

        $this->assertMatchesRegularExpression(
            '/animation:\s*chip-cae\s+var\(--dur-cae\)\s+var\(--ease-cae\)/',
            $chip,
            "la cascada de franjas ha dejado de caer con la curva LONA en el techo de la escala.\n".
            '▶ Son los números del artboard: 420 ms y `cubic-bezier(.2,1.56,.25,1)`.',
        );

        $this->assertStringContainsString(
            'transform-origin: 50% 100%', $chip,
            "el chip ha perdido su origen en la BASE.\n".
            '▶ Sin él el aplastado del aterrizaje se lee como un cambio de tamaño, no como peso.',
        );

        $this->assertMatchesRegularExpression(
            '/--cascada-desfase:\s*calc\(\s*var\(--dur-cae\)\s*\*/',
            $css,
            "el desfase de la cascada ha dejado de DERIVARSE del techo de la escala.\n".
            '▶ Escrito como literal (90 ms) se esconde de las dos guardas de arriba: un número dentro '.
            'de una `custom property` no lo ve ningún inventario de `transition` (`#222` §16.4).',
        );

        // ⚠️ El cuerpo de un `@keyframes` lleva llaves DENTRO (un bloque por fotograma), así que un
        // `[^}]*` se para en la primera y la guarda nace ciega. Se extrae equilibrando.
        $this->assertStringContainsString(
            'scale(1.12, 0.76)', $this->cuerpoDeKeyframes($css, 'chip-cae'),
            'la cascada ya no aplasta al aterrizar (1.12 / 0.76): es lo que la convierte en una lona '.
            'y no en un desvanecido.',
        );

        // ⚠️ Con movimiento reducido se cambia el NOMBRE de la animación, no se retira: así el
        // fundido se conserva y el desplazamiento no. Si alguien lo sustituye por `animation: none`,
        // la lista aparecerá de golpe y nada fallará.
        $reducido = $this->cuerpoDeRegla($css, '.purchase__chip', enReducido: true);

        $this->assertStringContainsString(
            'animation-name: chip-cae-quieta', $reducido,
            "con movimiento reducido la cascada no se queda en FUNDIDO.\n".
            '▶ La norma del artboard mantiene los cambios de opacidad: quitarla del todo deja la '.
            'lista apareciendo de golpe, que es lo que ese modo intenta evitar.',
        );

        $this->assertStringNotContainsString(
            'transform', $this->cuerpoDeKeyframes($css, 'chip-cae-quieta'),
            'la variante de movimiento reducido mueve algo: solo puede cambiar la opacidad.',
        );
    }

    /** El cuerpo de un `@keyframes`, equilibrando las llaves de sus fotogramas. */
    private function cuerpoDeKeyframes(string $css, string $nombre): string
    {
        $this->assertTrue(
            (bool) preg_match('/@keyframes\s+'.preg_quote($nombre, '/').'\s*\{/', $css, $m, PREG_OFFSET_CAPTURE),
            "no existe `@keyframes {$nombre}`: la guarda no vigilaría nada",
        );

        $i = $m[0][1] + strlen($m[0][0]);
        $profundidad = 1;
        $j = $i;

        while ($j < strlen($css) && $profundidad > 0) {
            $profundidad += match ($css[$j]) {
                '{' => 1, '}' => -1, default => 0
            };
            $j++;
        }

        return substr($css, $i, $j - $i - 1);
    }

    /**
     * ❗❗ **EL DESENLACE SON DOS PIEZAS, Y NI UNA MÁS** (`#278`, `[DECIDIDO owner]`: «quitamos el
     * confeti, tampoco vamos a saturar al cliente»).
     *
     * Su artboard de movimiento pone el techo en «máximo dos elementos animándose en pantalla», y el
     * de estados escribe que «la pegatina de estado nunca convive con otra en la misma pantalla». Con
     * el confeti a pantalla completa eran **tres**, y dos de ellas decían lo mismo.
     *
     * ▶ Lo que queda: la **pegatina** que confirma y el **sello** sobre el código, secuenciados —
     * primero confirma, después se sella—. Es la misma regla que su artboard aplica a la espera y el
     * check: «nunca se solapan».
     *
     * ⚠️ **El sello es la ÚNICA rotación animada del sistema**, y lo dice su norma: con dos dejaría de
     * leerse como un gesto.
     */
    public function test_the_outcome_is_two_pieces_and_no_confetti(): void
    {
        $css = $this->hojas();

        $check = $this->cuerpoDeRegla($css, '.purchase__party .state-badge');
        $sello = $this->cuerpoDeRegla($css, '.purchase__stamp');

        $this->assertMatchesRegularExpression(
            '/animation:\s*confirma-check\s+var\(--dur-cae\)\s+var\(--ease-cae\)/', $check,
            'la pegatina de éxito ha dejado de entrar con la curva y el techo de la escala.',
        );

        // ⚠️ El retardo es lo que las SECUENCIA. Sin él las dos arrancan juntas y el ojo no sabe cuál
        // mirar — que es exactamente lo que el techo de «dos a la vez» intenta evitar.
        $this->assertMatchesRegularExpression(
            '/animation:\s*confirma-sello\s+var\(--dur-cae\)\s+var\(--ease-cae\)\s+var\(--dur-estado\)/', $sello,
            "el sello ha dejado de esperar a la pegatina.\n".
            '▶ «Nunca se solapan: primero termina la espera» es regla del propio artboard.',
        );

        $this->assertStringContainsString(
            'rotate(-6deg)', $sello,
            'el sello ha perdido su giro de reposo: sin él no es un sello, es una etiqueta.',
        );

        // ⚠️⚠️ **El CONFETI no puede volver.** No basta con haberlo borrado: lo que esta guarda
        // impide es que alguien lo reintroduzca sin enterarse de que su sitio ya está ocupado por
        // dos piezas y de que el artboard de estados lo prohíbe explícitamente.
        $js = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*celebrate\s*\(\s*\)\s*\{/m', $js,
            "ha vuelto el confeti a pantalla completa (`celebrate()`).\n".
            "▶ `[DECIDIDO owner, 2026-08-30]`: se retiró para no saturar. Marcaba lo mismo que la\n".
            '  pegatina de éxito, y con ella y el sello serían TRES piezas donde el techo son dos.',
        );

        $reducido = $this->cuerpoDeRegla($css, '.purchase__stamp', enReducido: true);

        $this->assertStringContainsString(
            'animation-name: confirma-sello-quieta', $reducido,
            "con movimiento reducido el sello no aparece SIN CAER.\n".
            '▶ Su norma lo dice con esas palabras: «los cargadores pasan a forma estática y el sello '.
            'aparece sin caer». Retirarlo del todo lo haría aparecer de golpe.',
        );

        $this->assertStringNotContainsString(
            'translateY', $this->cuerpoDeKeyframes($css, 'confirma-sello-quieta'),
            'la variante de movimiento reducido del sello sigue teniendo recorrido.',
        );
    }

    /** El cuerpo de todas las reglas de un selector, dentro o fuera del bloque de movimiento reducido. */
    private function cuerpoDeRegla(string $css, string $selector, bool $enReducido = false): string
    {
        $ambito = $css;

        if ($enReducido) {
            preg_match_all('/@media[^{]*prefers-reduced-motion:\s*reduce[^{]*\{((?:[^{}]|\{[^{}]*\})*)\}/s', $css, $m);
            $ambito = implode("\n", $m[1] ?? []);
        }

        preg_match_all(
            '/(?:^|[{}])\s*'.preg_quote($selector, '/').'\s*\{([^{}]*)\}/m',
            $ambito, $hit,
        );

        $this->assertNotEmpty($hit[1], "no hay ninguna regla `{$selector}`".($enReducido ? ' con movimiento reducido' : '').': la guarda no vigilaría nada');

        return implode(' ', $hit[1]);
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
