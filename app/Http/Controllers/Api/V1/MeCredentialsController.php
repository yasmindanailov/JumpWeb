<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use App\Domain\Identity\Services\PasswordPolicy;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * **Las gestiones de credenciales del titular** (`specs/area-cliente.md` §9.3, tanda 2 · paso 6).
 *
 * Cambiar la contraseña y cerrar sesión en los demás dispositivos. Las dos exigen **reconfirmar la
 * contraseña actual** y las dos delegan en `Identity\Services\AccountCredentials`, que es donde vive
 * el limitador y el cierre por sus dos vías. Aquí solo se traduce el veredicto a HTTP.
 *
 * ⚠️ **NO exige sesión de cookie**, al revés que el login. Un cliente por token también puede
 * cambiar su contraseña, y ahí el cierre de las demás credenciales lo hace `revokeOtherAccess()` —
 * que es justamente la vía que más importa con un Bearer vivo (`RGPD-06`).
 */
class MeCredentialsController extends Controller
{
    /**
     * `PUT /me/password`.
     *
     * ⚠️ **El formato se valida ANTES de llamar al servicio**, y ese orden es el mismo que aplica la
     * web: una contraseña nueva que no cumple la política no es un intento de adivinar la actual, así
     * que no debe gastar uno de los cinco del limitador.
     *
     * ⚠️ Y **`confirmed` no se pide aquí**: repetir la contraseña es cosa de un formulario, no del
     * contrato. La misma decisión que en el registro (`PasswordPolicy` no lo incluye).
     */
    public function updatePassword(Request $request, AccountCredentials $credentials): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => PasswordPolicy::rules(),
        ]);

        /** @var User $user */
        $user = $request->user();

        return $this->respond($credentials->changePassword(
            $user, $data['current_password'], $data['password'], (string) $request->ip(),
        ));
    }

    /**
     * `POST /me/sessions/revoke-others`.
     *
     * Cierra las demás sesiones y revoca los demás tokens; **la credencial en curso sobrevive**, para
     * que el titular no se autoexpulse al defenderse (`RGPD-06`).
     */
    public function revokeOtherSessions(Request $request, AccountCredentials $credentials): JsonResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        return $this->respond($credentials->revokeOtherSessions(
            $user, $data['current_password'], (string) $request->ip(),
        ));
    }

    /**
     * El veredicto, traducido a HTTP.
     *
     * ⚠️ **La contraseña equivocada es un 422 POR CAMPO, no un 401**, y la diferencia con el login no
     * es cosmética: aquí el cliente **sí está autenticado**, así que un 401 le diría «tu sesión no
     * vale» cuando lo que pasa es que se ha equivocado escribiendo. El 422 por campo es además lo que
     * la interfaz necesita para pintarlo bajo su input — mismo criterio que el registro cuando el
     * correo ya existe.
     */
    private function respond(CredentialChangeResult $result): JsonResponse
    {
        if ($result->succeeded) {
            return response()->json(status: 204);
        }

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
