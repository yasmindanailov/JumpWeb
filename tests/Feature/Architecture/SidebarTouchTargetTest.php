<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **EL SUELO TÁCTIL DENTRO DEL CAJÓN** — la mitad que `TouchTargetTest` no puede ver.
 *
 * ⚠️⚠️ **Nace porque el suelo del producto NO llegaba al cajón y no lo veía NADIE.**
 * `TouchTargetTest` recorre **RUTAS de la web pública** y renderiza su HTML; el cajón no es una
 * ruta —lo monta Vue dentro de `.sidecart__panel`—, así que sus controles quedaban fuera del
 * barrido. La lección se pagó dos veces antes de escribir esto: `#557` con el stepper de cantidad
 * (32 px, el control más pulsado del embudo) y `#563` con el armazón.
 *
 * ── QUÉ ASEVERA, Y POR QUÉ ASÍ ───────────────────────────────────────────────────────────────
 * El censo de controles **se saca del MARCADO**, no de una lista escrita a mano: se leen las
 * plantillas de `resources/js/sidebar/**` y el armazón del cajón en `layout.blade.php`, y se
 * recogen los `<button>`, `<a href>`, `<summary>` y `<label class="check">` con sus clases. Una
 * lista a mano se queda corta **en silencio** —es exactamente lo que le pasó a `SidebarBodySizeTest`
 * con `qr-pass` y `dep-pick`—; un censo desde la fuente crece solo.
 *
 * ⚠️⚠️ **Y se asevera lo DECLARADO, no lo medido, a propósito.** Una sonda de navegador mide la
 * CAJA de un control: con ella `.bk-back` sale acusado a 20 px y **cumple** —su pseudo absoluto le
 * da 48—, y lo mismo `.cart__remove` y `.cal__nav` por el bloque del «Lote 9». *Un instrumento que
 * acusa a lo que ya está bien no vale para escribir una guarda.* Lo que esta guarda fija es el
 * MECANISMO: que cada control diga cómo llega al suelo, que es lo que la sonda no puede vigilar en
 * cada push.
 *
 * Un control alcanza el suelo por una de estas tres vías:
 *   1. `min-height: var(--tap-min)` (o `height`) — crecer de verdad, donde no daña;
 *   2. un `min-height` literal POR ENCIMA del suelo — el control es deliberadamente mayor;
 *   3. el pseudo absoluto del «Lote 9» (`max(100%, var(--tap-min))`), que amplía el área sin mover
 *      el dibujo. ⚠️ `max()` y no un valor a secas: un área del mínimo centrada sobre un control de
 *      60 px lo ENCOGE, y ese defecto es invisible (`#264`).
 *
 * ⚠️ **Lo que esta guarda NO puede hacer**: no mide píxeles. Que una regla declare el suelo no
 * demuestra que el área acabe midiéndolo —puede recortarla un ancestro con `overflow`, o puede
 * solaparse con la del vecino—. Eso lo dice `scripts/sonda-cajon.mjs`, y su cifra hay que leerla
 * sabiendo que mide cajas.
 */
