@php
    /**
     * @var \App\Models\Order $record
     *
     * Bloque "Totales del pedido" — REDISEÑO valor-primero (sesión 2026-06-06,
     * petición de la clienta). El bloque del pedido pasa a usar el MISMO vocabulario
     * que las cards de producto y a ser su SUMA EXACTA:
     *
     *     Valor final = Pagado online + A cobrar en el parque + Pagado en el parque
     *
     * y, en un eje aparte (dinero que vuelve al cliente): Devuelto + Pendiente de
     * devolución. Cada línea puede desplegar su detalle ↳ por reserva.
     *
     * Fuente ÚNICA: `Order::reservationFinancialsByPrincipal()` (Σ de las cards vía
     * {@see \App\Support\ReservationFinancials}) para el detalle por reserva, y
     * {@see \App\Support\OrderFinancialSummary} para los agregados autoritativos —
     * ambos reconcilian por construcción (#196/#198).
     *
     * Caso SIMPLE (sin cambios ni devoluciones): solo "Total" (sin ruido).
     *
     * Reglas de visibilidad:
     *  - Pagado online: SIEMPRE (en el caso con actividad); es la base del valor.
     *  - A cobrar en el parque: si `hasPendingAtGate` (cargo pendiente por edición).
     *  - Pagado en el parque: si `extraDueResolved > 0` (reservas ya finalizadas).
     *  - Devuelto: si hay reembolso registrado.
     *  - Pendiente de devolución: si `hasPendienteDevolucion` (online sin producto
     *    detrás aún no devuelto; cubre reembolsos fallidos/pendientes).
     *  - El detalle ↳ por reserva (online/parque/pendiente) se muestra cuando hay
     *    2+ reservas (con 1 reserva la línea YA es su propio detalle → sin ruido).
     *
     * Lectura sin N+1: eager-load de items.children.ticketType + slot + adjustments
     * + payments.refunds (abajo, defensivo).
     */
    use App\Models\PaymentRefund;

    $s = $record->financialSummary();

    $cur = ($record->currency === 'EUR') ? '€' : $record->currency;
    $fmt = fn (int $cents) => \App\Domain\Platform\Services\Money::amount($cents).' '.$cur;

    $record->loadMissing([
        'payments.refunds.orderItem.ticketType',
        'adjustments.orderItem.ticketType',
        'items.ticketType',
        'items.slot',
        'items.children.ticketType',
    ]);

    // ── Agregados autoritativos (OrderFinancialSummary) ──
    $valorFinal = $s->totalFinalNeto();        // = Σ valor de las cards (= productsValue)
    $aCobrar = $s->pendingAtGate();            // extra pendiente de cobrar en puerta
    // Cobrado en puerta (reservas finalizadas): extra de ediciones + resto de la señal (#225).
    $pagadoPuerta = (int) $s->extraDueResolved + (int) $s->depositRemainderResolved;
    // Pagado online que RESPALDA productos (= Σ itemCollectedCents). Derivado de la
    // identidad valorFinal = pagadoOnline + aCobrar + pagadoPuerta → cuadra exacto.
    $pagadoOnline = max(0, $valorFinal - $aCobrar - $pagadoPuerta);
    // "Devuelto": fuente LEGACY-SAFE (`Order.refund_amount_cents` + `refunded_at`).
    // Hay refunds pre-#142 sin fila `payment_refunds`; `OrderFinancialSummary` solo
    // lee `payment_refunds` y los perdería, así que para esta dimensión usamos la
    // columna agregada del Order (igual que el bloque anterior).
    $devuelto = (int) ($record->refund_amount_cents ?? 0);
    $hasRefund = $record->refunded_at !== null || $devuelto > 0;
    $aDevolver = $s->pendienteDevolucion();    // pagado online sin producto, pendiente
    // Bruto realmente pagado por web (ancla de conciliación con el banco). Deposit-aware (#225):
    // es lo COBRADO ONLINE (Σ payments pagados), NO `Order.total` (que con señal es el valor
    // pleno, no lo que entró por web → el caption decía «pagó 180» cuando solo se cobró la señal).
    $brutoOnline = (int) $record->payments->where('status', \App\Models\Payment::STATUS_PAID)->sum('amount');

    // Incluye el pendiente en puerta de la SEÑAL (#225): un pedido de solo-señal también
    // tiene desglose (Pagado online / A cobrar en el parque), no «solo Total».
    $hasBreakdown = $s->hasExtraDue() || $hasRefund || $s->hasPendienteDevolucion() || $s->hasPendingAtGate();

    // ── Detalle ↳ por reserva (mismas cifras que las cards) ──
    $reservations = $record->reservationFinancialsByPrincipal();
    $multi = count($reservations) > 1;   // con 1 reserva la línea ya es su detalle

    $onlineLines = [];
    $gateCollectedLines = [];
    $refundPendingLines = [];
    foreach ($reservations as $r) {
        $rf = $r['rf'];
        if ($rf->pagadoOnline > 0) {
            $onlineLines[] = ['name' => $r['name'], 'amount' => $rf->pagadoOnline];
        }
        if ($rf->cobradoPuerta > 0) {
            $gateCollectedLines[] = ['name' => $r['name'], 'amount' => $rf->cobradoPuerta];
        }
        if ($rf->pendienteReembolso > 0) {
            $refundPendingLines[] = ['name' => $r['name'], 'amount' => $rf->pendienteReembolso];
        }
    }

    // "A cobrar en el parque": líneas NETAS por item de EDICIONES (formato delta "+4 Cumpleaños
    // Jump", #171/#195). El resto de la señal NO sale aquí (solo netea extra_due); se añade en el
    // desglose ↳ POR PRODUCTO vía `Order::depositRemainderPendingByProduct()`. Σ(↳) == $aCobrar.
    $gateLines = $record->pendingAtGateLines();

    // "Devuelto": un renglón por reembolso con éxito ligado a un item concreto. Los
    // del pedido completo (order_item_id null) y los legacy no detallan renglón.
    $refundLines = [];
    foreach ($record->payments as $p) {
        foreach ($p->refunds as $rf) {
            if ($rf->status !== PaymentRefund::STATUS_SUCCEEDED || $rf->orderItem === null) {
                continue;
            }
            $refundLines[] = [
                'name' => $rf->orderItem->ticketType?->tr('name') ?? '—',
                'amount' => (int) $rf->amount_cents,
            ];
        }
    }

    // ── P4 (display): ¿cómo se pagó? online (Redsys) o manualmente (efectivo/datáfono) + fecha ──
    // Online → fecha del cobro (`paid_at`); manual → fecha de creación del pedido (no hay timestamp
    // fiable del cobro presencial). Si no hay pago confirmado → pendiente.
    $paidPayment = $record->payments->firstWhere('status', \App\Models\Payment::STATUS_PAID);
    $isPaidOnline = $paidPayment && $paidPayment->provider === 'redsys';
    $paymentDate = $paidPayment
        ? ($isPaidOnline ? ($paidPayment->paid_at ?? $paidPayment->created_at) : $record->created_at)
        : null;
    // P1/P10: la etiqueta de lo cobrado refleja el MÉTODO real — «Pagado online» solo si fue por la
    // web (Redsys); si se cobró manualmente (efectivo/datáfono) → «Cobrado (efectivo/datáfono)».
    $paidLabel = $isPaidOnline
        ? __('admin.orders.order_financial.pagado_online')
        : __('admin.orders.order_financial.cobrado_manual');
    $paidDateStr = $paymentDate ? \App\Domain\Platform\Services\DisplayTime::format($paymentDate, 'd/m/Y') : null;
