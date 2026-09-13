<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **EL MAPA DEL NARANJA DENTRO DEL CAJÓN** — la grieta 01, con trinquete (`DECISIONES #551`).
 *
 * El canvas auditó nuestro código (`Auditoria Sistema SPA PJP`) y su grieta **01** era la que marcaba
 * como la que más importa: *el botón que avanza la compra se pinta con `var(--zone-1)`*, o sea con un
 * token cuyo valor no decide el diseño —lo pone el tema de la instalación y, sobre una zona, el
 * servidor desde `zones.color`—. Medido antes de esta tanda, el cajón tenía el mapa **invertido**:
 *
 *   · `.bk-cta` —el CTA del pie en las once pantallas del embudo, **incluida la de pagar**— en cian;
 *   · `.btn--zone` en **26 sitios** de 22 componentes, también en cian;
 *   · y los **dos** únicos rellenos de acción del cajón eran «Ir al carrito» y «Iniciar sesión»,
 *     **los dos en la misma pantalla**, que es justo lo que la hoja de componentes del sistema
 *     prohíbe: *«solo un botón de relleno de acción por pantalla; si dos botones compiten, ninguno
 *     gana»*.
 *
 * Lo que este fichero impide es que vuelva. No vigila colores concretos —eso sería cementar el
 * paquete de un cliente—, vigila **el reparto de ROLES**:
 *
 *  1. que el relleno de ACCIÓN del cajón lo lleven solo los botones que COBRAN, enumerados;
 *  2. que el censo de lo que aún lee `--zone-*` sea exactamente el declarado, cada uno con su motivo,
 *     y que **solo encoja**;
 *  3. que ningún rol de TEXTO del cajón lea `--zone-*` — la regla dura del sistema es que cian,
 *     naranja, lima, amarillo y verde **no pueden ser texto sobre claro**, y los cinco que lo eran
 *     daban entre 2,21 y 2,70.
 *
 * ⚠️ **Lo que NO vigila, y conviene saberlo**: el valor que el navegador computa. Eso lo mide
 * `scripts/color-del-cajon.mjs`, que además paga dos trampas que esta guarda no puede ver —el ratón
 * se queda donde pulsó, así que mide un `:hover`; y la transición de color a medias devuelve un valor
 * que no existe en el sistema—.
 */
class SidebarActionRoleTest extends TestCase
{
    private const SHEETS = ['public/css/site.css', 'public/css/landing.css'];

    /** Lo que emite el cajón, por vocabulario de clase (el censo que `#550` dejó bueno). */
    private const PREFIJOS_DEL_CAJON = [
        'acc-tile', 'acct', 'account__', 'addons__', 'auth__', 'bk-', 'cal-more', 'cal__', 'cart',
        'cartbar', 'catalog', 'daystrip', 'dep-pick', 'entry__', 'guardnote', 'orders__', 'paydue',
        'prod-ico', 'purchase', 'qr-pass', 'qtybox', 'timestrip', 'whoblock', 'wiz__',
    ];

    /**
     * **Las reglas del cajón que rellenan con el rol de ACCIÓN.** Una, y es la que cobra.
     *
     * `--action` significa comprar en toda la web (`#209`, `ActionFillTest`), y dentro del cajón eso es
     * **«Pagar»**, el pie del paso 08. Quién vende no lo decide el CSS ni la plantilla: lo decide
     * `foot.js` con su campo `sells`, y `Foot.vue` solo traduce ese dato a esta clase.
     */
    private const RELLENO_DE_ACCION = [
        '.bk-cta--sells' => 'el pie del paso 08: «Pagar», el único del embudo que cobra',
    ];

