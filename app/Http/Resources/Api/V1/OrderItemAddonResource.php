<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1 — un complemento contratado dentro de una línea de pedido.
 *
 * Es un `OrderItem` hijo (`parent_item_id`), y se serializa aparte y con MENOS campos a propósito:
 * un complemento no tiene franja propia ni post-form —los hereda de su línea padre— y repetirlos
 * aquí invitaría a un cliente a leerlos del sitio equivocado.
 *
 * @property-read OrderItem $resource
 */
class OrderItemAddonResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'product_name' => (string) ($this->resource->ticketType?->tr('name') ?? ''),
            'quantity' => (int) $this->resource->quantity,
            'charged_subtotal_cents' => $this->resource->chargedSubtotalCents(),
            // Añadido en Fase 4 · paso 4.0b (a la COLA: `ApiContractTest` compara `required` con las
            // propiedades EN ORDEN). Unidades que van incluidas sin cargo: sin esto, una línea de
            // «2 incluidas + 1 extra» se pinta igual que una de «3 de pago», y el importe cobrado
            // no basta para distinguirlas cuando el extra vale 0.
            'free_quantity' => (int) $this->resource->free_quantity,
            // La cantidad con su sustantivo («2 unidades»), por lo mismo que en la línea principal:
            // el complemento sufría el MISMO defecto `L2` —`· 2×` pegado al importe— y arreglar solo
            // el principal habría dejado la ambigüedad viva una fila más abajo.
            'quantity_label' => $this->resource->displayQuantityLabel(),
            // El aviso de este complemento en la reserva, ya escrito con su cantidad (`#775`, contrato 1.34.0): «Tenéis 2
            // pares de calcetines comprados; os los damos en la puerta.». `null` si la instalación no lo escribió: Mi
            // cuenta lo nombra entonces con su cantidad (`product_name` · `quantity_label`), sin prometer nada.
            'note' => $this->resource->ticketType?->reservationNote((int) $this->resource->quantity),
        ];
    }
}