@endphp

<div class="rounded-lg bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ __('admin.orders.order_financial.heading') }}
    </div>

    @unless ($hasBreakdown)
        {{-- Caso SIMPLE: pedido sin cambios ni devoluciones → total + cómo/cuándo se pagó. --}}
        <div class="flex items-center justify-between gap-3 text-base font-semibold">
            <span class="text-gray-800 dark:text-gray-200">{{ __('admin.orders.amount_total') }}</span>
            <span class="text-gray-900 dark:text-gray-100">{{ $fmt($valorFinal) }}</span>
        </div>
        {{-- P1/P10: método + fecha del cobro (aquí no hay agregado «Pagado online» al que adjuntarlo). --}}
        <div class="mt-1 text-xs">
            @if ($paidPayment)
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $paidLabel }}</span>
                <span class="text-gray-500 dark:text-gray-400">· {{ $paidDateStr }}</span>
            @else
                <span class="font-medium text-amber-600 dark:text-amber-400">{{ __('admin.orders.not_paid_yet') }}</span>
            @endif
        </div>
    @else
        {{-- P13: UN SOLO «ver más» despliega TODOS los desgloses a la vez, cada uno EN SU SITIO (bajo
             su agregado). El estado `open` lo comparte el contenedor; cada bloque ↳ es `x-show="open"`.
             Los importes agregados están siempre visibles. --}}
        @php
            $hasDetail = $multi
                || $s->hasPendingAtGate()
                || $s->hasPendienteDevolucion()
                || ($hasRefund && count($refundLines) > 0);
        @endphp
        <div x-data="{ open: false }" class="space-y-1">
            {{-- Valor final --}}
            <div class="flex items-center justify-between gap-3 text-base font-bold">
                <span class="text-gray-900 dark:text-gray-100">{{ __('admin.orders.order_financial.valor_final') }}</span>
                <span class="text-gray-900 dark:text-gray-100">{{ $fmt($valorFinal) }}</span>
            </div>

            {{-- Pagado online / Cobrado (P1/P10: etiqueta según método + fecha). Detalle ↳ por reserva. --}}
            <div class="flex items-center justify-between gap-3 pl-3 text-sm text-gray-700 dark:text-gray-300">
                <span>@if ($paidPayment){{ $paidLabel }} <span class="text-gray-400 dark:text-gray-500">· {{ $paidDateStr }}</span>@else{{ __('admin.orders.order_financial.pagado_online') }}@endif</span>
                <span>{{ $fmt($pagadoOnline) }}</span>
            </div>
            @if ($multi)
                <div x-show="open" x-cloak class="space-y-0.5">
                    @foreach ($onlineLines as $line)
                        <div class="flex items-center justify-between gap-3 pl-6 text-xs text-gray-500 dark:text-gray-400">
                            <span class="truncate">↳ {{ $line['name'] }}</span>
                            <span class="whitespace-nowrap">{{ $fmt($line['amount']) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- A cobrar en el parque. Detalle ↳: cargos de edición + «Resto de la señal» por producto + nota. --}}
            @if ($s->hasPendingAtGate())
                <div class="flex items-center justify-between gap-3 pl-3 text-sm font-semibold text-orange-600 dark:text-orange-400">
                    <span>{{ __('admin.orders.order_financial.pending_at_gate') }}</span>
                    <span>+{{ $fmt($aCobrar) }}</span>
                </div>
                <div x-show="open" x-cloak class="space-y-0.5">
                    @foreach ($gateLines as $line)
                        <div class="flex items-center justify-between gap-3 pl-6 text-xs text-orange-600/90 dark:text-orange-400/80">
                            <span class="truncate">↳ {{ $line['label'] }}</span>
                            <span class="whitespace-nowrap">+{{ $fmt($line['amount']) }}</span>
                        </div>
                    @endforeach
                    @foreach ($record->depositRemainderPendingByProduct() as $dr)
                        <div class="flex items-center justify-between gap-3 pl-6 text-xs text-orange-600/90 dark:text-orange-400/80">
                            <span class="truncate">↳ {{ __('admin.orders.order_financial.deposit_remainder_line') }} {{ __('admin.orders.deposit_for_product', ['product' => $dr['name']]) }}</span>
                            <span class="whitespace-nowrap">+{{ $fmt($dr['amount']) }}</span>
                        </div>
                    @endforeach
                    <p class="pl-3 text-xs leading-snug text-gray-500 dark:text-gray-400">
                        {{ __('admin.orders.order_financial.pending_at_gate_caption') }}
                    </p>
                </div>
            @endif

            {{-- Pagado en el parque. Detalle ↳ por reserva. --}}
            @if ($pagadoPuerta > 0)
                <div class="flex items-center justify-between gap-3 pl-3 text-sm text-gray-700 dark:text-gray-300">
                    <span>{{ __('admin.orders.order_financial.pagado_puerta') }}</span>
                    <span>{{ $fmt($pagadoPuerta) }}</span>
                </div>
                @if ($multi)
                    <div x-show="open" x-cloak class="space-y-0.5">
                        @foreach ($gateCollectedLines as $line)
                            <div class="flex items-center justify-between gap-3 pl-6 text-xs text-gray-500 dark:text-gray-400">
                                <span class="truncate">↳ {{ $line['name'] }}</span>
                                <span class="whitespace-nowrap">{{ $fmt($line['amount']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- Devuelto. Detalle ↳ por producto. --}}
            @if ($hasRefund)
                <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-1.5 text-sm text-amber-700 dark:border-white/10 dark:text-amber-300">
                    <span>{{ __('admin.orders.amount_refunded') }}</span>
                    <span>−{{ $fmt($devuelto) }}</span>
                </div>
                @if (count($refundLines))
                    <div x-show="open" x-cloak class="space-y-0.5">
                        @foreach ($refundLines as $line)
                            <div class="flex items-center justify-between gap-3 pl-3 text-xs text-amber-600/90 dark:text-amber-300/80">
                                <span class="truncate">↳ {{ $line['name'] }}</span>
                                <span class="whitespace-nowrap">−{{ $fmt($line['amount']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif

            {{-- Pendiente de devolución. Detalle ↳ por reserva + nota. --}}
            @if ($s->hasPendienteDevolucion())
                <div @class([
                    'flex items-center justify-between gap-3 text-sm font-semibold text-amber-700 dark:text-amber-300',
                    'border-t border-gray-200 pt-1.5 dark:border-white/10' => ! $hasRefund,
                ])>
                    <span>{{ __('admin.orders.order_financial.pendiente_devolucion') }}</span>
                    <span>−{{ $fmt($aDevolver) }}</span>
                </div>
                <div x-show="open" x-cloak class="space-y-0.5">
                    @if ($multi)
                        @foreach ($refundPendingLines as $line)
                            <div class="flex items-center justify-between gap-3 pl-3 text-xs text-amber-600/90 dark:text-amber-300/80">
                                <span class="truncate">↳ {{ $line['name'] }}</span>
                                <span class="whitespace-nowrap">−{{ $fmt($line['amount']) }}</span>
                            </div>
                        @endforeach
                    @endif
                    <p class="text-xs leading-snug text-gray-500 dark:text-gray-400">
                        {{ __('admin.orders.order_financial.pendiente_devolucion_caption_web', ['total' => $fmt($brutoOnline), 'pendiente' => $fmt($aDevolver)]) }}
                    </p>
                </div>
            @endif

            {{-- P13: el ÚNICO botón que despliega/oculta TODOS los desgloses de arriba a la vez. --}}
            @if ($hasDetail)
                <button type="button" x-on:click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
                        class="inline-flex items-center gap-1 pt-0.5 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    <span x-show="! open">{{ __('admin.orders.show_more') }}</span>
                    <span x-show="open" x-cloak>{{ __('admin.orders.show_less') }}</span>
                </button>
            @endif
        </div>
    @endunless
</div>
