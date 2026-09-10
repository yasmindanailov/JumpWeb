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
        'menu_group' => ['section' => 'En esta página', 'page' => 'Otras páginas'],
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
        // Reorganización 2026-05-27: dos desplegables temáticos + dos atajos directos.
        'park' => 'El parque',
        'services' => 'Servicios',
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
        // Cada item: `t` título, `s` descripción corta (≤ ~30 chars).
        'park_items' => [
            'rides' => ['t' => 'Atracciones', 's' => 'Trampolines, foam, tirolinas'],
            'info' => ['t' => 'Ubicación y horario', 's' => 'Murcia · cómo llegar'],
        ],
        'services_items' => [
            'birthdays' => ['t' => 'Cumpleaños', 's' => 'Packs por niño · sala privada'],
            'school' => ['t' => 'Excursiones de colegio', 's' => 'Sesión de 30 min'],
            'team_building' => ['t' => 'Empresas', 's' => 'Desde 30 personas'],
            'adults' => ['t' => 'Excursión para mayores', 's' => '22:00–01:00 · mín. 30'],
            'events' => ['t' => 'Otros eventos', 's' => 'Despedidas, fiestas, rodajes'],
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
        'tag' => 'Parque de saltos, trampolines, tirolinas y mucho más en plena Murcia. Hecho para reír.',
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
        'title' => 'Cada uno tiene su zona',
        'rule' => 'Manda la edad. Si no cuadra, manda la altura.',
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
        'intro' => ':count atracciones dentro. Camas elásticas, toboganes, foam y piscina de bolas.',
        // La única puerta de la sección, y la única entrada a `/atracciones` desde la portada.
        'door' => 'Ver las :count atracciones',
        'zone_tab' => 'Zona',
        'book_zone' => 'Reservar :zone',
        'buy' => 'Comprar',
    ],
    /*
     * `/atracciones` — la página de las 23 (carril de diseño, T2d · `Atracciones PJP` 1a/1c).
     * ⚠️ El RÓTULO es la RUTA, que es la cabecera de página que el canvas cierra en
     * `Layout Paginas PJP`. Es la primera página que la estrena; las otras cinco la adoptan en la
     * Fase 3, igual que `.sec-head` estrenó en Tarifas y las demás secciones la adoptan al
     * rehacerse (`#479`).
     * ⚠️⚠️ La entradilla dice «con su edad» y NO «con su edad y su altura», que es lo que escribe el
     * artboard: el propio canvas retiró la altura por atracción —«el único dato de altura del
     * sistema es el 1,30 y es de la ZONA»— y aquí no hay columna que la guarde. Prometer un dato
     * que la ficha no puede pintar es la avería que `#309` describe con las anclas.
     */
    'attractions' => [
        'eyebrow' => '/atracciones',
        'title' => 'Todo lo que hay dentro',
        // `:count` es el recuento de lo que la PÁGINA enseña, no `Attraction::count()`.
        'intro' => 'Las :count atracciones del parque, con su edad.',
        'zone_tablist' => 'Zona',
        // La cifra grande y su frase. `:count` va en un `<span>` aparte —es la cifra en rótulo—, así
        // que aquí solo viaja la parte de texto.
        'count_phrase' => 'atracciones para :age',
        'count_phrase_plain' => 'atracciones en :zone',
        'see_zones' => 'Ver las zonas',
    ],
    'pricing' => [
        'title' => 'Tarifas',
        'intro' => 'Elige tu zona y mira los precios. Hoy las entradas se compran en taquilla o por teléfono.',
        'from' => 'desde', 'pick_zone' => 'Elige la zona', 'tab' => 'Entradas',
        'book' => 'Reservar', 'call' => 'Llamar',
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
        'intro' => 'Eliges la zona y cuánto rato. Desde :from.',
        'intro_plain' => 'Eliges la zona y cuánto rato.',
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
        'special_note' => 'Tarifa especial: :label.',

        // ❗ **La ZONA la dice la CHAPA de la tarjeta, no el botón** (`#480`, sobre la nota del
        // turno 15b: «con la zona en la chapa, en el botón sobra»). La llevó mientras la chapa no
        // existía; con las dos, se decía dos veces por tarjeta.
        'book_name' => 'Reservar :name',
        'zone_chip' => 'Zona :zone',

        // ── El AHORRO de una entrada larga frente a varias cortas ─────────────────────────
        // ⚠️ La cifra la calcula el dominio del catálogo; aquí solo va la FORMA de la frase.
        'saving' => 'Ahorras',
        'saving_base' => 'frente a :count de :unit',
        // ⚠️ El número va ESCRITO: la frase se lee, no se calcula. Fuera de esta lista corta el
        // dominio cae al dígito, que es preferible a inventar la palabra.
        'times' => [2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco', 6 => 'seis'],

        // ── El bloque de COMPLEMENTOS, debajo del carril ──────────────────────────────────
        'addons_title' => 'Complementos disponibles',
        'addons_intro' => 'Puedes añadirlos a cualquier entrada.',
        'from' => 'desde',
        // ⚠️ La unidad de un complemento sale del PIVOTE: `per_guest` o `fixed`. **No se escribe
        // «por persona» en uno `fixed`** — de ésos se elige cantidad, no se cobra uno por cabeza.
        'addon_per_guest' => 'por invitado',
        'addon_each' => 'cada uno',
        'from' => 'desde',
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
         * ⚠️⚠️ `title` sigue existiendo con su valor viejo porque lo usa **`/cumpleanos`**, que es
         * una PÁGINA con artboard propio y se rehace en la Fase 3. Cambiarlo aquí habría movido el
         * `<h1>` de esa página desde una tanda de la portada.
         */
        'section_title' => 'El cumple, resuelto',
        // ⚠️ La entradilla **vende con una cifra** del catálogo; sin packs con precio cae a la
        // variante sin cifra, porque un «desde» que no existe miente (la regla de `#479`).
        'section_intro' => 'Dos horas, la comida de los niños y los calcetines. Desde :from por niño.',
        'section_intro_plain' => 'Dos horas, la comida de los niños y los calcetines.',
        // La edad del PACK. Son tres formas y no una con un valor opcional: dicen cosas distintas.
        'age_between' => 'De :a a :b años',
        'age_from' => 'Desde :a años',
        'age_up_to' => 'Hasta :b años',
        // El sello girado: la cifra y su unidad.
        'per_child' => 'por niño',
        'special_suffix' => 'en tarifa especial',
        'see_pack' => 'Ver el cumple',
        // El pie de la sección: la edad mezclada, en una línea y sin cifras.
        'mixed_note' => '¿Y si vienen niños de las dos edades? Se ajusta niño por niño en recepción.',
        /*
         * EL RELOJ de las dos horas. ⚠️⚠️ **No reparte, y ésa es la regla**: las dos horas son para
         * todo —merienda, tarta y saltos— y no hay hora para nada. Un diagrama de tramos promete
         * horario aunque la letra diga lo contrario, así que se dibuja el TOTAL entero con las tres
         * cosas encima. `:duration` sale del catálogo.
         */
        'clock_title' => 'Las :duration, a vuestro ritmo',
        'clock_a' => 'Saltos', 'clock_b' => 'Merienda', 'clock_c' => 'Tarta',
        'clock_rule' => 'Dentro cabe todo y sin horario: si meriendan rápido, saltan más rato. El orden lo decidís vosotros.',
        'clock_monitor' => 'Un monitor con ellos de principio a fin. Vosotros, sentados.',
        // El bloque de complementos, con el molde compartido con las tarifas (`#483`).
        'addons_title' => 'Tu fiesta, tu manera',
        'addons_intro' => 'Añade lo que quieras: nada de esto hace falta para reservar.',
        'eyebrow' => 'Cumpleaños', 'title' => 'Cumpleaños',
        'included' => 'Incluido en el pack', 'from' => 'Desde',
        'choose' => 'Elige tu cumpleaños',
        'reserve_terms' => 'De :min a :max niños · Señal de :deposit € para reservar',
        'reserve_terms_rich' => 'De :min a :max niños · <strong>Señal de :deposit € para reservar</strong>, se descuenta del total.',
        'bd_photo_cap' => 'Cumpleaños que se recuerdan',
        'bd_sticker' => '¡Felicidades!',
        'bd_ticket_label' => 'Pack cumple',
        'bd_ticket_sub' => 'invitados',
        'invite_link' => '¿Te gustaría crear tu invitación personalizada?',
        'process_eyebrow' => 'Cómo se reserva',
        'process_title' => 'Paso a paso',
        'process_step' => 'Paso', 'process_of' => 'de',
        'process_prev' => 'Paso anterior', 'process_next' => 'Paso siguiente',
        'process' => [
            's1_k' => 'Reserva', 's1_t' => 'Eliges fecha y pack', 's1_s' => 'El día, la hora y el pack, en el calendario online.',
            's2_k' => 'Datos', 's2_t' => 'Rellenas los datos', 's2_s' => 'Tus datos de contacto y el nombre del cumpleañero.',
            's3_k' => 'Depósito', 's3_t' => 'Pagas :deposit € de depósito', 's3_s' => 'Guardan tu fecha y se descuentan del total. El resto, el día del cumple en el parque.',
            's4_k' => 'Detalles', 's4_t' => 'Completas los detalles', 's4_s' => 'Invitados, alergias y tarta — sin prisa, cuando lo tengas claro.',
            's5_k' => '¡Listo!', 's5_t' => '¡A esperar la fecha!', 's5_s' => 'Te recordamos todo por email 48 h antes.',
        ],
        'invite' => [
            'eyebrow' => 'Invitación',
            'title' => 'Tu invitación',
            'intro' => 'Rellena los datos, elige el color de la zona y comparte la tarjeta con los invitados. Se actualiza al instante.',
            'editor_title' => 'Editor — los cambios se guardan solos',
            'color_label' => 'Color',
            'card_eyebrow' => 'Fiesta de cumpleaños',
            'card_msg' => '¡Ven a saltar a mi cumple!',
            'card_ps' => 'PD: ven en calcetines — de los que no resbalan. ¡Vamos a saltar mucho!',
            'lead' => '¡Te invito a mi cumpleaños!',
            'years' => 'años',
            'when' => 'Cuándo', 'time' => 'Hora', 'where' => 'Dónde',
            'name_label' => 'Nombre', 'age_label' => 'Edad', 'date_label' => 'Fecha', 'time_label' => 'Hora',
            'name_placeholder' => 'Nombre del cumpleañero/a',
            'name_fallback' => 'tu nombre',
            'date_fallback' => 'elige la fecha',
            'sample_name' => 'Lucía',
            'download' => 'Descargar tarjeta',
            'share' => 'Compartir',
            'hint' => 'Edita los datos, descarga la tarjeta y compártela con tus invitados.',
            'share_text' => '¡Estás invitado/a a mi cumpleaños en :park! 🎉',
        ],
        'timeline_label' => 'Tu tarde, paso a paso',
        'timeline' => [
            ['time' => '17:00', 'label' => 'Llegada'],
            ['time' => '17:15', 'label' => 'Saltos libres'],
            ['time' => '18:30', 'label' => 'Pizza & tarta'],
            ['time' => '19:00', 'label' => 'Foto de grupo'],
            ['time' => '19:15', 'label' => 'Despedida'],
        ],
        'cta' => 'Reservar cumpleaños',
        'coming_soon' => 'Estamos preparando los packs de cumpleaños. Si quieres reservar antes, escríbenos y te ayudamos.',
        'coming_soon_cta' => 'Contactar',
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
        'title' => 'Dónde estamos y cuándo abrimos',
        'closed' => 'Cerrado',
        'open_generic' => 'Abierto',
        'day_range' => ':from a :to',
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
        'lede_two' => 'Dos horarios: :a y :b.',
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
    'rules' => [
        // ⚠️⚠️ **DE ESTE GRUPO SOLO QUEDAN DOS CLAVES, y su consumidor NO es la portada** (`#485`).
        // La sección de normas de la portada se retiró al entrar la 05 «Antes de venir», y con ella
        // se fueron `eyebrow`, `title`, `register_title`, `register_text`, `register_cta`,
        // `socks_cta` y `all_cta` — todas se quedaron sin quien las pintara.
        // ▶ **`socks_title`/`socks_text` se quedan porque las lee `<x-site.socks-note>`**, que
        // `/precios` sigue usando: esa página no tiene sección de normas donde recoger el requisito.
        // ⚠️ El grupo **no se renombra a `pricing`** aunque hoy solo lo use esa página: ya vivió ahí
        // y `#309` lo movió con su motivo escrito —una clave que nombra la sección de la que la nota
        // acaba de salir induce a error—. Moverlo otra vez sería repetir el viaje entero.
        'socks_title' => 'Calcetines antideslizantes obligatorios',
        'socks_text' => 'Son imprescindibles para saltar de forma segura. Puedes traerlos de casa o añadirlos a tu entrada.',
    ],

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
        'title' => 'Tu registro es este QR',
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
        'carries_lede' => 'Lo enseñas en la puerta y el empleado lo ve todo de una vez: qué has reservado, que ya firmaste y quién viene contigo. Sin buscar tu nombre, sin enseñar el correo y sin rellenar nada allí.',
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
    // ══ SECCIÓN 08 · «DUDAS» ═══════════════════════════════════════════════════════════════
    // `DECISIONES #488` · Fase 2 · T2h. Artboards `Dudas PJP` 1a y `Escritorio PJP` 5c.
    // ⚠️ El titular DEJA de ser «Dudas»: eso es el RÓTULO. `doc/voz.md` fija los ocho titulares
    //    como frases de 3 a 6 palabras porque «el rótulo ya dice el eje de la pregunta».
    // ⚠️ La entradilla dice de dónde salen las preguntas, que es lo que las hace creíbles: no son
    //    de relleno, son las que llegan por teléfono.
    'faq' => [
        'eyebrow' => 'Dudas',
        'title' => 'Lo que más nos preguntáis',
        'lede' => 'Las que llegan por teléfono, contestadas aquí.',
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
        'copy' => 'Reserva tu cumpleaños online en un minuto, con confirmación al instante. ¿Solo vienes a saltar? Ven directo o llámanos.',
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
