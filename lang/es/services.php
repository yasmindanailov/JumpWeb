<?php

// Textos de la página /servicios (diseño v2 «Editorial XL», mockup design_mockup/pagina-servicios-v2.*).
// Se MANTIENE la lógica de v1: página i18n-driven, CTA a /contacto, precios reales [PENDIENTE]
// del cliente (no se muestran). La estructura está pensada para sustituirse por contenido de BD
// cuando exista panel admin (Fase 7) — cada `sections[].anchor` (y `other.anchor`) es ESTABLE:
// está enlazado desde el nav (`/servicios#xxx`) y verificado por test, NO renombrar.
//
//   sections[] = un servicio (fila editorial): anchor · zone (kicker/badge/ficha) · title · body
//                · specs[] = filas de la mini-ficha {label, value} (condiciones rápidas)
//   other      = banda catch-all final («Otros eventos»), conserva el anchor `eventos`

return [
    'meta' => [
        'title' => 'Servicios',
        'description' => 'Excursiones de colegio, empresas, sesiones para adultos y eventos privados en Jumpingjump.',
    ],
    'eyebrow' => 'Para grupos y eventos',
    'title' => 'Más allá del salto libre',
    // Fragmento del título resaltado en el hero (`.blink`, mockup v2). Debe ser una subcadena
    // EXACTA de `title`; si no aparece, el hero cae con elegancia al título plano.
    'title_accent' => 'salto libre',
    'intro' => 'Adaptamos el parque a colegios, empresas y grupos, también fuera de nuestro horario habitual. Pídenos información sin compromiso y diseñamos la jornada contigo.',
    'cta_contact' => 'Pedir información',
    'service_label' => 'Servicio',
    'zone_label' => 'Zona',
    // Tabla de tarifas de grupo (informativa) de una sección — caso «Excursiones de colegio».
    'rates' => [
        'title' => 'Tarifas de grupo',
        'group' => 'Grupo',
        'weekday' => 'L–V',
        'weekend' => 'Finde/festivo',
        'kids' => ':count niños',
        'people' => ':count personas',
        'note_kids' => 'Precio por niño/a; baja según el tamaño del grupo. Sesiones fuera del horario de apertura al público. Reserva por teléfono o pídenos información.',
        'note_people' => 'Precio por persona; baja según el tamaño del grupo. Sesiones fuera del horario de apertura al público. Reserva por teléfono o pídenos información.',
    ],
    // `sections` migradas a la entidad CMS `LandingService` (#256, modelo A): el blade /servicios
    // las lee de BD (sembradas en LandingContentSeeder). Aquí queda solo el chrome de página.
    'other' => [
        'anchor' => 'eventos',
        'title' => 'Otros eventos',
        'body' => 'Despedidas, fiestas privadas, rodajes o cualquier otra idea. Cuéntanos qué tienes en mente y diseñamos una propuesta a medida.',
    ],
];
