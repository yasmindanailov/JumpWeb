<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CatalogProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1b — un producto del catálogo, en su forma de LISTA.
 *
 * Serializa el DTO `Booking\Contracts\CatalogProduct` sin recalcular nada. Dos cosas que el nombre
 * de los campos deja explícitas, porque un nombre que miente ya costó una corrección en el paso 1a:
 *
 *  - **`from_price_cents` es un «precio desde»**, el mínimo configurado, no lo que se va a cobrar.
 *    El importe real lo decide la tarifa del día y lo confirma `POST orders/quote` (paso 4).
 *  - **`deposit_label` viene formateada** («30,00 €», «30 %») porque componerla es regla de dominio
 *    —importe fijo o porcentaje del total—, no formato de presentación.
 *
 * El detalle del producto ({@see CatalogProductDetailResource}) reutiliza estos mismos campos y les
 * añade los suyos, así que la ficha de lista y la de detalle nunca dicen cosas distintas del mismo
 * producto.
 *
 * @property-read CatalogProduct $resource
 */
class CatalogProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'name' => $this->resource->name,
            'badge' => $this->resource->badge,
            'features' => $this->resource->features,
            'from_price_cents' => $this->resource->fromPriceCents,
            'price_varies' => $this->resource->priceVaries,
            'deposit_label' => $this->resource->depositLabel,
            'period_label' => $this->resource->periodLabel,
            'featured' => $this->resource->featured,
            // ⚠️ Campo NUEVO (`#259`), y es evolutivo: añadir una clave no rompe a ningún cliente.
            // Sin él, el cajón deducía el dibujo de `is_pack` y el catálogo entero se repartía en
            // DOS iconos — el patrón que `#140` ya había retirado de la cesta y del resumen.
            'icon' => $this->resource->icon,
            'zone' => $this->resource->zone === null
                ? null
                : (new CatalogZoneResource($this->resource->zone))->toArray($request),
        ];
    }
}
