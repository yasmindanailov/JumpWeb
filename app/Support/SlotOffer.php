<?php

namespace App\Support;

use App\Domain\Platform\Services\DisplayTime;
use App\Models\Slot;
use App\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fuente ÚNICA de "qué fechas y horas se pueden OFRECER" de un producto (entrada/pack).
 *
 * Antes (bug Fase 1, auditoría 2026-06-12) la compra pública (`Livewire\Tickets\Purchase`) y el
 * pedido manual del panel (`Filament\Pages\CreateManualOrderPage`) derivaban su oferta de fuentes
 * DISTINTAS: la web de las franjas materializadas (`slots`) + la ventana viva del día; el panel
 * abría todo el rango aritmético `hoy..hoy+horizonte` SIN mirar franjas ni la ventana del día. Por
 * eso "no correspondían" en fechas/horas. Este servicio centraliza la lógica CORRECTA de la web
 * para que AMBAS superficies la consuman y no puedan volver a divergir.
 *
 * Reglas (idénticas a las que ya aplicaba la web):
 *  - Solo franjas `sellableOnline()` (online_sales_open=true, status≠closed) de la zona del producto.
 *  - Dentro del horizonte de venta [hoy, hoy+`PaymentSettings::purchaseHorizonMonths`] (zona del parque).
 *  - Dentro de la ventana viva del día del producto (`ProductAvailability::allowsStart`): una
 *    `special_date` que cierra/recorta un día filtra la oferta EN VIVO (sin regenerar franjas).
 *  - Horas de PACK: solo las que tienen cupo para al menos `min_qty` invitados (un cumpleaños bajo
 *    su mínimo no es reservable). Horas de ENTRADA: todas las de la ventana; las llenas se marcan
 *    `sellable=false` (se muestran deshabilitadas, no se ocultan).
 *
 * "Hoy" se calcula en la zona OPERATIVA del parque (`DisplayTime::today()`), no en UTC.
 */
class SlotOffer
{
    public function __construct(
        private ProductAvailability $productWindow,
        private SlotAvailability $slotAvailability,
        private PackAvailability $packAvailability,
    ) {}

    /**
     * Franjas ofrecibles del producto: `sellableOnline` + zona + horizonte + ventana del día.
     * Si `$type` es null (mes inicial del calendario público antes de elegir producto) no filtra por
     * zona ni ventana: devuelve todas las franjas vendibles del horizonte (igual que hacía la web).
     *
     * @return Collection<int, Slot>
     */
    public function offeredSlots(?TicketType $type): Collection
    {
        $now = DisplayTime::now();
        $todayStr = $now->toDateString();
        $to = $now->copy()->addMonths(PaymentSettings::purchaseHorizonMonths())->toDateString();

        $query = Slot::query()
            ->whereBetween('date', [$todayStr, $to])
            ->sellableOnline()
            ->when($type?->isPack(), fn ($q) => $q->with('zone')) // packs leen el cupo por zona
            ->orderBy('date')
            ->orderBy('start_time');

        if ($type && $type->zone_id) {
            $query->where('zone_id', $type->zone_id);
        }

        return $query->get()->filter(function (Slot $s) use ($type, $now) {
            // Corte intra-día (floor, auditoría Fase 1): una franja de HOY cuya hora de inicio YA
            // pasó no se ofrece (p. ej. las 10:00 cuando son las 10:31). Aplica siempre. Fuente ÚNICA
            // (`passesIntradayFloor`) compartida con el backstop del checkout (`OrderCreator`).
            if (! self::passesIntradayFloor($s->date->toDateString(), (string) $s->start_time, $now)) {
                return false;
            }

            if ($type === null) {
                return true;
            }

            // Ventana viva del día (special_dates/horario) + ANTELACIÓN MÍNIMA de reserva del
            // producto (días de calendario u horas rodantes). Ambas comparten esta única fuente.
            return $this->productWindow->allowsStart($type, $s->date, $s->start_time)
                && $type->meetsMinAdvance($s->date->toDateString(), $s->start_time, $now);
        })->values();
    }

    /**
     * Corte intra-día (floor, auditoría Fase 1): ¿la franja `(date, startTime)` sigue siendo
     * ofrecible respecto a "ahora"? Una franja de HOY cuya hora de inicio YA pasó NO lo es; las de
     * días futuros, siempre. Fuente ÚNICA del corte para que la OFERTA (`offeredSlots`, web+panel) y
     * el BACKSTOP del checkout (`OrderCreator`) no diverjan. "Ahora" se pasa explícito en la zona
     * operativa del parque (`DisplayTime::now()`); ambos `date`/`startTime` en formato canónico de BD
     * (`Y-m-d` / `H:i:s`), comparables lexicográficamente.
     */
    public static function passesIntradayFloor(string $date, string $startTime, Carbon $now): bool
    {
        if ($date !== $now->toDateString()) {
            return true;
        }

        return substr($startTime, 0, 8) >= $now->format('H:i:s');
    }

    /**
     * Fechas (Y-m-d, ordenadas) con al menos una franja ofrecible para el producto.
     *
     * @return array<int, string>
     */
    public function offerableDates(TicketType $type): array
    {
        return $this->offeredSlots($type)
            ->map(fn (Slot $s) => $s->date->toDateString())
            ->unique()->values()->all();
    }

    /**
     * Horas ofrecibles para un producto en una fecha, con su disponibilidad para mostrar.
     * Misma semántica que la web: packs filtrados a cupo ≥ min_qty; entradas todas las de la ventana
     * (las llenas con `sellable=false`). El llamador pasa los ocupantes provisionales de su cesta.
     *
     * @param  array<int, array{entry_start:string, duration_min:int|null, seats:int}>  $cartOccupants
     * @param  array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>  $cartPackOccupants
     * @return array<string, array{available:int, sellable:bool}>
     */
    public function offerableTimes(TicketType $type, string $date, array $cartOccupants = [], array $cartPackOccupants = []): array
    {
        $slots = $this->offeredSlots($type)
            ->filter(fn (Slot $s) => $s->date->toDateString() === $date);

        $out = [];

        if ($type->isPack()) {
            $min = max(1, (int) ($type->min_qty ?? 1));
            foreach ($slots as $slot) {
                $free = $this->packAvailability->availableGuestsFor($slot, $type, $cartPackOccupants);
                if ($free < $min) {
                    continue; // bajo el mínimo de invitados: no reservable
                }
                $display = $this->packAvailability->freeGuestSlots($slot, $type, $cartPackOccupants) ?? $free;
                $out[(string) $slot->start_time] = ['available' => (int) $display, 'sellable' => true];
            }

            return $out;
        }

        foreach ($slots as $slot) {
            $available = $this->slotAvailability->availableFor($slot, $type->duration_min, $cartOccupants);
            $out[(string) $slot->start_time] = ['available' => (int) $available, 'sellable' => $available > 0];
        }

        return $out;
    }
}
