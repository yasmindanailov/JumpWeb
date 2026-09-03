<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **El PULIDO del cajón tras la prueba del owner en staging** — las cuatro reglas que ningún gate
 * anterior podía ver (`docs/specs/identidad-qr-puerta.md` §9.7 C·1/C·2/C·4 y
 * `docs/specs/menores-a-cargo.md` §9.11 D·2, `DECISIONES #217`).
 *
 * ⚠️⚠️ **Los cuatro defectos que arregla este trabajo eran INVISIBLES para la suite, y los cuatro
 * por el mismo motivo**: el contrato de árbol (`SidebarDomContractTest`) compara ETIQUETAS, CLASES y
 * anidamiento; el presupuesto de tokens mide la CALIDAD de las reglas que existen; y
 * `SidebarStyleWiringTest` comprueba que cada clase emitida tenga alguna regla. Ninguno pregunta
 * **qué hace esa regla**. Por eso pasaron en verde:
 *  - un índice de ocho filas idénticas donde el owner no encontraba lo suyo (C·1);
 *  - una fila `disabled` cuyo rótulo se veía exactamente igual que una activa —`.check` le pone
 *    `cursor: pointer` y nadie le quitaba la opacidad—, con el motivo pegado al nombre dentro del
 *    mismo `<span>` (D·2): el owner lo leyó como «no me deja darle al checkbox»;
 *  - una confirmación que pintaba el NAVEGADOR fuera del cajón y que el owner no llegó a ver (C·4).
 *
 * ▶ Lo que se asevera aquí es **la conducta, no la clase**: que la rejilla tenga dos columnas de
 * verdad, que el icono crezca por CSS y NUNCA por el atributo del `<svg>` (que es la copia byte a
 * byte de `SidebarIconParityTest`), que el aviso de renovar viva FUERA de la confirmación, y que la
 * fila no asignable se APAGUE y diga su motivo en su propio elemento.
 */
class SidebarDrawerPolishTest extends TestCase
{
    private const HOME = 'resources/js/sidebar/account/zones/AccountHomeZone.vue';

    private const PANEL = 'resources/js/sidebar/account/AccountPanel.vue';

    private const CARD = 'resources/js/sidebar/account/zones/CardZone.vue';

    private const PICKER = 'resources/js/sidebar/steps/DependentPicker.vue';

    private const DEPENDENTS = 'resources/js/sidebar/account/zones/DependentsZone.vue';

    private const ICONS = 'resources/js/sidebar/account/ZoneIcon.vue';

    private const SHEET = 'public/css/site.css';

    /**
     * ⚠️⚠️ **La guarda de la guarda, y aquí no es ceremonia.** Los seis casos de abajo son
     * `assertStringContainsString` sobre ficheros: si una ruta se renombra, `file_get_contents`
     * devolvería `''` y **todos los `assertStringNotContainsString` pasarían solos** —que es la mitad
     * de este fichero—. Es literalmente el modo de fallo de `PurchaseRetirementTest` (`DECISIONES
     * #63`) y el de la paridad de iconos, que miraba 22 de 32 componentes.
     */
    public function test_the_scan_actually_reads_every_file_it_judges(): void
    {
        $anclas = [
            self::HOME => 'HOME_ENTRIES',
            self::PANEL => 'acct__inner',
            self::CARD => 'account.card.intro',
            self::PICKER => 'dependents.title',
            self::DEPENDENTS => 'account.dependents.intro',
            self::ICONS => 'catalog__ico',
            self::SHEET => '.acct__inner',
        ];

        foreach ($anclas as $ruta => $ancla) {
            $this->assertStringContainsString(
                $ancla, $this->source($ruta),
                "El escaneo no está leyendo «{$ruta}»: sin esto, la mitad de este fichero pasaría sola."
            );
        }
    }

    // ── C·1 · El índice en TARJETAS ───────────────────────────────────────────────────────────

