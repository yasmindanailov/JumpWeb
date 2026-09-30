<?php

// Banner y panel de consentimiento de cookies (#219). Web pública trilingüe (es/en/fr).
// T3a de la analítica (`specs/analitica.md` §4.3): cuatro finalidades —mapa y reseñas, redes, análisis de uso
// identificado y publicidad—; la medición de audiencia propia es exenta y se explica en «Necesarias».
// ⚠️ Cada categoría de `CookieConsent::OPTIONAL` necesita su `<categoría>_title` y `<categoría>_desc`.

return [
    'banner' => [
        'aria' => 'Aviso de cookies',
        'eyebrow' => 'Cookies',
        'title' => 'Antes de saltar…',
        'text' => 'Usamos cookies propias para que la web funcione y para medir la audiencia de forma anónima. Solo con tu permiso: el mapa de Google, el análisis de uso vinculado a tu cuenta y la publicidad.',
        'policy' => 'Más información',
        'accept' => 'Aceptar',
        'reject' => 'Rechazar',
        'configure' => 'Configurar',
        'manage_link' => 'Configuración de cookies',
    ],

    'panel' => [
        'necessary_title' => 'Necesarias',
        'always_on' => 'Siempre activas',
        'necessary_desc' => 'Imprescindibles para la sesión, la seguridad de los formularios y el carrito, más una cookie propia de 13 meses que mide la audiencia de forma anónima, sin cruzar ni ceder datos. Están exentas de consentimiento.',
        // Solo el mapa desde `#771`/`#772`: las reseñas son nuestras y no se piden a Google (la finalidad se ESTRECHA: no
        // se vuelve a pedir el consentimiento). La clave `maps` no cambia.
        'maps_title' => 'Mapa (Google)',
        'maps_desc' => 'Permite mostrar el mapa de Google para llegar al parque. Google puede instalar sus propias cookies y tratar datos en EE. UU.',
        'social_title' => 'Redes sociales',
        'social_desc' => 'Permite mostrar nuestras últimas publicaciones de Instagram/TikTok mediante un widget externo, que puede instalar sus propias cookies.',
        'analytics_title' => 'Análisis de uso identificado',
        'analytics_desc' => 'Permite vincular tu navegación a tu cuenta cuando entras o compras, para entender cómo usas la web, y usar una herramienta de análisis con un identificador cifrado en lugar de tu nombre. La medición anónima de la audiencia no necesita este permiso.',
        'marketing_title' => 'Publicidad',
        'marketing_desc' => 'Permite cargar los píxeles de las plataformas de anuncios y comunicarles las compras, para medir qué campañas funcionan. Sin este permiso no se carga ningún píxel ni se comunica nada.',
        'reject_all' => 'Rechazar todo',
        'save' => 'Guardar preferencias',
        'accept_all' => 'Aceptar todo',
        'policy_link' => 'Leer la política de cookies',
    ],

    // EL LISTADO de `/cookies` (`specs/politica-de-cookies.md` §3): lo compone `CookieInventory` con lo que ESTA instalación
    // tiene encendido y viaja en `GET /legal/documents/cookies` (`inventory`). Una fila por cookie o por tercero.
    'inventory' => [
        'title' => 'Las cookies de esta web, una a una',
        'intro' => 'Este listado lo compone la propia web con lo que tiene activo ahora mismo: si algo se enciende o se apaga, el listado cambia con ello.',
        'labels' => ['holder' => 'Quién la pone', 'purpose' => 'Para qué', 'duration' => 'Cuánto dura', 'when' => 'Cuándo'],
        'own' => 'Nosotros (cookie propia)',
        'category' => [
            'necessary' => 'Necesaria',
            'on_request' => 'Preferencia (la pides tú)',
            'measurement' => 'Medición de audiencia (exenta)',
            'maps' => 'Mapa (con tu permiso)',
            'social' => 'Redes sociales (con tu permiso)',
            'analytics' => 'Análisis (con tu permiso)',
            'marketing' => 'Publicidad (con tu permiso)',
        ],
        'hours' => ':n horas desde tu última visita',
        'minutes' => ':n minutos desde tu última visita',
        'months' => ':n meses',
        'session' => ['purpose' => 'Mantiene tu visita: el recorrido de la compra y, si entras, tu sesión.', 'when' => 'Siempre'],
        'xsrf' => ['purpose' => 'Seguridad: comprueba que los formularios los envías tú y no otra web (protección CSRF).', 'when' => 'Siempre'],
        'visitor' => ['purpose' => 'Cuenta las visitas y de qué campañas llegan, solo en estadísticas anónimas para nosotros: no se cruza con otros sitios ni se cede.', 'when' => 'Siempre; no se renueva con cada visita'],
        'consent' => ['purpose' => 'Guarda lo que decidiste en el aviso de cookies, para no volver a preguntarte.', 'duration' => ':n meses (algunos navegadores la guardan menos)', 'when' => 'Cuando aceptas, rechazas o configuras'],
        'remember' => ['purpose' => 'Mantiene la sesión iniciada en este dispositivo, para no pedirte un código cada vez.', 'duration' => ':n días desde tu última visita, o hasta que cierres sesión', 'when' => 'Solo si marcas «Mantener la sesión iniciada en este dispositivo» al entrar'],
        'redsys' => ['name' => 'Las de la pasarela de pago', 'holder' => 'Redsys Servicios de Procesamiento, S.L., en su propia web', 'purpose' => 'Procesar el pago con tarjeta que tú inicias.', 'duration' => 'Las que fije Redsys', 'when' => 'Solo al pagar, en la página de Redsys'],
        'turnstile' => ['name' => 'Las del sistema antibots (Turnstile)', 'holder' => 'Cloudflare, Inc. (EE. UU., bajo el EU-US Data Privacy Framework)', 'purpose' => 'Distinguir a las personas de los robots en los formularios.', 'duration' => 'Temporales', 'when' => 'Al darte de alta'],
        'maps' => ['name' => 'Las de Google Maps', 'holder' => 'Google Ireland Limited (y Google LLC, en EE. UU., bajo el EU-US Data Privacy Framework)', 'purpose' => 'Mostrar el mapa de cómo llegar.', 'duration' => 'Las que fije Google (consulta su política)', 'when' => 'Solo si autorizas «Mapa (Google)»'],
        'social' => ['name' => 'Las del widget de redes (:provider)', 'holder' => ':provider (proveedor externo; su garantía de transferencia, en su política de privacidad)', 'purpose' => 'Mostrar nuestras últimas publicaciones de redes sociales.', 'duration' => 'Las que fije el proveedor', 'when' => 'Solo si autorizas «Redes sociales»'],
        'posthog' => ['name' => 'Las de PostHog (p. ej. «ph_…_posthog»)', 'holder' => 'PostHog Inc. (los datos se alojan en servidores de la Unión Europea)', 'purpose' => 'Entender cómo se usa la web con un identificador cifrado, sin tu nombre, tu correo ni tu dirección IP.', 'duration' => 'Hasta 12 meses', 'when' => 'Solo si autorizas «Análisis de uso identificado»'],
        'matomo' => ['name' => 'Las de Matomo (p. ej. «_pk_id» y «_pk_ses»)', 'holder' => 'Nosotros, con Matomo instalado en :host', 'purpose' => 'Entender cómo se usa la web con un identificador cifrado, sin tu nombre, tu correo ni tu dirección IP.', 'duration' => 'Hasta 13 meses («_pk_ses», 30 minutos)', 'when' => 'Solo si autorizas «Análisis de uso identificado»'],
        'google_ads' => ['name' => 'Las de Google Ads (p. ej. «_gcl_au»)', 'holder' => 'Google Ireland Limited (EE. UU., bajo el EU-US Data Privacy Framework)', 'purpose' => 'Saber qué anuncios traen compras: recibe la compra con un identificador y tus datos de contacto solo como huella irreversible (hash).', 'duration' => 'Hasta 90 días', 'when' => 'Solo si autorizas «Publicidad»'],
        'meta' => ['name' => 'Las de Meta, para Facebook e Instagram (p. ej. «_fbp»)', 'holder' => 'Meta Platforms Ireland Limited (EE. UU., bajo el EU-US Data Privacy Framework)', 'purpose' => 'Saber qué anuncios traen compras: recibe la compra con un identificador y tus datos de contacto solo como huella irreversible (hash).', 'duration' => 'Hasta 90 días', 'when' => 'Solo si autorizas «Publicidad»'],
        'tiktok' => ['name' => 'Las de TikTok (p. ej. «_ttp»)', 'holder' => 'TikTok Technology Limited (fuera del EEE, bajo cláusulas contractuales tipo)', 'purpose' => 'Saber qué anuncios traen compras: recibe la compra con un identificador y tus datos de contacto solo como huella irreversible (hash).', 'duration' => 'Hasta 13 meses', 'when' => 'Solo si autorizas «Publicidad»'],
    ],

    'frame' => [
        'maps_text' => 'Para ver el mapa hay que cargar contenido de Google Maps, que puede instalar cookies.',
        'maps_btn' => 'Cargar el mapa',
        'social_text' => 'Para ver el feed hay que cargar contenido de un proveedor externo, que puede instalar cookies.',
        'social_btn' => 'Cargar el contenido',
        'policy_link' => 'Política de cookies',
        'noscript' => 'Con JavaScript desactivado no cargamos este contenido de terceros, para no instalar cookies sin tu permiso.',
    ],
];
