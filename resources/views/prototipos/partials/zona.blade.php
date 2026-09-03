@props(['zones', 'hechos', 'variante' => 'altura', 'precioPor' => null, 'rateNormal' => null, 'tickets' => null])

{{-- EL SELECTOR DE ZONA — un solo componente para toda la página (D-G4). Cambia `zone` del `x-data="landing"`
     que envuelve la portada; precios, packs y juegos leen ese mismo estado.
     Tres variantes: `altura` (la marca de altura), `tarjetas` (dos tarjetas con dato), `pestanas`. --}}
@php
    $conAltura = $zones->filter(fn ($z) => ($hechos[$z->slug]['min_height_cm'] ?? null) !== null);
    // La línea de la regla: la altura mínima más alta de las zonas que la declaran. Sin ninguna, la
    // marca de altura no se puede dibujar y la variante cae a las tarjetas.
    $lineaCm = $conAltura->max(fn ($z) => $hechos[$z->slug]['min_height_cm']);
    $edadTexto = function (array $h): string {
        if ($h['min_age'] === null) return $h['texto'];
        if ($h['max_age'] !== null) return "{$h['min_age']}–{$h['max_age']} años";
        return "desde {$h['min_age']} años";
    };
    $alturaTexto = fn (array $h): ?string => $h['min_height_cm'] ? sprintf('desde %d,%02d m', intdiv($h['min_height_cm'], 100), $h['min_height_cm'] % 100) : null;
    $desde = function ($z) use ($tickets, $precioPor, $rateNormal): ?int {
        if (! $tickets || ! $precioPor || ! $rateNormal) return null;
        return $tickets->where('zone_id', $z->id)->map(fn ($t) => $precioPor($t, $rateNormal))->filter()->min();
    };
    $var = ($variante === 'altura' && $lineaCm === null) ? 'tarjetas' : $variante;
@endphp

@if ($var === 'altura')
    @php
        // Escala de la regla: de 90 a 170 cm; la línea en $lineaCm. Las zonas se ordenan por altura
        // mínima (la que no la declara va debajo: es la de los pequeños).
        $min = 90; $max = 170;
        $pos = fn (int $cm): float => 100 - (($cm - $min) / ($max - $min)) * 100;
        $ordenadas = $zones->sortByDesc(fn ($z) => $hechos[$z->slug]['min_height_cm'] ?? 0)->values();
    @endphp
    <div class="pg-ruler" role="tablist" aria-label="Elige la zona por altura y edad">
        <div class="pg-ruler__bar" aria-hidden="true">
            @foreach ([170, 150, 110, 90] as $cm)
                <span class="pg-ruler__tick" style="top: {{ $pos($cm) }}%">{{ $cm }}</span>
            @endforeach
            <span class="pg-ruler__tick pg-ruler__tick--line" style="top: {{ $pos($lineaCm) }}%">{{ sprintf('%d,%02d m', intdiv($lineaCm, 100), $lineaCm % 100) }}</span>
        </div>
        <div class="pg-ruler__zones">
            @foreach ($ordenadas as $z)
                @php
                    $h = $hechos[$z->slug]; $d = $desde($z);
                    // ⚠️ Compuesto en PHP: un `@endif` pegado a una palabra no se compila (trampa del repo).
                    $meta = implode(' · ', array_filter([
                        $edadTexto($h),
                        $alturaTexto($h) ?? 'sin altura mínima',
                        $d ? 'desde '.\App\Domain\Platform\Services\Money::amount($d).' €' : null,
                    ]));
                @endphp
                <button type="button" class="pg-zbtn" role="tab" data-tap
                        style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($z->color, $z->color_secondary, $z->accent) }}"
                        :class="zone === @js($z->slug) && 'is-active'" :aria-selected="zone === @js($z->slug) ? 'true' : 'false'"
                        @click="setZone(@js($z->slug))">
                    <span class="pg-zbtn__ilu" aria-hidden="true"><x-site.ilu :clave="'zone-'.$z->slug" class="ilu" /></span>
                    <span>
                        <span class="pg-zbtn__name">{{ $z->tr('name') }}</span>
                        <span class="pg-zbtn__meta">{{ $meta }}</span>
                    </span>
                    <span class="pg-zbtn__go" aria-hidden="true">Elegir →</span>
                </button>
            @endforeach
        </div>
    </div>
    <p class="pg-provisional" style="margin-top: var(--sp-12)">dato provisional: edad y altura leídas del texto «{{ $zones->map(fn ($z) => $hechos[$z->slug]['texto'])->join('» · «') }}» (D-G6)</p>

@elseif ($var === 'tarjetas')
    <div class="pg-zcards" role="tablist" aria-label="Elige la zona">
        @foreach ($zones as $z)
            @php
                $h = $hechos[$z->slug]; $d = $desde($z);
                $meta = implode(' · ', array_filter([$edadTexto($h), $alturaTexto($h)]));
            @endphp
            <div class="pg-zcard">
                <button type="button" class="pg-zbtn" role="tab" data-tap
                        style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($z->color, $z->color_secondary, $z->accent) }}"
                        :class="zone === @js($z->slug) && 'is-active'" :aria-selected="zone === @js($z->slug) ? 'true' : 'false'"
                        @click="setZone(@js($z->slug))">
                    <span>
                        <span class="pg-zbtn__name">{{ $z->tr('name') }}</span>
                        <span class="pg-zbtn__meta">{{ $meta }}</span>
                    </span>
                    <span class="pg-zbtn__go" aria-hidden="true">Elegir →</span>
                </button>
                <ul class="pg-zcard__facts">
                    @if ($d)<li><b>desde {{ \App\Domain\Platform\Services\Money::amount($d) }} €</b> por persona, lunes a jueves</li>@endif
                    <li><b>{{ $z->attractions->count() }}</b> atracciones</li>
                    @if ($z->tr('subtitle'))<li>{{ $z->tr('subtitle') }}</li>@endif
                </ul>
            </div>
        @endforeach
    </div>

@else
    <div class="pg-tabs" role="tablist" aria-label="Elige la zona">
        @foreach ($zones as $z)
            <button type="button" class="pg-tab" role="tab" data-tap :class="zone === @js($z->slug) && 'is-active'"
                    :aria-selected="zone === @js($z->slug) ? 'true' : 'false'" @click="setZone(@js($z->slug))">{{ $z->tr('name') }} · {{ $edadTexto($hechos[$z->slug]) }}</button>
        @endforeach
    </div>
@endif
