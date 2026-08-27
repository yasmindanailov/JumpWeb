<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CheckoutLine;
use App\Domain\Booking\Contracts\CheckoutLines;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;

/**
 * Read-model de BOOKING: las líneas principales de un pedido, en el orden de la cesta
 * ({@see CheckoutLines}; `docs/specs/menores-a-cargo.md` §9.9.3 D2).
 *
 * ⚠️ **`orderBy('id')` ES la promesa del contrato**, no un detalle: `OrderCreator::createPendingOrder()`
 * recorre `$cart` en orden y crea cada principal antes de sus complementos, así que los ids crecen
 * con la posición en la cesta. Si `OrderCreator` cambiara ese recorrido, cambiaría esta consulta —
 * el contrato dice «en el orden de la cesta», y quien lo garantiza es este módulo.
 *
 * Solo lectura y sin estado.
 */
class CheckoutLinesReader implements CheckoutLines
{
    /** @return list<CheckoutLine> */
    public function forOrder(int $orderId, int $userId): array
    {
        return OrderItem::query()
            ->whereNull('parent_item_id')
            ->whereHas('order', fn ($q) => $q->whereKey($orderId)->where('user_id', $userId))
            ->with(['ticketType', 'slot'])
            ->orderBy('id')
            ->get()
            ->values()
            ->map(static fn (OrderItem $item, int $index): CheckoutLine => new CheckoutLine(
                index: $index,
                orderItemId: (int) $item->getKey(),
                quantity: (int) $item->quantity,
                isEntry: $item->ticketType?->type === TicketType::TYPE_ENTRY,
                date: $item->slot?->date?->toDateString(),
            ))
            ->all();
    }
}