class SidebarTouchTargetTest extends TestCase
{
    /**
     * Lo que HOY no declara el suelo, con su motivo. **Esta lista solo ENCOGE.**
     *
     * ⚠️⚠️ **No son quince descuidos: son el hueco que deja no haber tenido guarda.** Hasta `#566`
     * el cajón no tenía ninguna, así que el suelo llegó donde llegó. Sacar una entrada de aquí es
     * arreglarla; meter una nueva es devolver un control al montón, y eso no se hace en silencio.
     *
     * ⚠️ **El motivo dice la cifra MEDIDA cuando la hay** (`scripts/sonda-cajon.mjs`, 32 pantallas
     * a 390 y 1280, informe `storage/app/audit/cajon-antes-566.json`). Donde dice «su alto lo da el
     * contenido» es peor de lo que parece: ese control cumple **por casualidad**, y el día que su
     * texto encoja deja de cumplir sin que falle nada — que es justo lo que `#476` midió en la web.
     *
     * @var array<string, string> combinación de clases → por qué no declara el suelo
     */
    private const SIN_DECLARAR = [
        // ── Medidos POR DEBAJO del suelo: deuda de verdad ────────────────────────────────────
        'acct__btn acct__btn--primary' => '45 px medidos: el primario del índice de la cuenta, a 3 del suelo',
        'acct__btn acct__btn--ghost' => '45 px medidos: su hermano fantasma, misma regla y mismo hueco',
        'addons__moreinfo' => '24 px: el «más info» de un complemento, dentro de una fila ya apretada',
        'auth__link' => '21 px: «¿olvidaste tu contraseña?», un botón con piel de enlace',
        'orders__gate-toggle' => '21 px: el desplegable de la puerta en «Mis pagos»',
        'pwd-input__toggle' => '32 px: el ojo de la contraseña, ANCLADO dentro del campo — crecerlo toca su posicionamiento',
        'timestrip__nav timestrip__nav--next' => '32 px: la flecha de la tira de horas',
        'timestrip__nav timestrip__nav--prev' => 'hermana de la anterior, misma regla',
        'daystrip__nav daystrip__nav--next' => '32 px: la flecha de la tira de días',
        'daystrip__nav daystrip__nav--prev' => 'hermana de la anterior, misma regla',

        // ── Sin declarar, y hoy por encima del suelo sólo porque su contenido los estira ──────
        'acc-tile' => 'su alto lo da el contenido (tarjeta del índice de cuenta), no una declaración',
        'acct__alert' => 'su alto lo da el contenido: el aviso del índice es un párrafo enlazado',
        'acct__btn acct__btn--primary acct__btn--qr' => 'variante con icono del primario: hereda el hueco de `.acct__btn`',
        'acct__btn acct__btn--ghost acct__btn--icon' => 'ídem, variante de icono',
        'acct__btn acct__btn--ghost acct__btn--reservas' => 'ídem, variante de «Mis reservas»',
        'cart__pending-save' => 'su alto lo da el contenido: el «Guardar» del borrador de línea (`#560`)',
        'cart__pending-discard' => 'su hermano «Descartar», misma fila',
        'cartbar' => 'su alto lo da el contenido: la barra del carrito del catálogo',
        'catalog__item' => 'su alto lo da el contenido: la fila de producto, que es una tarjeta',
        'catalog-acc__head' => 'su alto lo da el contenido: la cabecera del acordeón de categoría (`#553`)',
        'check' => 'la casilla es una FILA y su alto lo da el texto: 46,4 px medidos, a 1,6 del suelo',
        'check check--opt' => 'ídem, la variante opcional («mantener la sesión iniciada»)',
        'check dep-pick__pick' => 'ídem, la casilla de elegir un menor',
        'whoblock__head' => 'su alto lo da el contenido: la cabecera del bloque de «quién eres»',
    ];

    /** El techo del trinquete: lo declarado arriba. **Solo puede bajar.** */
    private const SIN_DECLARAR_MAX = 24;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El censo lee de verdad las plantillas del cajón.**
     *
     * Sin este caso, cualquiera de los de abajo podría estar pasando en verde sobre una lista
     * vacía — que es la forma en que una guarda nace ciega, y este repo la ha pagado cuatro veces.
     * Los dos números no sobran: el primero dice que se leen las plantillas de Vue, el segundo que
     * el armazón del cajón (que vive en Blade, no en Vue) también entra.
     */
    public function test_the_census_reads_the_drawer_markup(): void
    {
        $censo = $this->controlesDelCajon();

        $this->assertGreaterThan(
            30,
            count($censo),
            'el censo ve muy pocos controles: es el instrumento, no el cajón',
        );

        $this->assertArrayHasKey(
            'sidecart__close',
            $censo,
            'el censo no ve el armazón: el aspa de cerrar vive en `layout.blade.php`, no en un `.vue`',
        );
    }

