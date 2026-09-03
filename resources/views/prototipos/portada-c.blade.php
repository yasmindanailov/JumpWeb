{{-- FORMA C · MAP / DIAGRAM — un esquema del parque organiza «qué hay dentro»: las dos zonas como áreas
     con sus juegos dentro, la entrada (taquilla · registro · calcetines) y la sala de cumpleaños. El esquema
     ES el selector de zona. ⚠️ No es un plano a escala: no existe ese dato (la maqueta isométrica del canvas
     está fuera de alcance) — es un DIAGRAMA generado desde zonas y atracciones reales.
     (`docs/specs/guion-de-la-portada.md` §3.1·C) --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="true" :has-hero="true">
<link rel="stylesheet" href="{{ asset('prototipos/guion.css') }}?v={{ @filemtime(public_path('prototipos/guion.css')) }}">
@php
    $schedule = app(\App\Domain\Content\Services\ScheduleDisplay::class);
    $socks = \App\Domain\Booking\Models\TicketType::with('prices.rateType')->where('is_active', true)->get()->first(fn ($t) => str_contains(\Illuminate\Support\Str::lower($t->tr('name')), 'calcet'));
    $entradas = $tickets->where('type', \App\Domain\Booking\Models\TicketType::TYPE_ENTRY);

    // Geometría del esquema (unidades del viewBox 1200×640): las zonas se reparten el ancho por
    // número de atracciones; la franja de abajo es la entrada; arriba, la sala de cumpleaños.
    $W = 1200; $H = 640; $pad = 24; $top = 120; $bottom = 540;
    $total = max(1, $zones->sum(fn ($z) => max(3, $z->attractions->count())));
    $x = $pad; $areas = [];
    foreach ($zones as $z) {
        $w = ($W - 2 * $pad - $pad * ($zones->count() - 1)) * max(3, $z->attractions->count()) / $total;
        $areas[$z->slug] = ['x' => $x, 'w' => $w, 'y' => $top, 'h' => $bottom - $top];
        $x += $w + $pad;
    }
    $edad = function ($slug) use ($hechos): string {
        $h = $hechos[$slug]; if ($h['min_age'] === null) return $h['texto'];
        $t = $h['max_age'] !== null ? "{$h['min_age']}–{$h['max_age']} años" : "desde {$h['min_age']} años";
        return $h['min_height_cm'] ? $t.sprintf(' · desde %d,%02d m', intdiv($h['min_height_cm'], 100), $h['min_height_cm'] % 100) : $t;
    };
@endphp

<div x-data="landing" class="pg">
    <x-site.nav />
    <main id="main">
        @include('prototipos.partials.hero')

        {{-- EL ESQUEMA: qué hay dentro, dónde, y para quién --}}
        <section id="zones" class="section wrap">
            <div class="rides__head">
                <div><h2 class="rides__title">El parque</h2></div>
                <p>Dos zonas por <strong>edad y altura</strong>, la entrada con taquilla y registro, y la sala de cumpleaños. Pulsa una zona: sus precios y sus juegos bajan contigo.</p>
            </div>
            <svg class="pg-map" viewBox="0 0 {{ $W }} {{ $H }}" role="group" aria-label="Esquema del parque: zonas, entrada y sala de cumpleaños">
                {{-- sala de cumpleaños --}}
                <g class="pg-map__fixed">
                    <rect x="{{ $pad }}" y="{{ $pad }}" width="{{ $W - 2 * $pad }}" height="{{ $top - $pad * 2 }}" rx="16" />
                    <text x="{{ $W / 2 }}" y="{{ $pad + ($top - $pad * 2) / 2 + 4 }}" text-anchor="middle">Sala de cumpleaños · un pack por zona · reserva con señal</text>
                </g>
                {{-- zonas --}}
                @foreach ($zones as $z)
                    @php $a = $areas[$z->slug]; $n = $z->attractions->count(); $cols = max(2, (int) ceil(sqrt($n * ($a['w'] / max(1, $a['h']))))); $rows = max(1, (int) ceil($n / $cols)); $cw = $a['w'] / $cols; $rh = ($a['h'] - 110) / max(1, $rows); @endphp
                    <g class="pg-map__zone" role="tab" tabindex="0"
                       style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($z->color, $z->color_secondary, $z->accent) }}"
                       :class="zone === @js($z->slug) && 'is-active'" :aria-selected="zone === @js($z->slug) ? 'true' : 'false'"
                       @click="setZone(@js($z->slug))" @keydown.enter.prevent="setZone(@js($z->slug))" @keydown.space.prevent="setZone(@js($z->slug))">
                        <rect x="{{ $a['x'] }}" y="{{ $a['y'] }}" width="{{ $a['w'] }}" height="{{ $a['h'] }}" rx="24" />
                        <text class="pg-map__name" x="{{ $a['x'] + 28 }}" y="{{ $a['y'] + 56 }}">{{ $z->tr('name') }}</text>
                        <text class="pg-map__meta" x="{{ $a['x'] + 28 }}" y="{{ $a['y'] + 82 }}">{{ $edad($z->slug) }} · {{ $n }} atracciones</text>
                        @foreach ($z->attractions as $i => $ride)
                            @php $cx = $a['x'] + $cw * ($i % $cols) + $cw / 2; $cy = $a['y'] + 110 + $rh * intdiv($i, $cols) + $rh / 2; @endphp
                            <g class="pg-map__node">
                                <circle cx="{{ $cx }}" cy="{{ $cy - 10 }}" r="9" />
                                <text x="{{ $cx }}" y="{{ $cy + 14 }}" text-anchor="middle">{{ \Illuminate\Support\Str::limit($ride->tr('name'), 18, '…') }}</text>
                            </g>
                        @endforeach
                    </g>
                @endforeach
                {{-- la entrada --}}
                <g class="pg-map__fixed">
                    <rect x="{{ $pad }}" y="{{ $bottom + $pad }}" width="{{ $W - 2 * $pad }}" height="{{ $H - $bottom - $pad * 2 }}" rx="16" />
                    <text x="{{ $W / 2 }}" y="{{ $bottom + $pad + ($H - $bottom - $pad * 2) / 2 + 4 }}" text-anchor="middle">Entrada · taquilla · registro y descargo (una vez) · calcetines antideslizantes (+2 €)</text>
                </g>
            </svg>
            <div class="pg-map__legend">
                <span class="pg-note">Esquema, no plano a escala</span>
                <span class="pg-provisional">edad y altura leídas del texto (D-G6)</span>
            </div>
        </section>

        <section id="pricing" class="section wrap">
            <div class="rides__head"><div><h2 class="rides__title">Precios</h2></div><p>Por persona. Uno de <strong>lunes a jueves</strong> y otro los <strong>viernes, fines de semana y festivos</strong>.</p></div>
            @include('prototipos.partials.precio', ['variante' => $varPrecio, 'tickets' => $entradas, 'socks' => $socks])
        </section>

        <section class="section wrap">
            <div class="rides__head"><div><h2 class="rides__title">Juegos</h2></div><p x-text="'Los de la zona ' + ({{ \Illuminate\Support\Js::from($zones->mapWithKeys(fn ($z) => [$z->slug => $z->tr('name')])) }})[zone] + '.'"></p></div>
            @include('prototipos.partials.juegos')
        </section>

        @if ($packages->isNotEmpty())
        <section id="events" class="section wrap">
            <div class="rides__head"><div><h2 class="rides__title">Cumpleaños</h2></div><p>Un pack por zona, lado a lado. La señal guarda la fecha; los nombres, después.</p></div>
            @include('prototipos.partials.packs')
        </section>
        @endif

        <section id="rules" class="section wrap">
            <div class="rides__head"><div><h2 class="rides__title">Visita</h2></div><p>Cómo funciona, en cuatro pasos. <a class="pg-link" href="{{ route('normas') }}">Todas las normas →</a></p></div>
            @include('prototipos.partials.funciona', ['conCumple' => $packages->isNotEmpty()])
        </section>

        <section id="info" class="section wrap">
            <div class="rides__head"><div><h2 class="rides__title">{{ __('landing.info.title') }}</h2></div></div>
            <x-site.visit :schedule="$schedule" />
        </section>

        <section class="section wrap">
            <div class="faq"><div><h2 class="rides__title">{{ __('landing.faq.title') }}</h2></div>@include('prototipos.partials.dudas')</div>
        </section>

        @include('prototipos.partials.cierre')
    </main>
    <x-site.footer />
    <div class="reserve__runway" aria-hidden="true"></div>
    <span class="pg-stamp" aria-hidden="true">prototipo · forma C · map / diagram · precio={{ $varPrecio }}</span>
</div>
</x-layout>
