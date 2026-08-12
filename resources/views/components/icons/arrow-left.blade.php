@props([
    'width' => 16,
    'height' => 16,
])

{{-- Flecha izquierda canónica (volver / anterior). Espejo de `arrow-right`: mismo
     trazo (viewBox 24, stroke 2.2), `currentColor` + `aria-hidden` + `focusable="false"`.
     Unifica las flechas «atrás»/«prev» inline (compra, stepper de cumpleaños, slider). --}}
<svg {{ $attributes->merge(['class' => 'arrow-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    <line x1="19" y1="12" x2="5" y2="12" />
    <polyline points="12 19 5 12 12 5" />
</svg>