    /**
     * **Los botones del cajón que llevan `.btn` PELADO**, o sea el relleno de acción de la familia.
     *
     * ⚠️⚠️ Son los CUATRO que cobran, y hay que nombrarlos porque `.btn` a secas **hereda el rol de la
     * base de la familia, que ES la acción**. Ése fue el defecto preexistente que esta tanda destapó:
     * en «ventas pausadas» el canal SECUNDARIO iba en `.btn` pelado —naranja— mientras el principal
     * llevaba la marca, o sea que el botón que menos importaba era el único que pesaba. *Pintar «lo
     * demás» con la clase base no es neutro.*
     *
     * La lista **solo encoge**: un botón nuevo del cajón que quiera naranja tiene que pasar por aquí.
     *
     * ⚠️⚠️ **Se declara CUÁNTOS, no solo en qué fichero**, y eso lo pidió el arnés: con la lista por
     * fichero, un botón naranja nuevo dentro de `DeclinedStep` o de `ReservationCard` —que ya tienen
     * uno legítimo— entraba gratis. *Exceptuar un fichero es exceptuar todo lo que alguien meta después
     * en ese fichero.*
     *
     * @var array<string, array{int, string}>
     */
    private const VENDEN = [
        'steps/DeclinedStep.vue' => [1, 'reintentar el cobro denegado'],
        'steps/RedirectStep.vue' => [1, 'el suelo sin JS del salto al banco'],
        'account/zones/PurchaseCard.vue' => [1, '«Reintentar el pago» de un pedido'],
        'account/zones/ReservationCard.vue' => [1, '«Reintentar el pago» de una reserva'],
    ];

    /**
     * **EL CENSO: lo que todavía lee `--zone-*` dentro del cajón, y por qué cada uno.**
     *
     * Eran **29 reglas** y quedan **13**. Ninguna es un descuido, y el reparto importa:
     *
     *  · **lo que el ARTBOARD dibuja en cian** (la barra de fase y el cuadradito del contexto): la
     *    grieta 01 manda sacar la marca de la acción, de la cifra, del enlace y de la casilla — no de
     *    todas partes. *Casi cambié la barra de fase por aplicar la regla general sin mirar el dibujo.*
     *  · **los tres hovers de relleno de marca**: reposan en tinta y acusan el paso del cursor pasando
     *    a marca, que es el idioma de `.cta-med` (`#217`) y está enumerado en `ActionFillTest`.
     *  · **la marca como IDENTIDAD**: el spinner «Tres botes» (`#259`) y la inicial del avatar.
     *  · **el AVISO sobre papel**, que es deuda declarada: su rol (`tintePapel`) es el punto 7 de la
     *    lista del canvas y nace en otra tanda. Teñirlo de gris mientras tanto le quitaría el
     *    significado —«algo falta»— sin ganar nada.
     *
     * **Solo encoge.**
     */
    private const CENSO_DE_MARCA = [
        '.bk-seg__bar::after' => 'artboard: el relleno de la fase va en cian',
        '.bk-seg__item.is-current .bk-seg__bar' => 'su halo sigue al relleno',
        '.bk-context .jj-block' => 'artboard: el cuadradito del contexto es motivo de marca',
        '.purchase-loading' => 'el spinner «Tres botes» es pieza de MARCA (#259)',
        // ▶ `#568` · SALIÓ `.acct__avatar`: el avatar pasa al círculo del artboard, con la inicial en
        //   PAPEL sobre tinta, y deja de leer el color de zona.
        // ▶ `#540` · SALIERON de esta lista `.bk-cta:hover`, `.cartbar:hover` y
        //   `.acct__btn--primary:hover`, y la lista solo encoge: sus tres botones dejaron de
        //   reposar en TINTA —hoy reposan en el CIAN del secundario—, así que el argumento
        //   «acusa el paso PASANDO a marca» murió con su premisa: un botón que ya reposa en
        //   marca no puede pasar a ella. Su hover es el escalón siguiente de su propia escala.
        '.acct__alert' => 'AVISO sobre papel: su rol (`tintePapel`) nace en otra tanda',
        '.acct__alert:hover' => 'ídem',
        '.acct__alert-ico' => 'ídem',
        '.acct__alert-arrow' => 'ídem',
        '.purchase__note--guestform' => 'ídem: nota teñida, pendiente del rol de aviso',
    ];

    /**
     * Los roles de TEXTO del cajón que la grieta 01 rescató, con el contraste que daban.
     *
     * ⚠️ Se asevera el TOKEN y no el número: el valor lo pone el paquete de cada instalación, así que
     * fijar «6,43» ataría el producto a este cliente. Lo que no puede volver es que un texto lea un
     * token cuyo contraste no lo garantiza nadie.
     */
    private const TEXTOS_RESCATADOS = [
        '.bk-back' => '--interactive',          // 2,21 → enlace «Volver»
        '.auth__link' => '--interactive',       // 2,45 → «he olvidado mi contraseña»
        '.auth__switch button' => '--interactive', // 2,45 → los botones de texto del área
        '.bk-context' => '--fg',                // 2,21 → la línea de contexto es un DATO
        '.cart__when' => '--fg',                // 2,70 → el nombre del producto en la cesta
    ];

