@props(['tickets', 'zones'])

{{-- Catálogo de entradas con switcher de zona (Fase 5.1).
     Reutiliza el switcher de zonas (.zone-tabs/.zone-tab) con label "Entradas {zona}";
     no re-tematiza la página (estado local priceZone). Solo muestra las entradas de la zona elegida. --}}
@php
    $grouped = $tickets->groupBy('zone_id');
    $shared = $tickets->whereNull('zone_id'); // entradas para ambas zonas (si las hubiera)
    $default = $zones->first()?->slug;
    // Card «Regístrate antes de venir» (QR): POR ZONA — si el grid de esa zona deja hueco (≤3 entradas →
    // +1 ≤ 4 columnas, el máximo de la sección) la card entra INLINE como una columna más, alineada con
    // las entradas; si lo llena (≥4) va como BANDA horizontal debajo, visible solo cuando esa zona está
    // activa. Solo con registro EXTERNO. El SVG del QR se genera UNA vez y se reutiliza en cada instancia.
    $hasReg = ! empty($site['registration_url'] ?? null);
    $regSvg = $hasReg ? \App\Support\QrCode::svg($site['registration_url']) : null;
    $fullZoneSlugs = []; // zonas cuyo grid llena las 4 columnas → la card va de banda (no inline)
@endphp

<div class="ticket-prices" x-data="{ priceZone: @js($default) }">
    <div class="ticket-prices__tabs">
        <div class="zone-tabs" role="tablist" aria-label="{{ __('landing.pricing.pick_zone') }}">
            @foreach ($zones as $zone)
                <button type="button" class="zone-tab zone-tab--{{ $zone->accent }}"
                    :class="priceZone === @js($zone->slug) && 'active'"
                    @click="priceZone = @js($zone->slug)"
                    role="tab" :aria-selected="priceZone === @js($zone->slug) ? 'true' : 'false'">{{ __('landing.pricing.tab') }} {{ $zone->tr('name') }}</button>
            @endforeach
        </div>
    </div>

    @foreach ($zones as $zone)
        @php $zoneCount = ($grouped->get($zone->id)?->count() ?? 0) + $shared->count(); @endphp
        @if ($hasReg && $zoneCount >= 4) @php $fullZoneSlugs[] = $zone->slug; @endphp @endif
        <div class="pricing__grid pricing__grid--{{ $zone->accent }}{{ $hasReg && $zoneCount <= 3 ? ' pricing__grid--with-reg' : '' }}" role="tabpanel"
            x-show="priceZone === @js($zone->slug)"
            @if (! $loop->first) style="display:none" @endif>
            @foreach ($grouped[$zone->id] ?? [] as $ticket)
                <x-site.price-card :ticket="$ticket" />
            @endforeach
            @foreach ($shared as $ticket)
                <x-site.price-card :ticket="$ticket" />
            @endforeach
            {{-- Con hueco (≤3 entradas): la card entra como COLUMNA natural del grid, alineada con las
                 entradas. El SVG se pasa ya generado (una vez) para no repetir el trabajo. --}}
            @if ($hasReg && $zoneCount <= 3)
                <x-site.registration-qr :svg="$regSvg" :inline="true" />
            @endif
        </div>
    @endforeach
    {{-- Zonas con el grid lleno (≥4 entradas): la card va como BANDA horizontal a ancho completo debajo,
         visible solo cuando una de esas zonas está activa (una sola card visible a la vez). --}}
    @if ($hasReg && ! empty($fullZoneSlugs))
        <div x-show="@js($fullZoneSlugs).includes(priceZone)" x-cloak>
            <x-site.registration-qr :svg="$regSvg" />
        </div>
    @endif

    {{-- Nota general (no por zona): calcetines antideslizantes obligatorios. Debajo de las cards;
         solo con catálogo (acompaña al grid de precios, no aparece suelta si no hay entradas). --}}
    @if ($tickets->isNotEmpty())
        <x-site.socks-note />
    @endif
</div>
