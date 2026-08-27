{{--
    Página 404 con la identidad del sitio (#216, rediseño rico #218 item 4).

    Usa el layout COMPLETO (nav/pie): un 404 significa «esta página no existe, pero el sitio
    funciona — aquí tienes por dónde seguir». El CTA de «Reservar» abre el sidecart como en el
    resto de la web; si las reservas están en pausa, el propio sidecart muestra el aviso de
    mantenimiento con los canales de contacto (#218, item 3).
--}}
<x-layout :title="__('site.e404_title')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="e404 wrap">
        <div class="e404__badge" aria-hidden="true">404</div>

        <div class="e404__head">
            <div class="eyebrow">{{ __('site.e404_eyebrow') }}</div>
            <h1 class="e404__title">{{ __('site.e404_title') }}</h1>
            <p class="e404__body">{{ __('site.e404_body') }}</p>
        </div>

        <div class="e404__cta">
            <a href="{{ url('/') }}" class="btn btn--zone btn--lg">{{ __('site.e404_home') }}</a>
            <button type="button" class="btn btn--lg" @click="$store.purchase.open()">{{ __('site.e404_book') }} →</button>
        </div>

        <nav class="e404__links" aria-label="{{ __('site.e404_popular') }}">
            <span class="e404__links-label">{{ __('site.e404_popular') }}</span>
            <ul>
                <li><a href="{{ route('cumpleanos') }}">{{ __('landing.nav.events') }}</a></li>
                <li><a href="{{ route('precios') }}">{{ __('landing.nav.tickets') }}</a></li>
                <li><a href="{{ route('normas') }}">{{ __('site.rules_eyebrow') }}</a></li>
                <li><a href="{{ route('contacto') }}">{{ __('site.contact_eyebrow') }}</a></li>
            </ul>
        </nav>
    </main>

    <x-site.footer />
</div>
</x-layout>
