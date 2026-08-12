@php
    use App\Models\Payment;
    use App\Domain\Platform\Services\DisplayTime;
    use App\Support\RedsysCardCodes;
    use App\Support\RedsysResponseCode;

    /** @var Payment $payment */
    /** @var \Closure $fmtAmount */

    $raw = is_array($payment->raw_response) ? $payment->raw_response : [];
    $dsResponse = $raw['Ds_Response'] ?? null;
    $dsResponseInt = $dsResponse !== null && ctype_digit((string) $dsResponse) ? (int) $dsResponse : null;
    $isAuthorized = $dsResponseInt !== null && $dsResponseInt >= 0 && $dsResponseInt <= 99;
    $reasonText = $dsResponse !== null
        ? ($isAuthorized
            ? __('admin.orders.payments.ds_response_authorized')
            : RedsysResponseCode::reasonText((string) $dsResponse))
        : null;

    $dsDate = isset($raw['Ds_Date']) ? urldecode((string) $raw['Ds_Date']) : null;
    $dsHour = isset($raw['Ds_Hour']) ? urldecode((string) $raw['Ds_Hour']) : null;
    $bankTimestamp = $dsDate ? trim($dsDate.' '.($dsHour ?? '')) : null;

    $brandRaw = $raw['Ds_Card_Brand'] ?? null;
    $brandLabel = RedsysCardCodes::brand($brandRaw !== null ? (string) $brandRaw : null);
    $countryRaw = $raw['Ds_Card_Country'] ?? null;
    $countryLabel = RedsysCardCodes::country($countryRaw !== null ? (string) $countryRaw : null);

    $cardParts = [];
    if ($brandRaw !== null && $brandRaw !== '') {
        $cardParts[] = $brandLabel ?? __('admin.orders.payments.card_brand_unknown', ['code' => $brandRaw]);
    }
    if ($countryRaw !== null && $countryRaw !== '') {
        $cardParts[] = $countryLabel ?? __('admin.orders.payments.card_country_unknown', ['code' => $countryRaw]);
    }
    $cardText = $cardParts ? implode(' · ', $cardParts) : null;
@endphp

<div @class([
    'rounded-xl ring-1 p-4',
    'bg-green-50 ring-green-600/20 dark:bg-green-400/10 dark:ring-green-400/30' => $payment->status === Payment::STATUS_PAID,
    'bg-amber-50 ring-amber-600/20 dark:bg-amber-400/10 dark:ring-amber-400/30' => in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED], true),
    'bg-red-50 ring-red-600/20 dark:bg-red-400/10 dark:ring-red-400/30' => $payment->status === Payment::STATUS_FAILED,
    'bg-gray-100 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10' => $payment->status === Payment::STATUS_REFUNDED,
])>
    {{-- Header: tipo + estado + provider en la izquierda; importe + fecha en la
         esquina superior derecha. Layout robusto contra contenido largo (#145):
         - Parent `flex items-start gap-3` (sin flex-wrap) → derecha nunca cae a
           una nueva fila por desbordamiento.
         - Izquierda `min-w-0 flex-1` → puede encogerse y envolver internamente
           cuando el contenido no cabe.
         - Derecha `flex-shrink-0` → mantiene su ancho intrínseco y queda anclada
           arriba a la derecha. --}}
    <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1 flex items-center gap-2 flex-wrap">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('admin.orders.payments.events.type_payment') }}
            </span>
            <span @class([
                'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                'bg-green-100 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30' => $payment->status === Payment::STATUS_PAID,
                'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30' => in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_AUTHORIZED], true),
                'bg-red-100 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300 dark:ring-red-400/30' => $payment->status === Payment::STATUS_FAILED,
                'bg-gray-200 text-gray-700 ring-gray-500/30 dark:bg-gray-800 dark:text-gray-300' => $payment->status === Payment::STATUS_REFUNDED,
            ])>
                {{ __('admin.orders.payments.status.'.$payment->status) }}
            </span>
            <span class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('admin.orders.payments.provider_'.$payment->provider) }}
            </span>
        </div>
        <div class="flex-shrink-0 text-right">
            <div class="text-base font-semibold text-gray-900 dark:text-white">
                {{ $fmtAmount((int) $payment->amount, $payment->currency) }}
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-500">
                {{ __('admin.orders.payments.attempt_at', ['when' => DisplayTime::format($payment->created_at, 'd/m/Y H:i')]) }}
            </div>
        </div>
    </div>

    {{-- Datos Redsys útiles para el operador. 1 columna para que valores largos respiren
         (la card vive en el lado izquierdo del detalle). --}}
    <dl class="mt-3 grid grid-cols-1 gap-y-2 text-sm">
        @if ($payment->gateway_order)
            <div class="flex items-center gap-2">
                <dt class="font-medium text-gray-600 dark:text-gray-400">{{ __('admin.orders.payments.gateway_order') }}:</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $payment->gateway_order }}</dd>
                <div x-data="{ copied: false }" class="ml-1">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $payment->gateway_order }}'); copied = true; setTimeout(() => copied = false, 1500)"
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

        @if ($payment->auth_code)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.auth_code') }}:</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100 inline ml-1">{{ $payment->auth_code }}</dd>
            </div>
        @endif

        @if ($dsResponse !== null)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.ds_response') }}:</dt>
                <dd class="inline ml-1">
                    <span class="font-mono text-gray-900 dark:text-gray-100">{{ $dsResponse }}</span>
                    @if ($reasonText)
                        <span class="text-gray-600 dark:text-gray-400">— {{ $reasonText }}</span>
                    @endif
                </dd>
            </div>
        @endif

        @if ($bankTimestamp)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.bank_timestamp') }}:</dt>
                <dd class="font-mono text-gray-900 dark:text-gray-100 inline ml-1">{{ $bankTimestamp }}</dd>
            </div>
        @endif

        @if ($cardText)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.card_brand') }}:</dt>
                <dd class="text-gray-900 dark:text-gray-100 inline ml-1">{{ $cardText }}</dd>
            </div>
        @endif

        @if ($payment->paid_at)
            <div>
                <dt class="font-medium text-gray-600 dark:text-gray-400 inline">{{ __('admin.orders.payments.paid_at') }}:</dt>
                <dd class="text-gray-900 dark:text-gray-100 inline ml-1">{{ DisplayTime::format($payment->paid_at, 'd/m/Y H:i') }}</dd>
            </div>
        @endif
    </dl>
</div>
