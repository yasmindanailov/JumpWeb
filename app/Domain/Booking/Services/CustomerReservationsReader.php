<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model de BOOKING: las reservas de un cliente, para consumidores de otros módulos.
 *
 * Implementa `CustomerReservations`. El código es el que vivía en
 * `App\Domain\Identity\Services\CustomerAccountContext` (Identity), movido tal cual al módulo dueño de
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
     * ⚠️⚠️ **La consulta está DIRIGIDA por fecha desde el 2026-08-23, y antes no lo estaba.** Traía
     * **todo el histórico** de pedidos con pack —con sus ítems, tipos y franjas eager-loaded— y hacía
     * el corte de «ya celebrado» **entero en PHP**, mientras que su hermana `upcomingFor()` sí acotaba
     * por SQL. Un cliente con cinco años de cumpleaños materializaba los cinco años **en cada página
     * pública**, porque el nav pide este contexto siempre que hay sesión.
     * ▶ Lo destapó la revisión adversarial de `specs/account-context-vue.md` §4.4 al preguntarse qué
     * pasaba si esto se publicaba como endpoint —repetible bajo `throttle:api`—; el arreglo, sin
     * embargo, **vale igual sin endpoint**: el coste ya se pagaba.
     *
     * ⚠️ **El suelo tiene que dejar pasar el pack SIN FRANJA**, o cambiaría la conducta: un pack sin
     * franja da `isFinishedInPractice() === false` y **sigue avisando** (lo dice el párrafo de
     * arriba, y hay caso que lo fija). De ahí el `whereNull('slot_id') OR fecha >= suelo`, sobre el
     * MISMO ítem — no sobre el pedido—, para que un pedido con un pack viejo y otro futuro siga
     * entrando y el corte fino lo siga dando PHP.
     *
     * @return list<PendingGuestForm>
     */
    public function pendingGuestFormsFor(int $userId): array
    {
        // Mismo colchón de un día y por el mismo motivo que `upcomingItems()`: la fecha de franja es
        // naive `Y-m-d` y el corte exacto lo hace PHP.
        $floor = Carbon::now()->subDay()->toDateString();

        $orders = Order::query()
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($q) => $q->whereNull('cancelled_at')
                ->whereHas('ticketType', fn ($t) => $t->where('type', TicketType::TYPE_PACK))
                ->where(fn ($i) => $i->whereNull('slot_id')
                    ->orWhereHas('slot', fn ($s) => $s->whereDate('date', '>=', $floor))))
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
