<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Http\Api\AdmissionCodeMap;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 4 · paso 4.0b — «¿puede este titular reservar AHORA?», sin gastar nada.
 *
 * **`$wrap = null`**: lo devuelve el propio controlador, así que va en la raíz (§4.3 del spec de la
 * API; el envoltorio `data` es solo de las listas, que pasan por `ApiCollection`).
 *
 * **Qué NO lleva, y es la mitad del diseño**: ni cuántos pedidos pendientes tiene el titular, ni
 * cuáles, ni cuándo se levanta el límite de frecuencia. Un endpoint de elegibilidad es, por
 * naturaleza, un oráculo sobre el estado de una cuenta; se acota devolviendo lo justo para pintar
 * el aviso. `max_pending_orders` sí viaja porque el aviso lo dice («máximo N reservas sin pagar») y
 * porque ya se publica en el `error.params.max` del 409 de `POST /orders`: esconderlo aquí no
 * escondería nada.
 *
 * @mixin AdmissionDecision
 */
class ReservationEligibilityResource extends JsonResource
{
    /** @var AdmissionDecision */
    public $resource;

    public static $wrap = null;

    /**
     * `reason` es el **código público** (`AdmissionCodeMap`), no la constante del dominio: son
     * distintas a propósito —`too_many_pending` vs `too_many_pending_orders`— y el cliente ramifica
     * sobre el código, igual que hace con el sobre de error.
     *
     * Los tres campos van SIEMPRE, aunque valgan `null`: el contrato exige `required` completo, y un
     * campo que aparece y desaparece obliga a cada cliente a distinguir «no está» de «es nulo».
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reason = $this->resource->reason;

        return [
            'allowed' => $this->resource->allowed,
            'reason' => $reason === null ? null : AdmissionCodeMap::codeFor($reason)->value,
            'max_pending_orders' => $reason === AdmissionDecision::TOO_MANY_PENDING
                ? (int) ($this->resource->context['max'] ?? 0)
                : null,
        ];
    }
}
