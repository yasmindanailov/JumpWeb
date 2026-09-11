<x-layout :title="__('tickets.pay_redirecting_title')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap account">
        <x-site.page-head :eyebrow="__('account.orders.eyebrow')" :title="__('tickets.pay_redirecting_title')">
            <p class="account__intro">{{ __('tickets.order_code') }}: <strong>{{ $orderCode }}</strong></p>
        </x-site.page-head>

        {{-- Mismo patrón que el paso 9 del sidebar (#104): auto-POST al TPV Redsys.
             target="_top" rompe cualquier iframe; la tarjeta NO toca este servidor.
             Si JS está desactivado, fallback con botón visible (<noscript>). --}}
        <div class="purchase__redirecting" role="status" aria-live="polite">
            <p class="purchase__note">{{ __('tickets.pay_redirecting') }}</p>
            <form id="redsys-form"
                  action="{{ $redsysFormData['gatewayUrl'] }}"
                  method="POST"
                  target="_top"
                  x-data x-init="setTimeout(() => $el.submit(), 80)">
                <input type="hidden" name="Ds_SignatureVersion" value="{{ $redsysFormData['signatureVersion'] }}">
                <input type="hidden" name="Ds_MerchantParameters" value="{{ $redsysFormData['params'] }}">
                <input type="hidden" name="Ds_Signature" value="{{ $redsysFormData['signature'] }}">
                <noscript>
                    <button type="submit" class="btn btn--lg purchase__cta">
                        {{ __('tickets.pay_proceed_manual') }}
                    </button>
                </noscript>
            </form>
        </div>
    </main>

    <x-site.footer />
</div>
</x-layout>
