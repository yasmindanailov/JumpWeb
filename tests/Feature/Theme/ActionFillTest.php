<?php

namespace Tests\Feature\Theme;

use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL RELLENO DE ACCIÓN — el QUINTO mecanismo del tema** (`docs/specs/tema-por-instalacion.md` §15).
 *
 * Montar el paquete del 2.º cliente (`#206`) destapó lo único que su marca no podía pedirle al
 * producto: **su botón de comprar es naranja y el nuestro estaba atado a `background: var(--fg)`**.
 * No había token de acción, así que ninguna instalación podía pintar el CTA de otro color sin
 * tocar el producto.
 *
 * La pregunta que resolvió la tanda no fue «¿qué botones son oscuros?» —eso da una lista que
 * envejece— sino **«¿para qué sirve cada relleno de tinta?»**, la misma que sacó los tres roles de
 * sombra en `#196`. Medido con dos instrumentos independientes que coincidieron: **52 reglas** del
 * producto rellenan con `var(--fg)` sólido y solo **13** son ACCIÓN. Las otras 39 son superficie
 * invertida, hover que invierte, estado seleccionado o decoración, y **se quedan en tinta a
 * propósito**: el propio sistema del cliente dice «en claro el secundario es TINTA, no cian».
 *
 * Lo que este fichero vigila, y por qué cada cosa:
 *
 *  1. **Que el conjunto de reglas de acción sea EXACTAMENTE el declarado.** Aseverar por lista y
 *     no por recuento caza las dos direcciones: que alguien pinte de acción algo que no lo es
 *     (una pegatina, una pestaña activa) y que alguien devuelva un CTA a `var(--fg)`.
 *  2. **Que la conversión no quede a medias.** Es el defecto más caro de este carril: en `#194` el
 *     scrim del hero tenía cuatro paradas y se convirtieron dos. Un relleno de acción con el texto
 *     todavía en `var(--bg)` es crema sobre naranja — no falla, no avisa, y no se lee.
 *  3. **Que sin color de acción el producto se sirva EXACTAMENTE como hoy.** Es la mitad que
 *     permite que esta tanda no mueva un píxel: el conmutador no se emite y mandan los fallbacks.
 *  4. **Que el hover se derive y no se teclee**, con el valor del cliente como control.
 *  5. **Que el texto sobre el relleno pase AA**, calculado aquí y no copiado de una tabla.
 */
class ActionFillTest extends TestCase
{
    use RefreshDatabase;

    /** Las hojas del PRODUCTO. `client.css` es de una instalación y no se juzga (`#206`). */
    private const SHEETS = ['public/css/landing.css', 'public/css/site.css'];

