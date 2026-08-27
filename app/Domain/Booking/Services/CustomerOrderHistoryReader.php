<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CustomerOrderHistory;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-model de BOOKING: los pedidos de un cliente en forma portable (RGPD art. 20).
 *
 * Implementa {@see CustomerOrderHistory}. El código es el que vivía dentro de
 * `Http\Controllers\Account\AccountController::export`, movido **tal cual** al módulo dueño de los
 * datos — misma composición, mismos nombres de campo, mismos tipos—: la tanda 2 abre superficie de
 * API sobre dominio que ya existe, y reescribir la composición al moverla habría cambiado un
 * documento que los clientes ya se descargan (`DECISIONES #120(a)`).
 *
 * ⚠️ **Con UNA excepción, decidida por el owner al medirla** (`DECISIONES #120(s)`): el bloque
 * `tickets` publicaba `code`, y **`tickets` no tiene esa columna** —ni el modelo un accesor—, así
 * que el campo salía `null` desde el commit fundacional y lo único que comunicaba era cuántas
 * entradas hay. Pasa a publicar lo que la entrada SÍ es: su `status` y cuándo se emitió. Un campo
 * que nunca lleva valor es la familia de `#115` —algo que se lee como un dato y no lo es—, y
 * congelarlo en el contrato de la API lo habría hecho permanente.
 * ⚠️ **`qr_token` NO se publica**: es la credencial que canjea la entrada en la puerta, y un export
 * es un fichero que el titular guarda, reenvía y a veces pierde.
 *
 * Solo lectura y sin estado.
 */
class CustomerOrderHistoryReader implements CustomerOrderHistory
{
    /**
     * @return list<array<string, mixed>>
     */
    public function exportFor(int $userId): array
    {
        return $this->ordersOf($userId)
            ->map(fn (Order $order): array => [
                'code' => $order->code,
                'status' => $order->status,
                'subtotal_cents' => (int) $order->subtotal,
                'tax_cents' => (int) $order->tax,
                'total_cents' => (int) $order->total,
                'currency' => $order->currency,
                'created_at' => $order->created_at?->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
                'expires_at' => $order->expires_at?->toIso8601String(),
                'items' => $order->items->whereNull('parent_item_id')->values()
                    ->map(fn (OrderItem $item): array => $this->line($item))->all(),
                'tickets' => $order->tickets->map(fn (Ticket $ticket): array => [
                    'status' => $ticket->status,
                    'issued_at' => $ticket->created_at?->toIso8601String(),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Una línea principal con sus complementos anidados.
     *
     * ⚠️ **`event_data` va entero y en claro**: es el derecho de portabilidad del titular sobre datos
     * que son suyos (y de su hijo). Lo que obliga es servirlo con `no-store` (`RGPD-04`), no
     * recortarlo.
     *
     * @return array<string, mixed>
     */
    private function line(OrderItem $item): array
    {
        return [
            // El `id` del ítem NO se exporta: lo lleva para que Identity cruce con la asignación de
            // menores (Fase 6 · tanda 4) y lo retire antes de emitir. Es la única llave de la línea.
            'id' => (int) $item->id,
            'product' => $item->ticketType?->tr('name'),
            'date' => $item->slot?->date?->toDateString(),
            'time' => $item->slot?->start_time,
            'quantity' => $item->quantity,
            'unit_price_cents' => (int) $item->unit_price,
            'seats' => $item->seats,
            // `?: null` y no `?? null`: un `event_data` guardado como `[]` es «no hay datos», y
            // publicarlo como objeto vacío haría que el cliente distinguiera dos nadas.
            'event_data' => $item->event_data ?: null,
            'addons' => $item->children->map(fn (OrderItem $addon): array => [
                'product' => $addon->ticketType?->tr('name'),
                'quantity' => $addon->quantity,
                'unit_price_cents' => (int) $addon->unit_price,
            ])->values()->all(),
        ];
    }

    /**
     * Los pedidos del titular, con todo lo que la composición recorre ya cargado.
     *
     * ⚠️ **El orden se fija por `id` y antes era implícito.** `$user->orders` no declaraba ninguno,
     * así que el documento salía en el orden que quisiera el motor; al ser ahora una respuesta de API
     * con contrato, un orden que dependa del motor es una diferencia entre MySQL y el SQLite de la
     * suite esperando a aparecer. `id` ascendente es el orden en que se hicieron.
     *
     * @return Collection<int, Order>
     */
    private function ordersOf(int $userId): Collection
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with(['items.ticketType', 'items.slot', 'items.children.ticketType', 'tickets'])
            ->orderBy('id')
            ->get();
    }
}
