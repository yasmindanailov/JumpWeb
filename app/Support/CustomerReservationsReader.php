<?php

namespace App\Support;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model de BOOKING: las reservas de un cliente, para consumidores de otros módulos.
 *
 * Implementa `CustomerReservations`. El código es el que vivía en
 * `App\Support\CustomerAccountContext` (Identity), movido tal cual al módulo dueño de
 * los datos en el paso 1 de la modularización — Identity ya no consulta `Order`,
 * `OrderItem` ni `TicketType` (Fase 2, `docs/specs/modulos-dominio.md`).
 *
 * Vive en `app/Support` hasta el paso 6, cuando Booking mude a `app/Domain/Booking/Services`.
 * Solo lectura y sin estado: no memoiza (de eso se encarga el singleton que lo consume).
 */
class CustomerReservationsReader implements CustomerReservations
{
    /**
     * Reservas FUTURAS del usuario: ítems principales (no addon), no cancelados, con franja y aún
     * no finalizados, de pedidos PAGADOS, ordenados por fecha+hora. Consulta DIRIGIDA (solo franjas
     * recientes o futuras) para no materializar todo el histórico en cada página pública; el corte
     * fino de "ya finalizada" lo da `isFinishedInPractice` en PHP (frontera con hora + zona horaria).
     *
     * @return list<UpcomingReservation>
     */
    public function upcomingFor(int $userId): array
    {
        return $this->upcomingItems($userId)
            ->map(fn (OrderItem $item): UpcomingReservation => new UpcomingReservation(
                date: (string) $item->slot?->date?->format('Y-m-d'),
                timeWindow: $item->displayTimeWindow(),
                productName: (string) ($item->ticketType?->tr('name') ?? ''),
            ))
            ->all();
    }

    /**
     * Formularios de reserva (#217) pendientes: uno por pedido pagado con un pack que los pide y
     * cuya franja AÚN NO ha finalizado (simétrico a la próxima reserva — no avisamos por un
     * cumpleaños ya celebrado, que dejaría el puntito/aviso encendidos para siempre). La consulta
     * se acota a pedidos con algún ítem de tipo pack (los formularios solo existen en packs) para
     * no recorrer el histórico de entradas. Un pack sin franja (`isFinishedInPractice` → false)
     * sigue avisando.
     *
     * @return list<PendingGuestForm>
     */
    public function pendingGuestFormsFor(int $userId): array
    {
        $orders = Order::query()
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($q) => $q->whereNull('cancelled_at')
                ->whereHas('ticketType', fn ($t) => $t->where('type', TicketType::TYPE_PACK)))
            ->with(['items.ticketType', 'items.slot'])
            ->get();

        $pending = [];

        // Individualizado POR RESERVA (#217): un aviso por cada pack pendiente cuya franja aún no ha
        // finalizado (no avisamos por un cumpleaños ya celebrado). El aviso apunta a ESA reserva
        // (el `OrderItem`), no al pedido.
        foreach ($orders as $order) {
            $items = $order->guestFormItems()
                ->filter(fn (OrderItem $i): bool => $i->needsGuestForm() && ! $i->isFinishedInPractice());

            foreach ($items as $item) {
                $pending[] = new PendingGuestForm(
                    reservationId: (int) $item->id,
                    productName: (string) ($item->ticketType?->tr('name') ?? ''),
                );
            }
        }

        return $pending;
    }

    /** @return Collection<int, OrderItem> */
    private function upcomingItems(int $userId): Collection
    {
        // Margen de 1 día en el filtro SQL: la convención de fechas es naive `Y-m-d` con
        // APP_TIMEZONE=UTC mientras el negocio opera en su zona local; el colchón evita descartar
        // por error una reserva de hoy cerca de la medianoche (el corte exacto lo hace PHP).
        $floor = Carbon::now()->subDay()->toDateString();

        return OrderItem::query()
            ->active()
            ->whereNull('parent_item_id')
            ->whereNotNull('slot_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('status', Order::STATUS_PAID))
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $floor))
            ->with(['ticketType', 'slot'])
            ->get()
            ->reject(fn (OrderItem $item): bool => $item->isFinishedInPractice())
            ->sortBy(fn (OrderItem $item): string => $this->slotSortKey($item))
            ->values();
    }

    /** Clave de orden estable (fecha + hora de inicio) para elegir la franja más próxima. */
    private function slotSortKey(OrderItem $item): string
    {
        $date = $item->slot?->date?->format('Y-m-d') ?? '9999-12-31';
        $time = substr((string) ($item->slot?->start_time ?? '00:00:00'), 0, 8);

        return $date.' '.$time;
    }
}
