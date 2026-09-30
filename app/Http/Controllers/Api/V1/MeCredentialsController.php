<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\CredentialChangeResult;
use App\Domain\Identity\Contracts\Reconfirmation;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountCredentials;
use App\Domain\Identity\Services\PasswordPolicy;
use App\Domain\Identity\Services\SocialIdentities;
use App\Http\Api\ApiCollection;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\Concerns\TranslatesCredentialVerdicts;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserIdentityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
    use TranslatesCredentialVerdicts;

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
     * `GET /me/identities` — las cuentas de un proveedor externo vinculadas a ésta.
     *
     * ⚠️ **No publica el `sub`.** Lo que la pantalla necesita saber es *con qué cuenta se entra y
     * desde cuándo*; el identificador del proveedor es un dato del titular que sí viaja en el export
     * del art. 20, que es un acto explícito suyo. Misma doctrina que la IP en los consentimientos.
     */
    public function identities(Request $request, SocialIdentities $identities): ApiCollection
    {
        /** @var User $user */
        $user = $request->user();

        return new ApiCollection($identities->forUser($user), UserIdentityResource::class);
    }

    /**
     * `DELETE /me/identities/{provider}` — quitar el vínculo.
     *
     * ⚠️⚠️ **Es el contrapeso del aviso de vinculación** (`specs/auth-con-google.md` §5.2): el vínculo
     * se crea solo y se avisa por correo, y ese aviso solo sirve si quien lo recibe puede deshacerlo.
     *
     * ⚠️ **Exige la contraseña** (`[DECIDIDO owner]`) y comparte el limitador con el cambio de
     * contraseña. Quien entró con Google y no tiene ninguna la crea con «he olvidado mi contraseña»:
     * es lo que impide que alguien se cierre a sí mismo la única puerta que tenía.
     */
    public function unlinkIdentity(Request $request, string $provider, SocialIdentities $identities): JsonResponse
    {
        // La contraseña o un código `confirm` (A2a, `#855`): quien entró con Google y no tiene contraseña ya no se queda
        // sin puerta al desvincular —entra con un código al correo—.
        $data = $request->validate($this->reconfirmationRules());

        /** @var User $user */
        $user = $request->user();

        return $this->respond($identities->unlink($user, $provider, Reconfirmation::from($data), (string) $request->ip()));
    }

    /**
     * `POST /me/sessions/revoke-others`.
     *
     * Cierra las demás sesiones y revoca los demás tokens; **la credencial en curso sobrevive**, para
     * que el titular no se autoexpulse al defenderse (`RGPD-06`).
     */
    public function revokeOtherSessions(Request $request, AccountCredentials $credentials): JsonResponse
    {
        // La contraseña o un código `confirm` (A2a, `#855`).
        $data = $request->validate($this->reconfirmationRules());

        /** @var User $user */
        $user = $request->user();

        return $this->respond($credentials->revokeOtherSessions(
            $user, Reconfirmation::from($data), (string) $request->ip(),
        ));
    }

    /**
     * `POST /me/confirm-code` — el código para CONFIRMAR una acción sensible (A2a de `specs/acceso-con-codigo.md` §4.9,
     * `#855`), al correo de la cuenta y tras la respuesta. `action` dice cuál, y el correo lo cuenta: solo se pide con la
     * sesión abierta, así que quien lo recibe sin haberlo pedido sabe que alguien la tiene.
     *
     * `202` sin cuerpo; `429` con `retry_after` (uno por minuto y cinco por hora).
     */
    public function requestConfirmationCode(Request $request, AccountCredentials $credentials): Response|JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(AccountCredentials::CONFIRM_ACTIONS)],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $credentials->requestConfirmationCode($user, $data['action'], (string) $request->ip());

        if ($result->reason === ResendResult::THROTTLED) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        return response()->noContent(202);
    }

    /**
     * El veredicto, traducido a HTTP.
     *
     * La denegación vive en {@see TranslatesCredentialVerdicts} desde el paso 8, cuando `DELETE me`
     * la necesitó igual: sus dos decisiones —422 por campo y no 401, y `Retry-After` en el 429— son
     * las que una copia habría dejado divergir sin ponerse roja.
     */
    private function respond(CredentialChangeResult $result): JsonResponse
    {
        if ($result->succeeded) {
            return response()->json(status: 204);
        }

        return $this->credentialDenial($result);
    }
}
