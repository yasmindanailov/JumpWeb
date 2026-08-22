<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\PasswordResetResult;
use App\Domain\Identity\Services\PasswordPolicy;
use App\Domain\Identity\Services\PasswordRecovery;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

/**
 * Fase 3 · paso 3c — recuperación de contraseña por API (`docs/specs/api-v1.md` §4.4).
 *
 * Las reglas están en `Identity\Services\PasswordRecovery`, que consume también el modal de la web:
 * el limitador propio por IP, la rotación del `remember_token`, la invalidación de TODAS las
 * credenciales del titular (paso 3a) y la no-enumeración.
 *
 * **Aquí la anti-enumeración es estricta**, al contrario que en el alta. No es incoherencia: en el
 * registro la clienta decidió avisar de que un correo ya existe porque hay alguien intentando
 * comprar y esconderlo cuesta la venta; en la recuperación no hay nada que ganar diciéndolo y sí un
 * oráculo que regalar. Por eso pedir el enlace responde **202 siempre** y un token inválido y un
 * correo inexistente devuelven el MISMO 422.
 */
class PasswordRecoveryController extends Controller
{
    /**
     * Pide el enlace. **202 exista o no la cuenta**: el cliente no puede distinguirlo y por tanto
     * no puede enumerar. Es «aceptado, ya veremos», que es literalmente lo que ocurre.
     */
    public function sendLink(Request $request, PasswordRecovery $recovery): Response|JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $result = $recovery->requestLink($data['email'], (string) $request->ip());

        if ($result->outcome === PasswordResetResult::RATE_LIMITED) {
            return $this->throttled($result);
        }

        return response()->noContent(202);
    }

    /**
     * Fija la nueva contraseña con el token del correo. Al conseguirlo, todas las credenciales
     * anteriores del titular quedan invalidadas —incluidas las de la app—: es el caso de uso de
     * quien ha perdido el control de su cuenta.
     */
    public function reset(Request $request, PasswordRecovery $recovery): Response|JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => [...PasswordPolicy::rules(), 'confirmed'],
        ]);

        $result = $recovery->reset($data['token'], $data['email'], $data['password'], (string) $request->ip());

        if ($result->succeeded()) {
            return response()->noContent();
        }

        if ($result->outcome === PasswordResetResult::RATE_LIMITED) {
            return $this->throttled($result);
        }

        // Token inválido, caducado o correo inexistente: el MISMO mensaje para los tres.
        return ApiErrorResponse::make(
            ApiErrorCode::ValidationFailed,
            422,
            fields: ['email' => [__($result->outcome === PasswordResetResult::BROKER_THROTTLED
                ? Password::RESET_THROTTLED
                : Password::INVALID_TOKEN)]],
        );
    }

    private function throttled(PasswordResetResult $result): JsonResponse
    {
        return ApiErrorResponse::make(
            ApiErrorCode::TooManyRequests,
            429,
            params: ['retry_after' => $result->retryAfter],
            headers: ['Retry-After' => (string) $result->retryAfter],
        );
    }
}
