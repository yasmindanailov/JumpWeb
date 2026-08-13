<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CatalogZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1b — una zona operativa del catálogo.
 *
 * Serializa el DTO `Booking\Contracts\CatalogZone` tal cual. Se usa en dos sitios —la lista de
 * `GET catalog/zones` y la zona anidada en cada producto— y es el mismo objeto en los dos, para que
 * un cliente no tenga que aprender dos formas de «zona».
 *
 * @property-read CatalogZone $resource
 */
class CatalogZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
        ];
    }
}
