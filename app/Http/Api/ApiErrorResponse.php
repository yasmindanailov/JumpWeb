<?php

namespace App\Http\Api;

use Illuminate\Http\JsonResponse;

/**
 * Fase 3 · paso 0 — **el sobre de error único** de `/api/v1` (spec §4.3).
 *
 * Forma:
 *
 * ```json
 * { "error": { "code": "…", "message": "…", "params": {…}, "fields": {…} } }
 * ```
 *
 *  - `code` — contrato estable (`ApiErrorCode`), lo que el cliente PROGRAMA.
 *  - `message` — texto legible ya traducido, lo que el cliente MUESTRA si no sabe hacer nada mejor.
 *  - `params` — datos para interpolar el mensaje en el cliente. Existe porque `ReservationException`
 *    transporta `product`/`when` (spec §4.3): sin ellos el cliente pintaría «El producto — no está
 *    disponible». Su primer lector real es el 429, que informa `retry_after`.
 *  - `fields` — solo validación: `campo → [mensajes]`, la forma que ya devuelve `$e->errors()`.
 *
 * `params` y `fields` se OMITEN cuando están vacíos (y el esquema OpenAPI los declara opcionales):
 * un `"fields": {}` en cada 500 sería ruido que el cliente tendría que aprender a ignorar.
 *
 * Esta clase es de la capa de ENTREGA: construye HTTP, no decide negocio. Los errores de negocio
 * llegan aquí ya traducidos a un `ApiErrorCode` por quien los conoce.
 */
final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $params  datos para interpolar el mensaje en el cliente
     * @param  array<string, list<string>>  $fields  errores por campo (solo validación)
     * @param  array<string, string>  $headers  cabeceras a conservar (p. ej. `Retry-After`, `Allow`)
     */
    public static function make(
        ApiErrorCode $code,
        int $status,
        ?string $message = null,
        array $params = [],
        array $fields = [],
        array $headers = [],
    ): JsonResponse {
        $error = [
            'code' => $code->value,
            'message' => $message ?? $code->message(),
        ];

        if ($params !== []) {
            $error['params'] = $params;
        }

        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
