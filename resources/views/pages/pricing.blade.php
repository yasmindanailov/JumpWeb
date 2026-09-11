<x-layout :title="__('landing.nav.pricing')" :description="__('landing.pricing.intro')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        {{-- La cabecera del armazón (T3a·3). Antes era la cabecera VIEJA de sección
             (`.rides__head`), la última que quedaba en todo el sitio. --}}
        <x-site.page-head class="pricing__head" :title="__('landing.pricing.title')" :lede="__('landing.pricing.intro')">
            {{-- A3 · el abanico de rayos, QUIETO. Su regla es «uno por página», y éste es el de
                 `/precios`. Detrás del titular y NUNCA detrás de un párrafo (su regla 02). --}}
            <x-slot:deco><div class="rays pricing__rays" aria-hidden="true"></div></x-slot:deco>
        </x-site.page-head>

        <x-site.ticket-prices :tickets="$tickets" :zones="$zones" />

        <div class="page__cta">
            @if ($site['sales_online'])
                <a href="{{ route('entradas') }}" @click.prevent="$store.purchase.open()" class="btn btn--lg">{{ __('landing.reserve.cta') }} →</a>
            @elseif ($site['has_phone'])
                <a href="tel:{{ $site['phone_tel'] }}" class="btn btn--lg">{{ __('landing.pricing.call') }} · {{ $site['phone'] }}</a>
            @endif
            <a href="{{ route('cumpleanos') }}" class="btn btn--ghost btn--lg">{{ __('landing.nav.events') }}</a>
        </div>
    </main>

    <x-site.footer :closing="true" />
</div>
</x-layout>
