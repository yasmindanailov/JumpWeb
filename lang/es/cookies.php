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
        'text' => 'Usamos cookies propias para que la web funcione y para medir la audiencia de forma anónima. Solo con tu permiso: el mapa y las reseñas de Google, el análisis de uso vinculado a tu cuenta y la publicidad.',
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
        'maps_title' => 'Mapa y reseñas (Google)',
        'maps_desc' => 'Permite mostrar el mapa de ubicación de Google y las reseñas publicadas en Google, con la foto de quien las escribe. Google puede instalar sus propias cookies y tratar datos en EE. UU.',
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

    // T3a·2: la herramienta de análisis activa, nombrada en `/cookies` al pintar (no en el texto guardado).
    'policy' => [
        'tool_active' => 'Herramienta de análisis de uso activa en esta web: :tool. Solo se carga si autorizas la categoría «análisis», y nunca recibe tu nombre, tu correo ni tu dirección IP.',
        'tool_posthog' => 'PostHog (PostHog Inc.; los datos se alojan en servidores de la Unión Europea)',
        'tool_matomo' => 'Matomo (instalación propia en :host)',
        // T3b·3: las plataformas de anuncios activas, nombradas al pintar con su empresa responsable y su garantía
        // de transferencia. `[PENDIENTE: asesoría]` validar la garantía de cada una (ver `COOKIES.md` §1).
        'ads_active' => 'Plataformas de anuncios activas en esta web: :platforms. Solo se cargan si autorizas la categoría «publicidad»; reciben la compra con un identificador y tus datos de contacto solo como huella irreversible (hash), nunca en claro.',
        'ads_google_ads' => 'Google Ads (Google Ireland Limited; transferencias a Estados Unidos bajo el EU-US Data Privacy Framework)',
        'ads_meta' => 'Meta, para Facebook e Instagram (Meta Platforms Ireland Limited; transferencias a Estados Unidos bajo el EU-US Data Privacy Framework)',
        'ads_tiktok' => 'TikTok (TikTok Technology Limited; transferencias fuera del EEE bajo cláusulas contractuales tipo)',
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