    public function test_the_scanner_sees_the_drawer(): void
    {
        $this->assertGreaterThan(
            100,
            count($this->reglasDelCajon()),
            'el escáner ve muy pocas reglas del cajón: ¿ha cambiado el vocabulario de clases?',
        );

        $this->assertGreaterThan(
            30,
            count($this->fuentesDelCajon()),
            'el escáner ve muy pocas fuentes del cajón: ¿ha cambiado la carpeta?',
        );
    }

    /**
     * **GUARDA DE LA GUARDA: el lector de ramas.** Existe porque este predicado se equivocó DOS veces y
     * las dos las dijo el arnés, no una relectura:
     *
     *  · la primera unía las ramas del ternario, así que un `'btn--ink' : ''` parecía llevar siempre la
     *    variante y la rama vacía —la que deja el botón en naranja— pasaba invisible;
     *  · la segunda tomaba como clase el literal de una COMPARACIÓN (`=== 'pending' ? …`) e inventaba
     *    una rama que no existe.
     *
     * ▶ *Un predicado sin casos propios es una opinión con forma de código.*
     */
    public function test_the_class_branch_reader_reads_branches(): void
    {
        $casos = [
            // Solo estático: una rama.
            '<button class="btn btn--lg">' => ['btn btn--lg'],
            // Ternario de dos variantes: dos ramas, y ninguna pelada.
            '<a class="btn x" :class="cta.primary ? \'btn--ink\' : \'btn--ghost\'">' => ['btn x btn--ink', 'btn x btn--ghost'],
            // Ternario con rama VACÍA: la segunda rama es el estático a secas.
            '<button class="btn x" :class="cta.primary ? \'btn--ink\' : \'\'">' => ['btn x btn--ink', 'btn x'],
            // El literal de la comparación NO es una clase.
            '<a class="btn y" :class="row.state === \'pending\' ? \'btn--ink\' : \'btn--ghost\'">' => ['btn y btn--ink', 'btn y btn--ghost'],
            // Sin ternario: los literales son clases (concatenación u objeto).
            '<span class="b" :class="\'b--\' + row.key">' => ['b b--'],
            // Un `:class` ilegible cae al estático, que es el lado estricto.
            '<button class="btn" :class="clases">' => ['btn'],
        ];

        foreach ($casos as $etiqueta => $esperado) {
            $this->assertSame(
                $esperado,
                $this->ramasDeClase($etiqueta),
                "el lector de ramas no lee bien `{$etiqueta}`",
            );
        }
    }

    public function test_the_action_fill_of_the_drawer_is_exactly_the_one_that_charges(): void
    {
        $encontradas = [];

        foreach ($this->reglasDelCajon() as $selector => $cuerpo) {
            // ⚠️ `var(--action)` EXACTO, con su paréntesis: `var(--action\b` casa también con
            // `var(--action-hover)` —la frontera de palabra cae en el guion— y entonces el `:hover` del
            // pie entraba en el censo como si fuera un relleno en reposo.
            if (preg_match('/(?:background|background-color):\s*var\(--action\)/', $cuerpo)) {
                $encontradas[] = $selector;
            }
        }

        sort($encontradas);
        $esperadas = array_keys(self::RELLENO_DE_ACCION);
        sort($esperadas);

        $this->assertSame(
            $esperadas,
            $encontradas,
            "El reparto del relleno de ACCIÓN dentro del cajón ha cambiado.\n".
            "▶ El naranja significa COMPRAR, y en el cajón eso es «Pagar» (paso 08) y nada más.\n".
            '▶ Si de verdad entra o sale uno, dilo en `RELLENO_DE_ACCION` con su sujeto y su porqué.',
        );
    }

