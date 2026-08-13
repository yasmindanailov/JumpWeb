<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\OfferedDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 4b — un día reservable de un producto.
 *
 * Lleva el precio de ese día porque un calendario de compra sin precios obliga a entrar día a día
 * para comparar, y porque calcularlo en el cliente significaría reimplementar la resolución de
 * tarifas. `rate_key` es la clave de configuración de la tarifa (`normal`, `special`…), no una
 * etiqueta traducible: sirve para marcar los días especiales sin deducirlos del precio.
 *
 * @property-read OfferedDate $resource
 */
class OfferedDateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date,
            'price_cents' => $this->resource->priceCents,
            'rate_key' => $this->resource->rateKey,
        ];
    }
}
