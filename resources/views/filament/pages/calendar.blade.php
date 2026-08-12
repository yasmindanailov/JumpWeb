@php
    use App\Domain\Booking\Models\Zone;

    // Deep-link desde la card de un pedido (#179): `?date=YYYY-MM-DD` abre el
    // calendario en ESE día (vista de día). Validado (regex estricta) para no
    // inyectar nada arbitrario en la config JS.
    $requestedDate = request()->query('date');
    $initialDate = (is_string($requestedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate))
        ? $requestedDate
        : null;

    // Config para el componente Alpine `jjCalendar`.
    $calendarConfig = [
        'feedUrl' => route('admin.calendario.eventos'),
        'locale' => app()->getLocale() === 'zh_CN' ? 'zh-cn' : 'es',
        'initialView' => $initialDate ? 'timeGridDay' : 'dayGridMonth',
        'initialFilter' => 'all',
        'initialDate' => $initialDate,
    ];

    // Leyenda de zonas (color = identidad de la zona, #148). Solo las que tienen
    // color asignado; el resto cae al neutro en el calendario.
    $legendZones = Zone::query()->whereNotNull('color')->orderBy('id')->get(['name', 'color']);
@endphp

<x-filament-panels::page>
    <div
        x-data="jjCalendar(@js($calendarConfig))"
        wire:key="jj-calendar-root"
        class="space-y-4"
    >
        {{-- Barra de filtros (la leyenda baja al pie del calendario, plegable). --}}
        <div class="flex flex-wrap items-center gap-3">
            {{-- Filtro: ambos / entradas / packs (segmented pills) --}}
            <div class="inline-flex gap-1 rounded-full border border-gray-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-white/5">
                @foreach ([
                    'all' => __('admin.calendar.filter.all'),
                    'entry' => __('admin.calendar.filter.entries'),
                    'pack' => __('admin.calendar.filter.packs'),
                ] as $value => $label)
                    <button
                        type="button"
                        x-on:click="setFilter(@js($value))"
                        x-bind:class="filter === @js($value)
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10'"
                        class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Nodo de montaje de FullCalendar. `wire:ignore`: FullCalendar es dueño
             de su DOM y Livewire no debe morphearlo. --}}
        <div
            x-ref="calendar"
            wire:ignore
            class="jj-calendar fi-section rounded-2xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6"
        ></div>

        {{-- Leyenda del calendario (decisión clienta 2026-06-13): BAJO el calendario y PLEGABLE
             (plegada por defecto) para no recargar la vista; el empleado la abre cuando la necesita.
             El estado vive en un scope Alpine LOCAL (`legendOpen`), independiente de `jjCalendar`.
             El color de la ZONA va como línea vertical lateral del card → el swatch lo imita con
             una barrita. El filtro de arriba separa entradas/packs. --}}
        <div x-data="{ legendOpen: false }" class="text-xs">
            <button
                type="button"
                x-on:click="legendOpen = ! legendOpen"
                x-bind:aria-expanded="legendOpen ? 'true' : 'false'"
                class="inline-flex items-center gap-1 font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <x-filament::icon
                    icon="heroicon-m-chevron-right"
                    class="h-3.5 w-3.5 transition-transform duration-150"
                    x-bind:class="legendOpen && 'rotate-90'"
                />
                {{ __('admin.calendar.legend.toggle') }}
            </button>

            <div
                x-show="legendOpen"
                x-cloak
                x-transition.opacity.duration.150ms
                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-gray-600 dark:text-gray-300"
            >
                <span class="font-medium text-gray-500 dark:text-gray-400">{{ __('admin.calendar.legend.zones') }}:</span>
                @foreach ($legendZones as $zone)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="inline-block h-3.5 w-1 rounded-full" style="background-color: {{ $zone->color }};"></span>
                        {{ $zone->tr('name') }}
                    </span>
                @endforeach

                <span class="text-gray-300 dark:text-gray-600">·</span>

                {{-- P8: tipo de producto por su ICONO (la forma = tipo; el color de cada evento = su
                     zona, arriba). El icono sustituye al antiguo borde lateral de color. --}}
                <span class="inline-flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-o-ticket" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    {{ __('admin.calendar.legend.entry') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <x-filament::icon icon="heroicon-o-cake" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    {{ __('admin.calendar.legend.pack') }}
                </span>

                <span class="text-gray-300 dark:text-gray-600">·</span>

                {{-- Finalizado --}}
                <span class="inline-flex items-center gap-1.5 opacity-60 line-through">
                    {{ __('admin.calendar.legend.finished') }}
                </span>

                <span class="text-gray-300 dark:text-gray-600">·</span>

                {{-- Estado del post-form de los cumpleaños (#217): ✓ verde = completo, ! naranja =
                     pendiente. Decisión clienta (2026-06-13): glifo coloreado SIN círculo/fondo,
                     coherente con el calendario. Color INLINE (robusto, no lo purga Tailwind). --}}
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-sm font-bold leading-none" style="color: #16a34a;">✓</span>
                    {{ __('admin.calendar.guest_form_ok') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-sm font-bold leading-none" style="color: #d97706;">!</span>
                    {{ __('admin.calendar.guest_form_pending') }}
                </span>
            </div>
        </div>
    </div>
</x-filament-panels::page>
