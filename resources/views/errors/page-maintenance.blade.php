{{--
    Mantenimiento de UNA PÁGINA concreta (#218, item 1) — se sirve con estado 503.

    A diferencia del 503 de SITIO ENTERO (standalone), aquí solo está caída ESTA sección: usamos el
    layout COMPLETO (nav + pie) para que el visitante pueda navegar a las demás páginas, que sí
    funcionan. Mismo esqueleto que el 404 (identidad del sitio + CTA de volver al inicio).
--}}
<x-layout :title="__('site.page_maintenance.title')" :noindex="true">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap" style="min-height:52vh">
        <div class="page__head">
            <div class="eyebrow">{{ __('site.page_maintenance.eyebrow') }}</div>
            <h1 class="page__title">{{ __('site.page_maintenance.title') }}</h1>
        </div>

        <p class="page__body" style="max-width:60ch; margin-bottom:32px">{{ __('site.page_maintenance.body') }}</p>

        <div class="contact-quick__row">
            <a href="{{ url('/') }}" class="btn btn--lg">{{ __('site.e404_home') }}</a>
        </div>
    </main>

    <x-site.footer />
</div>
</x-layout>
