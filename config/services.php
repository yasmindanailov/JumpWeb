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

];
