<?php

namespace App\Filament\Resources\Orders\Pages\Concerns;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Services\OperatingSchedule;
use App\Domain\Booking\Services\ProductAvailability;
use App\Domain\Payments\Services\PaymentSettings;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * El calendario visual del modal Gestionar de `ViewOrder` (sub-fase
 * 7.2e.2bis6, decisión #160 del origen): estado Livewire, wire methods de
 * navegación/selección y composición de la vista (rejilla mensual + chips
 * de horas).
 *
 * Extracción 1 del desmontaje de `ViewOrder`
 * (`docs/specs/desmontar-view-order.md` §4.1, `DECISIONES #170`+): trait
 * sobre la MISMA clase Livewire — NO un componente hijo — para que las
 * properties y los wire methods sigan perteneciendo a `ViewOrder` (75
 * interacciones de test y el runtime del partial dependen de ello, spec
 * §8.8). Es estado de UI: capa de entrega, no dominio.
 *
 * ⚠️ Dependencias hacia la clase que lo compone (se resuelven al aplanar el
 * trait): `$this->record`, `$this->mountedActions`, `$this->resolveItem()`,
 * `$this->selectableDatesInRange()` y `$this->displayAvailableFor()` — las
 * dos últimas son LECTURAS DE DISPONIBILIDAD y se van con la extracción 3
 * (la consulta de re-programación del dominio, spec §4.1·3).
 */
trait ManagesItemCalendar
{
    /**
     * Estado del calendario visual del modal Gestionar (sub-fase 7.2e.2bis6,
     * decisión #160). Properties Livewire públicas que el partial blade del
     * calendario lee y actualiza vía `wire:click` a los métodos del
     * componente (`calendarPrevMonth`, `calendarNextMonth`, `calendarSelectDate`,
     * `calendarSelectTime`).
     *
     *  - `$calendarItemId`        : id del OrderItem operativo (se setea en mountUsing).
     *  - `$calendarMonth`         : 'YYYY-MM' del mes visible.
     *  - `$calendarSelectedDate`  : 'YYYY-MM-DD' del día elegido.
     *  - `$calendarSelectedTime`  : 'HH:MM:SS' de la hora elegida.
     *
     * El handler `executeManageItemSave` lee `$this->calendarSelectedDate`
     * y `$this->calendarSelectedTime` en lugar de los `$data['slot_*']` del
     * form Filament — el calendario reemplaza los Selects.
     */
    public ?int $calendarItemId = null;

    public ?string $calendarMonth = null;

    public ?string $calendarSelectedDate = null;

    public ?string $calendarSelectedTime = null;

    /**
     * Resetea el estado Livewire del calendario al abrir el modal Gestionar
     * para un item específico (sub-fase 7.2e.2bis6, #160).
     *
     * Si el item NO es editable (canEditItem false), igualmente seteamos los
     * valores iniciales para que el calendario muestre la franja actual del
     * item como referencia visual (read-only).
     *
     * Sub-fase 7.2e.2bis9 (#163): se mantiene `public` como API estable
     * para invocación desde closures donde `$this` puede no estar bien
     * bindado al page Livewire — patrón defensivo.
     */
    public function resetCalendarStateForItem(array $arguments): void
    {
        $item = $this->resolveItem($arguments);
        if ($item === null) {
            $this->calendarItemId = null;
            $this->calendarMonth = null;
            $this->calendarSelectedDate = null;
            $this->calendarSelectedTime = null;

            return;
        }

        $slot = $item->slot;
        $today = Carbon::today();
        $slotDate = $slot?->date ? Carbon::parse($slot->date->toDateString()) : null;

        $this->calendarItemId = $item->id;
        // El mes inicial es el del slot actual si está en el rango ofrecible
        // (today..today+horizon); si está en el pasado o fuera de rango,
        // arrancamos en el mes actual para no abrir en un mes vacío.
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $initialMonth = $slotDate !== null && $slotDate->gte($today) && $slotDate->lte($horizon)
            ? $slotDate
            : $today;
        $this->calendarMonth = $initialMonth->format('Y-m');
        $this->calendarSelectedDate = $slot?->date?->toDateString();
        $this->calendarSelectedTime = $slot?->start_time;
    }

    /**
     * Computa todas las variables que el partial blade del calendario
     * necesita para renderizar (sub-fase 7.2e.2bis7, fix de bug).
     *
     * Se invoca en cada render Livewire (closure pasada a `->viewData()`)
     * para que la rejilla refleje el `calendarMonth` actualizado tras un
     * `calendarPrevMonth`/`Next`, y la lista de horas refleje el
     * `calendarSelectedDate` actualizado tras un `calendarSelectDate`.
     *
     * @return array<string, mixed>
     */
    private function buildCalendarViewData(OrderItem $item): array
    {
        /** @var Order $order */
        $order = $this->record;
        $editable = $order->canEditItem($item);
        $blockedReason = $editable ? null : $order->editItemBlockedReason($item);

        // Sub-fase 7.2e.2bis9 (#163, fix render inicial): cuando las
        // properties Livewire están null (mountUsing rebindeado al Action
        // no las persistió), derivamos los DEFAULTS visuales DIRECTAMENTE
        // del slot del item — sin mutar las properties (eso lo hace
        // ensureCalendarStateInitialized en los wire methods cuando hay
        // interacción del usuario). El render inicial muestra el mes y
        // hora correctos del item; tras un click, los wire methods toman
        // el relevo y persisten state en el snapshot.
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $itemSlotDate = $item->slot?->date ? Carbon::parse($item->slot->date->toDateString()) : null;
        $defaultMonth = $itemSlotDate !== null && $itemSlotDate->gte($today) && $itemSlotDate->lte($horizon)
            ? $itemSlotDate->copy()->startOfMonth()
            : $today->copy()->startOfMonth();

        $monthCarbon = $this->calendarMonth
            ? Carbon::parse($this->calendarMonth.'-01')
            : $defaultMonth;
        $effectiveSelectedDate = $this->calendarSelectedDate ?? $item->slot?->date?->toDateString();
        $effectiveSelectedTime = $this->calendarSelectedTime ?? $item->slot?->start_time;

        return [
            'item' => $item,
            'record' => $order,
            'editable' => $editable,
            'blockedReason' => $blockedReason,
            'matrix' => $this->calendarMatrixForItemWithSelection(
                $item,
                $monthCarbon,
                $effectiveSelectedDate,
            ),
            'monthYmd' => $monthCarbon->format('Y-m'),
            'monthLabel' => $this->calendarMonthLabel($monthCarbon->format('Y-m')),
            'selectedDate' => $effectiveSelectedDate,
            'selectedTime' => $effectiveSelectedTime,
            'times' => $effectiveSelectedDate
                ? $this->calendarTimesForItemWithSelection($item, $effectiveSelectedDate, $effectiveSelectedTime)
                : [],
        ];
    }

    /**
     * Construye la rejilla del calendario para el mes visible: lunes a
     * domingo, 5 o 6 semanas que cubren el mes completo + días vecinos del
     * mes anterior/siguiente. Acepta el `$selectedDate` explícitamente (en
     * lugar de leerlo de `$this->calendarSelectedDate`): necesario para el
     * render inicial cuando las properties Livewire pueden estar null pero
     * queremos marcar como "selected" el slot actual del item.
     *
     * Cada celda incluye metadata para que el blade pueda pintar estado
     * visual sin lógica adicional:
     *
     *  - `date`         : 'YYYY-MM-DD'.
     *  - `day`          : número del día (1-31).
     *  - `in_month`     : bool — true si el día pertenece al mes visible
     *                     (el resto son días del mes anterior/siguiente que
     *                     completan las semanas; se renderizan atenuados).
     *  - `selectable`   : bool — el día tiene al menos un slot operable.
     *  - `is_current`   : bool — es el día del slot ACTUAL del item.
     *  - `is_selected`  : bool — es el día actualmente seleccionado en el
     *                     calendario (puede coincidir con `is_current` o no).
     *  - `is_past`      : bool — el día ya pasó (se renderiza disabled).
     *  - `is_beyond_horizon` : bool — más allá de today+horizon (disabled).
     *
     * @return array<int, array<int, array<string, mixed>>> semanas × días
     */
    private function calendarMatrixForItemWithSelection(OrderItem $item, Carbon $month, ?string $selectedDate): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());

        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $selectableDates = $this->selectableDatesInRange($item, $start, $end);

        // #173: el heatmap de saturación (#164) se retiró — la clienta no lo
        // necesita; ya no se computa el mapa ni se pasa al blade.
        $currentSlotDate = $item->slot?->date?->toDateString();

        $weeks = [];
        $week = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var Carbon $day */
            $ymd = $day->toDateString();
            $week[] = [
                'date' => $ymd,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'selectable' => in_array($ymd, $selectableDates, true),
                'is_current' => $ymd === $currentSlotDate,
                'is_selected' => $ymd === $selectedDate,
                'is_past' => $day->lt($today),
                'is_beyond_horizon' => $day->gt($horizon),
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /**
     * Variante de `calendarTimesForItem` con `$selectedTime` explícito —
     * paralelo a `calendarMatrixForItemWithSelection`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calendarTimesForItemWithSelection(OrderItem $item, string $date, ?string $selectedTime): array
    {
        if ($date === '') {
            return [];
        }
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $dateCarbon = Carbon::parse($date);
        $isParkOpen = app(OperatingSchedule::class)->isOpenOn($dateCarbon);
        $productWindow = app(ProductAvailability::class);
        $currentSlot = $item->slot;

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->sellableOnline()
            ->orderBy('start_time')
            ->get();

        $isPack = $ticketType->isPack();
        $seatsNeeded = (int) $item->seats;
        $entries = [];

        if ($isParkOpen) {
            foreach ($slots as $slot) {
                if (! $productWindow->allowsStart($ticketType, $dateCarbon, $slot->start_time)) {
                    continue;
                }
                // #164 + #173 (decisión clienta): el cómputo muestra las plazas
                // REALES libres CONTANDO la huella propia del item — FIDEDIGNO con
                // la lógica de reservas: un cumpleaños ocupa su ventana montaje+
                // fiesta+limpieza y las fiestas que solapan comparten cupo (misma
                // lógica que la compra pública). El slot actual SIEMPRE se incluye
                // en las opciones (mantenerlo es no-op). El excluir la huella propia
                // para crecer/recolocar se hará en la gestión de reservas futura.
                $available = $this->displayAvailableFor($slot, $ticketType);
                $isCurrentSlot = $currentSlot !== null && $currentSlot->id === $slot->id;
                if ($available < $seatsNeeded && ! $isCurrentSlot) {
                    continue;
                }
                $entries[] = [
                    'time' => $slot->start_time,
                    'display' => Str::substr($slot->start_time, 0, 5),
                    'available' => $available,
                    'is_current' => $isCurrentSlot,
                    'is_selected' => $slot->start_time === $selectedTime,
                ];
            }
        }

        if ($currentSlot !== null
            && $currentSlot->date->toDateString() === $date
            && ! collect($entries)->contains(fn (array $e) => $e['time'] === $currentSlot->start_time)
        ) {
            array_unshift($entries, [
                'time' => $currentSlot->start_time,
                'display' => Str::substr($currentSlot->start_time, 0, 5),
                'available' => $seatsNeeded,
                'is_current' => true,
                'is_selected' => $currentSlot->start_time === $selectedTime,
            ]);
        }

        return $entries;
    }

    /**
     * Lista de horas del día seleccionado en el calendario para el item
     * dado. Cada entrada incluye metadata para el blade (chips):
     *
     *  - `time`         : 'HH:MM:SS'.
     *  - `display`      : 'HH:MM' (lo que ve el operador).
     *  - `available`    : int — plazas libres en ese slot.
     *  - `is_current`   : bool — es la hora del slot ACTUAL del item.
     *  - `is_selected`  : bool — es la hora actualmente elegida.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calendarTimesForItem(OrderItem $item, string $date): array
    {
        if ($date === '') {
            return [];
        }
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $dateCarbon = Carbon::parse($date);
        $isParkOpen = app(OperatingSchedule::class)->isOpenOn($dateCarbon);
        $productWindow = app(ProductAvailability::class);
        $currentSlot = $item->slot;
        $selectedTime = $this->calendarSelectedTime;

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->sellableOnline()
            ->orderBy('start_time')
            ->get();

        $isPack = $ticketType->isPack();
        $seatsNeeded = (int) $item->seats;
        $entries = [];

        if ($isParkOpen) {
            foreach ($slots as $slot) {
                if (! $productWindow->allowsStart($ticketType, $dateCarbon, $slot->start_time)) {
                    continue;
                }
                // #164 + #173 (decisión clienta): el cómputo muestra las plazas
                // REALES libres CONTANDO la huella propia del item — FIDEDIGNO con
                // la lógica de reservas: un cumpleaños ocupa su ventana montaje+
                // fiesta+limpieza y las fiestas que solapan comparten cupo (misma
                // lógica que la compra pública). El slot actual SIEMPRE se incluye
                // en las opciones (mantenerlo es no-op). El excluir la huella propia
                // para crecer/recolocar se hará en la gestión de reservas futura.
                $available = $this->displayAvailableFor($slot, $ticketType);
                $isCurrentSlot = $currentSlot !== null && $currentSlot->id === $slot->id;
                if ($available < $seatsNeeded && ! $isCurrentSlot) {
                    continue;
                }
                $entries[] = [
                    'time' => $slot->start_time,
                    'display' => Str::substr($slot->start_time, 0, 5),
                    'available' => $available,
                    'is_current' => $isCurrentSlot,
                    'is_selected' => $slot->start_time === $selectedTime,
                ];
            }
        }

        // Slot actual siempre incluido si la fecha coincide y no estaba en
        // la lista (zone cerrado, etc.).
        if ($currentSlot !== null
            && $currentSlot->date->toDateString() === $date
            && ! collect($entries)->contains(fn (array $e) => $e['time'] === $currentSlot->start_time)
        ) {
            array_unshift($entries, [
                'time' => $currentSlot->start_time,
                'display' => Str::substr($currentSlot->start_time, 0, 5),
                'available' => $seatsNeeded,
                'is_current' => true,
                'is_selected' => $currentSlot->start_time === $selectedTime,
            ]);
        }

        return $entries;
    }

    /**
     * Etiqueta del mes visible para el header del calendario (ej. "Junio 2026").
     */
    private function calendarMonthLabel(?string $monthStr): string
    {
        if ($monthStr === null) {
            return '';
        }

        return Str::ucfirst(
            Carbon::parse($monthStr.'-01')
                ->locale(app()->getLocale())
                ->isoFormat('MMMM YYYY')
        );
    }

    // ─── Wire methods del calendario ─────────────────────────────────────

    /**
     * Lazy-initialization defensiva del estado del calendario desde el
     * Action montado (sub-fase 7.2e.2bis9, #163). Cubre el caso donde
     * `mountUsing` no se invocó (test directo) o donde las properties
     * Livewire se perdieron entre requests. Lee el `item_id` desde
     * `$this->mountedActions[0]['arguments']['item']` que Filament SÍ
     * persiste de forma fiable en el snapshot.
     */
    private function ensureCalendarStateInitialized(): void
    {
        if ($this->calendarMonth !== null) {
            return;
        }
        $itemId = $this->mountedActions[0]['arguments']['item'] ?? null;
        if (! $itemId) {
            return;
        }
        $this->resetCalendarStateForItem(['item' => $itemId]);
    }

    /**
     * Vuelve al mes que contiene el slot ACTUAL del item — atajo rápido para
     * el operador que ha navegado lejos y quiere volver a la "vista por
     * defecto" del item. Sub-fase 7.2e.2bis10 (#164).
     */
    public function calendarGoToItemMonth(): void
    {
        $this->ensureCalendarStateInitialized();
        $itemId = $this->mountedActions[0]['arguments']['item'] ?? null;
        if (! $itemId) {
            return;
        }
        $item = OrderItem::with('slot')->find($itemId);
        if ($item === null) {
            return;
        }
        $slotDate = $item->slot?->date ? Carbon::parse($item->slot->date->toDateString()) : Carbon::today();
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $target = $slotDate->gte($today) && $slotDate->lte($horizon) ? $slotDate : $today;
        $this->calendarMonth = $target->format('Y-m');
    }

    /**
     * Navega al mes anterior. Respeta el límite inferior (today).
     */
    public function calendarPrevMonth(): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarMonth === null) {
            return;
        }
        $month = Carbon::parse($this->calendarMonth.'-01');
        $today = Carbon::today()->startOfMonth();
        $prev = $month->copy()->subMonth()->startOfMonth();
        if ($prev->lt($today)) {
            return;
        }
        $this->calendarMonth = $prev->format('Y-m');
    }

    /**
     * Navega al mes siguiente. Respeta el límite superior (today+horizon).
     */
    public function calendarNextMonth(): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarMonth === null) {
            return;
        }
        $month = Carbon::parse($this->calendarMonth.'-01');
        $horizonMonth = Carbon::today()
            ->addMonths(PaymentSettings::purchaseHorizonMonths())
            ->startOfMonth();
        $next = $month->copy()->addMonth()->startOfMonth();
        if ($next->gt($horizonMonth)) {
            return;
        }
        $this->calendarMonth = $next->format('Y-m');
    }

    /**
     * Selecciona un día del calendario. Defense in depth: el wire:click del
     * partial filtra `selectable=true`, este método revalida en backend para
     * proteger contra manipulación JS.
     */
    public function calendarSelectDate(string $date): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarItemId === null) {
            return;
        }
        $item = OrderItem::with('ticketType', 'slot')->find($this->calendarItemId);
        if ($item === null) {
            return;
        }
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());

        try {
            $candidate = Carbon::parse($date);
        } catch (\Exception) {
            return;
        }
        if ($candidate->lt($today) || $candidate->gt($horizon)) {
            return;
        }

        // El día debe estar en la lista de selectables del mes actual.
        $monthStart = Carbon::parse($this->calendarMonth.'-01')->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $monthEnd = Carbon::parse($this->calendarMonth.'-01')->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $valid = $this->selectableDatesInRange($item, $monthStart, $monthEnd);
        if (! in_array($date, $valid, true)) {
            return;
        }

        $this->calendarSelectedDate = $date;
        // Reset de hora: si la nueva fecha incluye la hora actual, la
        // mantenemos; si no, la limpiamos para forzar al operador a elegir.
        $times = $this->calendarTimesForItem($item, $date);
        if (! collect($times)->contains(fn (array $e) => $e['time'] === $this->calendarSelectedTime)) {
            $this->calendarSelectedTime = null;
        }
    }

    /**
     * Selecciona una hora del calendario. Revalida que pertenezca a las
     * horas ofrecibles del día actualmente seleccionado.
     */
    public function calendarSelectTime(string $time): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarItemId === null || $this->calendarSelectedDate === null) {
            return;
        }
        $item = OrderItem::with('ticketType', 'slot')->find($this->calendarItemId);
        if ($item === null) {
            return;
        }
        $times = $this->calendarTimesForItem($item, $this->calendarSelectedDate);
        if (! collect($times)->contains(fn (array $e) => $e['time'] === $time)) {
            return;
        }
        $this->calendarSelectedTime = $time;
    }
}
