<x-layout :title="__('landing.nav.events')" :description="$packages->first()?->tr('description')">
<div x-data="landing">
    <x-site.nav />

    @if ($packages->isEmpty())
        <main id="main" class="page wrap">
            <div class="page__head">
                <h1 class="page__title">{{ __('landing.events.title') }}</h1>
            </div>
            <div class="page__body">
                <p>{{ __('landing.events.coming_soon') }}</p>
            </div>
            <a href="{{ route('contacto') }}" class="btn btn--zone">{{ __('landing.events.coming_soon_cta') }}</a>
        </main>
    @else
        {{-- El componente trae sus propias secciones `.wrap`; `.bd-standalone` solo añade el
             despeje superior bajo el nav fijo (#231). Aquí SÍ va la tarjeta de invitación. --}}
        <main id="main" class="bd-standalone">
            <x-site.events-section :packages="$packages" :show-invite="true" :level="1" />
        </main>
    @endif

    <x-site.footer />
</div>
</x-layout>
