{{-- FORMA B · SPLIT STUDIO — díptico alternado: a un lado la palabra (titular de una palabra, `#303`, y la
     respuesta), al otro la prueba (la pieza). La marca es «dos»: dos zonas, dos tipos de día, dos packs.
     (`docs/specs/guion-de-la-portada.md` §3.1·B) --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="true" :has-hero="true">
<link rel="stylesheet" href="{{ asset('prototipos/guion.css') }}?v={{ @filemtime(public_path('prototipos/guion.css')) }}">
@php
    $schedule = app(\App\Domain\Content\Services\ScheduleDisplay::class);
    $socks = \App\Domain\Booking\Models\TicketType::with('prices.rateType')->where('is_active', true)->get()->first(fn ($t) => str_contains(\Illuminate\Support\Str::lower($t->tr('name')), 'calcet'));
    $entradas = $tickets->where('type', \App\Domain\Booking\Models\TicketType::TYPE_ENTRY);
@endphp

<div x-data="landing" class="pg">
    <x-site.nav />
    <main id="main">
        @include('prototipos.partials.hero')

        <section id="zones" class="section wrap">
            <div class="pg-split">
                <div class="pg-split__text">
                    <h2 class="pg-q__ask">Zonas</h2>
                    <p class="pg-q__answer">Dos, por <strong>edad y altura</strong>. Los pequeños a la suya, los mayores a la suya, y cada una con sus juegos y sus precios.</p>
                </div>
                <div class="pg-split__proof">
                    @include('prototipos.partials.zona', ['variante' => $varZona, 'precioPor' => $precioPor, 'rateNormal' => $rateNormal, 'tickets' => $entradas])
                </div>
            </div>
        </section>

        <section id="pricing" class="section wrap">
            <div class="pg-split pg-split--flip">
                <div class="pg-split__text">
                    <h2 class="pg-q__ask">Precios</h2>
                    <p class="pg-q__answer">Uno de <strong>lunes a jueves</strong> y otro los <strong>viernes, fines de semana y festivos</strong>. Por persona, por el tiempo que elijas.</p>
                </div>
                <div class="pg-split__proof">
                    @include('prototipos.partials.precio', ['variante' => $varPrecio, 'tickets' => $entradas, 'socks' => $socks])
                </div>
            </div>
        </section>

        @if ($cumpleAntes)
            @include('prototipos.bloques.b-cumple')
            @include('prototipos.bloques.b-juegos')
        @else
            @include('prototipos.bloques.b-juegos')
            @include('prototipos.bloques.b-cumple')
        @endif

        <section id="rules" class="section wrap">
            <div class="pg-split pg-split--flip">
                <div class="pg-split__text">
                    <h2 class="pg-q__ask">Visita</h2>
                    <p class="pg-q__answer">Cómo funciona, en cuatro pasos. Lo de <strong>antes</strong> se hace desde el móvil en dos minutos.</p>
                    <p><a class="pg-link" href="{{ route('normas') }}">Todas las normas →</a></p>
                </div>
                <div class="pg-split__proof">
                    @include('prototipos.partials.funciona', ['conCumple' => $packages->isNotEmpty()])
                </div>
            </div>
        </section>

        <section id="info" class="section wrap">
            <div class="rides__head"><div><h2 class="rides__title">{{ __('landing.info.title') }}</h2></div></div>
            <x-site.visit :schedule="$schedule" />
        </section>

        <section class="section wrap">
            <div class="pg-split">
                <div class="pg-split__text"><h2 class="pg-q__ask">{{ __('landing.faq.title') }}</h2></div>
                <div class="pg-split__proof">@include('prototipos.partials.dudas')</div>
            </div>
        </section>

        @include('prototipos.partials.cierre')
    </main>
    <x-site.footer />
    <div class="reserve__runway" aria-hidden="true"></div>
    <span class="pg-stamp" aria-hidden="true">prototipo · forma B · split studio · zona={{ $varZona }} · precio={{ $varPrecio }}</span>
</div>
</x-layout>
