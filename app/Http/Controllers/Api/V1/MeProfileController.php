<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Contracts\ProfileUpdateResult;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountProfile;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * **El perfil del titular y el ciclo del cambio de correo** (`specs/area-cliente.md` §9.3, paso 7).
 *
 * Todo lo que decide vive en `Identity\Services\AccountProfile`; aquí se valida el cuerpo con las
 * MISMAS reglas que la web —las publica el servicio— y se traduce el veredicto a HTTP.
 */
class MeProfileController extends Controller
{
    /**
     * `PATCH /me`.
     *
     * ⚠️ **Devuelve el perfil actualizado y no un 204**, a propósito: si se ha pedido un cambio de
     * correo, la respuesta ya trae `pending_email` y su caducidad, así que la pantalla se entera sin
     * una segunda petición. Y el `email` que devuelve sigue siendo **el viejo**, que es justo lo que
     * el cliente necesita ver para no creer que el cambio ya está hecho.
     */
    public function update(Request $request, AccountProfile $profile): UserResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rules = AccountProfile::rules($user);

        // ⚠️ Solo se exige la PRESENCIA de la contraseña, y solo si el correo cambia: comprobarla es
        // del servicio, que además cuenta el intento para el limitador (§9.4).
        if ($request->string('email')->lower()->trim()->value() !== $user->email) {
            $rules['current_password'] = ['required', 'string'];
        }

        $data = $request->validate($rules);

        $result = $profile->apply($user, [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'locale' => $data['locale'],
            'email' => $data['email'],
        ], $data['current_password'] ?? null, (string) $request->ip());

        if ($result->failed()) {
            return $this->denial($result);
        }

        return new UserResource($user->fresh());
    }

    /**
     * `DELETE /me/pending-email`.
     *
     * ⚠️ **Idempotente a propósito**: cancelar algo que ya no está pendiente responde igual que
     * cancelarlo. Quien pulsa dos veces, o vuelve atrás y reintenta, no ha hecho nada malo — y un 404
     * ahí obligaría a cada cliente a distinguir dos situaciones que para él son la misma.
     */
    public function cancelEmailChange(Request $request, AccountProfile $profile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $profile->cancelEmailChange($user);

        return response()->json(status: 204);
    }

    /**
     * `POST /me/pending-email/resend`.
     *
     * ⚠️ Y aquí también «no había nada pendiente» **es un 204**, por lo mismo. Lo único que se
     * distingue es el cooldown, que sí le dice al cliente cuánto esperar.
     */
    public function resendPendingEmail(Request $request, AccountProfile $profile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $profile->resendPendingEmail($user);

        if (! $result->sent && $result->reason === ResendResult::THROTTLED) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        return response()->json(status: 204);
    }

    /** El veredicto de una actualización, traducido a HTTP. */
    private function denial(ProfileUpdateResult $result): JsonResponse
    {
        if ($result->wasRateLimited()) {
            return ApiErrorResponse::make(
                ApiErrorCode::TooManyRequests,
                429,
                params: ['retry_after' => $result->retryAfter],
                headers: ['Retry-After' => (string) $result->retryAfter],
            );
        }

        // ⚠️ La carrera de UNIQUE se enseña **en el campo del correo**, no como error de servidor:
        // para el cliente es exactamente lo mismo que si la validación lo hubiera rechazado, y
        // distinguirlo solo le daría un caso más que aprender para hacer lo mismo.
        $field = $result->reason === ProfileUpdateResult::EMAIL_TAKEN
            ? ['email' => [__('validation.unique', ['attribute' => __('account.account.profile.email')])]]
            : ['current_password' => [__('account.account.wrong_password')]];

        return ApiErrorResponse::make(ApiErrorCode::ValidationFailed, 422, fields: $field);
    }
}