    /**
     * ⚠️ **DOS columnas de verdad, y no «una rejilla»**: `grid` con una sola columna es una lista con
     * otro nombre, que es justo lo que el owner ya tenía. La cifra se asevera porque es la decisión.
     */
    public function test_the_account_index_is_a_two_column_grid_of_tiles(): void
    {
        $home = $this->source(self::HOME);

        $this->assertStringContainsString('class="acc-tiles"', $home);
        $this->assertStringContainsString('class="acc-tile"', $home);

        // ⚠️ Y NO reutiliza la fila del catálogo: cambiarla para que aquí se vean tarjetas repintaría
        // el paso 1 del embudo, que es la primera pantalla de la compra.
        $this->assertStringNotContainsString(
            'catalog__item', $this->code($home),
            'El índice ha vuelto a la fila del CATÁLOGO DE PRODUCTOS. Esa clase es del paso 1 del '.
            'embudo: compartirla ata el aspecto del área de cliente al de la compra.'
        );

        $this->assertMatchesRegularExpression(
            '/\.acc-tiles\s*\{[^}]*grid-template-columns:\s*repeat\(2,/',
            $this->source(self::SHEET),
            'La rejilla del índice ha dejado de tener DOS columnas. Con una es la lista de antes.'
        );
    }

    /**
     * ⚠️⚠️ **El icono crece por CSS y los atributos del `<svg>` NO se tocan.** Son la copia byte a
     * byte del `<x-icons.*>` que compara `SidebarIconParityTest`: subirlos a 26 allí pondría ese gate
     * en rojo y, peor, dejaría el dibujo del cajón distinto del de la web. Esta guarda fija el
     * mecanismo —regla de CSS— **y** que la tentación no se haya consumado.
     */
    public function test_the_tile_icon_grows_by_css_and_never_by_the_svg_attribute(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.acc-tile__ico svg\s*\{[^}]*width:\s*26px/',
            $this->source(self::SHEET),
            'La regla que agranda el icono de la tarjeta ha desaparecido: volvería a verse a 18 px.'
        );

        $iconos = $this->source(self::ICONS);

