<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\OfferedTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 4b — una hora de entrada reservable, con su cupo.
 *
 * ⚠️ **`available` y `max_quantity` no son el mismo número**, y el contrato lo dice porque
 * confundirlos vende de más: en una entrada coinciden, pero en un pack `available` son las plazas
 * de invitados que le quedan a la franja y `max_quantity` es cuántos admite ESA fiesta, topado por
 * el máximo del propio pack. `available` es para contar; `max_quantity`, para acotar el selector.
 *
 * @property-read OfferedTime $resource
 */
class OfferedTimeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'time' => $this->resource->time,
            'available' => $this->resource->available,
            'max_quantity' => $this->resource->maxQuantity,
            'sellable' => $this->resource->sellable,
        ];
    }
}
