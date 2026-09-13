<?php

// Textos de la página /servicios (diseño v2 «Editorial XL», mockup design_mockup/pagina-servicios-v2.*).
// Se MANTIENE la lógica de v1: página i18n-driven, CTA a /contacto, precios reales [PENDIENTE]
// del cliente (no se muestran). La estructura está pensada para sustituirse por contenido de BD
// cuando exista panel admin (Fase 7) — cada `sections[].anchor` es ESTABLE: está enlazado desde el
// nav (`/servicios#xxx`) y verificado por test, NO renombrar.
//
//   sections[] = un servicio (fila editorial): anchor · zone (kicker/badge/ficha) · title · body
//                · specs[] = filas de la mini-ficha {label, value} (condiciones rápidas)
//   ⚠️ `other` («Otros eventos», ancla `eventos`) se retiró en `#586` con su banda.

return [
    'meta' => [
        'title' => 'Excursiones de colegio',
        'description' => 'Excursiones escolares a un parque de trampolines: precio por alumno, calcetines y monitores incluidos, y reserva online.',
    ],
    'eyebrow' => 'Para grupos y eventos',
    // `#586`, `[DECIDIDO owner]`: de los servicios de grupo solo se ofrecen las excursiones de colegio.
    'title' => 'Excursiones de colegio',
    // Fragmento del título resaltado en el hero (`.blink`, mockup v2). Debe ser una subcadena
    // EXACTA de `title`; si no aparece, el hero cae con elegancia al título plano.
    'title_accent' => '',
    'intro' => 'Traed a vuestra clase a saltar. Todo lo que necesitáis saber, y la reserva, aquí.',
    'cta_contact' => 'Pedir información',
    'service_label' => 'Servicio',
    'zone_label' => 'Zona',
    // Tabla de tarifas de grupo (informativa) de una sección — caso «Excursiones de colegio».
    'rates' => [
        'title' => 'Tarifas de grupo',
        'group' => 'Grupo',
        'weekday' => 'Lunes a jueves',
        'weekend' => 'Viernes a domingo y festivos',
        // El pie de cada tabla: su duración, escrita («2 horas»; antes «2H»).
        'hours' => '{1} :count hora|[2,*] :count horas',
        'kids' => 'Desde :count alumnos',
        'people' => ':count personas',
        'note_kids' => 'Precio por alumno. Incluye calcetines y monitores; un profesor gratis por cada 15 alumnos. ¿Merienda? Pregúntanos.',
        'note_people' => 'Precio por persona; baja según el tamaño del grupo. Sesiones fuera del horario de apertura al público. Reserva por teléfono o pídenos información.',
    ],
    // `sections` migradas a la entidad CMS `LandingService` (#256, modelo A): el blade /servicios
    // las lee de BD (sembradas en LandingContentSeeder). Aquí queda solo el chrome de página.
];
