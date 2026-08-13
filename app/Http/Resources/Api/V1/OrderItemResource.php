<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\ReservationFinancials;
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
    /**
     * El pedido al que pertenece la línea.
     *
     * ⚠️ **Se INYECTA desde `OrderResource`, no se navega.** `$item->order` sería una consulta por
     * línea —y en `me/orders` son hasta 50 pedidos paginados—, así que el pedido baja desde arriba,
     * donde ya está cargado. Mismo patrón que `OrderPaymentResource::withPaymentTicket()`.
     */
    private ?Order $order = null;

    public function within(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        // Sin el pedido no se puede componer el desglose de la reserva, y devolver ceros en
        // silencio sería peor que fallar: el cliente pintaría «0 € pendientes en puerta» sobre una
        // reserva que sí debe dinero. Como el único llamante es `OrderResource`, esto no puede
        // ocurrir sin que alguien haya añadido un segundo llamante y se haya saltado `within()`.
        $order = $this->order;
        assert($order !== null, OrderItemResource::class.' necesita su Order: usa ->within($order)');

        $financials = ReservationFinancials::make($order, $item);

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
            // ── Añadidos en Fase 4 · paso 4.0b, para que el resumen de la reserva confirmada no
            //    tenga que adivinarse. Van a la COLA de la lista a propósito: `ApiContractTest`
            //    compara `required` con las propiedades **en el mismo orden**.
            //
            // Un pack se pinta distinto que una entrada («N invitados · Nombre» vs «N× Nombre»),
            // y el cliente no puede deducirlo de ningún otro campo.
            'is_pack' => $item->ticketType?->isPack() ?? false,
            // La hora de INICIO en crudo. `time_window` es un texto ya compuesto para mostrar
            // («10:00–11:00»); un cliente que necesite la hora sola tendría que partirlo, que es
            // exactamente la clase de parseo frágil que un contrato existe para evitar.
            'start_time' => $item->slot?->start_time,
            // Desglose de la RESERVA (principal + sus complementos), no del pedido: en una cesta
            // mixta entrada+pack, etiquetar el agregado como «señal pagada» engaña (#225 F3).
            'paid_online_cents' => $financials->pagadoOnline,
            'gate_remainder_cents' => $financials->aCobrarPuerta,
            // Y si procede enseñar «señal pagada · resto en el parque». Son TRES condiciones
            // compuestas en el dominio (`ReservationFinancials::showsDepositNote`): publicar solo
            // los números obligaría al cliente a recomponerlas, y es como divergen.
            'shows_deposit_note' => $financials->showsDepositNote($order, $item),
        ];
    }
}
