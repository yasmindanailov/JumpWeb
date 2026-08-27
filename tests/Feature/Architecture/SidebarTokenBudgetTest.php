<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Fase 4 · paso 4.0c — **la tokenización del sidebar solo puede SUBIR**
 * (`docs/specs/sidebar-spa.md` §4.3 y §4.3.bis, criterio CE-3).
 *
 * El owner decidió que cada instalación tendrá su propia hoja de estilos sobre tokens comunes. Eso
 * solo sirve si hay tokens a los que agarrarse: medido el 2026-08-13, de las declaraciones
 * TEMATIZABLES del sidebar solo el 43% usaba `var(--…)` — con lo cual una instalación podía
 * recolorear y poco más, que no es lo que se pidió.
 *
 * Es un test-presupuesto, hermano de `ApiOverheadTest`: no persigue un ideal, **impide que
 * empeore**. Si un cambio lo rompe, es una regresión del white-label.
 *
 * ⚠️ **Se mide sobre las propiedades TEMATIZABLES, no sobre todas.** El sidebar tiene 75
 * `display`, 28 `flex-direction` y 26 `align-items` que son ESTRUCTURA: un `display: flex` no se
 * tematiza, y contarlos premiaría convertirlos en tokens, que sería absurdo. La lista de abajo es
 * la frontera entre «cómo se ve» y «cómo se coloca».
 */
class SidebarTokenBudgetTest extends TestCase
{
    /**
     * Propiedades que un cliente podría querer cambiar sin tocar el marcado. Todo lo demás
     * (`display`, `position`, `flex`, `overflow`, `cursor`…) es estructura y queda fuera.
     *
     * @var list<string>
     */
    private const THEMABLE = [
        'font-size', 'font-weight', 'line-height', 'letter-spacing', 'font-family',
        'gap', 'row-gap', 'column-gap',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'border-radius', 'box-shadow', 'transition', 'transition-duration',
        'color', 'background', 'background-color', 'border', 'border-color',
        'border-top', 'border-bottom',
    ];

    /**
     * Suelo medido tras el paso 4.0c. **Solo puede subir.**
     *
     * 43% al abrir la fase · 49% tras la 1.ª mitad (pesos, radios y colores) · **75% tras la 2.ª**
     * (las escalas de tipografía y espaciado). Las tres subidas son equivalentes por construcción:
     * ninguna movió un píxel.
     */
    /**
     * ⚠️ **Este número NO es comparable con el 75 % anterior**: cambió el ÁMBITO, no la calidad del
     * CSS. Aquel se medía sobre lo que se rascaba de `purchase.blade.php`; este, sobre las familias
     * propias del cajón (`#99`), que incluyen 41 clases que el Blade no veía y excluyen el bloque de
     * formularios compartido con el sitio. **La guarda no se ha relajado: mide otra cosa, y bien.**
     */
    /**
     * ⚠️ **Sube a 72 el 2026-08-22.** Ocultar el bloque de cuenta durante la compra metió cuatro
     * declaraciones nuevas y bajó el ratio a 70; en vez de forzar el suelo se ejecutó **la mitad
     * pendiente de `DECISIONES #42`** —«en `transition` no se tokeniza la declaración, pero sí la
     * duración y las 2-3 curvas»—: nacen `--dur-collapse`, `--dur-fade`, `--ease-panel` y
     * `--ease-bounce`, y las **22** apariciones de las dos curvas del cajón pasan a token. Mismo
     * valor exacto en las 22, así que el riesgo visual es cero y el punto de control es real: una
     * instalación puede retemplar el movimiento sin reescribir una sola regla.
     */
    private const MIN_TOKENISED_PERCENT = 72;