    /**
     * **Los 13 rellenos de ACCIÓN, con su sujeto.** Es el contrato del rol.
     *
     * El criterio no es «botón sólido»: es **el control primario que hace avanzar** —comprar,
     * reservar, enviar, confirmar—. Por eso entran el CTA del nav y la barra del carrito, y NO
     * entran la pegatina de una foto (superficie invertida), el hover de un botón fantasma
     * (secundario, que el sistema del cliente quiere en tinta) ni una pestaña activa (selección).
     */
    private const ACTION_RULES = [
        // landing.css
        '.btn' => 'el botón sólido de la landing y del cajón',
        '.btn:disabled:hover, .btn[aria-disabled="true"]:hover' => 'su guarda de deshabilitado',
        // ⚠️ Aquí estaban `.zone-intro__cta` y `.zone-photo-card__cta`, los dos «Ver atracciones»
        // de las tarjetas de zona. **Se van con su sujeto** (`#302`, `[DECIDIDO owner]`: las
        // tarjetas fuera): las atracciones están ahora debajo del selector, así que el botón
        // llevaba a donde ya estabas. La lista de acción **solo encoge**, que es la regla.
        '.price--feat .price__cta' => 'el CTA de la tarifa destacada',
        '.bd-pack__cta' => '«Reservar este cumple»',
        // ⚠️ `.bd-btn--solid` vivía aquí y SE FUE CON SU SUJETO (T9): el par del editor de
        // invitaciones usa ahora `.btn`/`.btn--ghost`, la familia única del contenido — que ya
        // está en esta lista. La lista solo encoge, que es la regla.
        // site.css
        // ⚠️ **Entra en la 2c·8** (`#216`): el hero recupera sus dos botones y el primero es el que
        // hace avanzar la compra, o sea acción de manual. El segundo (`.hero__act--alt`) NO entra:
        // es un contorno sobre el vídeo, y su relleno es un velo de tinta, no un color de acción.
        '.hero__act--buy' => 'el CTA del hero, que vuelve con la 2c·8',
        // ⚠️⚠️ **`.cta-med` NO está aquí, y no es un olvido** (`DECISIONES #213`). Es el botón de
        // comprar más visible de la web, pero su color no lo manda el rol: lo manda una
        // COREOGRAFÍA — tinta con el menú cerrado, AVISO mientras el menú lo tapa—, que es lo que
        // hace el mockup del 2.º cliente y lo que el owner decidió con las tres fuentes delante.
        //
        // ⚠️⚠️ **Y desde `#225` NINGÚN CTA de armazón está en el rol.** Aquí estaba `.cta-prime`,
        // la barra de compra de móvil, con el argumento de que era «el hermano que sí es acción».
        // Ese argumento se cayó cuando se midió la barra: era un componente aparte que tenía que
        // parecerse a `.cta-med` y no se parecía (100 px de alto contra 54, y las dos mitades
        // naranjas). `[DECIDIDO owner]`: la barra pasa a SER `.cta-med`/`.cta-ghost`, así que
        // hereda la coreografía y sale del rol con su hermano. El rol lo siguen pintando `.btn`,
        // `.cartbar`, `.skip-link` y `.flash`.
        // ⚠️ **El CTA del CIERRE entra en `#235`**, y es del mockup: su botón de reservar del final
        // es NARANJA, no el color de marca. Encaja con `#209` —es el botón que hace avanzar la
        // compra— y con el sitio: el cierre es donde más falta hace que se distinga.
        // Nuestra versión anterior usaba `.btn--zone`, que es la MARCA (el cian).
        '.reserve__act' => 'el CTA del hero del cierre',
        '.cartbar' => 'la barra del carrito: lleva a pagar',
        '.svc-cta--book' => '«Reservar» de servicios',
        '.acct__btn--primary' => 'el primario del cajón de cuenta',
        '.acct__btn--primary:disabled:hover' => 'su guarda de deshabilitado',
    ];

