@props([
    'width' => 20,
    'height' => 14,
])

{{-- Glifo «abrir menú» del set (armazón · tanda 2c·3). Tres trazos de igual longitud, remate
     redondo, un solo color. Estaba dibujado EN LÍNEA dentro del armazón: al set porque el dibujo
     es uno de los tres mecanismos del tema (`landing-white-label.md` §4.5, «dibujos→el set de
     iconos»), y un dibujo suelto en el marcado no lo puede sustituir un cliente.
     ▶ Traslado EXACTO: mismas coordenadas, mismo grosor, mismo remate. Cero píxeles. --}}
<svg {{ $attributes->merge(['class' => 'menu-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 20 14" fill="none"
     aria-hidden="true" focusable="false">
    <path d="M1 1h18M1 7h18M1 13h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
</svg>
