{{-- LAS PIEZAS, una debajo de otra, para decidir D-G4 (el selector de zona) y D-G5 (el precio por día)
     con datos reales. Sin hero: es una hoja de decisión. --}}
<x-layout title="Piezas del guion" :noindex="true">
<link rel="stylesheet" href="{{ asset('prototipos/guion.css') }}?v={{ @filemtime(public_path('prototipos/guion.css')) }}">
@php
    $socks = \App\Domain\Booking\Models\TicketType::with('prices.rateType')->where('is_active', true)->get()->first(fn ($t) => str_contains(\Illuminate\Support\Str::lower($t->tr('name')), 'calcet'));
    $entradas = $tickets->where('type', \App\Domain\Booking\Models\TicketType::TYPE_ENTRY);
@endphp
<div x-data="landing" class="pg" x-init="zone = zone || @js($zones->first()?->slug)">
    <x-site.nav />
    <main id="main" class="page wrap" style="padding-top: calc(var(--nav-cluster-h, 70px) + 48px)">
        <h1 class="rides__title">Piezas</h1>
        <p class="pg-q__answer">Cada pieza responde a una pregunta del visitante. Las tres formas de cada una, con los datos reales de la instalación. El selector de arriba gobierna los precios de abajo.</p>

        <section class="section pg-q" style="padding-top: 0">
            <h2 class="pg-q__ask">D-G4 · a</h2>
            <p class="pg-q__answer">La marca de altura: la regla de la entrada del parque, dibujada.</p>
            @include('prototipos.partials.zona', ['variante' => 'altura', 'precioPor' => $precioPor, 'rateNormal' => $rateNormal, 'tickets' => $entradas])
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">D-G4 · b</h2>
            <p class="pg-q__answer">Dos tarjetas con dato: las dos a la vez, cada una con edad, altura, precio y qué hay.</p>
            @include('prototipos.partials.zona', ['variante' => 'tarjetas', 'precioPor' => $precioPor, 'rateNormal' => $rateNormal, 'tickets' => $entradas])
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">D-G4 · c</h2>
            <p class="pg-q__answer">Pestañas: lo actual, unificado en un componente. Esconde una zona.</p>
            @include('prototipos.partials.zona', ['variante' => 'pestanas', 'precioPor' => $precioPor, 'rateNormal' => $rateNormal, 'tickets' => $entradas])
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">D-G5 · a</h2>
            <p class="pg-q__answer">La semana como tira, y los dos precios.</p>
            @include('prototipos.partials.precio', ['variante' => 'semana', 'tickets' => $entradas, 'socks' => $socks])
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">D-G5 · b</h2>
            <p class="pg-q__answer">Los dos precios, llanos.</p>
            @include('prototipos.partials.precio', ['variante' => 'dos', 'tickets' => $entradas, 'socks' => $socks])
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">D-G5 · c</h2>
            <p class="pg-q__answer">«Desde» con el precio de finde impreso, y el día se elige al reservar (el turno 4 del diseñador del cliente).</p>
            @include('prototipos.partials.precio', ['variante' => 'desde', 'tickets' => $entradas, 'socks' => $socks])
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">Los dos packs</h2>
            @include('prototipos.partials.packs')
        </section>
        <hr class="pg-hairline">
        <section class="section pg-q">
            <h2 class="pg-q__ask">Cómo funciona una visita</h2>
            @include('prototipos.partials.funciona')
        </section>
    </main>
    <x-site.footer />
    <span class="pg-stamp" aria-hidden="true">prototipo · piezas</span>
</div>
</x-layout>
