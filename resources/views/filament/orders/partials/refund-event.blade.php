@php
    use App\Domain\Payments\Models\PaymentRefund;
    use App\Domain\Platform\Services\DisplayTime;
    use App\Domain\Payments\Services\RedsysResponseCode;

    /** @var PaymentRefund $refund */
    /** @var \Closure $fmtAmount */

    // Mismo patrón que la card de pago: extraemos los campos útiles del `raw_response`
    // que devolvió Redsys al hacer la devolución (#144).
    //  - `Ds_AuthorisationCode`: código autorización del banco para la devolución
    //    (distinto del auth_code del cobro original).
    //  - `Ds_Date` / `Ds_Hour`: timestamp del banco cuando proceó la devolución.
    //    Llegan URL-encoded; los decodificamos igual que en la card de cobro.
    $raw = is_array($refund->raw_response) ? $refund->raw_response : [];
    $refundAuthCode = $raw['Ds_AuthorisationCode'] ?? null;
    $rdsDate = isset($raw['Ds_Date']) ? urldecode((string) $raw['Ds_Date']) : null;
    $rdsHour = isset($raw['Ds_Hour']) ? urldecode((string) $raw['Ds_Hour']) : null;
    $bankTimestamp = $rdsDate ? trim($rdsDate.' '.($rdsHour ?? '')) : null;

    // Resultado del banco (decodificado al texto humano):
    //  - 0900 = devolución correcta (texto propio porque RedsysResponseCode no lo
    //    interpreta como "OK" sino como error).
    //  - cualquier otro código = pasa por RedsysResponseCode::reasonText.
    //  - MANUAL = sin código real (registro manual, sin REST).
    $code = $refund->gateway_response_code;
    $resultText = match (true) {
        $code === PaymentRefund::REDSYS_REFUND_SUCCESS_CODE
            => __('admin.orders.payments.refunds.code_refund_ok'),
        $code === PaymentRefund::MANUAL_RESPONSE_MARKER
            => null,
        $code !== null && $code !== ''
            => RedsysResponseCode::reasonText($code),
        default => null,
    };
    $isManual = $refund->mode === PaymentRefund::MODE_MANUAL;
    $isFailedTransport = $refund->status === PaymentRefund::STATUS_FAILED
        && $refund->failure_reason === PaymentRefund::FAILURE_TRANSPORT;

    // Sub-fase 7.2e.1bis5 (decisión #158, punto 6): nombre del producto/
    // complemento devuelto. Refunds por-item (`order_item_id` set) muestran
    // el nombre con cantidad — el operador identifica de un vistazo a qué
    // ítem físico corresponde la línea financiera. Refunds totales del Order
    // (#142, `order_item_id` null) muestran "Pedido completo".
    $refundedItem = $refund->orderItem;
    if ($refundedItem !== null) {
        // #F8: el importe del refund (amount_cents, mostrado arriba) ya es el dato
        // autoritativo de cuánto se devolvió. NO prefijamos con la cantidad del
        // item (`quantity` es el TOTAL del item, no lo reembolsado): en un refund
        // PARCIAL de un item con quantity>1 ese prefijo inducía a error.
        $refundedSubjectLabel = $refundedItem->ticketType?->tr('name')
            ?? __('admin.orders.payments.refunds.subject_item_missing');
    } else {
        $refundedSubjectLabel = __('admin.orders.payments.refunds.subject_full_order');
    }
@endphp

