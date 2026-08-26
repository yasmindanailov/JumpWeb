<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;

/**
 * La oferta de RE-PROGRAMACIÓN de un ítem ya comprado — la pregunta del
 * panel: «¿a dónde se puede MOVER este ítem?». Es OTRA pregunta que la de
 * compra (`AvailabilityOffer`/`SlotOffer`: «¿qué se puede COMPRAR?») y por
 * eso es un servicio propio y no una extensión del contrato de compra
 * (extracción 3 del desmontaje, `docs/specs/desmontar-view-order.md` §4.1·3
 * y §8.3). Con esto el panel deja de COMPONER su propia oferta (`AFORO-02`
 * aplicada al panel): la composición vive en el dominio y la página solo
 * decora.
 *
 * Reglas del contrato, cada una decidida y no heredada por accidente
 * (`[DECIDIDO owner, 2026-08-26]`, spec §9.4):
 *
 *  - **El ancla temporal es la zona del PARQUE** (`DisplayTime`), no UTC —
 *    la doctrina de `AFORO-09`, igual que la oferta pública. Entre las
 *    00:00 y las ~02:00 del parque el «hoy» UTC va un día por detrás.
 *  - **SIN antelación mínima y SIN corte intra-día**: el operador puede
 *    mover un ítem a una hora de HOY ya pasada (venta en mostrador). Es la
 *    exención del panel a la letra de `PAY-13`, ahora DECLARADA aquí.
 *  - **Las horas sin aforo para `item.seats` se OCULTAN** (no se enseñan
 *    deshabilitadas), salvo la actual.
 *  - **El slot ACTUAL siempre se ofrece** aunque no pase los filtros
 *    (mantenerlo es un no-op; decisión `#164` del origen).
 *  - **La huella propia se CUENTA en el display** (decisión `#173` del
 *    origen: el slider es fidedigno con la lógica real de reservas).
 *    Excluirla (`excludeItemId`, `AFORO-06`) es cosa de la VALIDACIÓN bajo
 *    lock en el guardado — no de la oferta.
 *  - **Un producto RETIRADO sigue siendo movible**: la oferta solo exige
 *    zona; no filtra por la vendibilidad del producto del ítem.
 *
 * ⚠️ Este servicio es SOLO LECTURA (composición de oferta): no toma locks
 * ni muta aforo, y por eso NO pertenece al `CRITICAL_RE` — la misma razón
 * por la que `ProductAvailability` es su control negativo declarado.
 */
class ItemRescheduleOffer
{
    public function __construct(
        private OperatingSchedule $schedule,
        private ProductAvailability $productWindow,
        private SlotAvailability $slotAvailability,
        private PackAvailability $packAvailability,
    ) {}

    /**
     * «Hoy» como fecha CIVIL del parque, normalizada a un Carbon de
     * medianoche en la zona por defecto de la app. Las fechas de franja se
     * comparan como fechas de calendario (`Y-m-d`), no como instantes: un
     * Carbon de medianoche-Madrid comparado con la medianoche-UTC de una
     * fecha de slot introduciría un sesgo de horas. Por eso se toma el
     * STRING del día del parque y se re-ancla.
     */
    public function today(): Carbon
    {
        return Carbon::parse(DisplayTime::now()->toDateString());
    }

    /** El extremo del horizonte de compra/edición, anclado en el día del parque. */
    public function horizon(): Carbon
    {
        return $this->today()->addMonths(SlotOffer::horizonMonths());
    }

    /**
     * Lista de fechas (Y-m-d) dentro de [$from, $to] que tienen al menos un
     * slot operable para el item (zone match + park open + product window
     * + aforo ≥ seats). Acotada a un rango específico (el del mes visible
     * del calendario) para evitar cargar 6 meses de slots cada render.
     *
     * @return array<int, string>
     */
    public function selectableDates(OrderItem $item, Carbon $from, Carbon $to): array
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $today = $this->today();
        $horizon = $this->horizon();
        $effectiveFrom = $from->copy()->max($today);
        $effectiveTo = $to->copy()->min($horizon);

        if ($effectiveFrom->gt($effectiveTo)) {
            return [];
        }

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->whereBetween('date', [$effectiveFrom->toDateString(), $effectiveTo->toDateString()])
            ->sellableOnline()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $valid = [];
        foreach ($slots->groupBy(fn (Slot $s) => $s->date->toDateString()) as $dateStr => $daySlots) {
            $dateCarbon = Carbon::parse($dateStr);
            if (! $this->schedule->isOpenOn($dateCarbon)) {
                continue;
            }
            $anyOperable = $daySlots->contains(
                fn (Slot $s) => $this->slotMeetsItemRequirements($s, $item)
            );
            if ($anyOperable) {
                $valid[] = $dateStr;
            }
        }