    /** Y la clase que lo lleva la emite la plantilla a partir del dato, no de una corazonada. */
    public function test_the_footer_class_comes_from_the_sells_flag(): void
    {
        $foot = (string) file_get_contents(base_path('resources/js/sidebar/steps/Foot.vue'));

        $this->assertMatchesRegularExpression(
            '/:class="footer\.sells \? .bk-cta--sells. : ..?"/',
            $foot,
            'El pie ha dejado de sacar su relleno del dato `sells`. Si la regla vuelve al marcado —o se '.
            'deduce del rótulo o del `action`—, el día que alguien añada un paso que cobre la plantilla '.
            'no se enterará y el botón saldrá en tinta SIN QUE NADA FALLE.',
        );

        // ⚠️⚠️ **AQUÍ HABÍA TRES ASERCIONES SOBRE LA SINTAXIS DE `foot.js` Y SE RETIRAN** (`#554`):
        // contaban literales `sells: false`, la firma de `cartFooter(… sells = false)` y la llamada del
        // paso 08 con su `true`. Las tres se pusieron ROJAS con el producto sano en cuanto el módulo
        // separó el pie de la cesta del de pagar — y la de contar literales **ya había fallado antes
        // por lo mismo y su propio comentario lo advertía**. *Una guarda que describe cómo está
        // escrita una regla envejece con cada forma nueva de escribirla.*
        //
        // ▶ **Lo que protegían lo cubre `foot.test.js` por CONDUCTA, y más fuerte**: «las cuatro formas
        // del pie declaran el campo» exige `typeof sells === 'boolean'` —que caza también un `null` o
        // un `undefined` explícito, invisibles para una regex— y «solo el pie del paso 08 declara que
        // vende» compara el conjunto de los que valen `true` contra `[8]`. Y esos casos **están en el
        // gate**: el `pre-push` corre `npm run test:js`.
        // ▶ Lo que queda aquí es lo que ningún test de módulo puede ver: que la PLANTILLA saque la
        // clase del dato, que es la aserción de arriba.
        $this->assertStringContainsString(
            'assert.deepEqual(venden, [8]',
            (string) file_get_contents(base_path('resources/js/sidebar/foot.test.js')),
            'El caso de `foot.test.js` que fija el mapa del naranja por conducta ha desaparecido, y esta '.
            'guarda delega en él: sin ese caso, quién vende deja de estar vigilado en ninguna parte.',
        );
    }

    public function test_only_the_buttons_that_charge_carry_the_bare_action_fill(): void
    {
        $culpables = [];

        foreach ($this->fuentesDelCajon() as $rel => $fuente) {
            $conAccion = [];

            foreach ($this->botones($fuente) as $etiqueta) {
                if ($this->rellenaConAccion($etiqueta)) {
                    $conAccion[] = mb_substr(preg_replace('/\s+/', ' ', $etiqueta) ?? '', 0, 90);
                }
            }

            $permitidos = self::VENDEN[$rel][0] ?? 0;

            // ⚠️ Se compara el RECUENTO, no la presencia: exceptuar un fichero exceptuaría también
            // cualquier botón naranja que alguien meta después dentro de él.
            if (count($conAccion) > $permitidos) {
                foreach (array_slice($conAccion, $permitidos) as $etiqueta) {
                    $culpables[] = $rel.' → '.$etiqueta;
                }
            }
        }

        $this->assertSame(
            [],
            $culpables,
            "Estos botones del cajón llevan `.btn` PELADO, o sea el relleno de ACCIÓN:\n  ".
            implode("\n  ", $culpables)."\n\n".
            "▶ `.btn` a secas hereda el rol de la base de la familia, y la base ES la acción: pintar\n".
            "  «lo demás» con la clase base no es neutro.\n".
            "▶ Si el botón NO cobra, su variante es `.btn--ink` (secundario, relleno de tinta) o\n".
            '  `.btn--ghost`. Si SÍ cobra, añádelo a `VENDEN` con su porqué.',
        );
    }