    /**
     * Colores CRUDOS que quedan en el sidebar (`#rrggbb`, `rgba(...)`). **Solo puede bajar**: son
     * los que impiden que una instalación cambie de paleta de verdad.
     *
     * Eran 13 y quedan 3. Los diez convertidos coincidían EXACTAMENTE con un token existente
     * —seis alfa de `--fg`, dos `--bg-soft`, uno `--err`, uno `--warn`—, comprobado por aritmética
     * RGB antes de tocar nada, así que el resultado renderizado es el mismo.
     * Los tres que quedan no tienen token que los represente y convertirlos CAMBIARÍA el color:
     * un velo blanco al 55%, un `#fff` puro, y un `rgba(20,19,15,0.22)` que vive en `landing.css`
     * —la copia del mockup, compartida con toda la landing— y por tanto fuera del alcance de esta
     * fase.
     */
    /**
     * Los colores escritos a mano que quedan en el ámbito REAL del cajón, medidos el 2026-08-16
     * (`DECISIONES #99`). Igual que arriba: **no es comparable con el 3 anterior**, que se medía sobre
     * un ámbito más estrecho. Eran SEIS y están nombrados para que se ataquen por su nombre.
     *
     * ⚠️ **BAJA a 5 el 2026-08-25** (`DECISIONES #143`): el barrido de color de la tanda A se llevó
     * `.cal__day--normal` (`background: rgba(20,19,15,0.05)`), que era `--fg` al 5 % escrito a mano.
     * No se eligió: entró con las otras 143 porque coincidía EXACTAMENTE con un token existente, y el
     * trinquete de abajo es quien avisó de que había que bajar el número. Los cinco que quedan:
     *
     *   · `.addons__badge--included`             `color: #fff`
     *   · `.addons-mini__badge`                  `background: rgba(34, 197, 94, 0.15)`
     *   · `.addons-mini__badge--included`        `color: #fff`
     *
     * ⚠️ **Los tres CAMBIAN PÍXEL si se tocan, y por eso siguen aquí.** Dos son blanco puro sobre el
     * ACENTO, que no es `--bg` (crema) ni `--sheet` (la hoja, que sigue a la superficie): el que les
     * toca es `--on-brand`, y sobre un acento claro `--on-brand` es tinta oscura, así que
     * convertirlos los repinta. El tercero es un verde de otra familia que el texto de al lado ya no
     * comparte —`color: var(--ok)` con `background` de otro verde—, así que es además un defecto de
     * coherencia. Cambiar un color exige verificación visual (DoD §4). Ficha en `DEUDA.md`.
     *
     * ▶ **Bajó de 5 a 3 en la tanda 1 de `specs/tema-por-instalacion.md`**, y en dos pasos, cada uno
     * avisado por el propio trinquete:
     *   · **5 → 4** al nacer `--sheet`: `.cal__day--special .cal__day-price` era
     *     `rgba(255, 255, 255, 0.55)`, o sea LA HOJA escrita a mano.
     *   · **4 → 3** al convertir los tintes: `.purchase__note--guestform` mezclaba contra `#fff`
     *     literal, así que su tinte no seguía a la superficie.
     * Ninguno de los dos cambia píxel —`--sheet` vale hoy `#FFFFFF`, verificado en navegador regla a
     * regla—, y los dos pasan de ser color del primer cliente a seguir el tema de la instalación.
     */
    private const MAX_RAW_COLOURS = 3;

    /** @var ?list<array{property: string, value: string}> */
    private ?array $declarations = null;

    public function test_the_scan_actually_sees_the_sidebar_rules(): void
    {
        $this->assertNotEmpty(self::FAMILIES, 'sin familias declaradas no hay ámbito que medir');
        $this->assertGreaterThan(100, count($this->sidebarDeclarations()), 'el escaneo ve muy pocas reglas: ¿ha cambiado el CSS de sitio?');
    }

