<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\PostFormAddonView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Traducción PURA del DTO del dominio al contrato (`PostFormAddon` de `openapi/v1.yaml`).
 *
 * Si aquí se decidiera algo, sería la segunda fuente de verdad que el contrato existe para no crear:
 * el precio que manda, el estado por fila y el motivo del cierre los decide `PostFormAddons`.
 *
 * @property-read PostFormAddonView $resource
 */
class PostFormAddonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            'product_id' => $view->productId,
            'product_name' => $view->productName,
            'unit_price_cents' => $view->unitPriceCents,
            'note' => $view->note,
            'features' => $view->features,
            'quantity' => $view->quantity,
            'max_quantity' => $view->maxQuantity,
            'charged_cents' => $view->chargedCents,
            'closed' => $view->closed,
            'closed_reason' => $view->closedReason,
            'closes_at' => $view->closesAt,
        ];
    }
}