    /** Y los cuatro que venden siguen existiendo: una lista que solo encoge necesita sujeto. */
    public function test_every_selling_button_still_has_a_subject(): void
    {
        foreach (self::VENDEN as $rel => [$cuantos, $motivo]) {
            $ruta = base_path('resources/js/sidebar/'.$rel);

            $this->assertFileExists($ruta, "`{$rel}` ya no existe: sácalo de `VENDEN`, que solo encoge.");

            $botones = array_filter(
                $this->botones((string) file_get_contents($ruta)),
                fn (string $etiqueta): bool => $this->rellenaConAccion($etiqueta),
            );

            // ⚠️ Se exige el número EXACTO, no «al menos uno»: si el botón que justificaba la excepción
            // desaparece y aparece otro distinto, el recuento cuadra y la excepción sobrevive sin sujeto.
            $this->assertCount(
                $cuantos,
                $botones,
                "`{$rel}` ya no lleva {$cuantos} botón(es) de relleno de acción ({$motivo}): ".
                'ajusta `VENDEN` o devuélvele su variante.',
            );
        }
    }

    public function test_the_brand_census_is_exactly_the_declared_one(): void
    {
        $encontradas = [];

        foreach ($this->reglasDelCajon() as $selector => $cuerpo) {
            if (preg_match('/--zone-[12]\b/', $cuerpo)) {
                $encontradas[] = $selector;
            }
        }

        sort($encontradas);
        $esperadas = array_keys(self::CENSO_DE_MARCA);
        sort($esperadas);

        $this->assertSame(
            $esperadas,
            $encontradas,
            "El censo de `--zone-*` dentro del cajón ha cambiado (grieta 01).\n".
            "▶ Eran 29 reglas y quedan 13, cada una con su motivo en `CENSO_DE_MARCA`.\n".
            "▶ La lista SOLO ENCOGE: si una pieza deja de leer la marca, retírala de ahí; si una\n".
            '  nueva la lee, escribe por qué — o úsala con su rol (`--action`, `--interactive`, `--ok`, `--fg`).',
        );
    }

    /**
     * **Las clases COMPARTIDAS cuyo color se corrige acotado al panel.**
     *
     * ⚠️⚠️ Ninguna de las cuatro la ve el censo del cajón, y es correcto: `.check`, `.form__hint` y
     * `.zone-tab` las emiten también el formulario de contacto, el post-form, el justificante, tarifas
     * y `/servicios`. Su regla BASE se queda como está —la sube el carril que vista esa superficie— y
     * dentro del panel manda el bloque acotado.
     *
     * ❗ **La de la pestaña la cazó una CAPTURA, no una lectura**: `.zone-tab.active` tiñe con el color
     * de la zona, y en el cajón esa pestaña elige entre «Entrar» y «Crear cuenta» — donde no hay
     * ninguna zona. Un censo que acota por vocabulario de clase no puede ver una clase prestada.
     */
    public function test_the_shared_colours_are_corrected_only_inside_the_panel(): void
    {
        $hoja = (string) file_get_contents(base_path('public/css/site.css'));

        $acotadas = [
            // ⚠️ **El `<button>` entra en `#566`**: `NoPasswordHint` pinta uno dentro de una pista y
            // no tenía regla, así que heredaba el gris del párrafo y salía sin subrayado — el defecto
            // que `#350` midió para el enlace de privacidad, una pantalla más allá.
            '.sidecart__panel .form__hint button { color: var(--interactive); }' => 'el enlace —o el botón— de una pista',
            '.sidecart__panel .check span a { color: var(--interactive); }' => 'el enlace legal de una casilla',
            '.sidecart__panel .check input { accent-color: var(--fg); }' => 'el tilde de la casilla',
            '.sidecart__panel .zone-tab.active { background: var(--fg); color: var(--bg); border-color: var(--fg); }' => 'la pestaña de «Entrar / Crear cuenta»',
        ];

        foreach ($acotadas as $regla => $sujeto) {
            $this->assertStringContainsString(
                $regla,
                $hoja,
                "Falta la regla acotada al panel de {$sujeto}: sin ella vuelve a leer el color de la MARCA ".
                'dentro del cajón, y su regla base no se puede tocar porque la comparten otras superficies.',
            );
        }

        // El contrario: si alguien «termina el trabajo» tocando la regla BASE, cambia el color de la
        // web y del post-form sin que nadie lo haya decidido.
        $this->assertMatchesRegularExpression(
            '/\.zone-tab\.active \{ background: var\(--zone-1\)/',
            (string) file_get_contents(base_path('public/css/landing.css')),
            'La regla BASE de `.zone-tab.active` ha cambiado: ahí la pestaña SÍ identifica una zona '.
            '(tarifas y `/servicios`), y ese carril es del otro ordenador.',
        );
    }

