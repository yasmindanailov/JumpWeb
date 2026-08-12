@php
    /**
     * @var \App\Models\OrderItem|null $item
     * @var \Illuminate\Support\Collection|null $children
     * @var string|null $orderUrl
     * @var string|null $userUrl
     * @var int $principalCents
     * @var int $addonsCents
     * @var int $totalCents
     *
     * Modal de detalle del producto/reserva (L4, pasada de diseño — decisión clienta 2026-06-13:
     * «limpiar y reorganizar, sin mucho lío»). Orden: TÍTULO (icono + nombre + meta inline
     * cantidad/invitados · fecha · horario; SIN duración) → Datos del evento (campos inline) →
     * estado del post-form (glifo sobrio, si aplica) → Datos del cliente (nombre + teléfono) →
     * Totales (sin cambios) → acciones (sin cambios). La «X» de Filament cierra el modal (el botón
     * «Cerrar» se retiró con `->modalCancelAction(false)` en la Action).
     */
@endphp

@if ($item === null)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('admin.calendar.item_modal.not_found') }}
    </p>
@else
@php
    $type = $item->ticketType;
    $zone = $type?->zone;
    $zoneColor = $zone?->color ?: '#9CA3AF';
    $isPack = $type?->isPack() ?? false;
    $productName = $type?->tr('name') ?? '—';
    $quantityLabel = $isPack
        ? __('tickets.guests_count', ['count' => $item->quantity])
        : $item->quantity.' × '.__('admin.orders.item_detail.unit_entries');
    $slot = $item->slot;
    $dateLabel = $slot ? \Illuminate\Support\Carbon::parse($slot->date)->isoFormat('ddd D MMM YYYY') : null;
    $timeLabel = $item->displayTimeWindow();
    $children = $children ?? collect();
    $principalCents = $principalCents ?? $item->chargedSubtotalCents();
    $addonsCents = $addonsCents ?? 0;
    $totalCents = $totalCents ?? ($principalCents + $addonsCents);
    $eventFields = ($isPack && $type) ? $type->eventFields() : [];
    $eventData = is_array($item->event_data) ? $item->event_data : [];
    $hasEventData = collect($eventFields)->contains(fn ($f) => trim((string) ($eventData[$f['key']] ?? '')) !== '');
    $formStatus = $item->guestFormStatus();
    $fmt = fn (int $cents) => \App\Domain\Platform\Services\Money::format($cents);
    $customer = $item->order?->user;
    $userUrl = $userUrl ?? null;
    $slipUrl = $slipUrl ?? null;
    $rf = $rf ?? null; // App\Support\ReservationFinancials — desglose detallado (#196)
@endphp

