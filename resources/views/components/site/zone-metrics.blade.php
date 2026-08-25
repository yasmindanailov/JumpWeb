@props(['zone'])

{{-- Las métricas de una zona (superficie · atracciones), COMPUESTAS UNA SOLA VEZ.

     ⚠️⚠️ **Una métrica sin dato NO se pinta, y por eso existe este componente** (`DECISIONES #144`).
     `zones.area_sqm` y `zones.rides_count` son NULLABLE y opcionales en el panel, y hasta el
     2026-08-25 las dos superficies hacían `number_format($zone->area_sqm, …)` directamente. Medido
     sobre la BD de desarrollo: **dos zonas visibles en la landing (`cap`, `cap2`) tenían las dos
     columnas a `null`, y la home servía «0 m²»** y la etiqueta «Atracciones» sin número. Un cliente
     que no rellena los m² de una zona no veía un hueco: veía un CERO, que afirma algo falso.
     ▶ Y no era solo cosmético: `number_format(null)` es un `DEPRECATED` en PHP 8 y un **TypeError en
     PHP 9** — hoy un aviso en el log, mañana un 500 en la portada.

     ⚠️ **Nace COMPARTIDO a propósito.** El defecto vivía en las DOS ramas del bucle de zonas
     —`.zone-intro__meta` (tarjeta sin foto) y `.zone-photo-card__meta` (con foto)— porque el mismo
     marcado estaba escrito dos veces. Arreglar una y no la otra era el resultado más probable.
     Es la misma lección de `ThemeSettings::zoneStyle()` (`#138`): una composición, N superficies
     que la pintan.

     ▶ Y el patrón ya existía en el producto: `heroStatus` vale `null` si no hay horario configurado
     y entonces la vista no pinta el chip. Aquí igual, incluido el contenedor. --}}

@php
    $metrics = collect([
        ['v' => $zone->area_sqm !== null ? number_format($zone->area_sqm, 0, ',', '.').' m²' : null,
            'l' => __('landing.zones.surface')],
        ['v' => $zone->rides_count !== null ? (string) $zone->rides_count : null,
            'l' => __('landing.zones.rides')],
    ])->whereNotNull('v');
@endphp

@if ($metrics->isNotEmpty())
    <div {{ $attributes }}>
        @foreach ($metrics as $metric)
            <div><span class="v">{{ $metric['v'] }}</span><span class="l">{{ $metric['l'] }}</span></div>
        @endforeach
    </div>
@endif
