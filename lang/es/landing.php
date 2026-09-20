<?php

return [
    'nav' => [
        // Items "viejos" que aún se usan en algún punto (footer, drawer mob): los conservamos.
        /*
         * Los DOS grupos del menú (`#477`). El rótulo dice a dónde te lleva el destino: bajar por
         * esta misma página o cambiar de página. ⚠️ Se escriben en minúscula porque el estilo los
         * pone en mayúsculas (`text-transform`): guardarlos ya en mayúsculas rompe el idioma de
         * quien no las use y deja el texto sin poder cambiarse desde aquí.
         */
        // ⚠️ «Páginas» y no «Otras páginas» (`#521`, `[DECIDIDO owner]`): la lista incluye la página
        // en la que estás, marcada, así que «otras» dejaría de ser cierto en cualquier interior.
        'menu_group' => ['section' => 'En esta página', 'page' => 'Páginas'],
        // Los rótulos del INVENTARIO de páginas (`#521`). Los leen el menú y el pie, que ofrecen lo
        // mismo. Son nombres de DESTINO y no el titular de cada página: `/contacto` se titula
        // «Hablamos» en su cabecera y aquí se llama por lo que es.
        'pages' => [
            'pricing' => 'Tarifas', 'events' => 'Cumpleaños', 'attractions' => 'Atracciones',
            'bar' => 'El bar',
            'rules' => 'Normas', 'services' => 'Excursiones', 'contact' => 'Contacto',
        ],
        'zones' => 'Zonas', 'rides' => 'Atracciones', 'pricing' => 'Precios',
        'events' => 'Cumpleaños', 'info' => 'Visítanos', 'reserve' => 'Registrarse',
        'reserve_tickets_aria' => 'Reservar entradas y cumpleaños',
        // CTA «Registro» del header → sistema externo de la clienta (#216). Defaults si el ajuste
        // por idioma está vacío. El subtítulo se muestra debajo de la etiqueta.
        'register' => 'Registro de acceso',
        'register_subtitle' => 'Para entrar al parque',
        'register_info' => 'Para entrar al parque necesitas completar el registro de acceso. Hazlo ahora y agiliza tu llegada.',
        // CTAs jerarquizados del header (mockup `design_mockup/jerarquia-ctas.html`).
        // El filled del header usa `cta_buy` + `cta_buy_from` en DESKTOP — mismo
        // copy que el hero (es un espejo compacto que aparece al hacer scroll).
        // En MÓVIL el espacio del nav no permite el copy largo: usamos `cta_book`
        // como versión de 1 palabra (controlado por dos `<span>` con clases
        // `__t--desktop` / `__t--mobile` y media query en site.css).
        'cta_buy' => 'Reservar',
        'cta_buy_from' => 'desde :amount',
        'cta_book' => 'Reservar',
        // ⚠️ `park` y `services` —los rótulos de los dos desplegables viejos— se fueron con `#521`.
        // `tickets` se queda: lo sigue usando la lista de atajos de la página 404.
        'tickets' => 'Entradas',
        // **Nombres accesibles del CTA DOBLE de móvil** (armazón · tanda 2c·4). Cuando una mitad
        // está COLAPSADA su pulsación no lleva a ninguna parte: la expande. El nombre tiene que
        // decir eso, o un lector de pantalla anuncia dos botones que dicen lo mismo y hacen cosas
        // distintas. Los `*_sub` son el subtítulo VISIBLE de la mitad expandida.
        'cta_switch_buy' => 'Cambiar a reservar entradas',
        'cta_switch_signup' => 'Cambiar a registrarse',
        'cta_switch_signup_sub' => 'o inicia sesión',
        'cta_switch_account' => 'Cambiar a mi cuenta',
        'cta_account_sub' => 'tus reservas y tus datos',
        'menu_label' => 'Menú principal',
        'menu_open' => 'Abrir menú',
        'menu_close' => 'Cerrar menú',
        // Rótulo VISIBLE del botón de menú (`#217`, como el mockup del 2.º cliente). NO es el
        // nombre accesible —ése lo dan `menu_open`/`menu_close`, que dicen la ACCIÓN—: éste es
        // una palabra corta al lado del dibujo, y por debajo de 620 px no se pinta.
        'burger_label' => 'Menú',
        'burger_label_open' => 'Cerrar',
        'skip' => 'Saltar al contenido',
        'slider_prev' => 'Anterior',
        'slider_next' => 'Siguiente',
        // ⚠️ De los ítems del menú viejo solo queda éste (`#521`): es el respaldo del dato «dónde
        // estamos» del menú cuando la instalación no tiene ciudad escrita. Los destinos del menú son
        // ya el inventario (`pages`, arriba) y las secciones de la portada, con su propio rótulo.
        'park_items' => [
            'info' => ['t' => 'Ubicación y horario'],
        ],
    ],
    'hero' => [
        'today' => 'Murcia · Abierto hoy',
        // Eslogan sobre el titular del hero. CORTO a propósito: el sistema del 2.º cliente
        // limita el rotulador a seis palabras («el eslogan, nada más»), y girado no se lee más.
        'kicker' => 'activa tu modo diversión',
        // ⚠️ `l2` es el rótulo del INTERRUPTOR del titular (`#254`, `[DECIDIDO owner]`:
        //    «Diversión ON, y ese ON que sea un toggle que esté activado»). Es un estado, no
        //    una palabra traducible: «ON» se lee igual en los tres idiomas y además es lo que
        //    dice el interruptor. Si algún día una instalación lo quiere en su idioma, se
        //    cambia aquí y el interruptor no se entera.
        'l1' => 'DIVERSIÓN', 'l2' => 'ON',
        'tag' => 'Parque de trampolines en Lorca, para peques y mayores.',
        'cta' => 'Reservas aquí', 'cta2' => 'Ver atracciones', 'reel' => 'Vídeo del parque',
        // CTA "prime" del hero (mockup). Subtítulo con precio cuando hay catálogo,
        // fallback `cta_buy_no_price` cuando aún no hay productos vendibles.
        'cta_buy' => 'Reservas aquí',
        'cta_buy_from' => 'desde :amount',
        // Segundo botón del hero: el contorno sobre el vídeo (`#216`). Lleva a la página de
        // precios; es el «Ver precios» del mockup del 2.º cliente.
        'cta_prices' => 'Ver precios',
        'cta_buy_no_price' => 'Cumpleaños online',
        // Chip de estado del hero (data-driven, App\Domain\Content\Services\HeroStatus). `:duration` ya viene formateada
        // («2 h» / «45 min»); `:time` = «HH:MM»; `:day` = día de la semana en minúscula.
        'status_open' => 'Abierto ahora',
        'status_opens_in' => 'Abrimos en :duration',
        'status_opens_tomorrow' => 'Abrimos mañana a las :time',
        'status_opens_day' => 'Abrimos el :day a las :time',
        'status_link_hint' => 'Ver horario y cómo llegar',
        'stats' => [
            ['num' => '7.000', 'label' => 'M² de diversión'],
            ['num' => '23', 'label' => 'Atracciones'],
            ['num' => '2', 'label' => 'Zonas por edad'],
            ['num' => '+1M', 'label' => 'Saltos al año'],
        ],
    ],
    'zones' => [
        'axis_label' => 'altura',
        'below_is' => 'debajo, :zone',
        'above_is' => 'encima, :zone',
        /*
         * La sección «Para quién» (`#478`). ⚠️ El RÓTULO no lleva número: el canvas lo decidió así
         * (`[DECIDIDO owner]` suyo) y aquí ya se había llegado a lo mismo en `#303`, que retiró la
         * etiqueta de toda vista pública.
         * ⚠️ La REGLA es la frase del parque y va entera: «manda la edad» primero y la altura como
         * desempate. Partirla en dos líneas la convierte en dos reglas.
         */
        'eyebrow' => 'Para quién',
        'title' => 'Una zona para cada edad',
        // La entradilla (`#589`, `[DECIDIDO owner]`): la sección era la única sin ella. ⚠️ No repite
        // edades ni alturas —las dice cada tarjeta— y no nombra zonas: el texto es del PRODUCTO.
        'lede' => 'Cada zona está pensada para una edad, con sus propias atracciones y su tarifa, para que todos salten a su ritmo y con seguridad. Elige la tuya y reserva tu hora en un minuto.',
        'from' => 'desde',
        'see_zone' => 'Ver la zona :zone',
        /*
         * La regla de altura, redactada por `ZoneCards`. ⚠️ Son TRES formas y no una con un valor
         * opcional: «hasta 1,30 m» y «desde 1,30 m» dicen lo contrario, y una sola frase con el
         * número dentro obligaría a que el idioma adivinara el sentido.
         */
        'height_up_to' => 'hasta :h m',
        'height_from' => 'desde :h m',
        'height_between' => 'de :a a :b m',
        'intro' => 'Diseñamos dos universos diferentes: uno para los que vuelan sin frenos y otro para los que están descubriendo el salto. Elige el tuyo.',
    ],
    /*
     * La sección 03 «Qué hay dentro» (`#482`). ⚠️ El RÓTULO no lleva número —decisión del canvas,
     * la misma de `#478`— y el TITULAR es una frase de 3 a 6 palabras, no una etiqueta: «El parque»
     * era lo anterior y `doc/voz.md` del canvas lo cambia a propósito, porque «Dentro» repetiría la
     * palabra del rótulo.
     * ⚠️⚠️ La entradilla **abre con la cifra**, y la cifra es DATO: es lo que sustituye a la chapa
     * del «18 más» que el recorte de presupuesto del canvas se llevó. Sin ella la sección enseña
     * cinco fotos y no dice en ningún sitio cuántas hay.
     */
    'rides' => [
        'eyebrow' => 'Qué hay dentro',
        'title' => 'Salta, trepa y déjate caer',
        'intro' => ':count atracciones: trampolines, toboganes, piscina de espuma y piscina de bolas.',
        // La única puerta de la sección, y la única entrada a `/atracciones` desde la portada.
        'door' => 'Ver las :count atracciones',
        'aside_title' => '¿Y yo qué hago mientras?',
        'zone_tab' => 'Zona',
        'book_zone' => 'Reservar :zone',
        'buy' => 'Comprar',
    ],
    /*
     * `/atracciones` — la página de las 23 (carril de diseño, T2d · `Atracciones PJP` 1a/1c).
     * ⚠️ El RÓTULO es la RUTA y NO vive aquí: lo deriva `<x-site.page-head>` de la URL real, con la
     * misma función que el menú (`#525`). Hasta entonces estaba escrito a mano en los tres idiomas.
     * ⚠️⚠️ La entradilla dice «con su edad» y NO «con su edad y su altura», que es lo que escribe el
     * artboard: el propio canvas retiró la altura por atracción —«el único dato de altura del
     * sistema es el 1,30 y es de la ZONA»— y aquí no hay columna que la guarde. Prometer un dato
     * que la ficha no puede pintar es la avería que `#309` describe con las anclas.
     */
    'attractions' => [
        'title' => 'Todo lo que hay dentro',
        // `:count` es el recuento de lo que la PÁGINA enseña, no `Attraction::count()`.
        'intro' => 'Las :count atracciones del parque, con su edad.',
        'zone_tablist' => 'Zona',
        // La cifra grande y su frase. `:count` va en un `<span>` aparte —es la cifra en rótulo—, así
        // que aquí solo viaja la parte de texto.
        'count_phrase' => 'atracciones para :age',
        'count_phrase_plain' => 'atracciones en :zone',
    ],
    /*
     * ══ LA PÁGINA `/precios` ═══════════════════════════════════════════════════════════════════
     * `DECISIONES #531` · carril de diseño Fase 3 · T3b. Artboard `Precios Pagina PJP` 1a/1b.
     *
     * ⚠️ El titular y la entradilla son los del artboard (`[DECIDIDO owner, 2026-09-11]`), que
     * sustituyen a los de `#525`: la página ya enseña exactamente lo que prometen —las dos columnas
     * de precio por día— así que «cada día tiene su precio escrito» es comprobable mirándola.
     */
    'pricing' => [
        'title' => 'Todas las tarifas',
        'intro' => 'Lo que cuesta saltar, por zona y por tiempo. Sin sumas: cada día tiene su precio escrito.',
        // ⚠️ `book` y `call` los siguen usando `/servicios` y el carril de la portada: la página de
        // tarifas ya no lleva botón propio, pero las claves NO son suyas.
        'from' => 'desde', 'book' => 'Reservar', 'call' => 'Llamar',

        // ── LA SEMANA DIBUJADA ────────────────────────────────────────────────────────────
        // ⚠️ Las INICIALES son de calendario, no de Carbon: en español el miércoles es «X» —«M» es
        // el martes— y en francés se repiten a propósito. El nombre completo lo pone Carbon y es el
        // que se lee en voz alta, así que la inicial nunca tiene que desambiguar sola.
        'week_initials' => [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 0 => 'D'],
        'week_label' => 'Qué tarifa rige cada día',
        'week_normal' => 'Tarifa normal',
        'week_special' => 'Tarifa especial',

        // ── LA TABLA ──────────────────────────────────────────────────────────────────────
        // ⚠️ La cabecera de la columna normal se DERIVA de los días que ninguna especial reclama:
        // aquí solo va la forma del rango («lunes a jueves»). La especial lleva el rótulo del panel y
        // `col_special` es solo el suelo de una tarifa sin rótulo (`#586`).
        'col_range' => ':from a :to',
        'col_special' => 'Especial',
        'table_label' => 'Tarifas de :zone',
        // ⚠️ **«—» no es «gratis»: es que ese día no se vende.** Lo dice en voz alta el lector de
        // pantalla, que no ve la raya.
        'not_sold' => 'No se vende ese día',
        'vat_note' => 'Precios con IVA incluido.',

        // ── QUÉ ES LA TARIFA ESPECIAL ─────────────────────────────────────────────────────
        // ⚠️ Los días salen del rótulo del panel (`:label`) y los normales se derivan: ni una lista
        // de días escrita a mano, que sería cierta en esta instalación y falsa en la siguiente.
        'special_title' => 'Qué es la tarifa especial',
        'special_text' => 'Los días de tarifa especial son: :label.',
        'special_plain' => 'El resto de días, :days, es la tarifa normal.',
        'special_calm' => 'No hay que calcular nada: al elegir el día, el precio que ves ya es el tuyo.',

        // ── LOS FESTIVOS ──────────────────────────────────────────────────────────────────
        // ⚠️ **Sin frase general**: cada fecha dice SU hecho (cerrado, su tarifa o su horario). El
        // artboard escribe «cuentan como fin de semana, en precio y en horario» y eso es justo lo
        // que `#487` retiró de la 07, porque el producto no puede afirmarlo.
        'holidays_title' => 'Los festivos',

        // ── LA LÍNEA A CUMPLEAÑOS ─────────────────────────────────────────────────────────
        // ⚠️ «con la comida incluida» es comprobable: el pack trae un menú marcado como incluido.
        // La zona NO se nombra —el canvas tiene pendiente si es exclusiva durante la fiesta—.
    ],

    /*
     * ══ SECCIÓN 02 DE LA PORTADA · «CUÁNTO» ══════════════════════════════════════════════════
     * `DECISIONES #479` · carril de diseño Fase 2 · T2c.
     *
     * ⚠️⚠️ **BLOQUE PROPIO, y no una ampliación de `pricing`.** Ese bloque lo comparte la PÁGINA
     * `/precios`, que tiene artboard propio y se rehace en la Fase 3: reutilizar sus claves habría
     * puesto el titular de una sección de portada —«Una hora, dos o el día»— como `<h1>` de una
     * página que se llama «Tarifas», y como `<meta description>` de esa misma página. *Dos
     * superficies distintas no comparten copy solo porque hablen del mismo tema.*
     */
    'rates' => [
        // Rótulo y titular del canvas: los ocho titulares son FRASES de 3 a 6 palabras y los
        // rótulos van SIN número.
        'eyebrow' => 'Cuánto',
        'title' => 'Una hora, dos o el día',
        // ⚠️ El precio va INTERPOLADO y no escrito: es el más barato del catálogo, con el criterio
        // de `#324` («desde» anuncia el precio real más bajo que existe). Sin catálogo vendible se
        // usa `intro_plain`, porque una entradilla que promete un precio que no hay miente.
        'intro' => 'Elige tu zona y cuánto tiempo quieres saltar, y reserva la hora que mejor te venga. Desde :from.',
        'intro_plain' => 'Elige tu zona y cuánto tiempo quieres saltar, y reserva la hora que mejor te venga.',
        'pick_zone' => 'Elige la zona',

        // ── Los DÍAS de cada tarifa ────────────────────────────────────────────────────────
        // Los nombres de día los pone Carbon; aquí solo va la FORMA de la frase.
        'days_range' => 'de :from a :to',
        'days_list' => ':days',
        // ⚠️ «solo» NO es un adorno: marca la entrada que ese día **no se vende** —el dominio
        // devuelve `null`—, no una que cueste menos. Ver `RateCards::card()`.
        'days_only' => 'solo :days',

        // La tarifa especial: su precio ENTERO y el nombre igual en todas las superficies.
        'special_suffix' => 'en tarifa especial',
        // Los días de la especial, UNA vez por sección. `:label` es el rótulo de la tarifa tal y
        // como lo escribe el panel — hoy «Viernes, findes y festivos».
        // Tras los dos puntos, en minúscula (`#589`): el rótulo de la tarifa viene del panel con mayúscula
        // inicial porque también es cabecera de columna. `:label_lc` lo trae ya en minúscula.
        'special_note' => 'Tarifa especial: :label_lc.',

        // ❗ **La ZONA la dice la CHAPA de la tarjeta, no el botón** (`#480`, sobre la nota del
        // turno 15b: «con la zona en la chapa, en el botón sobra»). La llevó mientras la chapa no
        // existía; con las dos, se decía dos veces por tarjeta.
        'book_name' => 'Reservar :name',
        'zone_chip' => 'Zona :zone',

        // ── El AHORRO de una entrada larga frente a varias cortas ─────────────────────────
        // ⚠️ La cifra la calcula el dominio del catálogo; aquí solo va la FORMA de la frase.
        'saving' => 'Ahorras',
        'saving_base' => 'frente a :count de :unit',
        // El precio de ANTES tachado (solo con `promo.percent`): la palabra es para el lector de pantalla.
        'was' => 'Antes',
        // ⚠️ El número va ESCRITO: la frase se lee, no se calcula. Fuera de esta lista corta el
        // dominio cae al dígito, que es preferible a inventar la palabra.
        'times' => [2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco', 6 => 'seis'],

        // ── El precio de un COMPLEMENTO ───────────────────────────────────────────────────
        // ⚠️ Desde `#583` la web no publica complementos al reservar (se ofrecen en el cajón): estas
        // tres las lee solo lo que se elige DESPUÉS de reservar, en `/cumpleanos`.
        'from' => 'desde',
        // ⚠️ La unidad de un complemento sale del PIVOTE: `per_guest` o `fixed`. **No se escribe
        // «por persona» en uno `fixed`** — de ésos se elige cantidad, no se cobra uno por cabeza.
        'addon_per_guest' => 'por invitado',
        'addon_each' => 'cada uno',
    ],
    'registration' => [
        'title' => 'Completa tu registro en casa',
        'copy' => 'Escanea el QR o pulsa el botón y termina tu registro antes de llegar al parque.',
        'copy2' => 'Así, al entrar solo compras la entrada y accedes más rápido, sin esperas para registrarte.',
        'cta' => 'Completar registro',
        'qr_aria' => 'Código QR para completar tu registro',
    ],
    'addons' => [
        'label' => 'Complementos disponibles',
    ],
    'events' => [
        /*
         * La sección 04 «Cumpleaños» rehecha desde el canvas (`#483`). ⚠️ El RÓTULO no lleva número
         * y el TITULAR es una frase, como las otras siete.
         * ▶ `/cumpleanos` tiene su propio grupo, `birthday` (`#528`); de éste comparte el reloj, la
         * edad del pack, `per_child`, `special_suffix`, el carril y el «próximamente».
         */
        'section_title' => 'El cumple, resuelto',
        // ⚠️ La entradilla **vende con una cifra** del catálogo; sin packs con precio cae a la
        // variante sin cifra, porque un «desde» que no existe miente (la regla de `#479`).
        'section_intro' => 'Dos horas de fiesta, merienda y regalos para todos: tú solo traes a los invitados. Desde :from por niño.',
        'section_intro_plain' => 'Dos horas de fiesta, merienda y regalos para todos: tú solo traes a los invitados.',
        // La edad del PACK. Son tres formas y no una con un valor opcional: dicen cosas distintas.
        'age_between' => 'De :a a :b años',
        'age_from' => 'Desde :a años',
        'age_up_to' => 'Hasta :b años',
        // El sello girado: la cifra y su unidad.
        'per_child' => 'por niño',
        'special_suffix' => 'en tarifa especial',
        'see_pack' => 'Ver el cumple',
        // La DURACIÓN del pack, primera línea de lo que incluye (`#583`): sustituye al reloj «Las 2 h, a
        // vuestro ritmo», que se retiró. `:duration` sale del catálogo, ya escrita («2 h»).
        'duration_feature' => ':duration de fiesta',
        'eyebrow' => 'Cumpleaños',
        // ⚠️ Sin máximo a la vista (`#586`, `[DECIDIDO owner]`): el panel guarda un tope técnico y la web
        // solo publica el mínimo. `:max` sigue llegando y no se escribe.
        // ⚠️ El espacio antes del «€» es DURO (`\u{00A0}`, `#661`): «Señal de 50 €» se podía partir de
        // renglón dejando el símbolo solo abajo. El texto que se lee no cambia ni una letra.
        'reserve_terms' => "Desde :min niños · Señal de :deposit\u{00A0}€ para reservar",
        // `/cumpleanos` sin packs vendibles: la entradilla y su salida.
        'coming_soon' => 'Estamos preparando los packs de cumpleaños. Si quieres reservar antes, escríbenos y te ayudamos.',
        'coming_soon_cta' => 'Contactar',
    ],
    /*
     * `/cumpleanos` · la página del cumple rehecha desde su artboard (`#528`, `Cumpleanos Pagina
     * PJP`). ⚠️ Los PLURALES van por `trans_choice`: con uno, dos o más packs la página dice cosas
     * distintas («El pack» · «Los dos packs» · «Igual en todos»), y los packs los pone el panel.
     * ⚠️ No hay cifras escritas aquí: importes, edades, niños y plazos llegan del catálogo.
     */
    'birthday' => [
        'title' => 'El cumple, al detalle',
        'lede' => 'Lo que incluye cada pack, lo que cuesta y lo que se decide después de reservar.',
        'packs_title' => '{1} El pack|{2} Los dos packs|[3,*] Los :count packs',
        'count_question' => '¿Cuántos niños vienen?',
        'count_less' => 'Un niño menos',
        'count_more' => 'Un niño más',
        'table_label' => 'Comparativa de los packs',
        'row_each' => 'Por niño',
        'row_each_special' => 'En tarifa especial',
        'row_age' => 'Edad',
        'row_kids' => 'Niños',
        'row_deposit' => 'Señal',
        'row_features' => 'Incluye',
        'row_gifts' => 'De regalo',
        'row_total' => 'Total',
        'row_total_special' => 'Total en tarifa especial',
        'kids_from' => 'Desde :min niños',
        'kids_note' => 'El mínimo para reservar.',
        'deposit_title' => 'Señal de :deposit para reservar',
        'deposit_note' => 'Se descuenta del total.',
        'deposit_line' => 'La señal de :deposit se descuenta del total; el resto se paga el día de la fiesta, en el parque.',
        'book' => 'Reservar el cumple',
        'menu_title' => 'Qué comen',
        'choice_title' => 'A elegir',
        'choice_lede' => '{1} Se elige al reservar.|{2} Se elige uno de los dos al reservar.|[3,*] Se elige uno de los :count al reservar.',
        'shared_title' => '{1} Lo que incluye|{2} Igual en los dos|[3,*] Igual en todos',
        'shared_lede' => '{1} Todo lo que trae el pack.|[2,*] Lo que no cambia de un pack a otro, para no tener que compararlo.',
        'after_title' => 'Después de reservar',
        'after_lede' => 'Al reservar te llega por correo el enlace al «:form», para contarnos quién viene. No hace falta rellenarlo del tirón: se puede cambiar hasta el día de la fiesta.',
        'after_children' => 'De cada niño',
        'after_group' => 'De vosotros',
        'after_extras' => 'Lo que podéis añadir',
        'after_paid' => 'Lo que se añade ahí se paga en el parque, el día de la fiesta.',
        'cutoff' => 'hasta :time antes',
        'cutoff_start' => 'hasta que empiece la fiesta',
        'mixed_title' => '{2} ¿Y si vienen niños de las dos edades?|[3,*] ¿Y si vienen niños de edades distintas?',
        'mixed_text' => 'Si vienen niños de las dos edades, cada uno paga el precio del pack de su edad. La diferencia se ajusta en el parque el día de la fiesta, nunca online.',
        'mixed_seal' => 'Y lo que reservaste no se mueve: cada reserva guarda las condiciones del día en que la hiciste, aunque cambien los precios.',
        'info_title' => 'Personalizamos cada cumple',
        'info_text' => 'Si hay una alergia, un miedo o una sorpresa que preparar, dínoslo y lo montamos.',
    ],
    'plan' => [
        'label' => 'Planea tu visita', 'heading' => 'Taquilla',
        'items' => [
            ['t' => 'Entradas', 's' => 'Ven directo o llámanos'],
            ['t' => 'Cumple del peque', 's' => 'Salas privadas'],
            ['t' => 'Grupos & empresas', 's' => 'Colegios, empresas y mayores'],
            ['t' => 'Excursión mayores', 's' => '22:00–01:00 · mín 30'],
            ['t' => 'Contacto', 's' => 'Escríbenos o llámanos'],
        ],
    ],
    'gallery' => [
        'title' => 'En directo',
        'intro' => 'Lo que pasa en el parque, en tiempo real.',
    ],
    'info' => [
        'eyebrow' => 'Visítanos',
        'title' => 'Horarios y cómo llegar',
        'closed' => 'Cerrado',
        'open_generic' => 'Abierto',
        'day_range' => ':from a :to',
        // Dos días seguidos no son un rango: «Sábado y domingo», no «Sábado a domingo» (`#589`).
        'day_pair' => ':from y :to',
        // ⚠️ **Aquí vivían `hours_title`, `special_dates_title`, `hours_tbd` y `until`, y se han ido
        // con su consumidor** (`#487`): la sección 07 rehecha desde el canvas no lleva rótulos
        // dentro de la tarjeta —el estado y la tabla se presentan solos— y la cola «· hasta las
        // 21:30» pasó a ser la LÍNEA del estado, que ahora dice la frase entera.
        'weekdays' => [0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'],
        'address_title' => 'Ubicación', 'directions' => 'Cómo llegar',

        /*
         * ── 07 · VISÍTANOS ── (`#487`)
         *
         * ⚠️⚠️ **La entradilla se DERIVA del horario y por eso son tres claves, no una.** El canvas
         * la fija como «Abrimos todos los días. Entre semana por la tarde, y de viernes a domingo
         * también por la mañana» — cierto en esta instalación y **falso en cualquiera que cierre un
         * día**. Escribirlo en el `lang/` del PRODUCTO sería la fuga que `DECISIONES #1` prohíbe.
         * El porqué y el reparto, en `ScheduleDisplay::weeklyLede()`.
         */
        'lede_one' => 'Un mismo horario todos los días.',
        // ⚠️ La coma no es adorno (`#589`): con dos días seguidos en un grupo («sábado y domingo»), sin
        // ella la frase encadena dos «y» y no se sabe dónde acaba el primer horario.
        'lede_two' => 'Dos horarios: :a, y :b.',
        'lede_many' => 'El horario cambia según el día.',

        /*
         * **LOS CUATRO ESTADOS.** Regla dura del sistema: «hoy no abre» y «hoy ya ha cerrado» son
         * hechos distintos, y decirlos igual deja el titular contradiciendo a la tabla.
         * ⚠️ **Ningún titular dice «mañana» ni «por la tarde»**: la hora es un dato y hay filas que
         * abren a las 11:00 — un título quemado contra un dato variable miente.
         */
        'today_is' => 'Hoy, :day',
        'state' => [
            'open' => 'Abierto ahora',
            'open_line' => 'Hasta las :time',
            'later' => 'Abre hoy',
            'later_line' => 'Abre a las :opens y cierra a las :closes',
            // Los dos cierres comparten línea —la próxima apertura— y se distinguen en el TÍTULO,
            // que es donde está el hecho: uno ya abrió hoy y el otro no abre hoy.
            'closed_now' => 'Ya hemos cerrado',
            'closed_today' => 'Hoy cerrado',
            'next_tomorrow' => 'Mañana abre a las :time',
            'next_day' => 'El :day abre a las :time',
        ],

        // El aviso de la excepción que viene, FUERA del pliegue: lo que urge no se esconde detrás
        // de un clic.
        // ❗❗ Es una FRASE, no una fila de datos (`#489`): escribía «:date — :detail» y se leía como
        // una tercera fila del horario. ⚠️ No dice «festivo» a propósito: `special_dates` es
        // cualquier excepción de horario, no solo un festivo — el producto las llama «fechas
        // especiales» en el pliegue de al lado.
        'special_soon' => 'El :date, horario especial: :detail',
        'special_soon_closed' => 'Cerramos el :date',
        'specials_open' => 'Ver las fechas especiales',
        'specials_close' => 'Cerrar las fechas especiales',

        // ⚠️ **«El mapa lo pone Google» no es un aviso legal**: es la atribución que el sistema pide
        // cuando el dato lo posee un tercero. El aviso de cookies lo da el bloqueo previo.
        'map_credit' => 'El mapa lo pone Google',
        'open_in_maps' => 'Abrir en Google Maps',
    ],
    /*
     * ⚠️⚠️ **EL GRUPO `rules` SE VA ENTERO EN `#531`, y sus dos últimas claves con él.** La sección de
     * normas de la portada la sustituyó la 05 «Antes de venir» (`#485`) y `socks_title`/`socks_text`
     * sobrevivían por su único consumidor, `<x-site.socks-note>` en `/precios`. Esa página se rehizo
     * desde su artboard: los calcetines son ahora una ficha del bloque «Lo que se añade» **con el
     * texto que el panel escribe en el propio complemento** (`ticket_types.features`), así que la
     * nota estática del producto dejó de tener sitio y de tener sentido — el dato lo pone el dueño.
     */

    /*
     * ── 05 · ANTES DE VENIR ─────────────────────────────────────────────────────────────────────
     * Carril de diseño Fase 2 · T2f (`#485`). Textos de `doc/voz.md` del canvas, que es el registro
     * de lo aprobado por el dueño.
     *
     * ⚠️⚠️ **La entradilla y el CTA NO son los del artboard de sección, y es deliberado.** `Antes de
     * Venir PJP` 2a escribe «Sin firmar el descargo… no se entra» y «Registrarse»; el ENTREGABLE
     * (`Portada PJP`, pasada de copy del 9 sep) escribe lo que hay aquí. La regla la fijó `#482`:
     * *los artboards de sección son el registro de sus turnos y el entregable es `Portada PJP`*.
     *
     * ⚠️ **«Mi QR» es el nombre del PRODUCTO** (`[DECIDIDO owner, 2026-09-10]`), el mismo que la
     * cuenta usa en `account.card.title`. El canvas escribe «Mi Play Jump QR» y «Crear Mi Play
     * Jump», que son marca del cliente: en el producto serían la fuga que `DECISIONES #1` prohíbe.
     */
    'before' => [
        'eyebrow' => 'Antes de venir',
        'title' => 'Llega con tu QR y a saltar',
        'lede' => 'Firmas el descargo de responsabilidad una vez, en el móvil. En la puerta solo enseñas el código.',

        // ⚠️ **El nombre accesible dice «de ejemplo»**: sin eso, un lector de pantalla anuncia un
        // código y quien lo oiga entenderá que hay algo que escanear. No lo hay, a propósito.
        'qr_aria' => 'Mi QR, de ejemplo',
        'qr_name' => 'Mi QR',
        'qr_sample' => 'de ejemplo',
        'qr_where' => 'En tu cuenta y en el correo de cada reserva. No hace falta imprimirlo.',

        // ⚠️⚠️ **El sujeto es el VISITANTE.** El canvas lo reescribió por esto: *«lo que ve el
        // empleado es su trabajo, no la ventaja del cliente»*. Lo que se cuenta no es que ellos
        // tengan una pantalla: es que tú no repites nada.
        'carries_title' => 'Un código para todo',
        'carries_lede' => 'Lo enseñas en la puerta y lo vemos todo de un vistazo: tu reserva, tu firma y quién viene contigo. Sin papeles y sin buscar tu nombre.',
        'rows' => [
            // ⚠️ Las tres filas dicen lo que el código lleva **siempre**, no una reserva concreta.
            'booking' => ['key' => 'Tus reservas', 'val' => 'Las que tengas y las que hagas después'],
            'waiver' => ['key' => 'Tu firma', 'val' => 'El descargo, firmado una sola vez'],
            'minors' => ['key' => 'Tus hijos', 'val' => 'Los que has añadido a tu cuenta'],
        ],
        'always' => 'Uno solo, siempre el mismo, y vale para todas las veces que vengáis.',

        // ⚠️ **Sin el precio**, y el motivo está en la vista: el producto no sabe cuál de sus
        // complementos son «los calcetines», y la cifra ya se publica en el carril de la sección 02.
        'socks_lead' => 'Lo único que no cabe en el código:',
        'socks_text' => 'calcetines antideslizantes. Tráelos de casa o cómpralos aquí, y te los quedas.',
        'guest_text' => '¿Viene un niño que no es de tu familia? Puedes mandar un enlace a sus padres para que firmen ellos, sin crear cuenta.',

        'all_rules' => 'Ver todas las normas',
        'cta' => 'Crear mi cuenta',
        // ⚠️ **Con sesión no se ofrece crear cuenta**: ya la tiene. Se ofrece el objeto del que habla
        // la sección, y el enlace abre el cajón en su zona `card`.
        'cta_account' => 'Ver mi QR',
    ],
    // ══ SECCIÓN 06 · «RESEÑAS» ═════════════════════════════════════════════════════════════
    // `DECISIONES #490` · Fase 2 · T2i·a. Artboards `Resenas PJP` 2a y `Escritorio PJP` 5b.
    // ⚠️⚠️ **La entradilla depende de la FUENTE y por eso son dos claves.** El artboard escribe «No
    //    las elegimos nosotros: son las que Google pone primero», que es lo que hace creíble a
    //    Google — y **sobre opiniones propias sería falso**, porque éstas sí las elige el parque.
    //    `lede_google` nace sin consumidor a propósito: la usa la mitad `b`, y dejarla escrita aquí
    //    es lo que impide que ese día alguien reutilice la de arriba sin mirar lo que dice.
    // ❗ El titular es del canvas y tiene **seis palabras**, el techo que fija `Voz PJP`.
    'reviews' => [
        'eyebrow' => 'Reseñas',
        'title' => 'Lo dicen los que ya han venido',
        'lede_own' => 'Lo que nos cuentan las familias al salir.',
        'lede_google' => 'No las elegimos nosotros: son las que Google pone primero.',
        // `role="img"` necesita un nombre que diga la NOTA. Cinco glifos sueltos los lee un lector
        // de pantalla como «estrella estrella estrella…», que no es el dato.
        'stars' => '{1} :n estrella sobre 5|[2,*] :n estrellas sobre 5',
        'out_of' => 'sobre 5',
        'count' => '{1} :n opinión|[2,*] :n opiniones',
        'score_aria' => ':value sobre 5 en Google',
        'read_more' => 'Ver más',
        'read_less' => 'Ver menos',
        'see_on_google' => 'Ver en Google',
        'prev' => 'Opinión anterior',
        'next' => 'Opinión siguiente',
        'go' => 'Ver la opinión :n',

        // ── ATRIBUCIÓN DE GOOGLE (`#494`) ──────────────────────────────────────────────────
        // ❗❗ El enlace al perfil es la tercera pata de la atribución obligatoria («avatar, name,
        //    and profile link»). El nombre accesible dice ADÓNDE lleva: sin él, un lector de
        //    pantalla anuncia «Ana G., enlace» y no hay forma de saber que sale del sitio.
        'author_on_google' => ':name en Google Maps',

        // ❗❗❗ El aviso de traducción es OBLIGATORIO («Make end users aware when a review has been
        //    translated from its original language»), y aquí es el caso normal: las reseñas del
        //    parque están en español, así que en inglés y en francés Google las traduce.
        // ⚠️ `translated` a secas es la salida cuando no se puede nombrar el idioma (sin `ext-intl`,
        //    o con un código que el sistema no conoce): avisar es lo obligatorio; nombrarlo, no.
        'translated_from' => 'Traducida del :lang',
        'translated' => 'Traducida automáticamente',
        'see_original' => 'Ver original',
        'see_translation' => 'Ver traducción',

        // ❗❗❗ LA FRASE ES DE GOOGLE, no nuestra: «Reviews aren't verified by Google, but Google
        //    checks for and removes fake content when it's identified». Su documentación pide
        //    informar de esto al enseñar reseñas Y valoración media.
        // ⚠️⚠️ Y es lo que cierra la petición de «verificado por Google»: eso NO se puede escribir,
        //    porque afirma lo contrario de lo que Google dice de sus propias reseñas.
        // ⚠️ Nombra a Google DENTRO de la frase a propósito: la sección puede estar enseñando
        //    opiniones propias debajo, y una redacción impersonal las alcanzaría también.
        'google_policy' => 'Google no verifica las reseñas, pero retira el contenido falso cuando lo detecta.',
        // `#592`: sin permiso de cookies y sin opiniones propias, este aviso va en el hueco de las
        // tarjetas. El botón ABRE el panel de cookies: nombra lo que hace, no lo que se ve después.
        'locked_text' => 'Las reseñas vienen de Google y, para leerlas aquí, necesitamos tu permiso para sus cookies.',
        'locked_btn' => 'Elegir cookies',
    ],

    // ══ SECCIÓN 08 · «DUDAS» ═══════════════════════════════════════════════════════════════
    // `DECISIONES #488` · Fase 2 · T2h. Artboards `Dudas PJP` 1a y `Escritorio PJP` 5c.
    // ⚠️ El titular DEJA de ser «Dudas»: eso es el RÓTULO. `doc/voz.md` fija los ocho titulares
    //    como frases de 3 a 6 palabras porque «el rótulo ya dice el eje de la pregunta».
    // ⚠️ La entradilla dice de dónde salen las preguntas, que es lo que las hace creíbles: no son
    //    de relleno, son las que llegan por teléfono.
    'faq' => [
        'eyebrow' => 'Dudas',
        'title' => 'Lo que más nos preguntáis',
        'lede' => 'Las que más nos hacéis por teléfono, respondidas aquí.',
    ],
    // **«Salta la ciudad»**, el minijuego del hero del cierre (`#231`). Rótulos cortos: viven
    // dentro de una tarjeta que ya está llena, y el juego se explica solo al primer toque.
    // **«Salta la ciudad»**, el minijuego del hero del cierre (`#231`). Rótulos cortos: viven
    // dentro de una tarjeta que ya está llena, y el juego se explica solo al primer toque.
    // ⚠️ Las claves `*_touch` NO son un lujo: el mockup cambia el texto según el puntero, y
    // «Espacio para saltar» en un móvil es una instrucción que no se puede seguir.
    'game' => [
        'play' => 'Espacio para saltar el castillo',
        'play_touch' => 'Toca para saltar el castillo',
        'rec' => 'réc :m m',
        'm' => 'm',
        'again' => 'Otra vez · espacio',
        'again_touch' => 'Otra vez',
        'book' => 'Reservar',
        'over' => 'Se acabó',
        'newrec' => '¡nuevo récord!',
        'bands' => 'pulseras',
        'record' => 'récord :m m',
        'aria' => 'Salta la ciudad: minijuego. Pulsa para empezar y para saltar.',
        'hint' => 'mantén espacio · esc sale',
        'hint_touch' => 'mantén para saltar más',
    ],
    'reserve' => [
        'title' => 'VAMOS', 'stroke' => 'A', 'fill' => 'SALTAR',
        'copy' => 'Entradas, cumpleaños y excursiones, con día y hora, en un minuto. Te confirmamos al momento.',
        'cta' => 'Reservas aquí', 'cta2' => 'Llamar',
    ],
    'footer' => [
        'tag' => 'Parque de saltos para toda la familia · Murcia',
        'col_park' => 'Parque', 'col_info' => 'Información', 'col_contact' => 'Contacto',
        'links_park' => ['Zona Jump', 'Zona Kids', 'Atracciones'],
        'links_info' => ['Tarifas', 'Cumpleaños', 'Grupos y empresas', 'Normas'],
        'rights' => 'Hecho para reír.',
        // 5 páginas legales (orden = $legalUrls en el footer): aviso-legal, privacidad, condiciones, cookies, waiver.
        'legal' => ['Aviso legal', 'Privacidad', 'Condiciones', 'Cookies', 'Descargo de responsabilidad'],
        'contact_link' => 'Contacto',
        'account_link' => 'Mi cuenta',
        'register_link' => 'Registro de acceso',
        'language' => 'Idioma',
    ],
];
