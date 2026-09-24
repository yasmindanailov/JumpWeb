<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Redsys: la clave secreta real del comercio vive en `.env` (vault de Enhance), NUNCA en BD
     * (un dump la expondría) ni en el repo. Auditoría Fase 1 (H3): leerla aquí —y no con `env()`
     * directo en código de app— es OBLIGATORIO para que sobreviva a `php artisan config:cache`
     * (paso del runbook de despliegue). Con la config cacheada, `env()` en runtime devuelve `null`
     * y el cobro caería al fallback de sandbox → apagón de cobros en producción. `config()` SÍ
     * se hornea en la caché. El resto de settings de Redsys (merchant_code, terminal, entorno…)
     * son data-driven desde el panel y NO son secretos; solo la clave pasa por `.env`/config.
     */
    'redsys' => [
        'secret_key' => env('REDSYS_SECRET_KEY'),
    ],

    /*
     * **Las credenciales PRIVADAS de la herramienta de análisis** (`specs/analitica.md` §4.3, T3a·2): las que
     * usa `ForgetPersonInDriver` para borrar a una persona en el driver cuando retira el consentimiento o se
     * anonimiza. ⚠️ Van en `.env` y nunca en `settings` (`PAY-06`, la misma razón que Redsys): el token PÚBLICO
     * de proyecto sí vive en el panel (`analytics.posthog_project`), porque viaja en cada página igualmente.
     * `POSTHOG_API_HOST` es la API de la nube (eu.posthog.com), distinta del host de ingesta (eu.i.posthog.com).
     */
    'posthog' => [
        'personal_api_key' => env('POSTHOG_PERSONAL_API_KEY'),
        'project_id' => env('POSTHOG_PROJECT_ID'),
        'api_host' => env('POSTHOG_API_HOST', 'https://eu.posthog.com'),
    ],

    'matomo' => [
        'token_auth' => env('MATOMO_TOKEN_AUTH'),
    ],

    /*
     * **Los tokens de las APIs de conversiones** (`specs/analitica.md` §4.3, T3b): Meta Conversions API y
     * TikTok Events API, que usa el job `SendConversionToPlatforms` (T3b·2) para comunicar la compra desde el
     * servidor con el código del pedido como id (dedup con el píxel). ⚠️ En `.env`, nunca en `settings`
     * (`PAY-06`): los ids PÚBLICOS de los píxeles sí viven en el panel (`marketing.*`), porque viajan en cada
     * página igualmente. Sin token, el job no manda nada y lo anota.
     */
    'meta' => [
        'access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'api_version' => env('META_CAPI_VERSION', 'v21.0'),
    ],

    'tiktok' => [
        'access_token' => env('TIKTOK_EVENTS_ACCESS_TOKEN'),
    ],

    /*
     * **La clave de Places API (New)** para las reseñas de la landing (`DECISIONES #491`,
     * `specs/google-reviews.md` §4.4).
     *
     * ⚠️⚠️ **Va en `.env` y NUNCA en el panel** (`SEC-11`), y se lee por `config()` y no con `env()`
     * directo, por el mismo motivo que la clave de Redsys: con `php artisan config:cache` —paso del
     * runbook de despliegue— `env()` devuelve `null` en runtime y la integración se apagaría en
     * silencio. `config()` sí se hornea.
     * ⚠️ El `place_id` NO va aquí: cambia por instalación y es la única cosa que la política de
     * Google exime de sus límites de caché, así que vive en `settings` (`social.google_place_id`).
     */
    'google_places' => [
        'key' => env('GOOGLE_PLACES_API_KEY'),
    ],

];
