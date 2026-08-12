<?php

namespace App\Http\Api;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Fase 3 · paso 0 — traduce CUALQUIER excepción de `/api/v1` al sobre único (spec §4.3).
 *
 * Se registra en `bootstrap/app.php` (`withExceptions`) y devuelve `null` para todo lo que no sea
 * la API: la web sigue con sus páginas de error de siempre.
 *
 * Tres decisiones que no son obvias:
 *
 * 1. **El mensaje NUNCA sale de la excepción.** Sale del catálogo i18n (`lang/<idioma>/api.php`). No es
 *    cosmética: `ModelNotFoundException` se convierte en un 404 cuyo `getMessage()` es «No query
 *    results for model [App\Domain\Booking\Models\Order] 123» — el FQCN del modelo y el id ajeno,
 *    servidos a un atacante. La única excepción es el 500 con `APP_DEBUG`, que es un entorno de
 *    desarrollo por definición.
 * 2. **Corre DESPUÉS de `prepareException`** (verificado en `Foundation\Exceptions\Handler::render`):
 *    aquí ya no llegan `ModelNotFoundException` ni `AuthorizationException`, sino los
 *    `HttpException` equivalentes. Por eso el mapa principal es por STATUS y no por clase.
 * 3. **Las cabeceras se conservan por allowlist.** `Retry-After` (429/503) y `Allow` (405) son
 *    parte de la respuesta correcta; el resto de cabeceras de una excepción no se propagan a
 *    ciegas.
 *
 * Añadir un status nuevo a la API exige añadir su `ApiErrorCode` aquí: el fallback por rango
 * mantiene el sobre íntegro, pero degrada la precisión del `code`, y eso lo vigila
 * `ApiErrorEnvelopeTest`.
 */
final class ApiExceptionRenderer
{
    /**
     * Cabeceras de la excepción que SÍ viajan al cliente: son semántica HTTP de la respuesta,
     * no detalle interno.
     *
     * @var list<string>
     */
    private const FORWARDED_HEADERS = ['Retry-After', 'Allow'];

    /** Status → código público. Lo no listado cae al fallback por rango (ver docblock). */
    private const STATUS_CODES = [
        400 => ApiErrorCode::BadRequest,
        401 => ApiErrorCode::Unauthenticated,
        403 => ApiErrorCode::Unauthorized,
        404 => ApiErrorCode::NotFound,
        405 => ApiErrorCode::MethodNotAllowed,
        419 => ApiErrorCode::SessionExpired,
        422 => ApiErrorCode::ValidationFailed,
        429 => ApiErrorCode::TooManyRequests,
        503 => ApiErrorCode::Maintenance,
    ];

    /** @return JsonResponse|null `null` = «no es cosa mía», y Laravel sigue con su render normal. */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! ApiSurface::handles($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return ApiErrorResponse::make(
                ApiErrorCode::ValidationFailed,
                $e->status,
                fields: $e->errors(),
            );
        }

        // No pasa por `prepareException`: llega tal cual. Sin esto, Laravel intentaría redirigir a
        // la pantalla de login, que para un cliente JSON es una respuesta inservible.
        if ($e instanceof AuthenticationException) {
            return ApiErrorResponse::make(ApiErrorCode::Unauthenticated, 401);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $headers = $this->forwardedHeaders($e->getHeaders());

            return ApiErrorResponse::make(
                self::STATUS_CODES[$status] ?? $this->fallbackCode($status),
                $status,
                params: $this->paramsFor($status, $headers),
                headers: $headers,
            );
        }

        return ApiErrorResponse::make(
            ApiErrorCode::ServerError,
            500,
            message: config('app.debug') === true ? $e->getMessage() : null,
        );
    }

    /**
     * El sobre se mantiene aunque el status no esté mapeado: el cliente nunca recibe dos formas
     * distintas de error. Pierde precisión en el `code`, no la forma.
     */
    private function fallbackCode(int $status): ApiErrorCode
    {
        return $status >= 500 ? ApiErrorCode::ServerError : ApiErrorCode::BadRequest;
    }

    /**
     * `params` con el dato que el cliente necesita para ACTUAR. Hoy solo el 429: sin `retry_after`
     * un cliente sensato no sabe cuándo reintentar y el impaciente vuelve a golpear el limitador.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function paramsFor(int $status, array $headers): array
    {
        if ($status === 429 && isset($headers['Retry-After'])) {
            return ['retry_after' => (int) $headers['Retry-After']];
        }

        return [];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function forwardedHeaders(array $headers): array
    {
        $forwarded = [];

        foreach ($headers as $name => $value) {
            foreach (self::FORWARDED_HEADERS as $allowed) {
                if (strcasecmp($name, $allowed) === 0) {
                    $forwarded[$allowed] = (string) $value;
                }
            }
        }

        return $forwarded;
    }
}
