{{-- FORMA A · CONVERSATIONAL FAQ — la portada SON las cuatro preguntas del visitante. Cada sección es una
     pregunta con su respuesta breve y la pieza que la contesta. Divisor: regla fina. Un solo CTA de relleno
     por pantalla. (`docs/specs/guion-de-la-portada.md` §3.1·A) --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="true" :has-hero="true">
<link rel="stylesheet" href="{{ asset('prototipos/guion.css') }}?v={{ @filemtime(public_path('prototipos/guion.css')) }}">
@php
    $schedule = app(\App\Domain\Content\Services\ScheduleDisplay::class);
    $socks = $tickets->first(fn ($t) => str_contains(\Illuminate\Support\Str::lower($t->tr('name')), 'calcet'))
        ?? \App\Domain\Booking\Models\TicketType::with('prices.rateType')->where('is_active', true)->get()->first(fn ($t) => str_contains(\Illuminate\Support\Str::lower($t->tr('name')), 'calcet'));
    $entradas = $tickets->where('type', \App\Domain\Booking\Models\TicketType::TYPE_ENTRY);
@endphp

<div x-data="landing" class="pg">
    <x-site.nav />
    <main id="main">
        @include('prototipos.partials.hero')

        {{-- 1 · ¿PUEDE SALTAR MI HIJO? --}}
        <section id="zones" class="section wrap pg-q">
            <h2 class="pg-q__ask pg-q__ask--pregunta">¿Puede saltar mi hijo?</h2>
            <p class="pg-q__answer">Dos zonas por <strong>edad y altura</strong>. Elige la suya y el resto de la página se ajusta.</p>
            @include('prototipos.partials.zona', ['variante' => $varZona, 'precioPor' => $precioPor, 'rateNormal' => $rateNormal, 'tickets' => $entradas])
        </section>
        <hr class="pg-hairline wrap">

        {{-- 2 · ¿CUÁNTO CUESTA EL DÍA QUE VOY? --}}
        <section id="pricing" class="section wrap pg-q">
            <h2 class="pg-q__ask pg-q__ask--pregunta">¿Cuánto cuesta el día que voy?</h2>
            <p class="pg-q__answer">Un precio de <strong>lunes a jueves</strong> y otro los <strong>viernes, fines de semana y festivos</strong>. Por persona; eliges cuánto tiempo.</p>
            @include('prototipos.partials.precio', ['variante' => $varPrecio, 'tickets' => $entradas, 'socks' => $socks])
        </section>
        <hr class="pg-hairline wrap">

        @php
            // Los dos bloques cuyo ORDEN se mide (D-G3): juegos y cumpleaños. `?orden=cumple` los permuta.
            $bloqueJuegos = 'prototipos.bloques.a-juegos';
            $bloqueCumple = 'prototipos.bloques.a-cumple';
        @endphp
        @if ($cumpleAntes)
            @include($bloqueCumple)
            @include($bloqueJuegos)
        @else
            @include($bloqueJuegos)
            @include($bloqueCumple)
        @endif

        {{-- 5 · ¿CÓMO FUNCIONA UNA VISITA? --}}
        <section id="rules" class="section wrap pg-q">
            <h2 class="pg-q__ask pg-q__ask--pregunta">¿Cómo funciona una visita?</h2>
            <p class="pg-q__answer">Cuatro pasos. Lo que hay que hacer <strong>antes</strong> se hace desde el móvil en dos minutos.</p>
            @include('prototipos.partials.funciona', ['conCumple' => $packages->isNotEmpty()])
            <p style="margin-top: var(--sp-16)"><a class="pg-link" href="{{ route('normas') }}">Todas las normas del parque →</a></p>
        </section>
        <hr class="pg-hairline wrap">

        {{-- 6 · ¿DÓNDE ESTÁIS? --}}
        <section id="info" class="section wrap pg-q">
            <h2 class="pg-q__ask pg-q__ask--pregunta">¿Dónde estáis y hasta qué hora?</h2>
            <x-site.visit :schedule="$schedule" />
        </section>
        <hr class="pg-hairline wrap">

        {{-- 7 · DUDAS --}}
        <section class="section wrap pg-q">
            <h2 class="pg-q__ask pg-q__ask--pregunta">¿Algo más?</h2>
            @include('prototipos.partials.dudas')
        </section>

        @include('prototipos.partials.cierre')
    </main>
    <x-site.footer />
    <div class="reserve__runway" aria-hidden="true"></div>
    <span class="pg-stamp" aria-hidden="true">prototipo · forma A · conversational faq · zona={{ $varZona }} · precio={{ $varPrecio }}</span>
</div>
</x-layout>