        return $valid;
    }

    /**
     * Horas ofrecibles del día para MOVER el item. Cada entrada:
     *
     *  - `time`       : 'HH:MM:SS'.
     *  - `available`  : int — plazas libres en ese slot (huella propia
     *                   CONTADA, decisión `#173` del origen).
     *  - `is_current` : bool — es la hora del slot ACTUAL del item.
     *
     * La decoración de UI (display 'HH:MM', is_selected) es de la capa de
     * entrega (`ManagesItemCalendar`), no de la oferta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function times(OrderItem $item, string $date): array
    {
        if ($date === '') {
            return [];
        }
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $dateCarbon = Carbon::parse($date);
        $isParkOpen = $this->schedule->isOpenOn($dateCarbon);
        $currentSlot = $item->slot;

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->sellableOnline()
            ->orderBy('start_time')
            ->get();

        $seatsNeeded = (int) $item->seats;
        $entries = [];

        if ($isParkOpen) {
            foreach ($slots as $slot) {
                if (! $this->productWindow->allowsStart($ticketType, $dateCarbon, $slot->start_time)) {
                    continue;
                }
                // #164 + #173 (decisión clienta del origen): el cómputo muestra
                // las plazas REALES libres CONTANDO la huella propia del item —
                // FIDEDIGNO con la lógica de reservas. El slot actual SIEMPRE se
                // incluye en las opciones (mantenerlo es no-op). El excluir la
                // huella propia para crecer/recolocar es de la VALIDACIÓN bajo
                // lock (AFORO-06), no de la oferta.
                $available = $this->displayAvailableFor($slot, $ticketType);
                $isCurrentSlot = $currentSlot !== null && $currentSlot->id === $slot->id;
                if ($available < $seatsNeeded && ! $isCurrentSlot) {
                    continue;
                }
                $entries[] = [
                    'time' => $slot->start_time,
                    'available' => $available,
                    'is_current' => $isCurrentSlot,
                ];
            }
        }

        // Slot actual siempre incluido si la fecha coincide y no estaba en
        // la lista (zone cerrado, parque cerrado, etc.).
        if ($currentSlot !== null
            && $currentSlot->date->toDateString() === $date
            && ! collect($entries)->contains(fn (array $e) => $e['time'] === $currentSlot->start_time)
        ) {
            array_unshift($entries, [
                'time' => $currentSlot->start_time,
                'available' => $seatsNeeded,
                'is_current' => true,
            ]);
        }

        return $entries;
    }

    /**
     * Plazas LIBRES de una franja para MOSTRAR al operador en el slider del
     * modal Gestionar (sub-fase 7.2e.3 pulido #168 del origen):
     *  - Entradas: `SlotAvailability::availableFor`.
     *  - Packs: `PackAvailability::freeGuestSlots` (cupo de invitados
     *    restante; si no hay tope, cae a `availableGuestsFor`).
     *
     * #173 (decisión clienta del origen): el slider es **FIDEDIGNO con la
     * lógica REAL de reservas** — muestra las plazas que un booking vería de
     * verdad, CONTANDO la huella propia del item. Por eso NO se excluye aquí
     * la huella propia: la asimetría con la validación (que sí la excluye
     * para permitir crecer/recolocar el item) se abordará en la fase de
     * gestión/edición de reservas.
     */
    public function displayAvailableFor(Slot $slot, TicketType $ticketType): int
    {
        if ($ticketType->isPack()) {
            return $this->packAvailability->freeGuestSlots($slot, $ticketType)
                ?? $this->packAvailability->availableGuestsFor($slot, $ticketType);
        }

        return $this->slotAvailability->availableFor($slot, $ticketType->duration_min);
    }

    /**
     * ¿El slot $slot cumple los requisitos del $item para ser ofrecible como
     * opción del modal Gestionar? (zone match implícito en la query; este
     * helper aplica producto+aforo).
     */
    private function slotMeetsItemRequirements(Slot $slot, OrderItem $item): bool
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null) {
            return false;
        }
        if (! $this->productWindow->allowsStart($ticketType, $slot->date, $slot->start_time)) {
            return false;
        }
        $seatsNeeded = (int) $item->seats;
        $available = $ticketType->isPack()
            ? $this->packAvailability->availableGuestsFor($slot, $ticketType)
            : $this->slotAvailability->availableFor($slot, $ticketType->duration_min);
        // Sub-fase 7.2e.2bis10 (#164 del origen): el slot ACTUAL del item
        // siempre cumple los requisitos (mantenerlo es no-op, no consume
        // aforo nuevo). El resto se valida con las plazas REALES (sin sumar
        // las del item).
        if ($item->slot && $item->slot->id === $slot->id) {
            return true;
        }

        return $available >= $seatsNeeded;
    }
}
