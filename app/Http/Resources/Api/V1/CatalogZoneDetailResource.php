<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CatalogZoneDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **La FICHA de una zona** — lo que sirve `GET /api/v1/catalog/zones` desde la T6 del menú de
 * hechos (`docs/specs/instancia-y-landing-fuera.md` §4.1; `DECISIONES #632` P1).
 *
 * **APLANA la composición del DTO**, exactamente como {@see CatalogProductDetailResource} hace con
 * el producto: `CatalogZoneDetail` *tiene* un `CatalogZone` porque en PHP eso es lo que evita
 * duplicar la definición de la identidad, pero para el cliente una zona es UN objeto. Los tres
 * campos comunes los sirve {@see CatalogZoneResource}, no una copia, así que la zona anidada en un
 * producto y ésta no pueden decir cosas distintas de la misma zona.
 *
 * ⚠️⚠️ **Lo que la instalación no rellenó NO viaja, ni como `""`** (la receta del menú de hechos).
 * Una zona sin descripción no trae la clave `description`, y por eso las dos son opcionales en el
 * contrato, declaradas en `OPTIONAL_BY_DESIGN` con su porqué. Publicar `""` obligaría a cada
 * cliente a tratar dos formas del mismo «no hay».
 *
 * @property-read CatalogZoneDetail $resource
 */
class CatalogZoneDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_filter(
            (new CatalogZoneResource($this->resource->zone))->toArray($request) + [
                'description' => $this->resource->description,
                'image_url' => $this->resource->imageUrl,
            ],
            fn (mixed $valor): bool => $valor !== null,
        );
    }
}
