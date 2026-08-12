@props([
    'width' => 50,
    'height' => 32,
])

{{-- Taco de entradas — icono de marca (set v2, E5). Cabecera «Entradas» del catálogo:
     representa la colección de tipos de entrada (la categoría), frente a la entrada
     individual (ticket-tear-off) de cada tarjeta. La de atrás se dispensa al hover de la
     cabecera del acordeón (e5-deal); reduced-motion la deja en reposo. Capa base `.icon`
     + animación en public/css/site.css. Decorativo → aria-hidden. --}}
<span {{ $attributes->class('icon ic-e5') }} aria-hidden="true">
    <svg viewBox="0 0 50 32" width="{{ $width }}" height="{{ $height }}">
        <g class="t-deal">
            <path d="M 12 4 L 46 4 L 46 7 A 2.2 2.2 0 0 0 46 11.4 L 46 16 L 12 16 L 12 11.4 A 2.2 2.2 0 0 0 12 7 Z" />
        </g>
        <path class="occ" d="M 8 9 L 42 9 L 42 12 A 2.2 2.2 0 0 0 42 16.4 L 42 21 L 8 21 L 8 16.4 A 2.2 2.2 0 0 0 8 12 Z" />
        <path class="occ" d="M 4 14 L 38 14 L 38 17 A 2.2 2.2 0 0 0 38 21.4 L 38 26 L 4 26 L 4 21.4 A 2.2 2.2 0 0 0 4 17 Z" />
        <path d="M 11 16.5 L 11 23.5" class="dashed" />
        <path d="M 15 18.5 L 33 18.5" />
        <path d="M 15 22 L 27 22" class="thin" />
    </svg>
</span>
