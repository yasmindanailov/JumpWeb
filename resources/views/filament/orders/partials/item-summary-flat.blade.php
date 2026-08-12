@php
    /**
     * @var \App\Domain\Booking\Models\OrderItem $item
     * @var bool $showChildren  Si true y el item tiene complementos, los
     *                          renderiza como lista intro debajo del card.
     *                          Default: false (compat retro con refund modal).
     *                          Usado true desde `cancelItemAction` (sub-fase
     *                          7.2e.1bis5, decisión #158, punto 5 feedback):
     *                          cancelar un producto cascada a sus children,
     *                          el operador debe verlos antes de confirmar.
     *
     * Sub-fase 7.2e.1bis2 — partial PLANO (sin `.zone-card` border-left) para
     * `modalContent` de las actions `cancelItem` y `refundItem`. NO usamos
     * border lateral coloreado — feedback empírico de la clienta "el card tiene
     * un border lateral izquierdo, eso quitalo, no es Filament ni lo usaremos
     * así". Estética Filament neutral.
     *
     * Sub-fase 7.2e.1bis5 (decisión #158, punto 2 feedback): el badge
     * superior izq pasa a mostrar el NOMBRE DEL PRODUCTO con el color de
     * zona. El subtítulo redundante con el nombre se elimina. El precio del
     * principal queda alineado a la derecha del bloque fecha+hora.
     */
    use App\Domain\Platform\Services\Duration;

    $showChildren = $showChildren ?? false;

    $ticketType = $item->ticketType;
    $isPack = $ticketType?->isPack() ?? false;
    $zone = $ticketType?->zone;
    $zoneColor = $zone?->color ?? '#9CA3AF';
    $productName = $ticketType?->tr('name') ?? '—';
    $durationLabel = Duration::formatHumane($ticketType?->duration_min);
    $quantityLabel = $isPack
        ? __('tickets.guests_count', ['count' => $item->quantity])
        : $item->quantity.' × '.__('admin.orders.item_detail.unit_entries');
    // F13: este importe es el COBRADO del producto (no el reembolsable, que el
    // modal de reembolso muestra por-fila en el CheckboxList). Se rotula en la
    // cabecera como "Importe del producto" para no confundir ambas cifras.
    $subtotal = $item->chargedSubtotalCents() / 100;
    // F14 (defensivo): el principal solo suma al total cascada si NO está
    // cancelado, igual que los children cancelados se excluyen abajo. El precio
    // por fila (`$subtotal`) se sigue mostrando siempre.
    $principalCascadeTotal = $item->isCancelled() ? 0 : $subtotal;

    // F3: ocultamos los complementos "fantasma" net-cero (cancelados, nunca
    // cobrados ni reembolsados) — misma autoridad única (Order::isVoidedLeftoverItem)
    // que items-list y el PDF; listarlos en el modal de cancelar solo confunde.
    $children = $showChildren
        ? $item->children->reject(fn ($c) => $item->order->isVoidedLeftoverItem($c))->values()
        : collect();
    // El total cascada excluye complementos CANCELADOS (ya no se cobran); se siguen
    // listando tachados, pero no suman. Coherente con el resto de desgloses.
    $childrenTotal = $children->reject(fn ($c) => $c->isCancelled())
        ->sum(fn ($c) => $c->chargedSubtotalCents()) / 100;
@endphp

{{-- Card plano con estética Filament neutral: ring sutil, fondo bg-gray-50/
     dark:bg-white/5. SIN border lateral coloreado. El color de zona vive
     SOLO en el `.zone-badge` (igual que en el sub-card de items-list desde
     7.2c#148bis). --}}
<div class="rounded-xl p-4 mb-4 bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <div class="flex flex-wrap items-center gap-2">
        <span class="zone-badge" style="--zone-color: {{ $zoneColor }};">{{ $productName }}</span>
        @if ($durationLabel)
            <span class="text-sm text-gray-600 dark:text-gray-400">·</span>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $durationLabel }}</span>
        @endif
        <span class="text-sm text-gray-600 dark:text-gray-400">·</span>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $quantityLabel }}</span>
    </div>

    @if ($item->slot)
        <div class="mt-3 flex items-baseline justify-between gap-3 flex-wrap">
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ \Illuminate\Support\Carbon::parse($item->slot->date)->isoFormat('ddd D MMM YYYY') }}
                <span class="text-gray-500 dark:text-gray-400">·</span>
                {{ $item->displayTimeWindow() }}
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.orders.item_detail.product_amount') }}</div>
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ number_format($subtotal, 2, ',', '.') }} €</span>
            </div>
        </div>
    @else
        <div class="mt-3 flex items-baseline justify-between gap-3 flex-wrap">
            <div class="text-sm italic text-gray-500 dark:text-gray-400">{{ __('admin.orders.item_detail.details_no_slot') }}</div>
            <div class="text-right">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.orders.item_detail.product_amount') }}</div>
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ number_format($subtotal, 2, ',', '.') }} €</span>
            </div>
        </div>
    @endif

    {{-- Sub-fase 7.2e.1bis5 (decisión #158, punto 5): lista de complementos
         que se cancelarán EN CASCADA cuando el operador cancele el producto
         principal. La intro hace explícita la semántica para que el operador
         NO confunda "cancelar pack" con "cancelar solo el pack y mantener
         tarta + decoración + monitor". --}}
    @if ($children->isNotEmpty())
        <div class="mt-4 pt-3 border-t border-gray-200 dark:border-white/10">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">
                {{ __('admin.orders.cancel_item.cascade_intro', ['count' => $children->count()]) }}
            </p>
            <ul class="space-y-1">
                @foreach ($children as $child)
                    @php
                        $childCancelled = $child->isCancelled();
                        $childSubtotal = $child->chargedSubtotalCents() / 100;
                    @endphp
                    <li @class(['flex items-center justify-between gap-3 text-sm', 'text-gray-500 line-through dark:text-gray-500' => $childCancelled, 'text-gray-700 dark:text-gray-300' => ! $childCancelled])>
                        <span>+ {{ $child->quantity }} × {{ $child->ticketType?->tr('name') ?? '—' }}</span>
                        <span class="text-gray-600 dark:text-gray-400">{{ number_format($childSubtotal, 2, ',', '.') }} €</span>
                    </li>
                @endforeach
            </ul>
            @if ($childrenTotal > 0)
                <div class="mt-2 pt-2 border-t border-gray-200 dark:border-white/10 flex items-center justify-between text-sm font-semibold text-gray-800 dark:text-gray-200">
                    <span>{{ __('admin.orders.cancel_item.cascade_total') }}</span>
                    <span>{{ number_format($principalCascadeTotal + $childrenTotal, 2, ',', '.') }} €</span>
                </div>
            @endif
        </div>
    @endif
</div>
