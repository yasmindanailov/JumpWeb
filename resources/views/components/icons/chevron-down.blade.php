@props([
    'width' => 16,
    'height' => 16,
])

{{-- Chevron «desplegar» canónico (acordeones, dropdowns). viewBox 24, stroke 2,
     `currentColor` + `aria-hidden` + `focusable="false"`. La rotación al abrir la aporta
     el contexto (una clase de estado en el padre), no el componente. --}}
<svg {{ $attributes->merge(['class' => 'chevron-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    <polyline points="6 9 12 15 18 9" />
</svg>