<div @class([
    'rounded-xl ring-1 p-4',
    // Coherencia visual con la card de cobro (#144): verde = éxito, ámbar = en curso,
    // rojo = fallo. La diferenciación entre "pago" y "devolución" la lleva el badge de
    // tipo del header.
    'bg-green-50 ring-green-600/20 dark:bg-green-400/10 dark:ring-green-400/30' => $refund->status === PaymentRefund::STATUS_SUCCEEDED,
    'bg-amber-50 ring-amber-600/20 dark:bg-amber-400/10 dark:ring-amber-400/30' => $refund->status === PaymentRefund::STATUS_PENDING,
    'bg-red-50 ring-red-600/20 dark:bg-red-400/10 dark:ring-red-400/30' => $refund->status === PaymentRefund::STATUS_FAILED,
])>
    {{-- Header: tipo "Devolución" + estado + modo a la izquierda; importe + fecha
         + operador en la esquina sup-derecha. Mismo layout robusto que payment-event
         (#145): la fila no se rompe por contenido largo (el bloque derecho queda
         anclado arriba y el izquierdo envuelve internamente). --}}
    <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('admin.orders.payments.events.type_refund') }}
            </span>
            <span @class([
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30' => $refund->status === PaymentRefund::STATUS_SUCCEEDED,
                'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30' => $refund->status === PaymentRefund::STATUS_PENDING,
                'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30' => $refund->status === PaymentRefund::STATUS_FAILED,
            ])>
                {{ __('admin.orders.payments.refunds.status.'.$refund->status) }}
            </span>
            <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('admin.orders.payments.refunds.mode_'.$refund->mode) }}
            </span>
        </div>
        <div class="flex-shrink-0 text-right">
            <div class="text-base font-semibold text-gray-900 dark:text-white">
                {{ $fmtAmount((int) $refund->amount_cents, $refund->currency) }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-500">
                {{ DisplayTime::format($refund->requested_at, 'd/m/Y H:i') }}
                @if ($refund->requestedBy)
                    · {{ $refund->requestedBy->name ?? $refund->requestedBy->email }}
                @endif
            </div>
        </div>
    </div>

    {{-- Datos Redsys útiles. Mismo esquema que el card de cobro para mantener
         consistencia visual: 1 columna, label + valor en monospace. --}}
    <dl class="mt-3 grid grid-cols-1 gap-y-2 text-sm">
        {{-- Sub-fase 7.2e.1bis5 (decisión #158, punto 6): producto/complemento
             devuelto. Primera fila para que sea lo primero que el operador ve
             al escanear la línea de devolución — la pregunta "¿de qué fue
             esta devolución?" es la más frecuente al reconciliar. --}}
        <div>
            <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.refunds.subject_label') }}:</dt>
            <dd class="text-gray-900 dark:text-gray-100 inline ml-1">{{ $refundedSubjectLabel }}</dd>
        </div>

        {{-- Nº pedido Redsys: el mismo que el del cobro original (Redsys lo reusa
             como ancla de la devolución). Visible incluso en modo manual para que
             el operador pueda reconciliar en el portal del banco. --}}
        @if ($refund->gateway_order)
            <div class="flex items-center gap-2">
                <dt class="font-medium text-gray-600 dark:text-gray-400">{{ __('admin.orders.payments.gateway_order') }}:</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $refund->gateway_order }}</dd>
                <div x-data="{ copied: false }" class="ml-1">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $refund->gateway_order }}'); copied = true; setTimeout(() => copied = false, 1500)"
                            class="inline-flex items-center gap-1 rounded-md bg-white px-1.5 py-0.5 text-xs font-medium text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:bg-white/5">
                        <svg x-show="!copied" class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/>
                        </svg>
                        <svg x-show="copied" x-cloak class="h-3 w-3 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                        <span x-text="copied ? '{{ __('admin.orders.payments.copied') }}' : '{{ __('admin.orders.payments.copy') }}'"></span>
                    </button>
                </div>
            </div>
        @endif

        {{-- Código autorización del banco para esta devolución (distinto del de cobro).
             Solo viene en respuestas REST exitosas; en manual no existe. --}}
        @if ($refundAuthCode)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.refunds.auth_code') }}:</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100 inline ml-1">{{ $refundAuthCode }}</dd>
            </div>
        @endif

        {{-- Resultado del banco. Para éxito muestra "0900 — Devolución correcta";
             para manual muestra texto explícito "apuntada como ya devuelta fuera";
             para fallo muestra motivo humano + código real entre paréntesis. --}}
        @if ($refund->status === PaymentRefund::STATUS_SUCCEEDED && ! $isManual && $code !== null)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.refunds.result_label') }}:</dt>
                <dd class="inline ml-1">
                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ $code }}</span>
                    @if ($resultText)
                        <span class="text-gray-600 dark:text-gray-400">— {{ $resultText }}</span>
                    @endif
                </dd>
            </div>
        @elseif ($refund->status === PaymentRefund::STATUS_SUCCEEDED && $isManual)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.refunds.result_label') }}:</dt>
                <dd class="text-gray-700 dark:text-gray-300 inline ml-1">{{ __('admin.orders.payments.refunds.result_manual') }}</dd>
            </div>
        @elseif ($refund->status === PaymentRefund::STATUS_FAILED)
            <div>
                <dt class="font-medium text-red-700 dark:text-red-400 inline">{{ __('admin.orders.payments.refunds.failure_label') }}:</dt>
                <dd class="inline ml-1 text-red-800 dark:text-red-300">
                    {{ __('admin.orders.payments.refunds.failure_reason.'.$refund->failure_reason) }}
                    @if ($code !== null && $code !== '' && $code !== PaymentRefund::MANUAL_RESPONSE_MARKER)
                        (<span class="font-mono">{{ $code }}</span>@if ($resultText) — {{ $resultText }}@endif)
                    @endif
                </dd>
            </div>
        @endif

        {{-- Timestamp del banco (cuando Redsys procesó la devolución). Solo aplica en
             modo REST con éxito o fallo gateway_denied. --}}
        @if ($bankTimestamp)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.bank_timestamp') }}:</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100 inline ml-1">{{ $bankTimestamp }}</dd>
            </div>
        @endif

        {{-- Cuándo lo registramos nosotros tras la respuesta de Redsys. Útil para
             distinguir el tiempo del banco vs el tiempo del panel. --}}
        @if ($refund->processed_at && $refund->status !== PaymentRefund::STATUS_PENDING)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.refunds.processed_at_label') }}:</dt>
                <dd class="text-gray-900 dark:text-gray-100 inline ml-1">{{ DisplayTime::format($refund->processed_at, 'd/m/Y H:i') }}</dd>
            </div>
        @endif
    </dl>

    {{-- Hint operativo permanente cuando hubo timeout/error de red. La instrucción
         es crítica (riesgo de doble devolución) y debe estar visible siempre, no
         solo en el banner momentáneo del refund. --}}
    @if ($isFailedTransport)
        <div class="mt-2 rounded-md bg-red-100/50 px-3 py-2 text-sm text-red-800 dark:bg-red-400/10 dark:text-red-300">
            {{ __('admin.orders.payments.refunds.transport_hint') }}
        </div>
    @endif
</div>