<div class="space-y-5">
    {{-- TÍTULO: icono de tipo (tintado con el color de zona, P8) + nombre del producto, y debajo la
         meta inline: cantidad/invitados · fecha · horario. La duración se retira. --}}
    <div class="border-b border-gray-200 pb-3 dark:border-white/10">
        <div class="flex items-center gap-2.5">
            <span class="flex-shrink-0" style="color: {{ $zoneColor }};">
                <x-filament::icon :icon="$isPack ? 'heroicon-o-cake' : 'heroicon-o-ticket'" class="h-5 w-5" />
            </span>
            <h3 class="min-w-0 truncate text-base font-semibold text-gray-900 dark:text-gray-100">{{ $productName }}</h3>
        </div>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
            <span>{{ $quantityLabel }}</span>
            @if ($dateLabel)
                <span class="text-gray-300 dark:text-gray-600">·</span>
                <span>{{ $dateLabel }}</span>
            @endif
            @if ($timeLabel)
                <span class="text-gray-300 dark:text-gray-600">·</span>
                <span>{{ $timeLabel }}</span>
            @endif
        </div>
    </div>

    {{-- Datos del evento (packs): campos INLINE («Etiqueta: valor» fluyendo). --}}
    @if ($hasEventData)
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('admin.calendar.item_modal.section_event_data') }}
            </h4>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                @foreach ($eventFields as $field)
                    @php $value = trim((string) ($eventData[$field['key']] ?? '')); @endphp
                    @if ($value !== '')
                        <span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $type->eventFieldLabel($field) }}:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $value }}</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Estado del formulario de reserva por-niño (#217): glifo sobrio (✓ verde / ! naranja, sin
         círculo ni fondo — coherente con el calendario). Solo en packs que piden el post-form.
         Si está COMPLETO (decisión clienta 2026-06-13): un «Ver formulario» despliega los datos
         por-niño (misma tabla que la ficha del pedido). Si está pendiente, no hay nada que mostrar. --}}
    @if ($formStatus !== null)
        @php
            $guestOk = $formStatus === \App\Models\OrderItem::GUEST_FORM_STATUS_OK;
            $guestFields = $type?->guestFields() ?? [];
            $guestData = $item->guestData();
        @endphp
        <div @if ($guestOk) x-data="{ guestOpen: false }" @endif class="text-sm">
            <div class="flex flex-wrap items-center gap-1.5">
                @if ($guestOk)
                    <span class="font-bold leading-none" style="color: #16a34a;">✓</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ __('admin.calendar.guest_form_ok') }}</span>
                    <button
                        type="button"
                        x-on:click="guestOpen = ! guestOpen"
                        x-bind:aria-expanded="guestOpen ? 'true' : 'false'"
                        class="ml-1 inline-flex items-center font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        <span x-show="! guestOpen">{{ __('admin.calendar.item_modal.guest_form_show') }}</span>
                        <span x-show="guestOpen" x-cloak>{{ __('admin.calendar.item_modal.guest_form_hide') }}</span>
                    </button>
                @else
                    <span class="font-bold leading-none" style="color: #d97706;">!</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ __('admin.calendar.guest_form_pending') }}</span>
                @endif
            </div>

            @if ($guestOk)
                <div x-show="guestOpen" x-cloak class="mt-2 overflow-x-auto rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    @if (! empty($guestData))
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="py-1 pr-2 font-medium">#</th>
                                    @foreach ($guestFields as $gf)
                                        <th class="py-1 pr-2 font-medium">{{ $type->guestFieldLabel($gf) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @for ($gi = 0; $gi < $item->quantity; $gi++)
                                    <tr class="border-t border-gray-100 dark:border-white/5">
                                        <td class="py-1 pr-2 text-gray-400">{{ $gi + 1 }}</td>
                                        @foreach ($guestFields as $gf)
                                            <td class="py-1 pr-2 text-gray-800 dark:text-gray-200">{{ $guestData[$gi][$gf['key']] ?? '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    @else
                        <p class="text-gray-400 dark:text-gray-500">{{ __('admin.orders.guests_empty') }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- Datos del cliente: nombre + teléfono (sin email, decisión clienta). --}}
    @if ($customer)
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('admin.calendar.item_modal.section_customer') }}
            </h4>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                <span>
                    <span class="text-gray-500 dark:text-gray-400">{{ __('admin.calendar.item_modal.field_customer_name') }}:</span>
                    @if (! empty($userUrl))
                        <a href="{{ $userUrl }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ $customer->name }}</a>
                    @else
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $customer->name }}</span>
                    @endif
                </span>
                @if (filled($customer->phone))
                    <span>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('admin.calendar.item_modal.field_customer_phone') }}:</span>
                        <a href="tel:{{ $customer->phone }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ $customer->phone }}</a>
                    </span>
                @endif
            </div>
        </div>
    @endif

    {{-- Totales del producto: desglose DETALLADO (#196) vía fuente única ReservationFinancials →
         mismas cifras que la sub-card del pedido y el PDF. SIN CAMBIOS. --}}
    <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('admin.orders.item_financial.heading') }}
        </h4>
        @if ($children->isNotEmpty())
            <div class="mb-1 space-y-1">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('admin.orders.item_financial.principal') }}</span>
                    <span class="text-gray-800 dark:text-gray-200">{{ $fmt($principalCents) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-gray-600 dark:text-gray-400">{{ __('admin.orders.item_financial.addons') }}</span>
                    <span class="text-gray-800 dark:text-gray-200">{{ $fmt($addonsCents) }}</span>
                </div>
            </div>
        @endif
        @if ($rf)
            @include('filament.orders.partials.reservation-financials', ['rf' => $rf, 'gateLines' => $item->order->reservationGateLines($item)])
        @else
            <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-1 font-semibold dark:border-white/10">
                <span class="text-gray-800 dark:text-gray-200">{{ __('admin.orders.item_financial.total') }}</span>
                <span class="text-gray-900 dark:text-gray-100">{{ $fmt($totalCents) }}</span>
            </div>
        @endif
    </div>

    {{-- Acciones: imprimir la hoja de reserva + ver el pedido, alineados a la derecha. SIN CAMBIOS. --}}
    <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
        {{-- Imprimir ESTA reserva (hoja individual #183) en pestaña nueva. DOS variantes (#235/④),
             coherente con la ficha del pedido: «Hoja de sala» (sin precios, operativa) y «Con precios»
             (desglose económico, `?precios=1`). Dos enlaces reales `target="_blank"` (sin bloqueo de popups). --}}
        @if (! empty($slipUrl))
            <x-filament::dropdown placement="bottom-end" teleport>
                <x-slot name="trigger">
                    <x-filament::button
                        tag="button"
                        type="button"
                        color="gray"
                        icon="heroicon-o-printer"
                        size="sm"
                    >
                        {{ __('admin.calendar.item_modal.print_slip') }}
                    </x-filament::button>
                </x-slot>
                <x-filament::dropdown.list>
                    <x-filament::dropdown.list.item
                        tag="a"
                        :href="$slipUrl"
                        target="_blank"
                        icon="heroicon-o-document-text"
                    >
                        {{ __('admin.orders.slip.print_operational') }}
                    </x-filament::dropdown.list.item>
                    <x-filament::dropdown.list.item
                        tag="a"
                        :href="$slipUrl.'?precios=1'"
                        target="_blank"
                        icon="heroicon-o-banknotes"
                    >
                        {{ __('admin.orders.slip.print_with_prices') }}
                    </x-filament::dropdown.list.item>
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif

        @if ($item->order && $orderUrl)
            <x-filament::button
                tag="a"
                :href="$orderUrl"
                color="gray"
                icon="heroicon-o-arrow-top-right-on-square"
                size="sm"
            >
                {{ __('admin.calendar.item_modal.view_order', ['code' => $item->order->code]) }}
            </x-filament::button>
        @endif
    </div>
</div>
@endif