    /**
     * **Las excepciones que quedan, y por qué NO son deuda escondida.**
     *
     * Se dejaron a propósito para que la tanda no moviera un píxel: convertirlas cambiaría un
     * color de verdad y eso es una decisión de diseño, no un mecanismo. **La lista solo puede
     * encoger** — igual que las de `ShapeScaleTest`.
     *
     * ⚠️ `.btn:hover` vivía aquí («pinta su texto con `--fg` donde las otras diez usan
     * `--on-brand`») y SE CONVIRTIÓ en la T9, con el OK del owner a «un solo botón»: su texto es
     * ya `var(--on-action-hover)` y sigue al rol como todas. Su ficha de `DEUDA.md` se retira con
     * ella. De dos excepciones queda UNA — la lista encogió sola, que es lo que prometía.
     */
    private const EXCEPTIONS = [
        // El CTA de la tarifa destacada INVIERTE al pasar el cursor (fondo de tarjeta + texto de
        // tinta) en vez de oscurecerse. Es otro patrón de hover, no el del rol.
        '.price--feat .price__cta:hover' => 'background+color',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El escaneo ve el corpus.**
     *
     * Sin esto, un localizador roto deja todo el fichero verde sin mirar nada — el modo de fallo
     * que este repo ya ha pagado cinco veces, y una de ellas era el propio gate documental.
     */
    public function test_the_scan_sees_the_corpus(): void
    {
        $rules = $this->rules();

        $this->assertGreaterThan(
            1500, count($rules),
            'el escaneo ve '.count($rules).' reglas en las dos hojas del producto y son miles: el '.
            'localizador se ha roto y todo lo de abajo pasaría sin mirar.',
        );

        // Y por NOMBRE, no por umbral: un umbral no distingue «leo poco» de «leo otra cosa».
        // ⚠️ `.cta-prime` estaba en esta lista y se retira con él (`#225`). Se sustituye por
        // `.cta-med`, que es la pieza que ocupó su sitio: la lista tiene que nombrar cosas que
        // EXISTEN, o deja de distinguir «leo poco» de «leo otra cosa», que es para lo que está.
        foreach (['.cta-med', '.btn', '.cartbar', '.skip-link', '.flash'] as $selector) {
            $this->assertArrayHasKey(
                $selector, $rules,
                "el escaneo no encuentra la regla `{$selector}`, que existe: está leyendo otra cosa.",
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El conjunto de rellenos de acción es EXACTAMENTE el declarado.**
     *
     * Se asevera el conjunto y no el recuento porque las dos direcciones importan: un CTA que
     * vuelve a `var(--fg)` deja de obedecer al paquete del cliente, y una pegatina que se pinta de
     * acción rompe la regla «un solo relleno de acción por pantalla» que el propio sistema del
     * cliente declara.
     */
    public function test_the_action_fills_are_exactly_the_declared_ones(): void
    {
        $found = [];

        foreach ($this->rules() as $selector => $body) {
            foreach ($body as [$property, $value]) {
                if (in_array($property, ['background', 'background-color'], true) && $value === 'var(--action)') {
                    $found[] = $selector;
                }
            }
        }

        sort($found);
        $expected = array_keys(self::ACTION_RULES);
        sort($expected);

        $this->assertSame($expected, $found, implode("\n", [
            'El conjunto de reglas que rellenan con `var(--action)` ha cambiado.',
            '  sobran: '.(implode(', ', array_diff($found, $expected)) ?: '—'),
            '  faltan: '.(implode(', ', array_diff($expected, $found)) ?: '—'),
            '',
            '▶ ACCIÓN es el control PRIMARIO que hace avanzar (comprar, reservar, enviar). No es',
            '  «botón sólido»: una pegatina, una pestaña activa o el hover de un fantasma también',
            '  rellenan de tinta y NO son acción — el propio sistema del 2.º cliente dice «en claro',
            '  el secundario es TINTA».',
            '▶ Si de verdad entra o sale uno, dilo en `ACTION_RULES` con su sujeto y su porqué.',
        ]));
    }

    /**
     * **Ningún relleno de acción se quedó con el texto a medias.**
     *
     * Es el caso con más valor del fichero. `background: var(--action); color: var(--bg)` es CSS
     * válido, la suite pasa, la página carga — y con un color de acción claro el texto es crema
     * sobre naranja: 1,9 de contraste. El defecto no falla, no avisa y solo se ve mirando.
     */
    public function test_no_action_fill_was_half_converted(): void
    {
        $offenders = [];

        foreach ($this->rules() as $selector => $body) {
            $rellena = false;
            $textos = [];

            foreach ($body as [$property, $value]) {
                if (in_array($property, ['background', 'background-color'], true) && $value === 'var(--action)') {
                    $rellena = true;
                }

                if (in_array($property, ['color', 'fill'], true)) {
                    $textos[] = [$property, $value];
                }
            }

            if (! $rellena) {
                continue;
            }

            foreach ($textos as [$property, $value]) {
                if (! str_contains($value, 'var(--on-action)')) {
                    $offenders[] = "{$selector} → {$property}: {$value}";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Estas reglas rellenan con `var(--action)` pero pintan su texto con otra cosa:'],
            array_map(fn (string $o): string => '  · '.$o, $offenders),
            ['',
                '▶ El texto sobre el relleno de acción es `var(--on-action)`, que sigue al relleno.',
                '▶ Con un color de acción claro, un `var(--bg)` aquí es crema sobre naranja. Es CSS',
                '  válido: no falla, no avisa, y solo se ve mirando la página.'],
        )));
    }

    /**
     * **Las piezas INTERIORES del CTA de acción: RETIRADO con su sujeto** (`#225`, 2026-08-28).
     *
     * Este caso aseveraba que `.cta-prime__ico .ic-e2`, su `.occ`, su `.tk` y `.cta-prime__s`
     * seguían al relleno de acción. **Su sujeto entero era `.cta-prime`**, que ya no existe: la
     * barra de compra de móvil es ahora `.cta-med`/`.cta-ghost` y su color lo manda la
     * coreografía, no el rol (ver el comentario de `ACTION_FILLS`).
     *
     * ⚠️ **No se re-apunta a otro botón, y eso se comprobó antes de borrarlo**: el único CTA que
     * queda en el rol con partes internas sería `.hero__act--buy`, y medido en las dos hojas **no
     * declara ninguna** (`__ico`, `__s`, glifo). Re-apuntar el caso a un selector sin piezas lo
     * habría dejado verde sin mirar nada, que es peor que no tenerlo — es el modo de fallo que
     * este fichero existe para evitar.
     *
     * ▶ Si algún día vuelve a haber un CTA de acción con velo interior, este caso vuelve con él.
     */

    /**
     * **Las excepciones están enumeradas y solo pueden encoger.**
     *
     * Tres reglas del rol NO se convirtieron, a propósito: convertirlas cambiaría un color de
     * verdad. Enumerarlas es lo que impide que se conviertan en deuda invisible, y que la lista
     * crezca sin que nadie lo note.
     */
    public function test_the_exceptions_are_enumerated_and_only_shrink(): void
    {
        $this->assertLessThanOrEqual(
            3, count(self::EXCEPTIONS),
            'la lista de excepciones del rol de acción ha CRECIDO. Solo puede encoger: cada entrada '.
            'nueva es una regla que dice ser de acción y no obedece al color de acción.',
        );

        // Y que sigan existiendo: una excepción que ya no apunta a nada es ruido que envejece.
        $rules = $this->rules();

        foreach (array_keys(self::EXCEPTIONS) as $selector) {
            $this->assertArrayHasKey(
                $selector, $rules,
                "la excepción `{$selector}` ya no existe en el CSS: retírala de `EXCEPTIONS`.",
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El conmutador
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Sin color de acción, la web se sirve EXACTAMENTE como antes de esta tanda.**
     *
     * Es la mitad que sostiene «cero píxeles movidos»: el conmutador no se emite, los cuatro
     * tokens caen a su fallback y el botón primario sigue a la superficie como siempre.
     */
    public function test_without_an_action_colour_nothing_is_emitted(): void
    {
        $this->assertNull(ThemeSettings::action());
        $this->assertStringNotContainsString('--action-brand', ThemeSettings::cssRootDeclarations());
    }

    /**
     * **Un valor inválido tampoco emite nada.**
     *
     * Defensivo como el resto de la clase: un `theme.action` a medio escribir en el panel no puede
     * emitir `--action-brand:rojo;`, porque eso anularía el fallback y dejaría el botón primario
     * **sin relleno** con la página cargando igual.
     */
    public function test_an_invalid_action_colour_is_ignored(): void
    {
        foreach (['rojo', '#F2711', 'F2711C', '', '   '] as $basura) {
            Setting::query()->updateOrCreate(['key' => 'theme.action'], ['value' => $basura, 'group' => 'theme']);
            Setting::flushMemo();

            $this->assertNull(ThemeSettings::action(), "«{$basura}» no debería pasar la validación");
            $this->assertStringNotContainsString('--action-brand', ThemeSettings::cssRootDeclarations());
        }
    }

    /**
     * **Con color de acción se emiten los cuatro valores, y el hover se DERIVA.**
     *
     * ⚠️ El control no es un número inventado: `#F2711C → #D56319` es el par exacto que declara el
     * sistema del 2.º cliente. Si la derivación deja de acertarlo, el paquete de ese cliente deja
     * de ser 1:1 con su diseño y nadie se enteraría.
     */
    public function test_with_an_action_colour_the_four_values_are_emitted(): void
    {
        Setting::query()->updateOrCreate(['key' => 'theme.action'], ['value' => '#F2711C', 'group' => 'theme']);
        Setting::flushMemo();

        $css = ThemeSettings::cssRootDeclarations();

        $this->assertSame('#F2711C', ThemeSettings::action());
        $this->assertStringContainsString('--action-brand:#F2711C;', $css);
        $this->assertStringContainsString('--action-brand-hover:#D56319;', $css);
        $this->assertStringContainsString('--on-action-brand:', $css);
        $this->assertStringContainsString('--on-action-brand-hover:', $css);

        $this->assertSame(
            '#D56319', ThemeSettings::actionHover('#F2711C'),
            'el hover derivado ya no acierta el que declara el sistema del 2.º cliente. El factor '.
            'es 0,88 y no es de gusto: acierta ese par al byte y también el `--err-hover` que el '.
            'producto ya tenía (#c0392b → #a93226).',
        );
    }

    /** **Y sobre un color casi negro se ACLARA**, porque oscurecerlo no se vería. */
    public function test_a_near_black_action_colour_lightens_instead(): void
    {
        $claro = ThemeSettings::actionHover('#0A0A0A');

        $this->assertNotSame('#0A0A0A', $claro);
        $this->assertGreaterThan(
            0x0A, hexdec(substr($claro, 1, 2)),
            'sobre un relleno casi negro el hover se oscurece y no se distingue del reposo: el '.
            'estado deja de existir para quien usa el ratón.',
        );
    }

    /**
     * **El texto sobre el relleno de acción pasa AA — calculado, no copiado.**
     *
     * ⚠️⚠️ **Este caso salió ROJO la primera vez y el defecto era real**, con el color del propio
     * cliente: la versión inicial reutilizaba `onBrand()`, que prefiere blanco y solo cae a tinta
     * por debajo de 3,0 —el umbral de texto GRANDE—. Sobre el hover `#D56319` elegía blanco y daba
     * **3,73**. El rótulo de un botón no es texto grande. `onAction()` elige el de más contraste,
     * como hace el propio sistema del cliente, y ahí da 4,99.
     *
     * Se prueba con colores muy distintos a propósito: el mecanismo tiene que sostener cualquier
     * elección del panel, no solo la naranja del cliente que lo motivó.
     */
    public function test_the_text_over_the_action_fill_passes_aa(): void
    {
        foreach (['#F2711C', '#0A5C93', '#A3C21C', '#FFFFFF', '#101418', '#E6007E', '#FF5B22'] as $accion) {
            foreach ([$accion, ThemeSettings::actionHover($accion)] as $fondo) {
                $ratio = $this->contrast(ThemeSettings::onAction($fondo), $fondo);

                $this->assertGreaterThanOrEqual(
                    4.5, $ratio,
                    "El texto sobre el relleno {$fondo} da {$ratio}: incumple AA (4.5). El rol se ".
                    'usa en el botón de comprar, que es el control con más peso de la web.',
                );
            }
        }
    }

    /**
     * **Y siempre se elige el que más contrasta — incluido el peor caso posible.**
     *
     * ⚠️ **No siempre existe una opción que pase AA, y es aritmética**: con un relleno de
     * luminancia ≈ 0,19 tinta y blanco empatan en **4,31**. Este caso fija ese suelo y, sobre
     * todo, fija que la ELECCIÓN sea siempre la mejor de las dos: si alguien devuelve aquí la
     * preferencia estética de `onBrand()`, el botón pierde hasta 1,3 puntos de contraste sin que
     * nada falle.
     */
    public function test_the_text_over_the_action_fill_is_always_the_best_of_the_two(): void
    {
        $peor = 21.0;

        // Barrido por toda la escala de grises: incluye el punto donde los dos empatan.
        for ($v = 0; $v <= 255; $v += 5) {
            $hex = sprintf('#%02X%02X%02X', $v, $v, $v);
            $tinta = $this->contrast('#14130F', $hex);
            $blanco = $this->contrast('#FFFFFF', $hex);
            $elegido = $this->contrast(ThemeSettings::onAction($hex), $hex);

            $this->assertSame(
                max($tinta, $blanco), $elegido,
                "sobre {$hex} se elige el texto de MENOS contraste ({$elegido} pudiendo ".
                max($tinta, $blanco).'): el rol de acción no tiene preferencia estética.',
            );

            $peor = min($peor, $elegido);
        }

        $this->assertGreaterThanOrEqual(
            4.3, $peor,
            "el peor contraste posible del rol ha bajado a {$peor}. El suelo aritmético es 4,31: ".
            'por debajo, algo ha dejado de elegir el mejor de los dos textos.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Herramientas
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Las reglas de las hojas del producto: selector normalizado → lista de `[propiedad, valor]`.
     *
     * ⚠️ Los comentarios se BLANQUEAN conservando la longitud en vez de borrarse: un `/* … *\/`
     * pegado al selector se comía la cabecera y el localizador daba cero reglas donde había una.
     * Es la trampa que ya pagó el instrumento de `#193`.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function rules(): array
    {
        $out = [];

        foreach (self::SHEETS as $sheet) {
            $css = (string) file_get_contents(base_path($sheet));
            $blind = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m): string => str_repeat(' ', strlen($m[0])),
                $css,
            );

            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $blind, $matches, PREG_SET_ORDER);

            foreach ($matches as $rule) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));

                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }

                foreach ($this->declarations($rule[2]) as $declaration) {
                    $out[$selector][] = $declaration;
                }
            }
        }

        return $out;
    }

    /**
     * Parte un cuerpo en declaraciones respetando paréntesis anidados — sin esto un
     * `color-mix(in srgb, …, transparent)` se corta por su propia coma.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function declarations(string $body): array
    {
        $out = [];
        $buffer = '';
        $depth = 0;

        foreach (str_split($body) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ';' && $depth === 0) {
                $out = $this->push($out, $buffer);
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        return $this->push($out, $buffer);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $out
     * @return list<array{0: string, 1: string}>
     */
    private function push(array $out, string $buffer): array
    {
        if (str_contains($buffer, ':')) {
            [$property, $value] = explode(':', $buffer, 2);
            $out[] = [trim(strtolower($property)), trim((string) preg_replace('/\s+/', ' ', $value))];
        }

        return $out;
    }

    private function contrast(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            $h = ltrim($hex, '#');
            $channel = static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $channel(hexdec(substr($h, 0, 2)) / 255)
                + 0.7152 * $channel(hexdec(substr($h, 2, 2)) / 255)
                + 0.0722 * $channel(hexdec(substr($h, 4, 2)) / 255);
        };

        $x = $lum($a);
        $y = $lum($b);

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }
}
