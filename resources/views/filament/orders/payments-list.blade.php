@php
    use App\Models\Payment;
    use App\Models\PaymentRefund;
    use App\Support\DisplayTime;
    use App\Support\Redsys;
    use App\Support\RedsysCardCodes;
    use App\Support\RedsysResponseCode;

    /** @var \App\Models\Order $record */
    // Eager-load refunds + el operador que las lanzó para que la card de devolución
    // pueda mostrar el nombre sin N+1 (#143 + #144).
    // Sub-fase 7.2e.1bis5 (decisión #158, punto 6): añadido `refunds.orderItem.ticketType`
    // para mostrar el nombre del producto/complemento devuelto en cada sub-card.
    // Para refunds parciales por-item, `order_item_id` está poblado desde 7.2e.0.
    // Para refunds totales del Order (#142), `order_item_id` es null (todo el pedido).
    $payments = $record->payments()
        ->with([
            'refunds' => fn ($q) => $q->orderByDesc('requested_at')->orderByDesc('id'),
            'refunds.requestedBy',
            'refunds.orderItem.ticketType',
        ])
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->get();
    $adminPanelUrl = app(Redsys::class)->adminPanelUrl();
    $isLive = app(Redsys::class)->isLive();

    // Sub-fase 7.2e.1bis (decisión #154) — separación visual:
    //
    //   📋 "En banco del cliente (Redsys)" agrupa:
    //       • Payments (cobros via Redsys/datáfono real).
    //       • PaymentRefunds mode=rest (devoluciones que realmente movieron
    //         dinero en el extracto bancario del cliente).
    //
    //   📝 "Apuntes internos (no afectan al banco)" agrupa:
    //       • PaymentRefunds mode=manual (operador registra una devolución
    //         hecha por otro canal — el cliente ya recibió/recibirá el dinero
    //         por OTRA vía: portal banco, efectivo, transferencia, etc.).
    //
    // Cada sección solo se renderiza si tiene ≥ 1 entrada. El operador
    // distingue de un vistazo qué está en el banco vs qué es solo apunte.
    //
    // Sort key (orden temporal descendente):
    //   - Payment: `created_at`. Refund: `requested_at`.
    //   - Tiebreak: refund antes que payment (semánticamente posterior); id desc.
    $sortEvents = function ($a, $b): int {
        $cmp = $b->at <=> $a->at;
        if ($cmp !== 0) {
            return $cmp;
        }
        if ($a->type !== $b->type) {
            return $a->type === 'refund' ? -1 : 1;
        }

        return $b->id <=> $a->id;
    };

    $bankEvents = collect();
    $internalEvents = collect();
    foreach ($payments as $payment) {
        // Solo los cobros por Redsys mueven dinero "en banco del cliente" a través de
        // nuestra pasarela. Efectivo / datáfono son cobros MANUALES en el establecimiento
        // (no pasan por Redsys) → van a "apuntes" (Fase 7.3, #120).
        $paymentBucket = $payment->provider === 'redsys' ? $bankEvents : $internalEvents;
        $paymentBucket->push((object) [
            'type' => 'payment',
            'at' => $payment->created_at,
            'id' => $payment->id,
            'model' => $payment,
        ]);
        foreach ($payment->refunds as $refund) {
            $bucket = $refund->mode === PaymentRefund::MODE_REST ? $bankEvents : $internalEvents;
            $bucket->push((object) [
                'type' => 'refund',
                'at' => $refund->requested_at,
                'id' => $refund->id,
                'model' => $refund,
            ]);
        }
    }
    $bankEvents = $bankEvents->sort($sortEvents)->values();
    $internalEvents = $internalEvents->sort($sortEvents)->values();

    $fmtAmount = function (int $cents, string $currency): string {
        return \App\Support\Money::format($cents, $currency);
    };
@endphp

<div class="space-y-4">
    {{-- Link al portal admin Redsys del entorno activo. Sin deep-link a transacción
         específica: Redsys no lo soporta. El operativo loguea con sus credenciales y
         busca por `Ds_Merchant_Order` (= gateway_order, copiable abajo). --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-gray-50 p-3 text-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
        <span class="text-gray-600 dark:text-gray-400">
            {{ __('admin.orders.payments.portal_intro') }}
        </span>
        <a href="{{ $adminPanelUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 rounded-md bg-white px-2.5 py-1.5 text-xs font-medium text-gray-900 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/10 dark:hover:bg-white/5">
            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
            </svg>
            {{ __('admin.orders.payments.portal_link', ['env' => $isLive ? __('admin.orders.payments.env_live') : __('admin.orders.payments.env_test')]) }}
        </a>
    </div>

    {{-- Sub-sección 1: movimientos REALES en el banco del cliente (Redsys). --}}
    @if ($bankEvents->isNotEmpty())
        <section aria-labelledby="payments-bank-heading" class="space-y-2">
            <header class="flex items-center gap-2">
                <h3 id="payments-bank-heading" class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('admin.orders.payments.section_bank') }}
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('admin.orders.payments.section_bank_help') }}
                </span>
            </header>
            <div class="space-y-3">
                @foreach ($bankEvents as $event)
                    @if ($event->type === 'payment')
                        @include('filament.orders.partials.payment-event', ['payment' => $event->model, 'fmtAmount' => $fmtAmount])
                    @else
                        @include('filament.orders.partials.refund-event', ['refund' => $event->model, 'fmtAmount' => $fmtAmount])
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Sub-sección 2: apuntes internos (NO afectan al banco del cliente). --}}
    @if ($internalEvents->isNotEmpty())
        <section aria-labelledby="payments-internal-heading" class="space-y-2">
            <header class="flex items-center gap-2">
                <h3 id="payments-internal-heading" class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('admin.orders.payments.section_internal') }}
                </h3>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('admin.orders.payments.section_internal_help') }}
                </span>
            </header>
            <div class="space-y-3">
                @foreach ($internalEvents as $event)
                    @if ($event->type === 'payment')
                        @include('filament.orders.partials.payment-event', ['payment' => $event->model, 'fmtAmount' => $fmtAmount])
                    @else
                        @include('filament.orders.partials.refund-event', ['refund' => $event->model, 'fmtAmount' => $fmtAmount])
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Empty state global: ni cobros ni refunds. --}}
    @if ($bankEvents->isEmpty() && $internalEvents->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.orders.payments.empty') }}</p>
    @endif
</div>
