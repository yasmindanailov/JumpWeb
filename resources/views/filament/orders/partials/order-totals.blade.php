@php
    /**
     * @var \App\Domain\Booking\Models\Order $record
     *
     * Bloque "Totales del pedido" — REDISEÑO valor-primero (sesión 2026-06-06,
     * petición de la clienta). El bloque del pedido pasa a usar el MISMO vocabulario
     * que las cards de producto y a ser su SUMA EXACTA:
     *
     *     Valor final = Pagado online + A cobrar en el parque + Liquidado en el parque (T5 · D9)
     *
     * y, en un eje aparte (dinero que vuelve al cliente): Devuelto + Pendiente de
     * devolución. Cada línea puede desplegar su detalle ↳ por reserva.
     *
     * Fuente ÚNICA: `Order::reservationFinancialsByPrincipal()` (Σ de las cards vía
     * {@see \App\Domain\Booking\Services\ReservationFinancials}) para el detalle por reserva, y
     * {@see \App\Domain\Booking\Services\OrderFinancialSummary} para los agregados autoritativos —
     * ambos reconcilian por construcción (#196/#198).
     *
     * Caso SIMPLE (sin cambios ni devoluciones): solo "Total" (sin ruido).
     *
     * Reglas de visibilidad:
     *  - Pagado online: SIEMPRE (en el caso con actividad); es la base del valor.
     *  - A cobrar en el parque: si `hasPendingAtGate` (cargo pendiente por edición).
     *  - Liquidado en el parque: si `extraDueResolved > 0` (reservas ya finalizadas).
     *  - Devuelto: si hay reembolso registrado.
     *  - Pendiente de devolución: si `hasPendienteDevolucion` (online sin producto
     *    detrás aún no devuelto; cubre reembolsos fallidos/pendientes).
     *  - El detalle ↳ por reserva (online/parque/pendiente) se muestra cuando hay
     *    2+ reservas (con 1 reserva la línea YA es su propio detalle → sin ruido).
     *
     * Lectura sin N+1: eager-load de items.children.ticketType + slot + adjustments
     * + payments.refunds (abajo, defensivo).
     */
    use App\Domain\Payments\Models\PaymentRefund;

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

    // ── EL DESGLOSE, del value object ÚNICO (`DECISIONES #127`) ──
    //
    // ⚠️⚠️ **Ni un solo importe se deriva ya aquí.** Los componía este blade por su cuenta —y el
    // cliente, el PDF y los correos, cada uno el suyo— y así es como divergieron: medido, 18 de 23
    // gestiones del panel dejaban la columna del cliente ilegible. Ahora los ocho sitios leen
    // `Booking\Services\OrderLedger`, el MISMO. Este blade decide qué líneas enseña y con qué voz
    // —tercera persona, que es la del operador—, no qué valen.
    $l = \App\Domain\Booking\Services\OrderLedger::forOrder($record);

    $valorFinal = $l->valor;
    $aCobrar = $l->pendientePuerta;
    $pagadoPuerta = $l->pagadoPuerta;
    // ⚠️ Antes se DERIVABA (`valorFinal − aCobrar − pagadoPuerta`), que era una segunda fórmula del
    // mismo importe: coincidía por álgebra, no por construcción, y no restaba la compensación.
    $pagadoOnline = $l->pagadoOnline;
    $pendienteOnline = $l->pendienteOnline;
    $compensado = $l->compensado;
    $devuelto = $l->devuelto;
    $hasRefund = $record->refunded_at !== null || $devuelto > 0;
    $aDevolver = $l->pendienteDevolucion;
    // Ancla de conciliación con el banco: lo REALMENTE cobrado por web.
    $brutoOnline = $l->cobradoOnline;

    $hasBreakdown = $l->hasBreakdown() || $hasRefund;

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
    $paidPayment = $record->payments->firstWhere('status', \App\Domain\Payments\Models\Payment::STATUS_PAID);
    // ⚠️ El MÉTODO lo decide el DOMINIO (`DECISIONES #128`). Estaba escrito aquí como literal
    // —`provider === 'redsys'`— y el cliente no lo distinguía en absoluto: dos definiciones de la
    // misma regla, que es como empiezan las divergencias que `OrderLedger` existe para cerrar.
    $isPaidOnline = $l->cobroMetodo === 'web';
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

    {{-- ⚠️⚠️ **EL DESGLOSE NO CIERRA** (`DECISIONES #132`). Al operador se le ENSEÑA —es quien puede
         arreglarlo— mientras que al cliente se le oculta la descomposición y se le da una frase
         honesta: la asimetría es deliberada. Hasta hoy no se enteraba ninguno de los dos. --}}
    @unless ($l->cuadra)
        <div class="mb-2 rounded-md bg-danger-50 p-2 text-xs leading-snug text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-500/10 dark:text-danger-300"
             role="alert">
            <span class="font-semibold">{{ __('admin.orders.order_financial.no_cuadra_title') }}</span>
            {{ __('admin.orders.order_financial.no_cuadra_body') }}
        </div>
    @endunless

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

            {{-- ⚠️ **«Al reservar se facturaron X. El pedido cambió después y ahora vale Y menos.»**
                 (`DECISIONES #145`). El CLIENTE ya leía esta frase en «Mis pedidos» y el operador
                 —que es quien tiene que explicar el cargo con el cliente delante— no tenía nada
                 equivalente: veía el valor final y ninguna pista de que antes fue otro. Era una
                 inversión rara, y arreglarla no cuesta un cálculo.
                 ▶ **La compone `OrderLedger::invoicedNoteFor`, no este blade**: es la MISMA frase que
                 publica `LedgerResource::invoiced_hint`, así que no hay dos redacciones que puedan
                 divergir (`LedgerSingleSourceTest`).
                 ▶ ⚠️ **La condición es que la frase EXISTA, no comparar importes** — es la lección de
                 `#134`/`L6`: `facturadoNota` es `null` exactamente cuando no hay nada que contar, y
                 re-derivarlo aquí con `facturado !== valor` es el defecto que aquel punto cerró.
                 No va como fila de la columna a propósito: no es un canal del desglose. --}}
            @if ($l->facturadoNota !== null)
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $l->facturadoNota }}</p>
            @endif

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
                            {{-- T5 (§25.6·7): signo consciente — desde la T4 la línea del descuento
                                 es NEGATIVA y el «+» clavado pintaba «+-4,00 €». --}}
                            <span class="whitespace-nowrap">{{ $line['amount'] < 0 ? '−' : '+' }}{{ $fmt(abs($line['amount'])) }}</span>
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

            {{-- Liquidado en el parque (T5 · D9). Detalle ↳ por reserva. --}}
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

            {{-- ⚠️ PENDIENTE DE COBRO ONLINE. Es el canal que faltaba: un pedido sin cobrar tenía su
                 importe en «Pagado online», leído en pasado — el operador veía «pagado» algo que
                 nadie había pagado (`DECISIONES #127`). --}}
            @if ($pendienteOnline > 0)
                <div class="flex items-center justify-between gap-3 pl-3 text-sm font-medium text-amber-700 dark:text-amber-300">
                    <span>{{ __('admin.orders.order_financial.pendiente_online') }}</span>
                    <span>{{ $fmt($pendienteOnline) }}</span>
                </div>
            @endif

            {{-- ⚠️ COMPENSACIÓN: dinero devuelto SIN que desapareciera producto. No es un canal de
                 cobro ni una bajada de valor — es su propio término, y por eso la «ley de caja»
                 arrastraba una excepción que en realidad era esta línea. --}}
            @if ($compensado > 0)
                <div class="flex items-center justify-between gap-3 pl-3 text-sm text-gray-700 dark:text-gray-300">
                    <span>{{ __('admin.orders.order_financial.compensado') }}</span>
                    <span>{{ $fmt($compensado) }}</span>
                </div>
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

            {{-- ⚠️ EL ANCLA DE CAJA: lo realmente cobrado por web. Es lo único que el operador puede
                 cotejar con el extracto del banco, y hasta la tanda B solo existía dentro de un
                 caption (`DECISIONES #127`).
                 ⚠️ La condición la decide el DOMINIO (`hasCash()`), no este blade: el cliente
                 derivaba la suya y le faltaba justo este término (`DECISIONES #128` · `L1`).
                 Medido sobre los 58 pedidos: el cambio de condición no altera el panel en ninguno. --}}
            @if ($l->hasCash())
                <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-1.5 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <span>{{ __('admin.orders.order_financial.cobrado_web') }}</span>
                    <span>{{ $fmt($brutoOnline) }}</span>
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
