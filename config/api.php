<?php

/*
 * Fase 3 — configuración de la superficie `/api/v1`.
 *
 * Es config de INFRAESTRUCTURA, no de negocio: por eso vive aquí y no en `settings` (BD). El
 * criterio data-driven del proyecto dice que lo configurable **desde el panel** es lo que un
 * cliente white-label ajusta por sí mismo (precios, textos, horarios); un techo de peticiones por
 * minuto lo ajusta quien opera el servidor, y hacerlo editable desde el panel sería regalar un
 * interruptor de denegación de servicio a la superficie de administración.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Limitador base de la API
    |--------------------------------------------------------------------------
    |
    | Suelo genérico aplicado a TODO `/api/v1` (limitador `api`, definido en
    | `App\Providers\ApiServiceProvider`). No sustituye a los limitadores específicos, que son
    | mucho más estrictos y viven con su endpoint: los dos de `SEC-06` en login/emisión de tokens
    | (paso 3) y el `throttle:6,1` del reintento de pago del cliente (paso 4).
    |
    | 60/min por identidad —o por IP si no la hay— es el suelo estándar y deja margen 2x sobre el
    | uso legítimo más intenso que tiene el sidebar (recalcular disponibilidad al cambiar de fecha
    | varias veces seguidas). Se sube por entorno, no tocando código.
    |
    */

    'rate_limit' => [
        'per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | La ingesta del libro de eventos
    |--------------------------------------------------------------------------
    |
    | `POST /events` (`specs/analitica.md` §4.1, `#678`) lleva SU limitador (`events`, por visitante) en vez
    | del suelo de arriba: apilado, la analítica se comería el cubo del embudo. El emisor manda un lote
    | cada 5 s o cada 10 eventos, así que 30 lotes/min sobra para una pestaña y frena a un script. El
    | tope diario acota lo que un solo visitante puede meter en una tabla que se conserva 25 meses.
    |
    */

    'events' => [
        'per_minute' => (int) env('API_EVENTS_PER_MINUTE', 30),
        'per_day' => (int) env('API_EVENTS_PER_DAY', 2000),
        'batch_max' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Especificación OpenAPI
    |--------------------------------------------------------------------------
    |
    | Dónde vive el documento, en dos piezas porque los tests de contrato necesitan el directorio
    | y el nombre por separado. Es un fichero ESTÁTICO versionado y escrito a mano: `DECISIONES #21`
    | descartó generarlo desde el código precisamente para que el contrato no pueda cambiar en
    | silencio con un refactor. Lo consumen los tests de contrato y, en Fase 6, quien construya la
    | app móvil.
    |
    */

    'openapi' => [
        'directory' => env('API_OPENAPI_DIRECTORY', 'openapi'),
        'file' => env('API_OPENAPI_FILE', 'v1.yaml'),
    ],

];
