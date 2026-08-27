<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\GateReservation;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;

/**
 * Fase 6 · subsistema A — la implementación de `GateReservations` (`docs/specs/identidad-qr-puerta.md`
 * §9.2 A·3): una consulta DIRIGIDA por fecha y los importes por **`OrderLedger::forReservation()`**.
 *
 * ⚠️ Es una SUPERFICIE del desglose (`LedgerSingleSourceTest::SURFACES`): pinta lo que el ledger dice,
 * no deriva ningún canal restando otros. Y el aviso de §4.7 vale aquí más que en ningún sitio: un
 * dato ROTO en la base de datos (`R-L6UTIA`) saldrá tal cual y lo verá un empleado con el cliente
 * delante — *comprueba si el dato es real antes de buscar el fallo en el código*.
 */
class GateReservationsReader implements GateReservations
{
    public function forHolder(int $userId, string $fromDate, string $toDate): array
    {
        return OrderItem::query()
            ->active()
            ->whereNull('parent_item_id')
            ->whereNotNull('slot_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('status', Order::STATUS_PAID))
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $fromDate)->whereDate('date', '<=', $toDate))
            ->with(['ticketType', 'slot', 'children.ticketType', 'order.adjustments', 'order.payments.refunds', 'order.items'])
            ->get()
            ->sortBy(fn (OrderItem $item): string => ($item->slot?->date?->format('Y-m-d') ?? '9999-12-31').' '.substr((string) ($item->slot?->start_time ?? '00:00:00'), 0, 8))
            ->values()
            ->map(function (OrderItem $item): GateReservation {
                /** @var Order $order */
                $order = $item->order;
                $ledger = OrderLedger::forReservation($order, $item);

                return new GateReservation(
                    orderId: (int) $order->getKey(),
                    orderCode: (string) $order->code,
                    orderItemId: (int) $item->getKey(),
                    date: (string) $item->slot?->date?->format('Y-m-d'),
                    timeWindow: $item->displayTimeWindow(),
                    productName: (string) ($item->ticketType?->tr('name') ?? ''),
                    isEntry: $item->ticketType?->type === TicketType::TYPE_ENTRY,
                    quantity: (int) $item->quantity,
                    addons: $item->children
                        ->reject(fn (OrderItem $child): bool => $child->isCancelled())
                        ->map(fn (OrderItem $child): string => $child->quantity.' × '.($child->ticketType?->tr('name') ?? ''))
                        ->values()
                        ->all(),
                    paidOnlineCents: (int) $ledger->pagadoOnline,
                    pendingGateCents: (int) $ledger->pendientePuerta,
                    chargeMethod: $ledger->cobroMetodo,
                    paidAt: $order->paid_at?->toIso8601String(),
                    createdAt: (string) $order->created_at?->toIso8601String(),
                );
            })
            ->all();
    }
}
