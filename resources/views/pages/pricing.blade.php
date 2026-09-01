<x-layout :title="__('landing.nav.pricing')" :description="__('landing.pricing.intro')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        <div class="rides__head">
            {{-- A3 · el abanico de rayos, QUIETO. Su regla es «uno por página», y éste es el de
                 `/precios`. Detrás del titular y NUNCA detrás de un párrafo (su regla 02). --}}
            <div class="rays pricing__rays" aria-hidden="true"></div>
            <div>
                <h1 class="rides__title">{{ __('landing.pricing.title') }}</h1>
            </div>
            <p>{{ __('landing.pricing.intro') }}</p>
        </div>

        <x-site.ticket-prices :tickets="$tickets" :zones="$zones" />

        <div class="page__cta">
            <a href="{{ route('entradas') }}" @click.prevent="$store.purchase.open()" class="btn btn--zone btn--lg">{{ __('landing.reserve.cta') }} →</a>
            <a href="{{ route('cumpleanos') }}" class="btn btn--ghost btn--lg">{{ __('landing.nav.events') }}</a>
        </div>
    </main>

    <x-site.footer />
</div>
</x-layout>
