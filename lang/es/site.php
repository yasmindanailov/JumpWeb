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
    'rules_axis_label' => 'altura',

    // LA CHAPA DEL DESCARGO: lo único de la página que se FIRMA, y por eso va en tinta.
    'rules_waiver_title' => 'El texto que firmas',
    'rules_waiver_text' => 'Al registrarte apruebas estas normas y el descargo de responsabilidad: el documento donde reconoces que saltar tiene su riesgo y que vas a seguir las indicaciones. Se firma una vez, para ti y para tus hijos.',
    'rules_waiver_cta' => 'Leer el descargo entero',

    // La línea final: lo que antes era el bloque «Información», que no es una norma.
    'rules_staff' => 'Cualquier duda sobre tarifas, cumpleaños o promociones, pregunta al personal del parque: están para eso.',
    'rules_updated' => 'Actualizado en :fecha',
    'back_home' => '← Volver al inicio',
    'legal_draft_notice' => 'Texto provisional pendiente de revisión legal.',

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
    'contact_form_lede' => 'Cuéntanos qué necesitas y te contestamos al correo.',
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
];
