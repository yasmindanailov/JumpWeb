<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AuthorizableOrder;
use App\Domain\Booking\Contracts\AuthorizableOrders;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Implementación de {@see AuthorizableOrders} (`specs/waiver-por-reserva.md` §4.6, §4.7).
 *
 * ⚠️⚠️ **El «cuánta gente se compró» NO es `SUM(order_items.quantity)`.** Esa suma incluye
 * complementos (`parent_item_id` no nulo), los PORTADORES del suplemento y del descuento de fiesta
 * mixta —que también son filas de `order_items`— y las líneas **canceladas**. Medido sobre la base
 * local: ocho pedidos divergen, y uno íntegramente cancelado admitiría dos autorizaciones. El tope
 * cuenta **líneas principales vivas**, que es lo que traduce «no se puede autorizar a más gente de la
 * que se ha comprado».
 *
 * ⚠️ La caducidad del enlace sale de `Order::guestFormLinkExpiresAt()` y no se recalcula aquí: es la
 * fuente única que `RGPD-03` nombra, y dos enlaces de la misma reserva con caducidades distintas
 * serían dos reglas.
 */
class AuthorizableOrdersReader implements AuthorizableOrders
{
    public function find(int $orderId): ?AuthorizableOrder
    {
        $order = Order::query()->whereKey($orderId)->first();
        if ($order === null) {
            return null;
        }

        /** @var Collection<int, OrderItem> $live */
        $live = OrderItem::query()
            ->where('order_id', $order->getKey())
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->with('slot')
            ->get();

        $dated = $live->filter(fn (OrderItem $item): bool => $item->slot?->date !== null);

        return new AuthorizableOrder(
            orderId: (int) $order->getKey(),
            code: (string) $order->code,
            isPaid: $order->status === Order::STATUS_PAID,
            capacity: (int) $live->sum('quantity'),
            // Con CERO líneas con fecha no ha terminado nada: `false`, no `true`. Un `all()` sobre una
            // colección vacía devuelve `true`, y eso cerraría el formulario de un pedido sin franjas
            // el día que se cree — en silencio.
            visitFinished: $dated->isNotEmpty()
                && $dated->every(fn (OrderItem $item): bool => $item->isFinishedInPractice()),
            linkExpiresAt: CarbonImmutable::instance($order->guestFormLinkExpiresAt()),
            visitDates: $dated
                ->map(fn (OrderItem $item): string => $item->slot->date->toDateString())
                ->unique()
                ->sort()
                ->values()
                ->all(),
        );
    }
}
