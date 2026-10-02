<?php

namespace App\Http\Api\Concerns;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;

/**
 * **El veredicto de una reconfirmación, traducido a HTTP** — una sola vez.
 *
 * Lo comparten los endpoints de `/api/v1` que exigen reconfirmar con un código `confirm` (desde la A5 de
 * `specs/acceso-con-codigo.md`, `#869`, el único: la contraseña del cliente se retiró): `POST me/sessions/revoke-others`,
 * `DELETE me/identities/{provider}` y `DELETE me`. Nace al aparecer la **segunda** copia (`DECISIONES #120(r)`): se
 * extrae antes de la segunda copia, no después de la cuarta.
 *
 * ⚠️ **Lo que de verdad protege que esté en un solo sitio** son las dos decisiones de abajo. Una copia que devolviera 401
 * en vez de 422, o que se olvidara del `Retry-After`, no rompería ningún test del OTRO endpoint — y por eso divergiría sin
 * que nadie lo viera.
 */
trait TranslatesCredentialVerdicts
{
    /**
     * ⚠️ **El código equivocado es un 422 POR CAMPO, no un 401**, y la diferencia con la puerta no es cosmética: aquí el
     * cliente **sí está autenticado**, así que un 401 le diría «tu sesión no vale» cuando lo que pasa es que se ha
     * equivocado escribiendo. El 422 sobre `code` es además lo que la interfaz necesita para pintarlo bajo su campo.
     *
     * ⚠️ **El 429 lleva `Retry-After`**: sin él, el cliente no sabe cuándo volver y lo único que puede hacer es reintentar
     * en bucle, que es exactamente lo que el limitador quiere evitar.
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

        return ApiErrorResponse::make(ApiErrorCode::ValidationFailed, 422, fields: ['code' => [__('api.confirm.wrong_code')]]);
    }

    /**
     * Las reglas de la reconfirmación: un código `confirm` (A2a, `#855`), el que pide `POST /me/confirm-code`.
     *
     * @return array<string, array<int, string>>
     */
    private function reconfirmationRules(): array
    {
        return ['code' => ['required', 'string', 'max:16']];
    }
}
