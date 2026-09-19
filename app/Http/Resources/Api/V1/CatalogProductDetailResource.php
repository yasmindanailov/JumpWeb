<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CatalogProductDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1b — la ficha completa de un producto del catálogo.
 *
 * **APLANA la composición del DTO**: `CatalogProductDetail` *tiene* un `CatalogProduct` porque en
 * PHP eso es lo que evita duplicar la definición de los campos comunes, pero para el cliente el
 * producto es UN objeto. Anidar un `product` dentro del producto sería filtrar al contrato público
 * una decisión de modelado interna, y obligaría a leer el nombre en dos rutas distintas según se
 * venga de la lista o del detalle.
 *
 * Los campos comunes los sirve {@see CatalogProductResource}, no una copia: por construcción, la
 * ficha de lista y la de detalle no pueden divergir.
 *
 * @property-read CatalogProductDetail $resource
 */
class CatalogProductDetailResource extends JsonResource
{
    /**
     * **`$wrap = null`** por el contrato de §4.3: «recurso en la raíz; listas bajo `data` + `meta`».
     * Sin esto Laravel envolvería la ficha en un `data` que el documento no declara. Los recursos
     * hermanos no lo necesitan porque se sirven anidados, no como respuesta; si alguno pasara a
     * responder solo, el test de contrato lo cazaría en la primera ejecución.
     *
     * @var string|null
     */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return (new CatalogProductResource($this->resource->product))->toArray($request) + [
            'min_quantity' => $this->resource->minQuantity,
            'max_quantity' => $this->resource->maxQuantity,
            'event_fields' => array_map(
                fn ($field): array => (new CatalogEventFieldResource($field))->toArray($request),
                $this->resource->eventFields,
            ),
            'addons' => array_map(
                fn ($addon): array => (new CatalogAddonResource($addon))->toArray($request),
                $this->resource->addons,
            ),
            // `specs/waiver-por-reserva.md` §12.2 — qué hace este producto con el justificante de un
            // menor invitado. La pantalla decide con esto si pinta una CASILLA (`optional`), una NOTA
            // (`required`) o nada (`none`).
            'guardian_authorization' => $this->resource->guardianAuthorization,
        ] + (
            // Qué es este producto (`#632` P1). Como la foto en la lista: si la instalación no lo
            // escribió, la clave NO viaja — ni como `""`.
            $this->resource->description === null ? [] : ['description' => $this->resource->description]
        );
    }
}
