<?php

namespace App\Http\Api\Concerns;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;

/**
 * **El veredicto de una reconfirmación de contraseña, traducido a HTTP** — una sola vez.
 *
 * Lo comparten los endpoints de `/api/v1` que exigen reconfirmar la contraseña actual: `PUT
 * me/password`, `POST me/sessions/revoke-others` (tanda 2 · paso 6) y `DELETE me` (paso 8). Nace al
 * aparecer la **segunda** copia, que es la regla que esta tanda ha aplicado cinco veces ya —el
 * rótulo de día, la política de contraseñas, el campo de contraseña, `form-outcome.js` y la lista de
 * idiomas—: se extrae antes de la segunda copia, no después de la cuarta (`DECISIONES #120(r)`).
 *
 * ⚠️ **Lo que de verdad protege que esté en un solo sitio** son las dos decisiones de abajo. Una
 * copia que devolviera 401 en vez de 422, o que se olvidara del `Retry-After`, no rompería ningún
 * test del OTRO endpoint — y por eso divergiría sin que nadie lo viera.
 */
trait TranslatesCredentialVerdicts
{
    /**
     * ⚠️ **La contraseña equivocada es un 422 POR CAMPO, no un 401**, y la diferencia con el login no
     * es cosmética: aquí el cliente **sí está autenticado**, así que un 401 le diría «tu sesión no
     * vale» cuando lo que pasa es que se ha equivocado escribiendo. El 422 por campo es además lo
     * que la interfaz necesita para pintarlo bajo su input — mismo criterio que el registro cuando el
     * correo ya existe.
     *
     * ⚠️ **El 429 lleva `Retry-After`**: sin él, el cliente no sabe cuándo volver y lo único que
     * puede hacer es reintentar en bucle, que es exactamente lo que el limitador quiere evitar.
     */
    private function credentialDenial(CredentialChangeResult $result): JsonResponse
    {
        if ($result->wasRateLimited()) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        return ApiErrorResponse::make(
            ApiErrorCode::ValidationFailed,
            422,
            fields: ['current_password' => [__('account.account.wrong_password')]],
        );
    }
}
