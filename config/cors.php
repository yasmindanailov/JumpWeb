<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Publicado y ENDURECIDO en Fase 3 · paso 0 tras un hallazgo empírico: `HandleCors` es middleware
| GLOBAL de Laravel y su configuración por defecto —que vive en el framework hasta que se publica
| este fichero— trae `'paths' => ['api/*']` con `'allowed_origins' => ['*']`. Es decir: crear
| `routes/api.php` abrió CORS a cualquier origen sin que nadie lo decidiera, y `curl -I` lo
| confirmó (`Access-Control-Allow-Origin: *`, presente solo en `/api/v1`).
|
| El riesgo inmediato era acotado —sin `supports_credentials` el navegador no envía cookies
| cross-origin, así que las respuestas autenticadas seguían protegidas—, pero el catálogo y la
| disponibilidad del paso 1 sí habrían quedado legibles desde cualquier web, y sobre todo era
| superficie REGALADA en la fase que abre el dominio al exterior. `INVARIANTES` §4 y el principio
| «el endurecimiento heredado no se regresa» piden lo contrario: decisión explícita.
|
| Diseño elegido, coherente con el resto del producto:
|  - **Nunca `*`.** El origen permitido se DERIVA de `APP_URL`, que es el dominio de la
|    instalación. Una instalación white-label no tiene que tocar código para nada.
|  - **La SPA de Fase 4 vive en el mismo dominio** que la web (spec §4.2), así que ni siquiera pasa
|    por CORS: en el caso normal esta configuración no emite ninguna cabecera. Es un cinturón para
|    el caso raro, no una pieza del camino feliz.
|  - **La app móvil nativa no usa CORS** (no es un navegador), así que Fase 6 no depende de esto.
|  - `CORS_ALLOWED_ORIGINS` (lista separada por comas) permite a una instalación servir la SPA
|    desde otro dominio. Es una decisión de despliegue, con nombre y sitio.
|
*/

$appUrl = (string) env('APP_URL', '');

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Orígenes exactos. Sin comodines y sin patrones: la comparación exacta evita que un
     * subdominio ajeno (`evil.cliente.com`) herede permiso del dominio del cliente.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', $appUrl))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
     * `true` para que una instalación que sí sirva la SPA desde otro dominio pueda usar el modo
     * stateful de Sanctum (cookie de sesión + CSRF). Es seguro porque va SIEMPRE acompañado de
     * orígenes exactos: el navegador prohíbe combinar credenciales con `*`, y aquí `*` no existe.
     */
    'supports_credentials' => true,

];
