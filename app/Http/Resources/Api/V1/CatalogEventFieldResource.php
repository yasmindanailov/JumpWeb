<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CatalogEventField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1b — un campo del formulario de evento de un pack (data-driven, #86).
 *
 * Serializa `Booking\Contracts\CatalogEventField`. Solo llegan aquí los campos de la etapa
 * `booking` —los que se piden al reservar—, así que el DTO no lleva `stage` y este recurso tampoco:
 * los de la etapa `postform` son de otra superficie y los servirá su propio endpoint (paso 5).
 *
 * @property-read CatalogEventField $resource
 */
class CatalogEventFieldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'type' => $this->resource->type,
            'required' => $this->resource->required,
            // El tramo de edades del pack, solo en `celebrant_age` (`#588`); `null` en el resto.
            'min' => $this->resource->min,
            'max' => $this->resource->max,
        ];
    }
}
