@php
    /**
     * Desglose financiero DETALLADO de una reserva (Totales del producto), fuente
     * de render compartida por la sub-card del pedido y el modal del calendario
     * (panel). Recibe un value object {@see \App\Support\ReservationFinancials} →
     * mismos números en todas las superficies.
     *
     * @var \App\Support\ReservationFinancials $rf
     * @var bool $struck  tachar el total (item principal cancelado)
     * @var list<array{label:string, amount:int}> $gateLines  desglose ↳ de «A cobrar en el
     *      parque» de la reserva (#225 F2): cargos de edición + «Resto de la señal». Su Σ ==
     *      `$rf->aCobrarPuerta` (ver {@see \App\Models\Order::reservationGateLines()}). Vacío =
     *      sin desglose (degrada a solo el agregado).
     */
    $struck = $struck ?? false;
    $gateLines = $gateLines ?? [];
    $fmt = fn (int $cents) => \App\Support\Money::format($cents);
@endphp

<div class="space-y-1">
    <div class="flex items-center justify-between gap-3 font-semibold">
        <span @class(['text-gray-800 dark:text-gray-200', 'line-through' => $struck])>{{ __('admin.orders.item_financial.total') }}</span>
        <span @class(['text-gray-900 dark:text-gray-100', 'line-through' => $struck])>{{ $fmt($rf->valor) }}</span>
    </div>

    {{-- Split del total en pagado online + lo de puerta (solo si hay actividad,
         para no recargar un producto pagado 100% online). --}}
    @if ($rf->hasActivity())
        <div class="flex items-center justify-between gap-3 pl-3 text-xs text-gray-500 dark:text-gray-400">
            <span>{{ __('admin.orders.item_financial.paid_online') }}</span>
            <span>{{ $fmt($rf->pagadoOnline) }}</span>
        </div>
        @if ($rf->aCobrarPuerta > 0)
            {{-- Desglose ↳ de los componentes del cargo de puerta (#225 F2): cargos de edición
                 ("+N producto") + «Resto de la señal». Σ == aCobrarPuerta (cuadra por construcción,
                 ver Order::reservationGateLines). #225 F3: OCULTO por defecto tras «Ver desglose»
                 (Alpine); solo si hay líneas que mostrar. --}}
            <div @if (count($gateLines)) x-data="{ open: false }" @endif>
                <div class="flex items-center justify-between gap-3 pl-3 text-xs font-medium text-orange-600 dark:text-orange-400">
                    <span>{{ __('admin.orders.item_financial.at_gate') }}</span>
                    <span>+{{ $fmt($rf->aCobrarPuerta) }}</span>
                </div>
                @if (count($gateLines))
                    <button type="button" x-on:click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                            class="pl-3 inline-flex items-center gap-1 text-[11px] font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        {{-- P2: misma etiqueta «ver más/ver menos» que el bloque del pedido (coherencia). --}}
                        <span x-show="! open">{{ __('admin.orders.show_more') }}</span>
                        <span x-show="open" x-cloak>{{ __('admin.orders.show_less') }}</span>
                    </button>
                    <div x-show="open" x-cloak class="mt-0.5 space-y-0.5">
                        @foreach ($gateLines as $line)
                            <div class="flex items-center justify-between gap-3 pl-6 text-[11px] text-orange-600/90 dark:text-orange-400/80">
                                <span class="truncate">↳ {{ $line['label'] }}</span>
                                <span class="whitespace-nowrap">+{{ $fmt($line['amount']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
        @if ($rf->cobradoPuerta > 0)
            <div class="flex items-center justify-between gap-3 pl-3 text-xs text-gray-500 dark:text-gray-400">
                <span>{{ __('admin.orders.item_financial.collected_at_gate') }}</span>
                <span>{{ $fmt($rf->cobradoPuerta) }}</span>
            </div>
        @endif
    @endif

    @if ($rf->devuelto > 0)
        <div class="flex items-center justify-between gap-3 text-xs text-amber-700 dark:text-amber-300">
            <span>{{ __('admin.orders.item_financial.refunded_label') }}</span>
            <span>−{{ $fmt($rf->devuelto) }}</span>
        </div>
    @endif

    @if ($rf->pendienteReembolso > 0)
        <div class="flex items-center justify-between gap-3 text-xs font-semibold text-amber-700 dark:text-amber-300">
            <span>{{ __('admin.orders.item_financial.pending_refund_label') }}</span>
            <span>{{ $fmt($rf->pendienteReembolso) }}</span>
        </div>
        <p class="text-[11px] leading-snug text-gray-500 dark:text-gray-400">
            {{ __('admin.orders.item_financial.pending_refund_caption') }}
        </p>
    @endif
</div>