    public function test_the_rescued_text_roles_read_their_role(): void
    {
        $reglas = $this->reglas();

        foreach (self::TEXTOS_RESCATADOS as $selector => $token) {
            $this->assertArrayHasKey(
                $selector,
                $reglas,
                "`{$selector}` ya no tiene regla: este caso miraría el vacío.",
            );

            $this->assertMatchesRegularExpression(
                '/color:\s*var\('.preg_quote($token, '/').'\)/',
                $reglas[$selector],
                "`{$selector}` ya no lee `var({$token})`.\n".
                "⚠️ Este rol de TEXTO se pintaba con `--zone-1` y medido daba entre 2,21 y 2,70 sobre\n".
                "  papel — por debajo del 3,0 que es el suelo de un texto GRANDE. La hoja de componentes\n".
                '  del sistema lo dice con su número: «en claro el secundario es tinta, nunca cian (2.45)».',
            );
        }
    }

    /** @return array<string, string> ruta relativa → fuente, de los componentes y módulos del cajón */
    private function fuentesDelCajon(): array
    {
        $base = base_path('resources/js/sidebar');
        $out = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $file) {
            $path = $file->getPathname();
            if (str_ends_with($path, '.vue')) {
                $out[ltrim(str_replace($base, '', $path), '/')] = (string) file_get_contents($path);
            }
        }

