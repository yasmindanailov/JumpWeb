<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1 — una línea de pedido vista por su dueño.
 *
 * **Todo campo derivado sale de un método del dominio**, ninguno se recalcula aquí:
 * `displayStatusForCustomer()`, `isCancelled()`, `chargedSubtotalCents()`, `displayTimeWindow()` y
 * `guestFormStatus()` son ya la fuente única que usan «Mis pedidos», el calendario del panel y la
 * ficha del pedido. Reimplementar cualquiera de ellos en el serializador crearía la segunda fuente
 * de verdad que toda esta fase existe para no crear.
 *
 * Los tres valores de estado que salen al contrato son **claves estables**, no etiquetas: el
 * dominio devuelve `active`/`finished`, `ok`/`pending`/`null` y el `status` crudo del pedido; la
 * traducción la hace quien pinta. Verificado leyendo los métodos, no supuesto.
 *
 * @property-read OrderItem $resource
 */
class OrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id' => $item->id,
            'product_name' => (string) ($item->ticketType?->tr('name') ?? ''),
            'date' => $item->slot?->date?->toDateString(),
            'time_window' => $item->displayTimeWindow(),
            'quantity' => (int) $item->quantity,
            'charged_subtotal_cents' => $item->chargedSubtotalCents(),
            'status' => $item->displayStatusForCustomer(),
            'cancelled' => $item->isCancelled(),
            // `null` = esta línea no pide datos por invitado (entrada, o pack sin esquema).
            // El cliente distingue así «no aplica» de «pendiente», que es lo que necesita para
            // decidir si enseña el aviso de formulario.
            'guest_form_status' => $item->guestFormStatus(),
            'needs_guest_form' => $item->needsGuestForm(),
            'addons' => OrderItemAddonResource::collection($item->children)->resolve($request),
        ];
    }
}
