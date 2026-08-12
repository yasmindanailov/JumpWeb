<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    | JumpWeb (Fase 3 · paso 0): la SPA del sidebar (Fase 4) vive en el MISMO dominio que la web,
    | así que en producción el único valor que importa es el que `APP_URL` aporta por
    | `currentApplicationUrlWithPort()`. Los `localhost*` del arranque se dejan porque son los que
    | usan el entorno local y la suite, y no amplían la superficie real: `trustHosts()`
    | (`bootstrap/app.php`) ya rechaza en producción cualquier `Host` que no sea el dominio de
    | `APP_URL` o un subdominio suyo, de modo que una petición nunca llega con esos hosts.
    | Instalación white-label con la SPA en otro dominio ⇒ fijar `SANCTUM_STATEFUL_DOMAINS` en su
    | `.env` (y revisar CORS), nunca tocar este fichero.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    | JumpWeb (Fase 3 · paso 0): el arranque de Sanctum trae `null`, que significa **tokens sin
    | caducidad**. Se fija un techo desde el primer día —30 días— aunque todavía no exista ningún
    | emisor (`POST auth/tokens` llega en el paso 3), porque el default inseguro es justo el que
    | nadie recuerda cambiar después. No afecta a la SPA: las sesiones de primera parte se rigen
    | por `SESSION_LIFETIME`.
    |
    | La política FINA del cliente móvil (abilities por token, renovación y qué pasa al caducar en
    | mitad de un pago) se decide en el paso 3 del spec, con el flujo delante. Esto es solo el
    | suelo seguro. La poda de los ya caducados la hace `sanctum:prune-expired` (`routes/console.php`).
    |
    */

    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 30),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