    public function test_the_themable_properties_stay_tokenised(): void
    {
        $themable = array_filter(
            $this->sidebarDeclarations(),
            fn (array $d): bool => in_array($d['property'], self::THEMABLE, true)
        );

        $this->assertNotEmpty($themable);

        $tokenised = count(array_filter($themable, fn (array $d): bool => str_contains($d['value'], 'var(--')));
        $percent = (int) floor($tokenised * 100 / count($themable));

        $this->assertGreaterThanOrEqual(
            self::MIN_TOKENISED_PERCENT, $percent,
            "La tokenización del sidebar ha BAJADO a {$percent}% (suelo: ".self::MIN_TOKENISED_PERCENT."%).\n".
            'Cada instalación tiene su propia hoja de estilos sobre estos tokens: un literal nuevo '.
            "es una cosa que un cliente ya no puede cambiar.\n".
            'Si has subido el ratio, sube también el suelo — es un presupuesto, no un objetivo.'
        );
    }

    public function test_raw_colours_in_the_sidebar_only_shrink(): void
    {
        $raw = 0;

        foreach ($this->sidebarDeclarations() as $declaration) {
            $raw += preg_match_all('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)/', $declaration['value']);
        }

        // ⚠️ **Trinquete ESTRICTO, no un techo** (`#99`): el nombre del caso siempre dijo «only
        // shrink» y la aserción era un `<=`, así que tokenizar un color no obligaba a bajar el número
        // y el siguiente crudo entraba gratis. Ahora bajarlo es parte del commit que lo tokeniza.
        $this->assertSame(
            self::MAX_RAW_COLOURS, $raw,
            "El sidebar tiene {$raw} colores crudos y la baseline dice ".self::MAX_RAW_COLOURS.".\n".
            "⚠️ Si han CRECIDO: un color escrito a mano no lo puede cambiar ninguna instalación. Los\n".
            "alfa sobre el texto salen con `color-mix(in srgb, var(--fg) X%, transparent)`.\n".
            'Si han BAJADO —enhorabuena—, actualiza `MAX_RAW_COLOURS` en este mismo commit.'
        );
    }

