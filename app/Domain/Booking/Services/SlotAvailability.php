<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aforo por OCUPACIÓN a lo largo de la visita (#60). Una entrada que entra en una
 * franja ocupa una plaza en CADA franja que abarca su duración (la ilimitada, hasta el
 * cierre de esa zona/día). Las plazas libres de una entrada = el mínimo de plazas libres
 * entre todas las franjas de su tramo. Cuenta los pedidos PAGADOS y los PENDIENTES cuya
 * retención de plaza no ha caducado (los caducados se ignoran aquí aunque el comando
 * `orders:expire` todavía no los haya marcado).
 */
class SlotAvailability
{
    /**
     * Plazas online libres para una entrada que entra en $entrySlot y dura $durationMin (null = ilimitada).
     *
     * $cartOccupants son ocupantes PROVISIONALES de la cesta en curso (líneas ya elegidas pero aún
     * sin pedido): restan aforo para que no se pueda sobrevender una franja desde la propia cesta
     * (5.4b, #70). El que llama los filtra por zona y día.
     *
     * $excludeItemId (sub-fase 7.2e.3, decisión #167) excluye `order_items.id` del cómputo de
     * ocupación. Necesario al EDITAR un item desde el panel: para saber si una nueva cantidad/
     * duración cabe en SU PROPIA franja hay que descontar primero la huella actual del item (si no,
     * un crecimiento legítimo se contaría a sí mismo y se bloquearía). Inocuo cuando el item está
     * en otra franja distinta de la que se evalúa.
     *
     * ⚠️ **Admite VARIOS ids desde la hora extra** (`specs/hora-extra.md` §4.6·1, el borde 1): al
     * excluir un padre hay que excluir **su descendencia** — con un solo id, la edición del padre
     * competiría contra su propia hora extra y vería la franja más llena de lo que está. Es el
     * cambio de firma que la spec pedía, no un parche: un `int` suelto sigue valiendo.
     *
     * @param  array<int, array{entry_start:string, duration_min:int|null, seats:int}>  $cartOccupants
     */
    public function availableFor(Slot $entrySlot, ?int $durationMin, array $cartOccupants = [], int|array|null $excludeItemId = null): int
    {
        // Solo bloquea si está EXPLÍCITAMENTE cerrada (online_sales_open=false o status=closed);
        // null (p. ej. valor por defecto no cargado en memoria) se trata como abierta.
        if ($entrySlot->online_sales_open === false || $entrySlot->status === Slot::STATUS_CLOSED) {
            return 0;
        }

        $spanned = $this->spannedSlots($entrySlot, $durationMin);
        if ($spanned->isEmpty()) {
            return 0;
        }

        // La visita debe caber ENTERA en el horario: sus franjas deben cubrir contiguamente toda su
        // duración. Si la rejilla se queda corta (p. ej. una entrada de 2 h que empieza 1 h antes del
        // cierre → la franja siguiente no existe, recortada por el horario) o hay un hueco en medio,
        // la entrada NO cabe → 0 plazas. Antes se ofrecía/vendía tiempo FUERA DE HORARIO. La duración
        // `null` («ilimitada, hasta el cierre») no tiene fin propio que validar: ocupa lo que haya.
        if ($durationMin !== null && ! $this->spanCoversDuration(
            $spanned,
            (string) $entrySlot->start_time,
            self::spanEnd((string) $entrySlot->start_time, $durationMin),
        )) {
            return 0;
        }

        $occupancy = $this->occupancyMap($entrySlot->zone_id, $entrySlot->date->toDateString(), $cartOccupants, $excludeItemId);

        return (int) $spanned->min(
            fn (Slot $slot) => max(0, $slot->online_capacity - ($occupancy[$slot->start_time] ?? 0))
        );
    }

    /**
     * Franjas (misma zona y día) que abarca la visita desde $entrySlot.
     *
     * @return Collection<int, Slot>
     */
    public function spannedSlots(Slot $entrySlot, ?int $durationMin): Collection
    {
        $query = Slot::where('zone_id', $entrySlot->zone_id)
            ->where('date', $entrySlot->date->toDateString())
            ->where('start_time', '>=', $entrySlot->start_time)
            ->orderBy('start_time');

        if ($durationMin) {
            $query->where('start_time', '<', self::spanEnd($entrySlot->start_time, $durationMin));
        }

        return $query->get();
    }

    /**
     * ¿Cubren las franjas del tramo TODA la duración de la visita, de forma contigua, hasta su fin
     * ($spanEnd)? Garantiza que la sesión NO se salga del horario: si la rejilla se acaba antes (la
     * franja siguiente fue recortada por el cierre) o hay un hueco/franja que falta, devuelve false
     * y la entrada no se ofrece ni se vende. Tolera rejillas solapadas (avanza la cobertura al fin
     * más lejano); para una rejilla normal (franjas seguidas) es contigüidad estricta.
     *
     * @param  Collection<int, Slot>  $spanned  franjas del tramo, ordenadas por start_time
     */
    private function spanCoversDuration(Collection $spanned, string $start, string $spanEnd): bool
    {
        $covered = $start; // hasta dónde llega la cobertura contigua (H:i:s)
        foreach ($spanned as $slot) {
            if ((string) $slot->start_time > $covered) {
                return false; // hueco: la siguiente franja empieza más tarde de donde llega la cobertura
            }
            if ((string) $slot->end_time > $covered) {
                $covered = (string) $slot->end_time;
            }
            if ($covered >= $spanEnd) {
                return true; // cubierta toda la visita
            }
        }

        return false; // la rejilla se acaba antes de cubrir toda la duración
    }

    /**
     * Mapa franja (start_time) → plazas ocupadas, para una zona y un día. Cada ocupante
     * (su franja de entrada + la duración de su tipo) ocupa todas las franjas de su tramo.
     * Cuenta los pedidos (pagados + pendientes vivos) y, opcionalmente, los ocupantes
     * PROVISIONALES de la cesta en curso (5.4b, #70) ya filtrados por esta zona y día.
     *
     * $excludeItemId (sub-fase 7.2e.3): omite `order_items.id` del recuento (ver `availableFor`;
     * varios ids desde la hora extra — el padre y su descendencia se excluyen JUNTOS).
     *
     * @param  array<int, array{entry_start:string, duration_min:int|null, seats:int}>  $cartOccupants
     * @return array<string, int>
     */
    public function occupancyMap(int $zoneId, string $date, array $cartOccupants = [], int|array|null $excludeItemId = null): array
    {
        $excludeIds = $excludeItemId === null ? [] : array_values(array_map('intval', (array) $excludeItemId));

        $stored = OrderItem::query()
            ->select('order_items.seats', 'entry.start_time as entry_start', 'ticket_types.duration_min')
            ->join('slots as entry', 'entry.id', '=', 'order_items.slot_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('entry.zone_id', $zoneId)
            ->where('entry.date', $date)
            ->when($excludeIds !== [], fn ($q) => $q->whereNotIn('order_items.id', $excludeIds))
            // Sub-fase 7.2e.2 (decisión #159): items soft-cancelados liberan
            // su plaza para que el aforo refleje la realidad operativa. La
            // decisión #152 prometió esta exclusión pero solo se aplicó a
            // `displayOperativeStatus` del Order; el aforo
            // online (`SlotAvailability`/`PackAvailability`) seguía contando
            // items cancelled, sobreestimando ocupación. Con este fix los
            // selectores fecha+hora del modal Gestionar (7.2e.2+) ofrecen
            // slots con aforo real, no inflado por cancelaciones.
            ->whereNull('order_items.cancelled_at')
            ->where(fn ($q) => $q
                ->where('orders.status', Order::STATUS_PAID)
                ->orWhere(fn ($q2) => $q2
                    ->where('orders.status', Order::STATUS_PENDING)
                    ->where(fn ($q3) => $q3
                        ->whereNull('orders.expires_at')
                        ->orWhere('orders.expires_at', '>', now())
                    )
                )
            )
            ->get()
            ->map(fn ($item) => [
                'entry_start' => $item->entry_start,
                'duration_min' => $item->duration_min,
                'seats' => (int) $item->seats,
            ]);

        // Ocupantes = pedidos en BD + líneas provisionales de la cesta (mismo tratamiento de tramo).
        $occupants = $stored->concat($cartOccupants);
        if ($occupants->isEmpty()) {
            return [];
        }

        // start_times de la zona/día (define las franjas válidas del tramo).
        $starts = Slot::where('zone_id', $zoneId)->where('date', $date)
            ->orderBy('start_time')->pluck('start_time')->all();

        $map = [];
        foreach ($occupants as $occupant) {
            $end = $occupant['duration_min']
                ? self::spanEnd($occupant['entry_start'], (int) $occupant['duration_min'])
                : null;

            foreach ($starts as $start) {
                if ($start < $occupant['entry_start']) {
                    continue;
                }
                if ($end !== null && $start >= $end) {
                    continue;
                }
                $map[$start] = ($map[$start] ?? 0) + (int) $occupant['seats'];
            }
        }

        return $map;
    }

    /**
     * Fin [exclusivo] del tramo de un ocupante en H:i:s, CLAMPADO al día (auditoría Fase 1 · L3):
     * si `entry_start + duración` cruza medianoche, `format('H:i:s')` envolvería y daría un fin
     * lexicográficamente menor que el inicio → tramo invertido (mismo fallo que `PackAvailability`).
     * Se acota a '24:00:00' (> cualquier `start_time` ≤ 23:59:59) para incluir el resto del día.
     *
     * `public static` desde la hora extra (`specs/hora-extra.md` §4.6·8): el inicio del tramo de la
     * HIJA es exactamente este fin del tramo del padre, y `AddonOccupancy` tiene que calcularlo con
     * LA MISMA aritmética que el aforo — una copia divergiría justo donde más duele. El clamp a
     * '24:00:00' además falla hacia invisible ahí: nunca casa con un `start_time` real, así que un
     * padre cuyo tramo cruza medianoche no tiene «franja siguiente» que ofrecer.
     */
    public static function spanEnd(string $start, int $durationMin): string
    {
        $startC = Carbon::parse($start)->setMicrosecond(0);
        $endC = $startC->copy()->addMinutes($durationMin);

        return $endC->toDateString() !== $startC->toDateString() ? '24:00:00' : $endC->format('H:i:s');
    }
}
