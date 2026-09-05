@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    /**
     * Sub-fase 7.2e.2bis7 (fix de bug #160) — Calendario visual del Tab 1
     * "Producto y reserva" del modal Gestionar.
     *
     * El partial recibe TODAS las variables computadas desde
     * `ViewOrder::buildCalendarViewData($item)` via la closure de
     * `ViewComponent::make(...)->viewData(...)`. NO se usa `$this`
     * porque dentro del closure de viewData, `$this` referencia al
     * View object, no al page Livewire. Las llamadas wire:click sí
     * funcionan porque se procesan en el contexto del component padre.
     *
     * Variables esperadas:
     *
     * @var \App\Domain\Booking\Models\OrderItem $item
     * @var \App\Domain\Booking\Models\Order $record
     * @var bool $editable
     * @var string|null $blockedReason
     * @var array<int, array<int, array<string, mixed>>> $matrix  semanas × días
     * @var string $monthLabel  "Junio 2026"
     * @var string|null $selectedDate  'YYYY-MM-DD'
     * @var string|null $selectedTime  'HH:MM:SS'
     * @var array<int, array<string, mixed>> $times  cada hora con metadata
     */
    $weekdayHeaders = collect(range(0, 6))
        ->map(fn (int $i) => Str::ucfirst(
            Carbon::now()
                ->startOfWeek(Carbon::MONDAY)
                ->addDays($i)
                ->locale(app()->getLocale())
                ->isoFormat('dd')
        ))
        ->all();
@endphp

@if ($blockedReason !== null)
    @include('filament.orders.partials.manage-item-blocked-banner', ['reason' => $blockedReason])
@endif

