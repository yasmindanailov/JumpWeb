<x-layout :title="__('landing.nav.pricing')" :description="__('landing.pricing.intro')">
<div x-data="landing">
    <x-site.nav />

    <main class="page wrap">
        <div class="rides__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.pricing.eyebrow') }}</div>
                <h1 class="rides__title">{{ __('landing.pricing.title') }}<br /><em style="font-style:normal; color:var(--zone-1)">{{ __('landing.pricing.title_em') }}</em></h1>
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