    /**
     * **Un comentario mal cerrado no rompe el CSS: se come la regla siguiente, en silencio.**
     *
     * Lo encontró la verificación de la 2.ª mitad del paso 4.0c, y llevaba tiempo: un comentario de
     * `site.css` enumeraba tokens con comodines —«--r» seguido de asterisco y barra— y esa pareja
     * **cerraba el comentario a media frase**. El resto del texto pasaba a leerse como un selector,
     * se pegaba al de la regla siguiente y el navegador **descartaba la regla entera** — que resultó
     * ser `.gf-sr-only`, la que oculta visualmente el texto para lectores de pantalla en la hoja del
     * post-form. Efecto real: un aviso que debía ser solo accesible se veía, duplicando lo que el
     * medidor de al lado ya decía.
     *
     * La firma de ese fallo es exacta y barata de vigilar: un cierre de comentario dentro de un
     * selector. Un selector desmedido delata lo mismo cuando el texto no llega a incluir el cierre.
     */
    public function test_no_stylesheet_rule_is_swallowed_by_a_broken_comment(): void
    {
        foreach (['landing.css', 'site.css'] as $file) {
            $css = (string) file_get_contents(public_path('css/'.$file));

            // Se quitan los comentarios BIEN cerrados; lo que quede en un selector es texto que se
            // escapó de uno.
            $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $css);

            preg_match_all('/([^{}]+)\{/', $stripped, $matches);

            foreach ($matches[1] as $selector) {
                $selector = trim($selector);

                $this->assertStringNotContainsString(
                    '*/', $selector,
                    "«{$file}»: un selector contiene un cierre de comentario, así que un comentario ".
                    'de más arriba se cerró antes de tiempo y su texto está contaminando esta regla '.
                    "—que el navegador descarta ENTERA, sin avisar—:\n  ".mb_substr($selector, 0, 160)
                );

                // La longitud se mide por selector INDIVIDUAL, no por el grupo: una regla puede
                // enumerar legítimamente veinte selectores separados por comas —`landing.css` tiene
                // uno de 257 caracteres— y medir el grupo entero solo produce falsos positivos.
                foreach (explode(',', $selector) as $single) {
                    $single = trim($single);

                    $this->assertLessThan(
                        160, mb_strlen($single),
                        "«{$file}»: un selector de ".mb_strlen($single).' caracteres no es un selector, '.
                        "es prosa que se ha escapado de un comentario:\n  ".mb_substr($single, 0, 160)
                    );
                }
            }
        }
    }

    /**
     * ⚠️⚠️ **EL CAJÓN NO HEREDA SU ANCHO DEL VIEWPORT** (2026-08-23).
     *
     * `.zone-tabs` nace `inline-flex` porque en la landing vive dentro de contenedores que la centran,
     * y la regla que la pone a ancho completo está **dentro de un media query de MÓVIL**. El cajón no
     * es una pantalla: es un **panel estrecho a cualquier viewport**, así que heredar de la ventana le
     * daba dos aspectos para el mismo sitio.
     *
     * ▶ **Y el síntoma no fue estético**: sin un bloque delante, la barra de pestañas compartía línea
     * con el botón «Volver» —`inline-flex` también—, y durante meses lo tapó por accidente el `<h2>`
     * del armazón. El día que ese título dejó de pintarse en las zonas de auth, quedó a la vista.
     *
     * ⚠️ Se asevera que la declaración está **fuera de cualquier `@media`**: dentro volvería a atar el
     * aspecto del panel al tamaño de la ventana, que es exactamente el fallo.
     */
    public function test_the_drawer_tabs_do_not_depend_on_the_viewport(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        // Fuera todo lo que viva dentro de un `@media { … }`: lo que quede es incondicional.
        $sinMedia = (string) preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.purchase__authtabs\s*\{[^}]*display:\s*flex[^}]*width:\s*100%/s',
            $sinMedia,
            "La barra de pestañas del cajón ha vuelto a depender del viewport.\n".
            "⚠️ `.zone-tabs` es `inline-flex` por defecto —está pensada para la landing— y el cajón es\n".
            "un panel estrecho SIEMPRE. Sin esta declaración incondicional, en escritorio las pestañas\n".
            'comparten línea con el botón «Volver», que también es `inline-flex`.'
        );
    }

    /**
     * ⚠️ **Y el «Volver» del área lleva su propio aire**, en vez de depender de que la pantalla
     * siguiente empiece por un bloque con margen. Así estuvo hasta el 2026-08-23, y se rompió en
     * cuanto una zona dejó de traer título.
     */
    public function test_the_area_back_button_owns_its_spacing(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.account__back\s*\{[^}]*margin-bottom/',
            (string) file_get_contents(public_path('css/site.css')),
            'El «Volver» del área ha dejado de separarse solo: su ritmo vuelve a depender de lo que '.
            'venga detrás, que es como se pegó a las pestañas de auth.'
        );
    }

    /**
     * **El hueco donde monta el motor SPA es un eslabón de la cadena flex del panel, y sin regla
     * propia la parte** (Fase 4 · paso 4.3·1).
     *
     * Vue monta DENTRO de su contenedor, no lo reemplaza, así que `#sidecart-spa` queda entre
     * `.sidecart__body` y `.purchase`. Lo que sostiene «el contenido scrollea y el pie queda anclado
     * al fondo» es una cadena de HIJOS DIRECTOS —`.sidecart__body{display:flex;flex-direction:column}`
     * → `.purchase{flex:1;min-height:0}` → `.purchase__scroll{flex:1;min-height:0;overflow-y:auto}`—,
     * y un `display:block` en medio corta la altura: el scroll no recorta y el pie deja de estar
     * pegado.
     *
     * ⚠️ **Ningún diff de árbol puede ver esto**: todos los casos de `SidebarDomContractTest` anclan
     * DENTRO de `.purchase`, y el nodo intermedio ni siquiera existe en el motor Livewire. Hasta 4.3·1
     * el fallo estaba latente porque el motor SPA no emitía todavía ni la zona scrollable ni el pie.
     */
    public function test_the_spa_mount_point_keeps_the_flex_chain_of_the_panel(): void
    {
        $css = (string) file_get_contents(public_path('css/site.css'));

        preg_match('/#sidecart-spa\s*\{([^}]*)\}/', $css, $rule);

        $this->assertNotEmpty(
            $rule,
            'Falta la regla de `#sidecart-spa` en `site.css`. Sin ella el hueco donde monta Vue es un '.
            '`display:block` en medio de la cadena flex del panel: el cajón SPA deja de recortar el '.
            'scroll y el pie deja de estar anclado al fondo.'
        );

        foreach (['display: flex', 'flex-direction: column', 'flex: 1', 'min-height: 0'] as $declaration) {
            $this->assertStringContainsString(
                $declaration, $rule[1],
                "La regla de `#sidecart-spa` ha perdido «{$declaration}», que es una de las cuatro que ".
                'reproducen lo que `.purchase` recibe de `.sidecart__body` en el motor Livewire.'
            );
        }
    }

    /**
     * **Un token de escala que no existe no falla: se descarta en silencio.** `font-size:
     * var(--fs-99)` con `--fs-99` sin definir no es un tamaño raro — es una declaración inválida
     * que el navegador tira, así que el texto sale al tamaño heredado y nadie se entera hasta que
     * alguien lo ve. Es el riesgo que la 2.ª mitad del paso 4.0c introduce al cambiar 202 literales
     * por indirecciones, y por eso viene con su guarda.
     *
     * Vigila los dos sentidos: usar sin definir (rompe) y definir sin usar (escala inventada, que
     * es cómo un sistema de diseño empieza a mentir sobre lo que el producto usa de verdad).
     */
    public function test_every_scale_token_used_is_defined_and_every_one_defined_is_used(): void
    {
        $defined = [];
        $used = [];

        foreach (['landing.css', 'site.css'] as $file) {
            $css = (string) file_get_contents(public_path('css/'.$file));

            preg_match_all('/^\s*(--(?:fs|sp)-[\w-]+)\s*:/m', $css, $matches);
            $defined = array_merge($defined, $matches[1]);

            preg_match_all('/var\((--(?:fs|sp)-[\w-]+)\)/', $css, $matches);
            $used = array_merge($used, $matches[1]);
        }

        $defined = array_unique($defined);
        $used = array_unique($used);

        $this->assertNotEmpty($used, 'no se ha visto ni un token de escala: ¿ha cambiado el nombre?');

        $this->assertSame(
            [], array_values(array_diff($used, $defined)),
            'Tokens de escala USADOS sin definir. Un `calc()` sin su variable es una declaración '.
            'inválida: el navegador la descarta y el valor cae al heredado, sin avisar.'
        );

        // Las unidades son el punto de control de cada escala: existen para que las use quien
        // instala, no las reglas, así que no cuentan como «definidas sin usar».
        $this->assertSame(
            [], array_values(array_diff($defined, $used, ['--fs-unit', '--sp-unit'])),
            'Tokens de escala definidos que no usa nadie. Una escala con escalones muertos describe '.
            'un sistema que el producto no tiene; si un valor dejó de usarse, se retira el token.'
        );
    }

    /**
     * Las clases que emite el sidebar: las de su propia vista más la CARCASA del cajón, que vive en
     * el layout (`sidecart*`) y es donde están las reglas de más valor — la que lo abre, entre
     * ellas. Olvidarla fue uno de los agujeros que la revisión del spec encontró.
     *
     * @return list<string>
     */
    /**
     * Las clases del cajón, **por FAMILIA y no rascando una plantilla** (Fase 4 · paso 4.7·2b·2·C,
     * `DECISIONES #99` — cierra el `[DECISION-PENDIENTE]` de `#74`).
     *
     * ⚠️ **Antes esto salía de `class="…"` de `purchase.blade.php`**, y eso tenía dos problemas: el
     * ámbito dependía del MOTOR —desaparece con el Blade en ·2b·3— y no veía las 41 clases que solo
     * viven en los `.vue` porque el escáner no seguía los parciales incluidos.
     *
     * La regla ahora es una propiedad del CSS, no de una vista: **una clase es del cajón si pertenece
     * a una de sus familias**. Medido el 2026-08-16, el ámbito pasa de 1.073 declaraciones (Blade) a
     * **1.127**, y no a las 1.307 del escaneo ancho — porque la diferencia entre ambos es exactamente
     * lo que NO es del cajón y se excluye a propósito:
     *
     * ⚠️ **`auth__*`, `form__*`, `pwd-*` y `check` NO entran**, aunque el cajón los pinte: son los
     * formularios de login y alta, **compartidos con el modal de la cabecera y con `/mi-cuenta`**.
     * Tokenizarlos es trabajo de Fase 5, y meterlos aquí haría que el presupuesto del cajón subiera y
     * bajara por cambios que no son suyos. Lo mismo con `eyebrow`, `icon`, `tk`, `btn--` y `zone-`,
     * que son del sitio.
     *
     * @return list<string>
     */
    private const FAMILIES = [
        'sidecart', 'purchase', 'cart', 'cartbar', 'catalog', 'addons',
        'cal__', 'bk-', 'jj-', 'qtybox', 'wiz__', 'eventfields', 'entry__',
    ];

    /**
     * Declaraciones de las reglas cuyo selector toca alguna clase del sidebar, en los DOS ficheros:
     * `site.css` y también `landing.css`, donde viven `btn--lg`, `btn--ghost` y `zone-tab`.
     *
     * @return list<array{property: string, value: string}>
     */
    private function sidebarDeclarations(): array
    {
        // Memo de INSTANCIA, no `static`: un `static` dentro de un método sobrevive al objeto y al
        // test siguiente del mismo proceso, que es justo lo que `SUITE-02` documenta como fuente de
        // fallos fantasma. Aquí el contenido no cambiaría, pero la forma sí importa.
        if ($this->declarations !== null) {
            return $this->declarations;
        }

        $declarations = [];

        foreach (['site.css', 'landing.css'] as $file) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('css/'.$file)));

            preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                if (! $this->selectorTouchesSidebar($rule[1])) {
                    continue;
                }

                foreach (explode(';', $rule[2]) as $declaration) {
                    if (! str_contains($declaration, ':')) {
                        continue;
                    }

                    [$property, $value] = explode(':', $declaration, 2);
                    $declarations[] = ['property' => trim($property), 'value' => trim($value)];
                }
            }
        }

        // ⚠️ `return $memo = $declarations;` — así estaba hasta el 2026-08-25, y `$memo` era una
        // variable LOCAL que se descartaba al salir: el memo de instancia nunca se rellenaba y la
        // guarda de arriba (`$this->declarations !== null`) nunca daba positivo. Los dos ficheros se
        // releían y se re-parseaban en cada caso. No falseaba ningún resultado —de ahí que durara—,
        // pero el párrafo de arriba explica con detalle por qué el memo es de instancia y no `static`
        // sobre un memo que no existía.
        return $this->declarations = $declarations;
    }

    /** @param  list<string>  $classes */
    /**
     * ⚠️ **Los modificadores de ESTADO (`is-…`) siguen fuera**, y por el mismo motivo de siempre: son
     * compartidos (`.sidecart.is-open`, `.modal.is-open`, `.cal__day.is-selected`), así que contarlos
     * mete en el ámbito rincones del CSS que no son del cajón. Se descubrió al sacar `is-open` de un
     * `:class` de Alpine: los crudos subieron de 3 a 4 sin tocar una línea de CSS.
     */
    private function selectorTouchesSidebar(string $selector): bool
    {
        foreach (self::FAMILIES as $family) {
            if (preg_match('/\.'.preg_quote($family, '/').'[\w-]*(?![\w-])/', $selector) === 1) {
                return true;
            }
        }

        return false;
    }
}
