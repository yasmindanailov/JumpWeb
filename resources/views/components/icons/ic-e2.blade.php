@props([
    'width' => 50,
    'height' => 32,
])

{{-- Par de entradas — icono de marca (set v2, E2). Icono del primario «Reservas aquí».
     La entrada de atrás (.tk-back) se abre en abanico al hover/focus del CTA (e2-fan);
     reduced-motion la deja en reposo (la pose base ya muestra el par). Capa base `.icon`
     + animación en public/css/site.css. Decorativo → aria-hidden. --}}
<span {{ $attributes->class('icon ic-e2') }} aria-hidden="true">
    <svg viewBox="0 0 50 32" width="{{ $width }}" height="{{ $height }}">
        <g class="tk-back">
            <path d="M 8 3 L 44 3 L 44 7.5 A 2.8 2.8 0 0 0 44 13.1 L 44 19 L 8 19 L 8 13.1 A 2.8 2.8 0 0 0 8 7.5 Z" />
        </g>
        <path class="occ" d="M 6 11 L 42 11 L 42 15.5 A 2.8 2.8 0 0 0 42 21.1 L 42 27 L 6 27 L 6 21.1 A 2.8 2.8 0 0 0 6 15.5 Z" />
        <path d="M 15 14 L 15 24" class="dashed" />
        <path d="M 19.5 16.5 L 36 16.5" />
        <path d="M 19.5 21 L 30 21" class="thin" />
    </svg>
</span>
