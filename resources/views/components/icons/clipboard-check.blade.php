@props([
    'width' => 22,
    'height' => 14,
])

{{-- Icono "clipboard + check de marca" (Crear cuenta / Registro).
     Adaptado del mockup `design_mockup/jerarquia-ctas.css` (.ic-reg).
     Distinto al tear-off para que el ojo distinga "acción cuenta" vs "acción compra".
     El trazo del check usa `--zone-1` (color de marca) para acento visual.
     Estilos en `public/css/site.css` (.ic-reg). --}}
<span {{ $attributes->class('ic-reg') }} aria-hidden="true">
    <svg viewBox="0 0 40 24" width="{{ $width }}" height="{{ $height }}" fill="none">
        <rect x="8" y="3" width="24" height="20" rx="2" />
        <rect x="15" y="1" width="10" height="4" rx="1" />
        <line x1="13" y1="10" x2="27" y2="10" class="thin" />
        <line x1="13" y1="14" x2="22" y2="14" class="thin" />
        <path class="accent" d="M 21 18 L 24 21 L 29 15" />
    </svg>
</span>
