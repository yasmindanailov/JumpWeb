<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Contracts\AuthorizableReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Implementación de {@see AuthorizableReservations} (`specs/waiver-por-reserva.md` §13).
 *
 * ⚠️⚠️ **El «cuánta gente cabe» es la CANTIDAD DE LA LÍNEA, y ése es el arreglo.** El lector anterior
 * sumaba las líneas principales vivas del PEDIDO, así que un pedido con una excursión de 80 y una
 * entrada suelta ofrecía **81** plazas a un justificante de la entrada. Medido sobre `R-LUKFD2`.
 *
 * ⚠️ **Lo que este lector NO puede restar son los menores a cargo ya asignados**, y no es un olvido:
 * `dependent_assignments` vive en Identity y **Booking no puede mirar a Identity**. La resta la hace
 * quien conoce las dos cosas (`Identity\Services\GuardianPlaces`), sobre esta cantidad bruta.
 *
 * ⚠️ Un COMPLEMENTO y una línea CANCELADA no son reservas autorizables: no traen a nadie. Se filtran
 * en la consulta y no en quien pregunta, para que ningún consumidor pueda olvidarse.
 */
class AuthorizableReservationsReader implements AuthorizableReservations
{
    public function find(int $reservationId): ?AuthorizableReservation
    {
        $item = $this->query()->whereKey($reservationId)->first();

        return $item === null ? null : $this->describe($item);
    }

    public function markedForOrder(int $orderId): array
    {
        return $this->query()
            ->where('order_id', $orderId)
            ->where('guardian_authorization', true)
            ->orderBy('id')
            ->get()
            ->map(fn (OrderItem $item): AuthorizableReservation => $this->describe($item))
            ->all();
    }

    /** @return Builder<OrderItem> */
    private function query(): Builder
    {
        return OrderItem::query()
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->with(['order', 'slot', 'ticketType']);
    }

    private function describe(OrderItem $item): AuthorizableReservation
    {
        $order = $item->order;

        return new AuthorizableReservation(
            reservationId: (int) $item->getKey(),
            orderId: (int) $item->order_id,
            orderCode: (string) ($order?->code ?? ''),
            // El nombre que el cliente compró, con la etiqueta de fiesta MIXTA si la lleva: es el
            // mismo compositor que usan la hoja de sala y la puerta (`#245`), para que el padre lea
            // exactamente lo que lee el operador.
            productName: $item->displayProductName(),
            date: $item->slot?->date?->toDateString(),
            // ⚠️ La duración EFECTIVA, nunca `slot->end_time`: la rejilla es de 60 min y una fiesta de
            // dos horas decía «17:00 – 18:00» en la hoja que firma el padre (`#426`).
            timeWindow: $item->displayTimeWindow(),
            quantity: (int) $item->quantity,
            isPaid: $order?->status === Order::STATUS_PAID,
            // Sin franja no ha terminado nada: `false`, no `true`. Es la misma trampa que el lector
            // anterior documentaba —`every()` sobre una colección vacía devuelve `true`— reducida a
            // una línea: aquí basta con no dar por cerrada una reserva que no tiene día.
            visitFinished: $item->slot?->date !== null && $item->isFinishedInPractice(),
            // ⚠️ La caducidad sigue siendo la del PEDIDO (`RGPD-03`): dos enlaces de la misma compra
            // con plazos distintos serían dos reglas, y el plazo lo fija una invariante.
            linkExpiresAt: CarbonImmutable::instance(
                $order?->guestFormLinkExpiresAt() ?? now()->addDays(14),
            ),
        );
    }
}
