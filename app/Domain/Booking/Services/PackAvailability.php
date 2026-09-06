<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aforo de PACKS (cumpleaños) por CUPO configurable por franja (#82), con POOL PROPIO para
 * DECIDIR si cabe una fiesta: este servicio no mira los asientos de la franja, solo su cupo.
 * Es el gemelo de {@see SlotAvailability}, pero con DOS topes por franja en lugar de uno:
 *
 * ❗❗ **CORRECCIÓN (2026-08-25, `DECISIONES #148`): aquí decía «un cumpleaños no resta plazas de
 * entrada ni viceversa», y la mitad de esa frase es FALSA.** Medido en frío: una franja con 10
 * plazas y una fiesta de 8 invitados deja **2 plazas de entrada**, no 10.
 * ▶ La causa: {@see SlotAvailability::occupancyMap()} suma los `seats` de **todos** los
 * `order_items` de la zona/día **sin filtrar por tipo**, y una línea de pack lleva `seats` como
 * cualquier otra. Así que la independencia es **de una sola dirección**: una entrada no consume
 * cupo de fiestas, pero **una fiesta sí consume asientos de entrada**.
 * ▶ ✅ **[DECIDIDO owner, `#151` (2026-08-25)]: es lo CORRECTO, y la regla es «la independencia de
 * cupos se hace POR ZONA».** Dentro de una zona, `seats` cuenta ocupación física real sea del
 * producto que sea; un producto que necesite plazas propias se lleva a su zona (cumpleaños en la
 * suya hoy; excursiones de colegio con la suya mañana). `occupancyMap` NO se filtra por tipo.
 * ▶ Lo destapó el escenario `mixed` de `purchase:verify-oversell`, cuyo número de ganadores VARÍA
 * entre ejecuciones justo por esto. Lo guarda `PackConsumesEntrySeatsTest` — ya como regla
 * decidida, no como comportamiento fijado a la espera.
 *   - `packs.max_per_slot`        → nº de cumpleaños por franja (0 = sin tope).
 *   - `packs.max_guests_per_slot` → nº de niños totales por franja (0 = sin tope).
 *
 * Una fiesta ocupa cupo en TODAS las franjas que abarca su duración y —si el ajuste
 * `packs.prep_blocks_cupo` está activo (por defecto sí, #82, decisión 2026-05-25)— también
 * su montaje (`prep_before_min`) y su limpieza (`prep_after_min`), que bloquean las franjas
 * vecinas. El toggle permite desactivar ese comportamiento desde el panel (Fase 7).
 *
 * Pensado para correr bajo `lockForUpdate` en {@see OrderCreator} (anti-sobreventa): el
 * bloqueo de las franjas de la zona/día serializa las compras concurrentes de cumpleaños.
 */
class PackAvailability
{
    public const SETTING_MAX_PER_SLOT = 'packs.max_per_slot';

    public const SETTING_MAX_GUESTS_PER_SLOT = 'packs.max_guests_per_slot';

    public const SETTING_PREP_BLOCKS_CUPO = 'packs.prep_blocks_cupo';

    /**
     * Plazas de invitados libres para UNA fiesta nueva que empieza en $slot, contando las
     * demás fiestas (pedidos vivos + cesta provisional). Devuelve 0 si el cupo de FIESTAS de
     * alguna franja del tramo ya está completo o si la franja está cerrada. El llamador acota
     * después la cantidad al rango [min_qty, max_qty] del pack.
     *
     * $cartOccupants son fiestas PROVISIONALES de la cesta en curso (ya elegidas, sin pedido),
     * filtradas por esta zona y día; restan cupo para no sobrevender desde la propia cesta.
     *
     * $excludeItemId (sub-fase 7.2e.3, decisión #167) excluye un `order_items.id` del cupo: al
     * EDITAR un pack desde el panel hay que descontar su propia huella para saber si una nueva
     * cantidad de invitados/duración cabe en su franja (si no, se contaría a sí mismo). Inocuo
     * cuando el item está en otra franja.
     *
     * @param  array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>  $cartOccupants
     */
    public function availableGuestsFor(Slot $slot, TicketType $pack, array $cartOccupants = [], ?int $excludeItemId = null, int $extraMinutes = 0): int
    {
        if ($slot->online_sales_open === false || $slot->status === Slot::STATUS_CLOSED) {
            return 0;
        }

        $spanned = $this->spannedSlots($slot, $pack, $extraMinutes);
        if ($spanned->isEmpty()) {
            return 0;
        }

        // La FIESTA debe caber entera en el horario: sus franjas deben cubrir su DURACIÓN hasta el
        // fin. Si la rejilla se queda corta (un pack que empieza demasiado tarde → la franja siguiente
        // fue recortada por el cierre), NO cabe → 0 (no se vende tiempo fuera de horario). El montaje/
        // limpieza (prep) NO se valida aquí: es operación de la casa, no «venta fuera de horario».
        $stay = self::stayMinutes($pack, $extraMinutes);
        if ($stay !== null && ! $this->spanCoversDuration(
            $spanned, (string) $slot->start_time, $this->spanEnd((string) $slot->start_time, $stay),
        )) {
            return 0;
        }

        $maxParties = $this->maxPartiesPerSlot($slot->zone);
        $maxGuests = $this->maxGuestsPerSlot($slot->zone);

        [$parties, $guests] = $this->occupancyMaps($slot->zone_id, $slot->date->toDateString(), $cartOccupants, $excludeItemId, $slot->zone);

        $freeGuests = PHP_INT_MAX;
        foreach ($spanned as $spannedSlot) {
            // Tope de FIESTAS: si alguna franja del tramo está completa, no cabe otra fiesta.
            if ($maxParties > 0 && ($parties[$spannedSlot->start_time] ?? 0) >= $maxParties) {
                return 0;
            }
            // Tope de NIÑOS por franja (0 = sin tope): el cuello de botella es la franja más llena.
            if ($maxGuests > 0) {
                $freeGuests = min($freeGuests, max(0, $maxGuests - ($guests[$spannedSlot->start_time] ?? 0)));
            }
        }

        // Sin tope de niños configurado → solo lo limita el máximo del propio pack.
        if ($freeGuests === PHP_INT_MAX) {
            return (int) ($pack->max_qty ?? $freeGuests);
        }

        return $pack->max_qty ? (int) min($freeGuests, $pack->max_qty) : (int) $freeGuests;
    }

    /**
     * Plazas de invitados LIBRES de la franja para MOSTRAR al operador (sub-fase
     * 7.2e.3 pulido #168) — es la capacidad real restante del cupo de niños
     * (`max_guests_per_slot − ocupados`), **SIN topar por el `max_qty` del pack**.
     *
     * `availableGuestsFor` topa el resultado al `max_qty` del pack (p. ej. 20)
     * porque sirve para acotar cuántos invitados puede tener UNA fiesta nueva;
     * pero para el slider eso ocultaba la ocupación: con cupo 60 y un pack de 20,
     * `min(60−20, 20)` = 20 → la franja "siempre ponía 20" aunque hubiera
     * invitados reservados. Aquí devolvemos las plazas reales (40 en ese caso),
     * que SÍ decrementan con cada invitado — igual que las entradas muestran sus
     * plazas reales. Cuenta la huella del propio item salvo que se excluya con
     * `$excludeItemId`.
     *
     * Devuelve `null` cuando NO hay tope de invitados configurado
     * (`max_guests_per_slot = 0`): no hay un número de plazas que mostrar y el
     * llamador cae al comportamiento anterior (`max_qty`). Devuelve `0` si la
     * franja está cerrada o el cupo de FIESTAS de su tramo está completo.
     *
     * @param  array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>  $cartOccupants
     */
    public function freeGuestSlots(Slot $slot, TicketType $pack, array $cartOccupants = [], ?int $excludeItemId = null, int $extraMinutes = 0): ?int
    {
        if ($slot->online_sales_open === false || $slot->status === Slot::STATUS_CLOSED) {
            return 0;
        }

        $maxGuests = $this->maxGuestsPerSlot($slot->zone);
        if ($maxGuests <= 0) {
            return null; // sin tope de invitados → no hay "plazas" que mostrar
        }

        $spanned = $this->spannedSlots($slot, $pack, $extraMinutes);
        if ($spanned->isEmpty()) {
            return 0;
        }

        // Misma garantía que `availableGuestsFor`: la fiesta no se ofrece si su duración no cabe en la
        // rejilla (no se vende tiempo fuera de horario).
        $stay = self::stayMinutes($pack, $extraMinutes);
        if ($stay !== null && ! $this->spanCoversDuration(
            $spanned, (string) $slot->start_time, $this->spanEnd((string) $slot->start_time, $stay),
        )) {
            return 0;
        }

        $maxParties = $this->maxPartiesPerSlot($slot->zone);
        [$parties, $guests] = $this->occupancyMaps($slot->zone_id, $slot->date->toDateString(), $cartOccupants, $excludeItemId, $slot->zone);

        $free = PHP_INT_MAX;
        foreach ($spanned as $spannedSlot) {
            if ($maxParties > 0 && ($parties[$spannedSlot->start_time] ?? 0) >= $maxParties) {
                return 0;
            }
            $free = min($free, max(0, $maxGuests - ($guests[$spannedSlot->start_time] ?? 0)));
        }

        return $free === PHP_INT_MAX ? null : $free;
    }

    /**
     * Franjas (misma zona de packs y día) que abarca una fiesta que empieza en $slot: su
     * duración y, si el cupo cuenta la preparación, también el montaje antes y la limpieza
     * después.
     *
     * @return Collection<int, Slot>
     */
    public function spannedSlots(Slot $slot, TicketType $pack, int $extraMinutes = 0): Collection
    {
        [$windowStart, $windowEnd] = $this->window(
            $slot->start_time,
            (int) $pack->prep_before_min,
            self::stayMinutes($pack, $extraMinutes),
            (int) $pack->prep_after_min,
            $this->prepBlocksCupo($slot->zone),
        );

        return Slot::where('zone_id', $slot->zone_id)
            ->where('date', $slot->date->toDateString())
            ->where('start_time', '>=', $windowStart)
            ->where('start_time', '<', $windowEnd)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Mapas franja(start_time) → nº de fiestas y → nº de niños, para una zona de packs y un
     * día. Cuenta los pedidos con líneas de pack (pagados + pendientes cuya retención no ha
     * caducado) y, opcionalmente, las fiestas PROVISIONALES de la cesta. Pool propio: solo
     * cuentan líneas `type=pack`.
     *
     * $excludeItemId (sub-fase 7.2e.3): omite un `order_items.id` del recuento (ver `availableGuestsFor`).
     *
     * @param  array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>  $cartOccupants
     * @return array{0: array<string,int>, 1: array<string,int>}
     */
    public function occupancyMaps(int $zoneId, string $date, array $cartOccupants = [], ?int $excludeItemId = null, ?Zone $zone = null): array
    {
        // Reusa la zona ya cargada por el llamador (evita un Zone::find por llamada); solo la
        // busca si no la recibe.
        $prepBlocks = $this->prepBlocksCupo($zone ?? Zone::find($zoneId));

        $stored = OrderItem::query()
            ->select(
                'order_items.seats as guests',
                'entry.start_time as start',
                'ticket_types.prep_before_min',
                'ticket_types.prep_after_min',
            )
            // ⚠️⚠️ **La hora extra de un pack alarga la VENTANA de esta misma fiesta** — no añade una
            // fiesta nueva (`specs/hora-extra.md` §10.3): sigue contando **1** en `parties` y **sus**
            // invitados en `guests`, sólo que durante más rato. Modelarlo como un ocupante aparte era
            // el defecto (b) de §10.1: una línea hija que dice «1 persona» donde hay veinte.
            // Se suma en SQL por lo mismo que en `SlotAvailability`: este mapa es la vía caliente.
            ->selectRaw('(ticket_types.duration_min + order_items.extra_minutes) as duration_min')
            ->join('slots as entry', 'entry.id', '=', 'order_items.slot_id')
            ->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('ticket_types.type', TicketType::TYPE_PACK)
            ->where('entry.zone_id', $zoneId)
            ->where('entry.date', $date)
            ->when($excludeItemId !== null, fn ($q) => $q->where('order_items.id', '!=', $excludeItemId))
            // Sub-fase 7.2e.2 (decisión #159): packs soft-cancelados liberan
            // su cupo. Mismo razonamiento que `SlotAvailability` — antes de
            // este fix, un cumple cancelado seguía contando como "fiesta de
            // la franja", bloqueando inserciones legítimas de otros packs en
            // el mismo slot.
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
                'start' => $item->start,
                'prep_before_min' => (int) $item->prep_before_min,
                'duration_min' => $item->duration_min,
                'prep_after_min' => (int) $item->prep_after_min,
                'guests' => (int) $item->guests,
            ]);

        $occupants = $stored->concat($cartOccupants);

        $parties = [];
        $guests = [];
        if ($occupants->isEmpty()) {
            return [$parties, $guests];
        }

        // start_times de la zona/día (define las franjas válidas del tramo).
        $starts = Slot::where('zone_id', $zoneId)->where('date', $date)
            ->orderBy('start_time')->pluck('start_time')->all();

        foreach ($occupants as $occupant) {
            [$windowStart, $windowEnd] = $this->window(
                $occupant['start'],
                (int) ($occupant['prep_before_min'] ?? 0),
                $occupant['duration_min'] ?? null,
                (int) ($occupant['prep_after_min'] ?? 0),
                $prepBlocks,
            );

            foreach ($starts as $start) {
                if ($start < $windowStart || $start >= $windowEnd) {
                    continue;
                }
                $parties[$start] = ($parties[$start] ?? 0) + 1;
                $guests[$start] = ($guests[$start] ?? 0) + (int) $occupant['guests'];
            }
        }

        return [$parties, $guests];
    }

    /**
     * ¿La preparación (montaje/limpieza) cuenta para el cupo, bloqueando franjas vecinas? (#82)
     * Override por zona si está definido; si no, el ajuste global.
     */
    public function prepBlocksCupo(?Zone $zone = null): bool
    {
        if ($zone?->prep_blocks_cupo !== null) {
            return (bool) $zone->prep_blocks_cupo;
        }

        return (bool) (int) Setting::value(self::SETTING_PREP_BLOCKS_CUPO, '1');
    }

    /** Tope de cumpleaños por franja (0 = sin tope). Override por zona → ajuste global. */
    public function maxPartiesPerSlot(?Zone $zone = null): int
    {
        if ($zone?->max_per_slot !== null) {
            return (int) $zone->max_per_slot;
        }

        return (int) Setting::value(self::SETTING_MAX_PER_SLOT, '0');
    }

    /** Tope de niños por franja (0 = sin tope). Override por zona → ajuste global. */
    public function maxGuestsPerSlot(?Zone $zone = null): int
    {
        if ($zone?->max_guests_per_slot !== null) {
            return (int) $zone->max_guests_per_slot;
        }

        return (int) Setting::value(self::SETTING_MAX_GUESTS_PER_SLOT, '0');
    }

    /**
     * Ventana [inicio, fin) en H:i:s que ocupa una fiesta: su duración y —si el cupo cuenta la
     * preparación— el montaje antes y la limpieza después.
     *
     * @return array{0:string, 1:string}
     */
    /**
     * ¿Cubren las franjas TODA la duración de la fiesta, de forma contigua, hasta su fin? Gemelo del
     * de {@see SlotAvailability}: garantiza que la fiesta no se salga del horario (la franja siguiente
     * recortada por el cierre, o un hueco, devuelven false). Las franjas de prep ANTES del inicio se
     * ignoran (empiezan antes que la cobertura); en cuanto se cubre hasta `$spanEnd` (fin de la fiesta)
     * devuelve true, así que el prep posterior no afecta.
     *
     * @param  Collection<int, Slot>  $spanned  franjas del tramo (ventana de cupo), ordenadas por start_time
     */
    private function spanCoversDuration(Collection $spanned, string $start, string $spanEnd): bool
    {
        $covered = $start;
        foreach ($spanned as $slot) {
            if ((string) $slot->start_time > $covered) {
                return false; // hueco entre la cobertura y la siguiente franja
            }
            if ((string) $slot->end_time > $covered) {
                $covered = (string) $slot->end_time;
            }
            if ($covered >= $spanEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * **Los minutos que ocupa la fiesta que se está evaluando**: su duración más lo que la alargan
     * los complementos que extienden la estancia (`specs/hora-extra.md` §10.3).
     *
     * ⚠️ Es la mitad VIVA de la aritmética —la de una compra que aún no existe—; la mitad ALMACENADA
     * la suma `occupancyMaps()` en SQL (`duration_min + extra_minutes`). Las dos tienen que decir lo
     * mismo, y por eso las dos suman en el mismo sitio conceptual: la duración del producto más los
     * minutos comprados.
     *
     * ⚠️ `null` (pack ilimitado) se queda en `null`: lo que ya llega al cierre no se alarga, y el
     * guard del pivote impide venderle una extensión.
     */
    private static function stayMinutes(TicketType $pack, int $extraMinutes): ?int
    {
        return $pack->duration_min === null ? null : (int) $pack->duration_min + max(0, $extraMinutes);
    }

    /** Fin [exclusivo] de la fiesta en H:i:s, clampado al día (igual que {@see SlotAvailability::spanEnd}). */
    private function spanEnd(string $start, int $durationMin): string
    {
        $startC = Carbon::parse($start)->setMicrosecond(0);
        $endC = $startC->copy()->addMinutes($durationMin);

        return $endC->toDateString() !== $startC->toDateString() ? '24:00:00' : $endC->format('H:i:s');
    }

    private function window(string $start, int $prepBefore, ?int $durationMin, int $prepAfter, bool $prepBlocks): array
    {
        $before = $prepBlocks ? $prepBefore : 0;
        $after = $prepBlocks ? $prepAfter : 0;
        $duration = (int) ($durationMin ?? 60);

        $startC = Carbon::parse($start);
        $startC->setMicrosecond(0);
        $beforeC = $startC->copy()->subMinutes($before);
        $endC = $startC->copy()->addMinutes($duration + $after);

        // Clamp al DÍA (auditoría Fase 1 · L3): si la aritmética cruza medianoche, `format('H:i:s')`
        // envolvería y daría un fin lexicográficamente MENOR que el inicio → la ventana se invierte y
        // (a) el pack se vuelve invendible en franjas tardías (fail-closed) y (b) una fiesta ya
        // almacenada cuya ventana cruza 00:00 DESAPARECE del cupo (fail-open → sobreventa de
        // `max_per_slot`). Acotamos a [00:00:00, 24:00:00): todas las franjas del día caen dentro y la
        // comparación de strings con `start_time` (≤ 23:59:59) sigue siendo correcta.
        return [
            $beforeC->toDateString() !== $startC->toDateString() ? '00:00:00' : $beforeC->format('H:i:s'),
            $endC->toDateString() !== $startC->toDateString() ? '24:00:00' : $endC->format('H:i:s'),
        ];
    }
}
