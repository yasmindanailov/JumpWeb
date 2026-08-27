@props([
    'width' => 18,
    'height' => 18,
])

{{-- Glifo «cerrar» del set (armazón · tanda 2c·3). Aspa de dos trazos, remate redondo.
     Mismo motivo que `menu`: estaba dibujado en línea y al set se puede sustituir por
     instalación. ▶ Traslado EXACTO desde el cierre del cajón: cero píxeles. --}}
<svg {{ $attributes->merge(['class' => 'close-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 18 18" fill="none"
     aria-hidden="true" focusable="false">
    <path d="M3 3l12 12M15 3L3 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
</svg>