        // ⚠️⚠️ **La frontera de palabra no es cosmética: sin ella esto casa DENTRO de
        // `stroke-width`.** Lo destapó `#257`, al entrar en el cajón el primer icono del set nuevo
        // que pinta con trazo (`qr`, que declara `stroke-width="3"` en el `<svg>`): la guarda se
        // puso roja con el producto sano. Es la tercera vez en dos días que un patrón sin
        // `(?<![-\w])` mide otra cosa —también le pasó al extractor de esta misma tanda—.
        $this->assertSame(
            0, preg_match_all('/<svg[^>]*(?<![-\w])width="(?!18")/', $iconos),
            "Algún icono de `ZoneIcon.vue` ha dejado de medir 18: esos atributos son la COPIA del\n".
            'componente Blade, y el tamaño de la tarjeta se decide en CSS.'
        );
    }

    // ── C·2 · «Mi QR» junto al nombre ─────────────────────────────────────────────────────────

    /**
     * ⚠️ **La sub-línea se retira de la cara identificada y solo de ella.** La de invitado dice otra
     * cosa —por qué merece la pena entrar— y no la repite nadie, así que el recuento tiene que ser
     * EXACTAMENTE uno: con `assertStringNotContainsString` se iría también la del invitado sin que
     * nada avisara, y con `assertStringContainsString` volvería la de arriba sin que nada avisara.
     */
    public function test_the_next_booking_line_lives_in_one_place_and_the_qr_shortcut_took_its_seat(): void
    {
        $panel = $this->source(self::PANEL);

        $this->assertSame(
            1, substr_count($panel, 'class="acct__sub"'),
            "La sub-línea del bloque de cuenta tiene que existir UNA vez, en la cara de INVITADO.\n".
            "⚠️ Si son dos, ha vuelto la próxima reserva bajo el nombre y se dice en dos sitios.\n".
            'Si son cero, el invitado se ha quedado sin el motivo para entrar.'
        );

        $this->assertSame(
            1, substr_count($panel, 'accountStore.openZone(ZONES.CARD)'),
            'El atajo «Mi QR» del bloque de cuenta ya no pide su zona: sería un botón que no falla y '.
            'no hace nada (`DECISIONES #117`).'
        );

        // ⚠️ El rótulo sale del título de SU zona: un texto propio sería un segundo nombre para la
        // misma pantalla, y el cliente creería que va a otro sitio.
        $this->assertStringContainsString('{{ panel.card }}', $panel);
    }

    // ── C·4 · Renovar, con aviso permanente y confirmación propia ─────────────────────────────

    /**
     * ⚠️⚠️ **Las DOS mitades, y la primera es la que el owner echó en falta.** El aviso de que el QR
     * anterior deja de valer tiene que estar SIEMPRE visible: dentro del diálogo solo lo leía quien
     * ya había pulsado. Se asevera que `rotate_notice` está en la plantilla **y fuera** del bloque
     * `v-if="asking"` — que es exactamente la diferencia entre avisar y avisar tarde.
     */
    public function test_renewing_warns_always_and_confirms_inside_the_drawer(): void
    {
        $card = $this->source(self::CARD);

        $this->assertStringNotContainsString(
            'window.confirm', $this->code($card),
            "«Mi carné» ha vuelto al diálogo del NAVEGADOR. Sale fuera del cajón, no habla nuestros\n".
            'tres idiomas y el owner no llegó a verlo en su recorrido por staging.'
        );

        $this->assertStringContainsString("a('account.card.rotate_notice')", $card);

        $bloque = $this->between($card, '<div v-if="asking"', '</section>');

        $this->assertStringNotContainsString(
            'rotate_notice', $bloque,
            "El aviso se ha metido DENTRO de la confirmación: así solo lo lee quien ya ha pulsado, y\n".
            'volveríamos al defecto que este cambio arregla.'
        );

        // Dos salidas: confirmar y cancelar. Con una sola, la pregunta es una trampa.
        $this->assertSame(2, substr_count($bloque, '<button'), 'la confirmación necesita SUS DOS botones');
        $this->assertStringContainsString("a('account.card.rotate_confirm_yes')", $bloque);
        $this->assertStringContainsString("a('account.card.rotate_confirm_no')", $bloque);
    }

    // ── D·2 · El selector de menores ──────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **Que una fila esté `disabled` no significa que se VEA deshabilitada**, y esa distancia
     * es el defecto entero: medido en navegador, el rótulo conservaba `opacity: 1` y `cursor:
     * pointer` porque los pone `.check`. Aquí se fija el mecanismo por los dos extremos — la clase se
     * emite por el mismo predicado que deshabilita la casilla, y la hoja la apaga.
     *
     * ⚠️ **Re-apuntado en `#242`, no reescrito**: su sujeto —«una fila bloqueada se ve bloqueada»—
     * sigue vivo. Lo que cambió es que ahora se apaga **también** la fila que no cabe porque la línea
     * está llena (`[OWNER]`, sustituye a la línea «No caben más»), así que las dos condiciones viven
     * en un predicado con nombre en vez de en línea.
     */
    public function test_a_minor_that_cannot_be_assigned_looks_disabled(): void
    {
        $this->assertStringContainsString(
            ':class="blocked(option) ? \'dep-pick__row--off\' : \'\'"',
            $this->source(self::PICKER),
            'La fila de un menor bloqueado ha dejado de marcarse: se vería idéntica a una activa.'
        );

        $hoja = $this->source(self::SHEET);

        $this->assertMatchesRegularExpression(
            '/\.dep-pick__row--off \.dep-pick__pick\s*\{[^}]*opacity:/', $hoja,
            'La fila apagada ha dejado de atenuarse.'
        );
        $this->assertMatchesRegularExpression(
            '/\.dep-pick__row--off \.dep-pick__pick\s*\{[^}]*cursor:\s*not-allowed/', $hoja,
            "La fila apagada ha vuelto a ofrecer el cursor de «púlsame», que es lo que `.check` pone\n".
            'por defecto y lo que hizo al owner insistir sobre una casilla que nunca iba a marcarse.'
        );
    }

    /**
     * ⚠️ **El motivo va en SU elemento**, no dentro del `<span>` del nombre: fundidos se leían como
     * una sola frase («Vera · 6 años — exención sin firmar») y el motivo parecía parte del nombre.
     */
    public function test_the_reason_is_its_own_element_and_not_glued_to_the_name(): void
    {
        $picker = $this->source(self::PICKER);

        $this->assertMatchesRegularExpression(
            '/<p v-if="option\.reasonKey"[^>]* class="dep-pick__why">/', $picker,
            'El motivo ha dejado de tener elemento propio.'
        );

        // ⚠️ **Y sigue ANUNCIÁNDOSE** (2026-08-28, revisión de `#217`): sacar el motivo del `<label>`
        // lo hizo legible para el ojo y lo desconectó del lector de pantalla, que pasó a decir
        // «casilla, no disponible» sin decir por qué. `aria-describedby` los vuelve a unir sin
        // devolver el texto dentro del rótulo — y el `id` lleva `scope` porque en el paso 4 este
        // componente se pinta UNA VEZ POR LÍNEA con la misma lista: sin prefijo, dos filas
        // distintas apuntarían al mismo `id` y el lector leería el motivo de otra línea.
        $this->assertStringContainsString(
            ':aria-describedby="option.reasonKey ? whyId(option.id) : null"', $picker,
            'La casilla deshabilitada ha dejado de decir POR QUÉ a un lector de pantalla.'
        );

        $this->assertStringContainsString(
            'const whyId = (id) => `dep-why-${props.scope}-${id}`;', $picker,
            'El `id` del motivo ha dejado de llevar el prefijo por línea: en el carrito se repetiría.'
        );

        $quien = $this->between($picker, '<span class="dep-pick__who">', '</label>');

        $this->assertStringNotContainsString(
            'reasonKey', $quien,
            'El motivo ha vuelto a meterse dentro del bloque del nombre: es la mitad del defecto que '.
            'el owner cazó, y el árbol congelado no lo vería (compara nodos, no texto).'
        );
    }

    /**
     * ⚠️⚠️ **La regla de negocio NO se relaja**: en modo interno un menor sin exención firmada no es
     * asignable (`[DECIDIDO owner]` `#202`·2), y el servidor lo rechaza igual. El rediseño cambia
     * cómo se LEE, no quién puede marcarse — este caso lo deja escrito donde se vería la tentación.
     */
    public function test_the_redesign_did_not_touch_who_can_be_assigned(): void
    {
        $picker = $this->source(self::PICKER);

        // ⚠️ **Re-apuntado en `#242`**: la condición se mudó a un predicado con nombre porque ahora la
        // usan DOS sitios —la casilla y la clase de la fila—, y escribirla dos veces es como diverge.
        // Lo que se asevera sigue siendo lo mismo: **las dos mitades de la regla del owner**.
        $this->assertStringContainsString(':disabled="blocked(option)"', $picker);

        $this->assertStringContainsString(
            'const blocked = (option) => ! option.assignable || (full.value && ! checked(option.id));', $picker,
            "La condición que deshabilita una casilla ha cambiado. Es la regla del owner, no estilo:\n".
            'sin exención firmada no se asigna, y con la línea llena no caben más.'
        );

        // ⚠️⚠️ Y el motivo NO puede irse con ella: apagar sin decir por qué deja al titular sin saber
        // qué hacer. Es la mitad que `#242` conserva a propósito al retirar el estado positivo.
        $this->assertStringContainsString('class="dep-pick__why"', $picker);
    }

    /**
     * ⚠️⚠️ **«Exención firmada» se RETIRÓ, y el hueco no se queda vacío** (`DECISIONES #242`,
     * `[OWNER]`: «es innecesario, porque es obvio: no podemos asignar menores sin firmar la
     * exención»). Tiene razón — **una fila marcable ya significa que está en regla**—, así que el
     * rótulo repetía con palabras lo que el control decía solo.
     *
     * En su sitio va lo que el control NO puede decir: que a ese menor ya se le asignó una entrada.
     * Y va **en segundo plano**: es una nota al margen del nombre, no una medalla.
     */
    public function test_the_assigned_hint_replaced_the_obvious_waiver_status(): void
    {
        $picker = $this->source(self::PICKER);

        // El estado positivo se fue de las tres capas: el módulo, el componente y el diccionario.
        $this->assertStringNotContainsString('statusKey', $picker, 'El estado positivo ha vuelto al selector.');
        $this->assertStringNotContainsString(
            'statusFor', $this->source('resources/js/sidebar/assignment.js'),
            'El estado positivo ha vuelto al módulo que compone las opciones.'
        );
        $this->assertStringNotContainsString(
            "'signed' =>", $this->source('lang/es/tickets.php'),
            'El rótulo «exención firmada» sigue en el diccionario del embudo.'
        );

        // Y el aviso nuevo va en la fila MARCADA, no en cualquiera.
        $this->assertStringContainsString(
            '<span v-if="checked(option.id)" class="dep-pick__ok">{{ t(\'dependents.assigned\') }}</span>',
            $picker,
            'El aviso de «1 entrada asignada» ha dejado de colgar de que el menor esté marcado.'
        );

        // Sutil: sin mayúsculas forzadas y sin el peso que tenía el estado que sustituye.
        $hoja = $this->source(self::SHEET);
        $ok = $this->between($hoja, '.dep-pick__ok {', '}');

        $this->assertStringNotContainsString(
            'text-transform: uppercase', $ok,
            'El aviso ha vuelto a gritar. `[OWNER]`: «al lado de manera SUTIL».'
        );
        $this->assertStringNotContainsString('var(--fw-bold)', $ok, 'El aviso ha vuelto a llevar peso de negrita.');
    }

    /**
     * ⚠️ **La línea «No caben más» se sustituye por apagar las filas que no caben** (`#242`,
     * `[OWNER]`). Con una entrada y tres menores dice lo mismo y ahorra una línea, que en un cajón de
     * 390 px es sitio de verdad. Verificado en navegador: al marcar uno, las otras dos filas quedan
     * apagadas con opacidad 0,55, y al desmarcar VUELVEN.
     */
    public function test_the_full_line_became_two_dimmed_rows(): void
    {
        $picker = $this->source(self::PICKER);

        // ⚠️ Se prohíbe la ESTRUCTURA, no solo el rótulo. La primera versión miraba únicamente la
        // clave `dependents.full`, y una mutación que reintrodujera la línea con otro texto pasaba en
        // verde: lo que el owner retiró es **una línea que ocupa alto**, se llame como se llame.
        $this->assertDoesNotMatchRegularExpression(
            '/<p[^>]*v-if="full"/', $picker,
            'La línea «No caben más» ha vuelto: el owner pidió apagar las filas en su lugar.'
        );
        $this->assertStringNotContainsString('dependents.full', $picker);
        $this->assertStringNotContainsString(
            "'full' =>", $this->source('lang/es/tickets.php'),
            'El rótulo «No caben más» sigue en el diccionario, sin nadie que lo pinte.'
        );

        // ⚠️ Y lo que NO se puede perder al apagar por «lleno»: la fila que no se puede marcar NUNCA
        // sigue llevando su MOTIVO. Es lo único que no es obvio de una fila apagada.
        $this->assertStringContainsString('class="dep-pick__why"', $picker);
    }

    // ── EL QR COMO CREDENCIAL (encargo del owner, 2026-08-28) ─────────────────────────────────

    /**
     * ⚠️⚠️ **El QR se enmarca con el marco que el producto YA TIENE, no con uno nuevo.**
     * `qr-frame` / `qr-tile` / `qr-slot` y las cuatro `qr-corner` son las clases con las que
     * `<x-site.registration-qr>` dibuja un QR en la landing desde `#268`. Reutilizarlas es lo que hace
     * que el mismo objeto se vea igual en las dos superficies y que un paquete de instalación que
     * retoque ese marco las retoque las dos — inventar `qr-pass__frame` habría dejado dos dibujos del
     * mismo objeto divergiendo en silencio, que es la deriva de siempre.
     *
     * ▶ Se asevera por los DOS extremos: que la zona las emite **y** que la hoja las define. Solo lo
     * primero pasaría igual el día que alguien retirase el bloque de la landing.
     */
    public function test_the_qr_is_framed_with_the_frame_the_landing_already_uses(): void
    {
        $card = $this->source(self::CARD);
        $hoja = $this->source(self::SHEET);

        foreach (['qr-frame', 'qr-tile', 'qr-slot', 'qr-corner--tl', 'qr-corner--tr', 'qr-corner--bl', 'qr-corner--br'] as $clase) {
            // ⚠️ Dentro de un `class="…"`, no la cadena suelta: las esquinas van como
            // `class="qr-corner qr-corner--tl"` y buscar `class="qr-corner--tl` no las encuentra
            // (falló así al escribir este caso). Y suelta casaría con la prosa del docblock.
            $this->assertMatchesRegularExpression(
                '/class="[^"]*'.preg_quote($clase, '/').'(?![\w-])/', $card,
                "«Mi QR» ha dejado de usar `{$clase}`, del marco de `<x-site.registration-qr>`."
            );
            $this->assertMatchesRegularExpression(
                '/\.'.preg_quote($clase, '/').'(?![\w-])/', $hoja,
                "La regla de `.{$clase}` ya no existe: el marco del QR se serviría sin dibujar."
            );
        }

        // Y la imagen va DENTRO del hueco del marco: fuera de él no la recorta ni la cuadra nadie.
        $this->assertMatchesRegularExpression(
            '/<div class="qr-slot">\s*<img /', $card,
            'La imagen del QR ha salido del `qr-slot`: volvería a ser el «QR así suelto» del encargo.'
        );
    }

    /**
     * ⚠️⚠️ **La regla que se rompe en el CAJÓN y en ningún otro sitio.** `.qr-tile` nace con
     * `width: var(--qr-size)` y `--qr-size` lo define `.regcard`, que aquí no existe; y el `<img>` trae
     * `width="264"` en atributo, que es su tamaño natural. Sin las tres declaraciones de abajo el
     * recuadro se sale del panel —que mide `min(440px, 100%)` y a 380 px de pantalla deja ~300 px de
     * interior— y el cliente ve el QR cortado. **Medido en navegador a 380 px**, que es donde lo vería
     * el owner: ningún gate de árbol, de clases ni de tokens puede ver un desbordamiento.
     */
    public function test_the_qr_is_measured_against_the_real_width_of_the_drawer(): void
    {
        $hoja = $this->source(self::SHEET);

        $this->assertMatchesRegularExpression(
            '/\.qr-pass\s*\{[^}]*--qr-size:/', $hoja,
            "`.qr-pass` ha dejado de dar valor a `--qr-size`.\n".
            '⚠️ Lo lee `.qr-tile`, y sin él ese `width` es una declaración inválida: el marco nace a `auto`.'
        );
        $this->assertMatchesRegularExpression(
            '/\.qr-pass \.qr-tile\s*\{[^}]*width:\s*min\(var\(--qr-size\), 100%\)/', $hoja,
            'El recuadro del QR ha dejado de encoger con el cajón: a 380 px se sale del panel.'
        );
        $this->assertMatchesRegularExpression(
            '/\.qr-pass \.qr-slot img\s*\{[^}]*width:\s*100%/', $hoja,
            "La imagen ha dejado de ajustarse al hueco: conservaría sus 264 px de atributo.\n".
            '⚠️ La regla hermana (`.qr-slot svg`) NO la alcanza: la landing mete un SVG y aquí va un `<img>`.'
        );
    }

    /**
     * ⚠️ **La jerarquía de las dos acciones es la decisión, no el estilo.** Renovar **invalida en el
     * acto** el QR del correo y cualquier copia impresa (§4.5): una acción destructiva y rara no puede
     * llevar el botón principal, y descargar —lo que el cliente hace de verdad con su QR— sí.
     * Hasta el rediseño era justo al revés: renovar era `btn--zone auth__submit` y descargar un enlace
     * subrayado.
     */
    public function test_downloading_is_the_primary_action_and_renewing_is_the_quiet_one(): void
    {
        $card = $this->code($this->source(self::CARD));

        $this->assertMatchesRegularExpression(
            '/<a class="btn btn--zone"[^>]*download="carne-qr\.png"/', $card,
            'Descargar ha dejado de ser la acción principal de la pantalla.'
        );

        $renovar = $this->between($card, '<div class="qr-pass__renew">', '<p class="account__card-sub');

        $this->assertStringContainsString(
            'btn--ghost', $renovar,
            'El botón de renovar ha vuelto a tener el aspecto de la acción principal.'
        );
        $this->assertStringNotContainsString(
            'auth__submit', $renovar,
            "Renovar ha vuelto a ser el botón a todo el ancho.\n".
            '⚠️ Es lo que MATA el QR del correo y el impreso: no puede ser lo primero que se ofrece.'
        );
    }

    // ── «MENORES A CARGO»: el alta desplegable y la lista paginada ────────────────────────────

    /**
     * ⚠️⚠️ **Una revelación son TRES cosas y con dos parece que funciona**: el `aria-expanded` que
     * sigue al estado, el `aria-controls` que apunta al `id` de lo que abre, y **que ese `id` EXISTA
     * siempre** — por eso el formulario se oculta con `v-show` y no con `v-if`. Con `v-if`, el nodo se
     * va y el `aria-controls` queda colgando: un lector de pantalla anuncia un botón que controla algo
     * que no está. Nada de esto lo ve el contrato de árbol (el área no tiene casos de contrato) ni el
     * cableado de clases.
     */
    public function test_the_add_form_is_a_real_disclosure(): void
    {
        $zona = $this->source(self::DEPENDENTS);

        $this->assertStringContainsString(
            ':aria-expanded="view.adding"', $zona,
            'El disparador del alta ha dejado de anunciar si está abierto.'
        );
        $this->assertStringContainsString('aria-controls="acct-dep-add"', $zona);

        $this->assertMatchesRegularExpression(
            '/<section v-show="view\.adding" id="acct-dep-add"/', $zona,
            "El formulario de alta ha cambiado de mecanismo.\n".
            "⚠️ Con `v-if` el `id` desaparece con él y el `aria-controls` del disparador queda colgando.\n".
            'Lo que hace falta —sacar los dos campos del orden de tabulación— ya lo hace `display: none`.'
        );

        // Y el foco: sin esto, quien abre el formulario con teclado se queda en el botón, tabulando a
        // ciegas hasta el primer campo. Es la mitad de la revelación que nadie echa de menos mirando.
        $this->assertStringContainsString(
            'nextTick(() => nameInput.value?.focus())', $this->code($zona),
            'Al desplegar el alta el foco ya no viaja al primer campo.'
        );
    }

    /**
     * ⚠️⚠️ **Paginar es cortar la lista, y el corte se pierde con UN carácter.** Si el `v-for` vuelve
     * a recorrer `store.items`, el paginador **sigue pintándose y sigue cambiando de página** —los
     * rótulos salen de la cuenta total— pero la lista enseña las veinte tarjetas siempre. Es un verde
     * perfecto en toda la suite y una pantalla rota: por eso se asevera el origen del `v-for`, no la
     * existencia del paginador.
     */
    public function test_the_list_paints_the_page_and_not_the_whole_list(): void
    {
        $zona = $this->source(self::DEPENDENTS);

        $this->assertStringContainsString(
            'v-for="dependent in view.rows(store.items)"', $zona,
            "La lista de menores ha vuelto a recorrerse entera.\n".
            '⚠️ El paginador seguiría pintándose y cambiando de página sin cortar nada.'
        );

        // ⚠️ Y el paginador NO se pinta si no hace falta: `dependentsPager()` devuelve `null` con una
        // sola página (su caso vive en `dependents.test.js`) y aquí se fija que la plantilla lo
        // respeta. Una barra de páginas sobre dos tarjetas es el adorno que el encargo descarta.
        $this->assertMatchesRegularExpression(
            '/<nav v-if="pager" class="pagination dep-page"/', $zona,
            'El paginador ha dejado de depender de que haga falta: se pintaría con dos menores.'
        );
    }

    /**
     * Lo que hay entre dos anclas literales. Sin expresiones regulares a propósito: las dos anclas
     * son marcado, y un `/` dentro de una etiqueta de cierre rompería el delimitador (pasó al
     * escribir esto: `preg_match(): Unknown modifier 'p'`).
     */
    private function between(string $source, string $from, string $to): string
    {
        $start = strpos($source, $from);

        $this->assertNotFalse($start, "no se encuentra el ancla «{$from}»");

        $rest = substr($source, (int) $start);
        $end = strpos($rest, $to);

        $this->assertNotFalse($end, "no se encuentra el cierre «{$to}»");

        return substr($rest, 0, (int) $end);
    }

    /**
     * ⚠️ **El fuente SIN comentarios.** Este fichero documenta en su propio docblock que la
     * confirmación *era* `window.confirm`, así que buscar esa cadena sobre el fichero entero daría un
     * rojo permanente por la prosa que explica el arreglo. Se mide el CÓDIGO, como hace
     * `SidebarComponentBudgetTest`.
     */
    private function code(string $source): string
    {
        $sinBloques = (string) preg_replace('#/\*.*?\*/#s', '', $source);
        $sinHtml = (string) preg_replace('#<!--.*?-->#s', '', $sinBloques);

        return (string) preg_replace('#^\s*//.*$#m', '', $sinHtml);
    }

    private function source(string $relative): string
    {
        return (string) file_get_contents(base_path($relative));
    }

    // ─── Las flechas de las tiras (`#241`) ────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **Las flechas de las tiras SOLO pueden existir donde hay ratón.**
     *
     * Nacen de un defecto real (`[OWNER, 2026-08-28]`: «en escritorio no hay manera de deslizar con el
     * ratón, y no hay flechas — sí o sí hay que desplegar el calendario»), pero en una pantalla táctil
     * son dos botones flotando ENCIMA de la tira: tapan chips y compiten con el gesto que ya funciona.
     *
     * ▶ Y las dos consultas van JUNTAS a propósito: `hover: hover` sola la cumple un táctil con lápiz,
     * que no es este caso. Verificado en navegador las dos mitades — a 1440 px se ven y mueven la tira;
     * a 390 px con `hasTouch` **no se pinta ninguna**.
     */
    public function test_the_strip_arrows_only_exist_where_there_is_a_mouse(): void
    {
        $css = (string) file_get_contents(base_path('public/css/site.css'));

        $this->assertMatchesRegularExpression(
            '/\.daystrip__nav,\s*\.timestrip__nav\s*\{\s*display:\s*none;?\s*\}/',
            $css,
            'Las flechas de las tiras han dejado de nacer APAGADAS. En táctil tapan la tira.'
        );

        $this->assertStringContainsString(
            '@media (hover: hover) and (pointer: fine)', $css,
            "Las flechas ya no se acotan a los dispositivos con ratón.\n".
            '⚠️ Las DOS consultas: `hover: hover` sola la cumple un táctil con lápiz.'
        );
    }

    /**
     * ⚠️⚠️ **El cableado de la tira se engancha al NODO, no al montaje del componente**, y esta guarda
     * existe porque la primera versión hizo lo segundo y **las flechas nacieron muertas**: el carril
     * vive dentro de un `v-if` que espera la oferta del servidor, así que al montar el componente el
     * elemento **todavía no existe** y no se registraba ningún oyente. Medido en navegador: 11.535 px
     * de recorrido en un carril de 440 y la flecha oculta por su propio `v-show`.
     *
     * ▶ *Un composable que asume que su elemento existe al montar falla justo en los componentes que
     * esperan datos, que son casi todos.*
     */
    public function test_the_strip_wiring_watches_the_node_instead_of_the_mount(): void
    {
        $fuente = (string) file_get_contents(base_path('resources/js/sidebar/useStrip.js'));

        // ⚠️ **Se miran las líneas de CÓDIGO, no el fichero entero.** La primera versión de esta
        // guarda salió en rojo con el código correcto: el docblock EXPLICA el fallo y por tanto
        // nombra `onMounted`. Es la misma lección que costó una medición en `armazon-y-menu.md` §1.2
        // —un `grep` que cuenta comentarios no está midiendo el código—, aquí por el otro lado.
        $codigo = preg_replace('#/\*.*?\*/#s', '', $fuente);
        $codigo = preg_replace('#^\s*(//|\*).*$#m', '', (string) $codigo);

        $this->assertStringNotContainsString(
            'onMounted', (string) $codigo,
            "`useStrip.js` ha vuelto a engancharse en `onMounted`.\n".
            'El carril nace dentro de un `v-if`: al montar el componente todavía no existe.'
        );
        $this->assertStringContainsString('watch(track', (string) $codigo);

        // Control del propio despojo: si dejara de quitar comentarios, esto lo diría.
        $this->assertStringContainsString(
            'onMounted', $fuente,
            'El docblock ha dejado de explicar POR QUÉ no se usa `onMounted`, y esa es la mitad que '
            .'impide que el siguiente lo vuelva a poner.'
        );
    }
}
