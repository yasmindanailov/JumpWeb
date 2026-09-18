<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\LoginResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\ApiTokenIssuer;
use App\Domain\Identity\Services\PasswordLogin;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;

/**
 * F4 del programa — emitir y rotar tokens Bearer (`docs/specs/token-bearer.md` §4.3, `DECISIONES #630`).
 *
 * La puerta de quien NO es un navegador de primera parte (la app nativa de F6). El cajón no la usa:
 * vive en el mismo dominio que la API y sigue con cookie de sesión + CSRF ({@see AuthSessionController}).
 *
 * Como su hermano, **no comprueba credenciales ni cuenta intentos**: eso es
 * `Identity\Services\PasswordLogin::verify()`, que comparte con el login los DOS limitadores de
 * `SEC-06` y sus claves. Ni decide con qué nace un token ni lo retira: eso es `ApiTokenIssuer` y
 * `User`. Aquí solo se valida la forma, se llama y se responde.
 *
 * ⚠️ **No usa `requireSession`**, al revés que `auth/login`: esta puerta existe justo para quien no
 * tiene sesión ni puede tenerla.
 */
class AuthTokenController extends Controller
{
    public function issue(Request $request, PasswordLogin $passwordLogin, ApiTokenIssuer $issuer): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'min:1', 'max:60'],
        ]);

        $result = $passwordLogin->verify($data['email'], $data['password'], (string) $request->ip());

        if ($result->failed()) {
            return $this->denial($result);
        }

        /** @var User $user */
        $user = $result->user;

        return $this->issued($request, $user, $issuer->issue($user, $data['device_name'], (string) $request->ip()));
    }

    public function rotate(Request $request, ApiTokenIssuer $issuer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $issuer->rotate($user, (string) $request->ip());

        // Con cookie de sesión no hay ningún token que rotar: no es un fallo de permisos, es una
        // petición que no tiene sentido, igual que abrir sesión sin sesión en `auth/login`.
        if ($token === null) {
            return ApiErrorResponse::make(ApiErrorCode::BadRequest, 400);
        }

        return $this->issued($request, $user, $token);
    }

    /**
     * El valor en claro viaja UNA vez, aquí; el servidor solo guarda su hash. El `no-store` lo pone
     * el middleware de la ruta: esta respuesta es pública para `NoStoreWhenAuthenticated` (nadie
     * está autenticado todavía) y aun así transporta una credencial.
     */
    private function issued(Request $request, User $user, NewAccessToken $token): JsonResponse
    {
        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => (new UserResource($user))->resolve($request),
        ], 201);
    }

    /** El mismo veredicto → respuesta que `auth/login`: 429 con `retry_after`, o 401 genérico (`SEC-06`). */
    private function denial(LoginResult $result): JsonResponse
    {
        if ($result->wasRateLimited()) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        return ApiErrorResponse::make(ApiErrorCode::InvalidCredentials, 401);
    }
}
