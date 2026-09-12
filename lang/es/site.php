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

    'contact_eyebrow' => 'Contacto',
    'contact_title' => 'Hablamos',
    'contact_intro' => '¿Tienes dudas, quieres venir en grupo o organizar un evento? Escríbenos y te respondemos lo antes posible.',
    'contact_name' => 'Nombre',
    'contact_email' => 'Email',
    'contact_phone' => 'Teléfono (opcional)',
    'contact_message' => 'Mensaje',
    'contact_send' => 'Enviar mensaje',
    'contact_success' => '¡Gracias! Hemos recibido tu mensaje y te responderemos pronto.',
    'contact_hp' => 'No rellenar este campo',

    // CTAs directos de la página de contacto (data-driven: solo si el dato existe en Ajustes).
    'contact_quick_title' => 'O contáctanos directamente',
    'contact_call' => 'Llamar',
    'contact_whatsapp' => 'WhatsApp',
    'contact_location' => 'Cómo llegar',

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
