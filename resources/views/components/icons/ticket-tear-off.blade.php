@props([
    'width' => 30,
    'height' => 22,
])

{{-- Icono "tear-off" (ticket con cuerpo, perforación troquelada y matriz numerada).
     Adaptado del mockup `design_mockup/jerarquia-ctas.css` (.tk).
     Usa `currentColor` → se integra en CTAs filled (sobre `--fg`) y ghost (sobre claro).
     Estilos en `public/css/site.css` (.tk). --}}
<span {{ $attributes->class('tk') }} aria-hidden="true">
    <svg viewBox="0 0 60 36" width="{{ $width }}" height="{{ $height }}" fill="none">
        <g class="body">
            <path d="M 4 4 L 42 4 L 42 8 A 1.4 1.4 0 0 0 42 12 L 42 16 A 1.4 1.4 0 0 0 42 20 L 42 24 A 1.4 1.4 0 0 0 42 28 L 42 32 L 4 32 L 4 28 A 1.4 1.4 0 0 0 4 24 L 4 20 A 1.4 1.4 0 0 0 4 16 L 4 12 A 1.4 1.4 0 0 0 4 8 Z" />
            <line x1="14" y1="14" x2="34" y2="14" class="thin" />
            <line x1="14" y1="20" x2="34" y2="20" class="thin" />
        </g>
        <path class="dashed" d="M 42 5.5 L 42 30.5" />
        <g class="stub">
            <path d="M 42 4 L 56 4 L 56 8 A 1.4 1.4 0 0 0 56 12 L 56 16 A 1.4 1.4 0 0 0 56 20 L 56 24 A 1.4 1.4 0 0 0 56 28 L 56 32 L 42 32 L 42 28 A 1.4 1.4 0 0 1 42 24 L 42 20 A 1.4 1.4 0 0 1 42 16 L 42 12 A 1.4 1.4 0 0 1 42 8 Z" />
            <text class="stubnum" x="49" y="22.5" text-anchor="middle">1</text>
        </g>
    </svg>
</span>