    /** El barrido de la hoja ve reglas y sabe leer un pseudo-elemento. */
    public function test_the_stylesheet_scan_sees_the_touch_mechanism(): void
    {
        $reglas = $this->reglas();

        $this->assertGreaterThan(500, count($reglas), 'el barrido de CSS se ha quedado corto');

        $pseudo = array_filter(
            $reglas,
            fn (string $cuerpo, string $sel): bool => str_contains($sel, '::after') && str_contains($cuerpo, 'var(--tap-min)'),
            ARRAY_FILTER_USE_BOTH,
        );

        $this->assertNotEmpty($pseudo, 'el barrido no encuentra el mecanismo de área táctil del «Lote 9»');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La propiedad
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Todo control que el cajón emite declara cómo llega al suelo, o está nominado. */
    public function test_every_control_the_drawer_emits_declares_how_it_reaches_the_floor(): void
    {
        $culpables = [];

        foreach ($this->controlesDelCajon() as $combo => $sitios) {
            if (array_key_exists($combo, self::SIN_DECLARAR)) {
                continue;
            }

            if ($this->alcanzaElSuelo($combo) !== null) {
                continue;
            }

            $culpables[] = "{$combo}  ({$sitios} sitio/s)";
        }

        $this->assertSame(
            [],
            $culpables,
            "Controles del cajón que no declaran el suelo táctil (`--tap-min`):\n  ".
            implode("\n  ", $culpables).
            "\n\nO le das `min-height: var(--tap-min)`, o el pseudo del «Lote 9» ".
            '(`max(100%, var(--tap-min))`), o lo nominas en `SIN_DECLARAR` con su motivo medido.',
        );
    }

    /**
     * **La nominación solo encoge.**
     *
     * El trinquete es lo que convierte una lista de deuda en una lista que se vacía. Sin él, la
     * forma normal de «arreglar» un rojo sería añadir una línea aquí.
     */
    public function test_the_nominated_list_only_shrinks(): void
    {
        $this->assertLessThanOrEqual(
            self::SIN_DECLARAR_MAX,
            count(self::SIN_DECLARAR),
            'la lista de controles sin suelo ha CRECIDO: un control nuevo se declara, no se nomina.',
        );
    }

    /** Ninguna nominación puede quedarse sin sujeto: si el control ya no existe, la línea sobra. */
    public function test_every_nomination_still_has_a_subject(): void
    {
        $censo = $this->controlesDelCajon();

        foreach (array_keys(self::SIN_DECLARAR) as $combo) {
            $this->assertArrayHasKey(
                $combo,
                $censo,
                "`{$combo}` ya no lo emite el cajón: sácalo de la lista, que solo encoge. ".
                'Una entrada sin sujeto es una guarda que pasa sin mirar nada.',
            );
        }
    }

    /**
     * **El área a medida del «Volver» lee el TOKEN y no un literal.**
     *
     * ⚠️⚠️ Nace de un defecto medido: su pseudo se escribió en `#237` con `inset: -14px`, cuando el
     * control medía 17 px y el suelo era 44 — daba 45 y cumplía. Al subir el cuerpo del cajón
     * (`#550`) el control pasó a 20 y el área a **48 exactos**, o sea que hoy cumple **por
     * casualidad aritmética**: nadie ató ese número al suelo, y el día que el suelo suba o el
     * cuerpo baje se queda corto sin que falle nada. Es el defecto que `#476` midió en la web con
     * cinco familias que daban «exactamente 44».
     */
    public function test_the_back_buttons_touch_area_reads_the_token(): void
    {
        $cuerpo = $this->reglas()['.bk-back::before'] ?? '';

        $this->assertNotSame('', $cuerpo, 'el «Volver» del cajón ha perdido su área táctil');

        $this->assertStringContainsString(
            'var(--tap-min)',
            $cuerpo,
            'el área táctil del «Volver» se calcula con un literal: el suelo vive en el token, '.
            'o el día que cambie este control se queda corto sin que falle nada (`#476`).',
        );
    }

    /**
     * **El valor del suelo no se escribe a mano dentro del cajón.**
     *
     * Es la regla que `#238` y `#250` fijaron para la columna, aplicada al suelo táctil: el número
     * vive en un sitio o vive en seis. Un `min-height: 48px` cumple hoy y deja de cumplir el día
     * que `--tap-min` suba, **en silencio** — que es exactamente lo que pasó al subir de 44 a 48.
     */
    public function test_the_floor_value_is_never_written_by_hand(): void
    {
        $culpables = [];

        foreach ($this->reglas() as $selector => $cuerpo) {
            if (! $this->esDelCajon($selector)) {
                continue;
            }

            if (preg_match('/(?<![-\w])min-height:\s*48px/', $cuerpo)) {
                $culpables[] = $selector;
            }
        }

        $this->assertSame(
            [],
            $culpables,
            "Estas reglas del cajón escriben el suelo táctil a mano:\n  ".implode("\n  ", $culpables).
            "\nUsa `var(--tap-min)`: el número vive en un sitio.",
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Los controles que el cajón EMITE, sacados de sus plantillas.
     *
     * @return array<string, int> combinación de clases → en cuántos sitios aparece
     */
    private function controlesDelCajon(): array
    {
        $out = [];

        $ficheros = array_merge(
            $this->plantillasVue(),
            [base_path('resources/views/components/layout.blade.php')],
        );

        foreach ($ficheros as $fichero) {
            $fuente = (string) file_get_contents($fichero);
            $esVue = str_ends_with($fichero, '.vue');

            if ($esVue) {
                if (! preg_match('/<template>([\s\S]*)<\/template>/', $fuente, $m)) {
                    continue;
                }

                $marcado = $m[1];
            } else {
                $marcado = $fuente;
            }

            // Los comentarios se retiran: dentro de ellos hay marcado de ejemplo (`#482`).
            $marcado = (string) preg_replace('/<!--[\s\S]*?-->/', '', $marcado);

            foreach (['button', 'a', 'summary', 'label'] as $etiqueta) {
                preg_match_all('/<'.$etiqueta.'(\s[^>]*?)?>/', $marcado, $etiquetas, PREG_SET_ORDER);

                foreach ($etiquetas as $abre) {
                    $atributos = $abre[1] ?? '';

                    // ⚠️ `(?<!:)` descarta `:class`: una clase dinámica no se puede vigilar por
                    // selector, y darla por estática ataría la guarda a una expresión de Vue.
                    $clases = preg_match('/(?<!:)class="([^"]*)"/', $atributos, $c) ? trim($c[1]) : '';

                    if ($clases === '') {
                        continue;   // sin clase no hay selector que vigilar: lo cuenta el caso de abajo
                    }

                    // Un `<a>` sin destino es un ancla, no un control.
                    if ($etiqueta === 'a' && ! str_contains($atributos, 'href')) {
                        continue;
                    }

                    // De los `<label>` solo el de casilla: los demás rotulan, no se pulsan.
                    if ($etiqueta === 'label' && ! str_contains($clases, 'check')) {
                        continue;
                    }

                    // Del armazón en Blade solo entra lo del cajón: esa plantilla es de la web entera.
                    if (! $esVue && ! str_contains($clases, 'sidecart')) {
                        continue;
                    }

                    $clases = (string) preg_replace('/\s+/', ' ', $clases);
                    $out[$clases] = ($out[$clases] ?? 0) + 1;
                }
            }
        }

        ksort($out);

        return $out;
    }

    /** @return list<string> las plantillas de Vue del cajón */
    private function plantillasVue(): array
    {
        $out = [];
        $raiz = base_path('resources/js/sidebar');

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $fichero) {
            if ($fichero->isFile() && $fichero->getExtension() === 'vue') {
                $out[] = $fichero->getPathname();
            }
        }

        sort($out);

        return $out;
    }

    /**
     * ¿Alguna de las clases del control declara el suelo? Devuelve la vía, o `null`.
     *
     * ⚠️ Se recorre la combinación entera porque el suelo casi nunca vive en la clase más
     * específica: `btn btn--ghost orders__guestform-btn` lo hereda de `.btn`.
     */
    private function alcanzaElSuelo(string $combo): ?string
    {
        foreach (explode(' ', $combo) as $clase) {
            foreach ($this->reglas() as $selector => $cuerpo) {
                // Frontera de palabra: `.cal__day-price` CONTIENE `.cal__day` y no es lo mismo.
                if (! preg_match('/'.preg_quote('.'.$clase, '/').'(?![\w-])/', $selector)) {
                    continue;
                }

                // ⚠️⚠️ **Las dos vías de «crecer de verdad» NO valen sobre un pseudo-elemento, y eso lo
                // dijo el ARNÉS**: al mutar la receta del «Lote 9» a `width/height: var(--tap-min)` —el
                // defecto de `#264`, que ENCOGE— la guarda pasaba en VERDE, porque ese `height` del
                // PSEUDO casaba aquí antes de llegar a la comprobación de abajo. *El alto de un
                // pseudo-elemento no es el alto del control: es el de la capa que lo cubre.*
                $esPseudo = str_contains($selector, '::');

                if (! $esPseudo && preg_match('/(?<![-\w])(?:min-)?height:\s*var\(--tap-min\)/', $cuerpo)) {
                    return 'token';
                }

                if (! $esPseudo && preg_match('/(?<![-\w])min-height:\s*(\d+)px/', $cuerpo, $m) && (int) $m[1] >= 48) {
                    return 'literal';
                }

                if ($this->pseudoValido($selector, $cuerpo) && $this->anclaElPseudo($clase)) {
                    return 'pseudo';
                }
            }
        }

        return null;
    }

    /**
     * ¿El pseudo amplía de verdad el área?
     *
     * ⚠️⚠️ **Las dos condiciones las puso el ARNÉS, no una relectura.** La primera versión de este
     * método aceptaba «lleva `var(--tap-min)` y es absoluto», y con eso **dos mutaciones reales
     * pasaron en verde**: cambiar `max(100%, var(--tap-min))` por `var(--tap-min)` a secas —que
     * ENCOGE los controles que ya cumplen, el defecto de `#264` que el propio comentario del CSS
     * advierte— y quitarle al anfitrión su `position: relative`, que deja el pseudo anclado al
     * ancestro posicionado más cercano, o sea el área táctil **en otro sitio de la pantalla**.
     */
    private function pseudoValido(string $selector, string $cuerpo): bool
    {
        if (! str_contains($selector, '::after') && ! str_contains($selector, '::before')) {
            return false;
        }

        if (! str_contains($cuerpo, 'position: absolute')) {
            return false;
        }

        foreach (['width', 'height'] as $eje) {
            if (! preg_match('/(?<![-\w])'.$eje.'\s*:\s*max\(\s*100%\s*,\s*var\(--tap-min\)\s*\)/', $cuerpo)) {
                return false;
            }
        }

        return true;
    }

    /** ¿El anfitrión del pseudo está posicionado? Sin eso, el área se ancla donde no toca. */
    private function anclaElPseudo(string $clase): bool
    {
        foreach ($this->reglas() as $selector => $cuerpo) {
            if (str_contains($selector, '::')) {
                continue;
            }

            if (! preg_match('/'.preg_quote('.'.$clase, '/').'(?![\w-])/', $selector)) {
                continue;
            }

            if (preg_match('/(?<![-\w])position:\s*(relative|absolute|sticky|fixed)/', $cuerpo)) {
                return true;
            }
        }

        return false;
    }

    /** ¿El selector es vocabulario del cajón? */
    private function esDelCajon(string $selector): bool
    {
        foreach (array_keys($this->controlesDelCajon()) as $combo) {
            foreach (explode(' ', $combo) as $clase) {
                if (preg_match('/'.preg_quote('.'.$clase, '/').'(?![\w-])/', $selector)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @var array<string, string>|null */
    private ?array $memoria = null;

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function reglas(): array
    {
        if ($this->memoria !== null) {
            return $this->memoria;
        }

        $out = [];

        foreach (['public/css/site.css', 'public/css/landing.css'] as $hoja) {
            $css = (string) file_get_contents(base_path($hoja));
            // Se BLANQUEAN, no se borran: la hoja tiene llaves y clases dentro de comentarios (`#482`).
            $ciego = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m): string => str_repeat(' ', strlen($m[0])),
                $css,
            );

            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $ciego, $matches, PREG_SET_ORDER);

            foreach ($matches as $regla) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $regla[1]));

                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }

                $out[$selector] = ($out[$selector] ?? '').' '.trim($regla[2]);
            }
        }

        return $this->memoria = $out;
    }
}
