<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\WaiverSettings;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Concerns\AuthorizesGuestForm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GuestFormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * **EL DESCARGO DE QUIEN CUMPLE, por la API** (F7c de `specs/fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]` `#752`):
 * el titular dice cuál de sus menores a cargo es quien cumple y queda atado a él (`DependentAssigner::assignHonoree()`).
 * La vía de la cuenta de la web (`Http\Controllers\HonoreeWaiverController`), para la app.
 *
 * ⚠️ **Solo ata**: dar de alta al hijo o firmar su descargo ya tienen su endpoint (`POST /me/dependents` y
 * `POST /me/dependents/{id}/waiver`), y la app los compone. Un endpoint que además creara o firmara sería una segunda
 * copia de esas reglas —la edad, el tope, el correo verificado, el texto vigente— y acabaría discrepando.
 *
 * ⚠️ **Con la identidad del TITULAR y nunca con la firma del enlace** (`RGPD-03`): va dentro de `auth:sanctum`, y la
 * reserva tiene que ser suya. El justificante de su padre o madre —la otra vía— lo firma quien sea en la página web
 * (`honoree_waiver.authorization_url`), no aquí.
 */
class HonoreeWaiverController extends Controller
{
    use AuthorizesGuestForm;

    public function update(Request $request, int $reservation, DependentAssigner $assigner): JsonResponse
    {
        // A mano, sin binding implícito: la escalada 403 → 410 → 404 del formulario (una reserva ajena y una que no existe
        // responden igual).
        $item = $this->resolveGuestFormReservation($reservation);
        abort_unless($this->ownsGuestForm($request, $item), 403);
        $this->authorizeGuestFormAccess($request, $item);
        /** @var OrderItem $item Garantizado por la autorización. */
        if ($item->isFinishedInPractice()) {
            return ApiErrorResponse::make(ApiErrorCode::GuestFormClosed, 409);
        }
        if (! WaiverSettings::isInternal()) {
            return ApiErrorResponse::make(ApiErrorCode::WaiverNotInternal, 409);
        }

        $validated = $request->validate(['dependent_id' => ['required', 'integer', 'min:1']]);

        /** @var User $holder */
        $holder = $request->user();
        $out = $assigner->assignHonoree($holder, (int) $item->order_id, (int) $item->getKey(), (int) $validated['dependent_id']);

        if (! $out->ok()) {
            // Los motivos del dominio, por campo, como la asignación al comprar (`OrdersController::store`).
            $reason = array_values($out->rejections)[0] ?? null;

            throw ValidationException::withMessages([
                'dependent_id' => $reason !== null ? __('api.dependents.'.$reason) : __('fiesta.lista.cumple_firma.err_otro'),
            ]);
        }

        return response()->json(['honoree_waiver' => GuestFormResource::honoreeWaiverOf($item)]);
    }
}
