<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CatalogAddon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1b — un complemento tal y como se OFRECE dentro de un producto.
 *
 * Serializa `Booking\Contracts\CatalogAddon`. Los campos describen el ofrecimiento (incluido,
 * obligatorio, por invitado, grupo excluyente, dependencia), no una selección: el catálogo no sabe
 * qué ha elegido nadie. `selected_by_default` es la única pista de estado, y viene del dominio
 * (`AddonResolver::defaultSelection()`) para que ningún cliente adivine cuál es el miembro
 * preseleccionado de un grupo.
 *
 * `price_cents` es el precio unitario configurado. Lo que se cobra por una línea depende de las
 * unidades incluidas y de la cantidad, y lo calcula el servidor al presupuestar (paso 4).
 *
 * @property-read CatalogAddon $resource
 */
class CatalogAddonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'features' => $this->resource->features,
            'gifts' => $this->resource->gifts,
            'price_cents' => $this->resource->priceCents,
            'included' => $this->resource->included,
            'included_quantity' => $this->resource->includedQuantity,
            'mandatory' => $this->resource->mandatory,
            'per_guest' => $this->resource->perGuest,
            'allow_extra' => $this->resource->allowExtra,
            'max_quantity' => $this->resource->maxQuantity,
            'choice_group' => $this->resource->choiceGroup,
            'requires_addon_id' => $this->resource->requiresAddonId,
            'selected_by_default' => $this->resource->selectedByDefault,
        ];
    }
}
