<?php

return [
    'legal_eyebrow' => 'Información legal',
    'rules_eyebrow' => 'Normas',
    /*
     * ⚠️ `rules_title` es el NOMBRE del destino —lo usan el 404 y el `<title>`— y el TITULAR de la
     * página es otro: el artboard `Normas PJP` escribe «Lo que hay que cumplir», que dice qué vas a
     * leer en vez de repetir la etiqueta del menú (`DECISIONES #533`).
     */
    'rules_title' => 'Normas',
    'rules_headline' => 'Lo que hay que cumplir',
    // La entradilla de la cabecera de `/normas` (`#525`), la que escribe `Layout Paginas PJP`.
    'rules_intro' => 'Son pocas y todas tienen un motivo. Se leen una vez y ya está.',

    /*
     * LOS TRES MOMENTOS (`#533`). Cada uno lleva DOS rótulos y no es redundancia: el de arriba dice
     * **cuándo** (es el índice por el que se recorre la página) y el titular dice **dónde estás**.
     * ⚠️ Las claves son las de `VenueRule::MOMENTS`: si alguien añade un momento sin su rótulo, la
     * página lo diría con la clave en crudo — lo vigila la guarda.
     */
    'rules_moment' => [
        'before' => ['label' => 'Antes de venir', 'title' => 'En casa'],
        'gate' => ['label' => 'En la puerta', 'title' => 'Al llegar'],
        'inside' => ['label' => 'Dentro', 'title' => 'Mientras saltas'],
    ],
    // Las normas que el panel dejó sin momento: se publican igual, al final y sin rótulo de grupo.
    'rules_other' => 'Además',

    // LA ESCALA DE ALTURA. ⚠️ La nota va debajo y NO dentro de las bandas: son las excepciones con
    // condiciones («si tiene la edad pero mide entre…»), y ahí no caben ni se leen.
    'rules_axis_title' => 'La altura, de un vistazo',
    // La franja que comparten dos zonas en la escala de altura (`#589`): «Kids o Jump · según la edad».
    'rules_axis_overlap' => ':zones · según la edad',
    'rules_axis_or' => 'o',

    // LA CHAPA DEL DESCARGO: lo único de la página que se FIRMA, y por eso va en tinta.
    'rules_waiver_title' => 'El texto que firmas',
    'rules_waiver_text' => 'Al registrarte apruebas estas normas y el descargo de responsabilidad: el documento donde reconoces que saltar tiene su riesgo y que vas a seguir las indicaciones. Se firma una vez, para ti y para tus hijos.',
    'rules_waiver_cta' => 'Leer el descargo entero',

    // La línea final: lo que antes era el bloque «Información», que no es una norma.
    'rules_staff' => '¿Te queda alguna duda? Pregúntanos en el parque, por teléfono o desde Contacto.',
    'rules_updated' => 'Actualizado en :fecha',
    'back_home' => '← Volver al inicio',

    // ══ /contacto ═══════════════════════════════════════════════════════════════════════════
    // Carril de diseño Fase 3 · T3b (`DECISIONES #535`). Artboard `Contacto PJP` 1a/1b.
    // ⚠️ **La entradilla NO enumera canales** aunque el artboard lo haga: los suyos son tres y los
    // de una instalación son los que su panel tenga. Quien los dice es el bloque de canales, que
    // sale del dato.
    'contact_eyebrow' => 'Contacto',
    'contact_title' => 'Hablamos',
    // Dos redacciones porque el plazo se DERIVA: con horario declarado se dice cuándo, y sin él no
    // se promete nada. `[DECIDIDO owner, 2026-09-12]`: el horario de atención ES el de apertura.
    'contact_intro' => 'Cuéntanos qué necesitas: te contestamos dentro de nuestro horario de apertura.',
    'contact_intro_plain' => 'Cuéntanos qué necesitas y te contestamos lo antes posible.',
    'contact_name' => 'Nombre',
    'contact_email' => 'Correo',
    'contact_phone' => 'Teléfono',
    'contact_phone_hint' => 'si prefieres que te llamemos',
    'contact_message' => 'Tu mensaje',
    'contact_send' => 'Enviar',
    'contact_success' => '¡Gracias! Hemos recibido tu mensaje y te responderemos pronto.',
    'contact_hp' => 'No rellenar este campo',
    'contact_form_title' => 'Escríbenos',
    // No repite la entradilla (`#586`): la de arriba ya dice «cuéntanos qué necesitas».
    'contact_form_lede' => 'Te contestamos al correo en horario de apertura.',
    // Junto al botón. La entradilla dice CUÁNDO; esto dice POR DÓNDE, que es lo que hay que saber
    // justo antes de pulsar (el artboard lo pide ahí a propósito).
    'contact_reply_note' => 'Te contestamos al correo.',
    // ⚠️ El enlace va FUERA de la frase de la casilla y se distingue del párrafo: el mismo arreglo
    // que `#350` («su enlace era INVISIBLE»). Sin casilla, por `[DECIDIDO owner, 2026-09-12]`.
    'contact_privacy_notice' => 'Usamos lo que nos escribas solo para contestarte.',
    'contact_privacy_link' => 'Cómo tratamos tus datos',

    // El motivo del mensaje. ⚠️ **Sin opción preseleccionada**: dejarla en «Un cumpleaños» haría
    // que quien no lo toca mandara un tema que no ha elegido — una ausencia no es una afirmación.
    'contact_topic' => '¿Sobre qué?',
    'contact_topic_none' => 'Elige un tema (opcional)',
    'contact_topics' => [
        'birthday' => 'Un cumpleaños',
        'groups' => 'Grupos y colegios',
        'booking' => 'Una reserva que ya tengo',
        'other' => 'Otra cosa',
    ],

    // Los canales. Salen del panel; el que no tiene dato no se pinta.
    // ⚠️ `phone_whatsapp` es UNA tarjeta y existe porque el dato lo decide: cuando el teléfono y el
    // WhatsApp son el mismo número, dos tarjetas dirían el mismo número dos veces.
    'contact_channels_title' => 'Por dónde prefieras',
    'contact_channel' => [
        'phone' => ['t' => 'Teléfono', 'd' => 'Para hablar con recepción.'],
        'phone_whatsapp' => ['t' => 'Teléfono y WhatsApp', 'd' => 'Llámanos o escríbenos al mismo número.'],
        'whatsapp' => ['t' => 'WhatsApp', 'd' => 'Si prefieres escribir, es lo más rápido.'],
        'email' => ['t' => 'Correo', 'd' => 'Para grupos, facturas y todo lo que necesite quedar por escrito.'],
    ],

    // La chapa de atajos: un contacto que se puede evitar es un correo que no hay que contestar.
    'contact_answers_title' => 'Quizá ya está contestado',

    // La dirección, escrita. ⚠️ **Aquí NO hay mapa**: vive en la sección de la portada y esto
    // apunta a ella (regla del artboard, `#535`).
    'contact_where_title' => 'Dónde estamos',
    'contact_where_cta' => 'Ver el mapa y el horario',

    // ══ /bar ══════════════════════════════════════════════════════════════════════════════════
    // Carril de diseño Fase 3 · T3b (`DECISIONES #536`). Artboard `Bar PJP` 1a/1b.
    // ⚠️ **Aquí SOLO va lo que es del PRODUCTO.** El nombre del bar, su frase y el pie de la foto
    // son de este cliente y viven en el panel (`bar.*`). Lo de abajo es cierto en cualquier
    // instalación: ninguna vende comida por la web, así que en todas se pide en la barra.
    'bar_menu_title' => 'La carta',
    'bar_menu_zoom' => 'Ver a tamaño completo',
    // ⚠️ **La carta es una IMAGEN** (`[DECIDIDO owner]`), así que esta frase es la que explica por
    // qué hay que ampliarla en un móvil. Sin ella, quien no puede leerla no sabe que puede.
    'bar_menu_hint' => 'Toca la carta para verla a tamaño completo.',
    'bar_counter_title' => 'Se pide en la barra',
    'bar_counter_text' => 'No hace falta reservar mesa ni pedir por la web: te sientas donde quieras y pides en la barra. Se paga allí mismo.',
    // ❗ Obligación legal (Reglamento UE 1169/2011): con comida hay que informar de los alérgenos, y
    // la norma admite hacerlo de viva voz SIEMPRE que se diga de forma visible dónde preguntarlo.
    // `[DECIDIDO owner, 2026-09-12]`: esta línea es esa indicación.
    'bar_allergens' => 'Si tienes alguna alergia o intolerancia, pregunta en la barra antes de pedir: te decimos los alérgenos de cualquier plato.',
    'bar_free_entry_yes' => 'Puedes venir solo al bar, sin sacar entrada al parque.',
    'bar_free_entry_no' => 'Para entrar al bar hace falta entrada al parque.',
    'bar_party_line' => 'Si venís de cumpleaños, la comida de los niños va incluida en el pack y se sirve en estas mesas.',

    'visit_hours' => 'Horarios y ubicación',
    'visit_hours_sub' => 'Cuándo y dónde estamos',

    // Página 404.
    'e404_eyebrow' => 'Te has salido del trampolín',
    'e404_title' => 'Página no encontrada',
    'e404_body' => 'La página que buscas no existe o se ha movido. Vuelve al inicio, reserva tu salto o salta a una de estas secciones.',
    'e404_home' => 'Volver al inicio',
    'e404_book' => 'Reservas aquí',
    'e404_popular' => '¿Buscabas alguna de estas?',

    // Mantenimiento (#218). Página 503 de sitio entero + banner de bypass para el personal.
    'maintenance' => [
        'eyebrow' => 'Mantenimiento',
        'title' => 'Volvemos enseguida',
        'body' => 'Estamos haciendo mejoras en la web. Vuelve dentro de un rato; si necesitas algo, llámanos o escríbenos.',
        'contact' => '¿Necesitas algo ahora?',
        'preview_banner' => 'Estás viendo la web en MODO MANTENIMIENTO: solo tú (personal) la ves; los visitantes ven la página de «Volvemos enseguida».',
        'preview_manage' => 'Gestionar mantenimiento',
    ],

    // Mantenimiento POR PÁGINA (#218, item 1): solo esta sección está caída (con nav/pie para seguir).
    'page_maintenance' => [
        'eyebrow' => 'Sección no disponible',
        'title' => 'Esta sección está en mantenimiento',
        'body' => 'Estamos actualizando esta página. Vuelve dentro de un rato; mientras, puedes seguir navegando por el resto de la web.',
    ],

    // ── LAS BANDAS DE ENLACE ────────────────────────────────────────────────────────────────────
    // Carril de diseño Fase 3 · artboard `Bandas PJP` · reparto en `Content\Services\LinkBands`.
    //
    // ⚠️⚠️ **NINGÚN CUERPO AFIRMA UN DATO DEL PARQUE, y es una desviación deliberada del artboard.**
    // Él escribe la gorda de `/atracciones` como «A partir de 1,30 m se sube solo. Desde 1 m, con un
    // adulto al lado», y esas alturas son de ESTE parque: viven en `park_rules`, las pone el panel y
    // `/normas` las pinta desde ahí (`#533`). Escribirlas aquí las clavaría en el producto
    // (`DECISIONES #1`) y, peor, podrían **desmentir a la página a la que la banda lleva** sin que
    // nada fallara. Cada cuerpo DESCRIBE su destino; los datos los dice el destino.
    //
    // ⚠️ Y la pregunta de `/precios` dice «en grupo» donde el artboard dice «ocho»: el número de
    // personas a partir del cual sale a cuenta un pack es `min_qty`, que es dato del catálogo.
    //
    // ▶ Varias frases de `go` reutilizan a propósito palabras que el producto ya escribe («Lo que
    // hay que cumplir» es `rules_headline`; «Todo lo que hay dentro» titula `/atracciones`): dos
    // nombres para el mismo sitio son dos sitios para quien lee.
    'bands' => [
        'lead' => 'Sigue por aquí',

        // Lo que cada página deja abierto, por página de ORIGEN.
        'ask' => [
            'atracciones' => [
                'q' => '¿Puede subir tu hijo?',
                'body' => 'Qué hace falta para entrar en cada zona, las edades y lo que hay que traer puesto. Está todo en una página.',
            ],
            'precios' => [
                'q' => '¿Y si venís en grupo?',
                'body' => 'Los cumpleaños tienen su propia tarifa, y no es lo mismo que sumar entradas sueltas.',
            ],
            'cumpleanos' => [
                'q' => '¿Y los adultos qué hacen?',
                'body' => 'Qué hay para comer y beber mientras los niños celebran, y dónde sentarse a esperar.',
            ],
            'bar' => [
                'q' => '¿Y qué hay para saltar?',
                'body' => 'Las zonas del parque, una por una, con lo que se puede hacer en cada una.',
            ],
            'normas' => [
                'q' => 'Ya lo sé todo, ¿cuánto es?',
                'body' => 'Las tarifas por zona y por tiempo, y qué incluye cada una.',
            ],
            // ⚠️ `ask` va por la página de ORIGEN: esta es la banda del pie de `/servicios`, que lleva a
            // Contacto (`#589`, `[DECIDIDO owner]`: la página vende excursiones, y a quien viene con otro
            // grupo se le invita a escribir).
            'servicios' => [
                'q' => '¿Venís con otro tipo de grupo?',
                'body' => 'Empresas, asociaciones, campamentos o un grupo de amigos: cuéntanos cuántos sois y qué tenéis en mente, y te respondemos con las opciones y el precio.',
            ],
        ],

        // Qué se encuentra en cada DESTINO: el rótulo del botón de la gorda y el nombre de la fina.
        // ▶ `what` es también la frase bajo cada destino del MENÚ (`SiteDestinations::pages()`, `#587`):
        // una frase por destino, la misma en las dos superficies.
        'go' => [
            'atracciones' => ['cta' => 'Ver las atracciones', 'what' => 'Todo lo que hay dentro'],
            'precios' => ['cta' => 'Ver las tarifas', 'what' => 'Cuánto cuesta saltar'],
            'cumpleanos' => ['cta' => 'Ver los cumpleaños', 'what' => 'Celebra su día aquí'],
            'bar' => ['cta' => 'Ver el bar', 'what' => 'Para comer y esperar'],
            'normas' => ['cta' => 'Ver las normas', 'what' => 'Lo que hay que saber'],
            'servicios' => ['cta' => 'Ver las excursiones', 'what' => 'Para colegios'],
            'contacto' => ['cta' => 'Pedir información', 'what' => 'Llámanos o escríbenos'],
        ],
    ],
];