{{-- Sub-fase 7.2e.2bis8 (decisión #162) — Fix bug interacción wire:click
     dentro del schema de un Filament Action.

     CAUSA: dentro de `Schemas\Components\View::make()->viewData()`, los
     botones con `wire:click="metodo"` directos NO se procesan como un
     wire request del componente padre (Livewire scope del page) — Filament
     intercepta el evento via Alpine. La solución canónica Filament 5 +
     Livewire 3 es invocar via Alpine `$wire.call('metodo')` que llama
     EXPLÍCITAMENTE al método del componente raíz Livewire.

     Adicionalmente:
     - `wire:key="manage-calendar-{$calendarMonth}-{$selectedDate}-{$selectedTime}"`
       fuerza a Livewire a re-renderizar el DOM cuando cualquiera de las
       3 properties que afectan al render cambia, evitando el caso donde
       el partial se queda "congelado" con el HTML del primer render.

     Sub-fase 7.2e.2bis7 (decisión #161): calendario compacto.
     El wrapper `mx-auto max-w-sm` centra y limita el ancho a ~24rem para
     que no ocupe todo el modal en desktop. Spacing/typography reducidos
     manteniendo legibilidad. --}}
<div
    class="manage-calendar space-y-3"
    wire:key="manage-calendar-{{ $monthLabel }}-{{ $selectedDate ?? 'none' }}-{{ $selectedTime ?? 'none' }}"
>
    {{-- P5: dos columnas en md+ — calendario a la IZQUIERDA, franjas horarias a la DERECHA (lista
         vertical con scroll). En móvil se apilan (calendario arriba, franjas debajo). --}}
    <div class="grid gap-4 md:grid-cols-2 md:items-start">
    {{-- ── Columna izquierda: calendario ── --}}
    <div class="space-y-3">
    {{-- Header: navegación mensual + botón volver al mes del slot actual.
         Sub-fase 7.2e.2bis10: nuevo botón "Hoy" estilo link sutil debajo del
         label del mes que vuelve al mes que contiene el slot actual del item.
         Solo aparece si el mes mostrado NO es el del slot actual (para no
         saturar visualmente cuando ya estás en el mes correcto). --}}
    <div class="flex items-center justify-between gap-2">
        <button
            type="button"
            x-data
            x-on:click.prevent="$wire.call('calendarPrevMonth')"
            class="inline-flex items-center justify-center rounded-md bg-white p-1.5 text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5"
            aria-label="{{ __('admin.orders.manage_item.calendar_prev') }}"
        >
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
            </svg>
        </button>

        <div class="flex flex-col items-center">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ $monthLabel }}
            </h3>
            @php
                $itemMonth = $item->slot?->date?->format('Y-m');
                $showJumpToCurrent = $itemMonth !== null && ($monthYmd ?? null) !== $itemMonth;
            @endphp
            @if ($showJumpToCurrent)
                <button
                    type="button"
                    x-data
                    x-on:click.prevent="$wire.call('calendarGoToItemMonth')"
                    class="mt-0.5 text-[10px] font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    {{ __('admin.orders.manage_item.calendar_back_to_current') }}
                </button>
            @endif
        </div>

        <button
            type="button"
            x-data
            x-on:click.prevent="$wire.call('calendarNextMonth')"
            class="inline-flex items-center justify-center rounded-md bg-white p-1.5 text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5"
            aria-label="{{ __('admin.orders.manage_item.calendar_next') }}"
        >
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
        </button>
    </div>

    {{-- Cabeceras de días de la semana (lun-dom) --}}
    <div class="grid grid-cols-7 gap-0.5 text-center text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
        @foreach ($weekdayHeaders as $h)
            <div>{{ $h }}</div>
        @endforeach
    </div>

    {{-- Grid del mes (celdas más pequeñas: aspect-square con texto xs) --}}
    @if (count($matrix) === 0)
        <div class="rounded-lg bg-gray-50 p-4 text-xs text-gray-500 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
            {{ __('admin.orders.manage_item.calendar_empty_month') }}
        </div>
    @else
        <div class="space-y-0.5">
            @foreach ($matrix as $week)
                <div class="grid grid-cols-7 gap-0.5">
                    @foreach ($week as $cell)
                        @php
                            // #172: los días de RELLENO del mes vecino
                            // (in_month=false) son inertes — ni seleccionables
                            // ni con punto de disponibilidad, aunque su fecha
                            // caiga en una franja abierta. Antes parecían vacíos
                            // pero mostraban punto verde y eran un <button>.
                            $clickable = $editable && $cell['selectable'] && $cell['in_month'];
                            $cellClasses = collect([
                                'aspect-square flex flex-col items-center justify-center rounded-md text-xs transition relative',
                            ]);
                            if (! $cell['in_month']) {
                                $cellClasses->push('text-gray-300 dark:text-gray-600');
                            } elseif ($cell['is_past'] || $cell['is_beyond_horizon']) {
                                $cellClasses->push('text-gray-300 dark:text-gray-600 cursor-not-allowed');
                            } elseif ($cell['is_selected']) {
                                $cellClasses->push('bg-primary-600 text-white font-semibold shadow-sm ring-1 ring-primary-700');
                            } elseif ($cell['is_current']) {
                                $cellClasses->push('bg-amber-100 text-amber-800 font-semibold ring-1 ring-amber-600/30 dark:bg-amber-400/15 dark:text-amber-300 dark:ring-amber-400/40');
                            } elseif ($cell['selectable']) {
                                $cellClasses->push('bg-white text-gray-900 ring-1 ring-gray-200 hover:bg-gray-50 cursor-pointer dark:bg-gray-900 dark:text-gray-100 dark:ring-white/10 dark:hover:bg-white/5');
                            } else {
                                $cellClasses->push('text-gray-400 dark:text-gray-500 line-through cursor-not-allowed');
                            }
                        @endphp
                        {{-- #173: se eliminó el punto de saturación (heatmap) de
                             las celdas — la clienta no necesita ese feature ahora.
                             Se conserva solo el punto del slot "actual". --}}
                        @if ($clickable)
                            <button
                                type="button"
                                x-data
                                x-on:click.prevent="$wire.call('calendarSelectDate', @js($cell['date']))"
                                class="{{ $cellClasses->implode(' ') }}"
                                aria-label="{{ Carbon::parse($cell['date'])->locale(app()->getLocale())->isoFormat('ddd D MMM') }}"
                            >
                                <span>{{ $cell['day'] }}</span>
                                @if ($cell['is_current'] && ! $cell['is_selected'])
                                    <span class="absolute bottom-1 inline-block h-1 w-1 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                @endif
                            </button>
                        @else
                            <div class="{{ $cellClasses->implode(' ') }}" aria-disabled="true">
                                <span>{{ $cell['day'] }}</span>
                                @if ($cell['is_current'])
                                    <span class="absolute bottom-1 inline-block h-1 w-1 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    {{-- Leyenda visual compacta (#173: quitados los 3 puntos de saturación). --}}
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1">
            <span class="inline-block h-2.5 w-2.5 rounded bg-primary-600 ring-1 ring-primary-700"></span>
            {{ __('admin.orders.manage_item.calendar_legend_selected') }}
        </span>
        <span class="inline-flex items-center gap-1">
            <span class="inline-block h-2.5 w-2.5 rounded bg-amber-100 ring-1 ring-amber-600/30"></span>
            {{ __('admin.orders.manage_item.calendar_legend_current') }}
        </span>
    </div>

    </div>{{-- ── /columna izquierda (calendario) ── --}}

        {{-- ── Columna derecha: franjas horarias (P5: lista VERTICAL con scroll, sustituye al
             carrusel horizontal). En md+ separada por un borde izquierdo. --}}
        <div class="space-y-2 md:border-l md:border-gray-200 md:pl-4 dark:md:border-white/10">
            @if ($selectedDate)
                <h4 class="text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ __('admin.orders.manage_item.times_for_day', [
                        'date' => Str::ucfirst(Carbon::parse($selectedDate)->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY')),
                    ]) }}
                </h4>

                @if (count($times) === 0)
                    <p class="text-xs italic text-gray-500 dark:text-gray-400">
                        {{ __('admin.orders.manage_item.no_times_available') }}
                    </p>
                @else
                    {{-- P5: franjas en columna VERTICAL con scroll. Al abrir, lleva a la vista la franja
                         seleccionada (coincide con la actual del item). --}}
                    <div
                        x-data
                        x-init="$nextTick(() => $el.querySelector('[data-selected=true]')?.scrollIntoView({ block: 'nearest' }))"
                        class="max-h-72 space-y-1.5 overflow-y-auto pr-1"
                    >
                        @foreach ($times as $t)
                            @php
                                $isLowAvailability = $t['available'] > 0 && $t['available'] <= 3;
                                $chipClasses = collect([
                                    'flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-sm font-medium ring-1 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:focus-visible:ring-primary-400',
                                ]);
                                if ($t['is_selected']) {
                                    $chipClasses->push('bg-primary-600 text-white ring-primary-700 shadow-sm');
                                } elseif ($t['is_current']) {
                                    $chipClasses->push('bg-amber-50 text-amber-800 ring-amber-600/30 hover:bg-amber-100 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30');
                                } elseif ($isLowAvailability) {
                                    $chipClasses->push('bg-orange-50 text-orange-800 ring-orange-600/30 hover:bg-orange-100 dark:bg-orange-400/10 dark:text-orange-300 dark:ring-orange-400/30');
                                } else {
                                    $chipClasses->push('bg-green-50 text-green-800 ring-green-600/30 hover:bg-green-100 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30');
                                }
                            @endphp
                            @if ($editable)
                                <button
                                    type="button"
                                    x-data
                                    x-on:click.prevent="$wire.call('calendarSelectTime', @js($t['time']))"
                                    aria-current="{{ $t['is_selected'] ? 'true' : 'false' }}"
                                    data-selected="{{ $t['is_selected'] ? 'true' : 'false' }}"
                                    class="{{ $chipClasses->implode(' ') }}"
                                >
                                    <span>{{ $t['display'] }}@if ($t['is_current'] && ! $t['is_selected']) <span class="text-xs opacity-75">{{ __('admin.orders.manage_item.current_marker') }}</span>@endif</span>
                                    <span class="whitespace-nowrap text-xs">{{ trans_choice('admin.orders.manage_item.seats_available', $t['available'], ['count' => $t['available']]) }}</span>
                                </button>
                            @else
                                <div class="{{ $chipClasses->implode(' ') }} cursor-not-allowed opacity-75" aria-disabled="true" data-selected="{{ $t['is_selected'] ? 'true' : 'false' }}">
                                    <span>{{ $t['display'] }}@if ($t['is_current'] && ! $t['is_selected']) <span class="text-xs opacity-75">{{ __('admin.orders.manage_item.current_marker') }}</span>@endif</span>
                                    <span class="whitespace-nowrap text-xs">{{ trans_choice('admin.orders.manage_item.seats_available', $t['available'], ['count' => $t['available']]) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            @else
                {{-- Sin fecha elegida aún: invitación a elegir una en el calendario de la izquierda. --}}
                <p class="text-xs italic text-gray-500 dark:text-gray-400">
                    {{ __('admin.orders.manage_item.pick_a_date') }}
                </p>
            @endif
        </div>{{-- ── /columna derecha (franjas) ── --}}
    </div>{{-- ── /grid 2 columnas ── --}}

    {{-- Confirmación visual de la selección actual (compacta) --}}
    @if ($selectedDate && $selectedTime)
        <div class="rounded-lg bg-primary-50 px-3 py-2 text-xs text-primary-900 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30">
            <p class="flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>
                    {{ __('admin.orders.manage_item.selection_summary', [
                        'date' => Str::ucfirst(Carbon::parse($selectedDate)->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY')),
                        'time' => Str::substr($selectedTime, 0, 5),
                    ]) }}
                </span>
            </p>
        </div>
    @endif

    {{-- **Lo que el día elegido le hace a los complementos** (`#417`, `[DECIDIDO owner]`).

         ⚠️ Vive en su PROPIO partial y no en línea aquí, por una razón de verificación: el contenido
         de un modal de Filament **no aparece en el HTML del componente** —medido: ni el calendario ni
         su resumen salen en `->html()`—, así que un `assertSee` sobre la página pasa en VACÍO (la
         trampa de `#161`). Suelto se puede renderizar y aseverar de verdad. --}}
    @if (! empty($addonDatePlan))
        @include('filament.orders.partials.addon-date-notice', [
            'plan' => $addonDatePlan,
            'currency' => $record->currency ?? 'EUR',
        ])
    @endif
</div>