        return $out;
    }

    /**
     * Las etiquetas de apertura de `<button>` y `<a>` de una fuente, con los comentarios fuera.
     *
     * ⚠️ Se desnudan las TRES formas de comentario de un `.vue` —docblock, línea y marcado—: dos
     * fuentes del cajón citan la clase retirada para contar su historia, y eso es verdadero.
     */
    private function botones(string $fuente): array
    {
        $limpio = (string) preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m', '#<!--.*?-->#s'], '', $fuente);

        preg_match_all('/<(?:button|a)\b[^>]*>/s', $limpio, $m);

        return $m[0];
    }

    /**
     * ¿Esta etiqueta rellena con el rol de ACCIÓN?
     *
     * Es `.btn` **sin ninguna otra clase que declare su propio relleno**.
     *
     * ⚠️⚠️ **Se mira el `class` Y el `:class` juntos**, porque media docena de botones del cajón
     * reparten la variante en el dinámico (`:class="cta.primary ? 'btn--ink' : 'btn--ghost'"`): un
     * escáner que solo lea el estático los acusa a todos, y uno que solo lea el dinámico no ve ninguno.
     *
     * ⚠️⚠️ **Y «qué clase declara un relleno» se MIDE en las hojas, no se escribe a mano.** La primera
     * versión de este predicado conocía solo `btn--ink` y `btn--ghost`, y acusó a dos botones que están
     * perfectamente bien: el de **borrar la cuenta** (`.account__delete-btn`, que rellena con `--err`,
     * que es lo correcto para una acción destructiva) y el de **entrar con Google**
     * (`.auth__google`, blanco con su borde — la ÚNICA excepción escrita del sistema, `#345`).
     * ▶ *Una lista de variantes escrita a mano convierte en excepción todo lo que no se le ocurrió a
     * quien la escribió, y entonces el mensaje de la guarda miente.*
     */
    private function rellenaConAccion(string $etiqueta): bool
    {
        foreach ($this->ramasDeClase($etiqueta) as $rama) {
            if (! preg_match('/(?<![\w-])btn(?![\w-])/', $rama)) {
                continue;
            }

            $tieneRelleno = false;

            foreach ($this->clasesQueRellenan() as $clase) {
                if ($clase !== 'btn' && preg_match('/(?<![\w-])'.preg_quote($clase, '/').'(?![\w-])/', $rama)) {
                    $tieneRelleno = true;
                    break;
                }
            }

            if (! $tieneRelleno) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los conjuntos de clases que este elemento puede llegar a tener.
     *
     * ❗❗❗ **UNA RAMA POR ALTERNATIVA DEL `:class`, y esto lo pidió el ARNÉS.** La primera versión unía
     * todos los literales del atributo en un solo texto, así que un ternario como
     * `:class="cta.primary ? 'btn--ink' : ''"` parecía llevar siempre `btn--ink` — y la rama vacía, que
     * es la que deja el botón en `.btn` pelado (o sea en NARANJA), pasaba invisible. La mutación
     * «el canal secundario del aviso vuelve a `.btn` pelado» **SOBREVIVÍA**.
     *
     * ▶ *Un escáner que une las ramas de un condicional no mide ninguna de las dos.*
     *
     * @return list<string>
     */
    private function ramasDeClase(string $etiqueta): array
    {
        preg_match('/(?<!:)class="([^"]*)"/', $etiqueta, $e);
        $estatico = trim($e[1] ?? '');

        if (! preg_match('/:class="([^"]*)"/', $etiqueta, $d)) {
            return [$estatico];
        }

        // ⚠️⚠️ **En un ternario solo son clases los literales DE DESPUÉS del `?`**, y esto también lo
        // pidió el arnés: con todos los literales del atributo, la comparación
        // `row.guestForm.state === 'pending' ? …` metía `pending` como si fuera una clase, y entonces
        // aparecía una rama `btn orders__guestform-btn pending` —`.btn` pelado— que no existe.
        // ▶ *Un literal dentro de una expresión no es un valor de la expresión.*
        $expresion = $d[1];

        if (str_contains($expresion, '?')) {
            $expresion = substr($expresion, (int) strpos($expresion, '?') + 1);
        }

        preg_match_all("/'([^']*)'/", $expresion, $lits);

        if ($lits[1] === []) {
            // Un `:class` sin literales (una variable, un objeto calculado) no se puede leer desde
            // aquí: se mide solo el estático, que es el lado estricto.
            return [$estatico];
        }

        $ramas = [];

        foreach ($lits[1] as $literal) {
            $ramas[] = trim($estatico.' '.trim($literal));
        }

        return array_values(array_unique($ramas));
    }

    /**
     * Las clases de las hojas del producto que declaran un `background` propio **en reposo**.
     *
     * Son las que pueden sacar a un botón del rol de acción. Se excluyen los estados (`:hover`,
     * `:disabled`…): un relleno que solo existe al pasar el cursor no describe el reposo del botón.
     *
     * @return list<string>
     */
    private function clasesQueRellenan(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];

        foreach ($this->reglas() as $selector => $cuerpo) {
            if (! preg_match('/(?:^|;)\s*background(?:-color)?:/', $cuerpo)) {
                continue;
            }

            foreach (explode(',', $selector) as $parte) {
                $parte = trim($parte);

                // Solo el ÚLTIMO componente del selector y solo si es una clase pelada: `.x:hover`,
                // `.a .b` y `[attr]` describen un estado o un descendiente, no el reposo del botón.
                if (preg_match('/^\.([\w-]+)$/', $parte, $m)) {
                    $cache[] = $m[1];
                }
            }
        }

        $cache = array_values(array_unique($cache));

        return $cache;
    }

    /** @return array<string, string> las reglas cuyo selector es vocabulario del cajón */
    private function reglasDelCajon(): array
    {
        $out = [];

        foreach ($this->reglas() as $selector => $cuerpo) {
            foreach (self::PREFIJOS_DEL_CAJON as $prefijo) {
                if (str_contains($selector, '.'.$prefijo)) {
                    $out[$selector] = $cuerpo;
                    break;
                }
            }
        }

        return $out;
    }

    /** @return array<string, string> selector → cuerpo, con los comentarios blanqueados */
    private function reglas(): array
    {
        $out = [];

        foreach (self::SHEETS as $hoja) {
            $css = (string) file_get_contents(base_path($hoja));
            // Los comentarios se BLANQUEAN, no se borran: estas hojas tienen llaves y nombres de clase
            // dentro de ellos (`#482`), y un escáner que no los enmascara abre reglas donde no las hay.
            $ciego = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $ciego, $matches, PREG_SET_ORDER);

            foreach ($matches as $regla) {
                $selector = trim((string) preg_replace('/\s+/', ' ', $regla[1]));
                $selector = (string) preg_replace('/^@media[^{]*\{\s*/', '', $selector);

                if ($selector === '' || str_starts_with($selector, '@')) {
                    continue;
                }

                $out[$selector] = ($out[$selector] ?? '').' '.trim($regla[2]);
            }
        }

        return $out;
    }
}
