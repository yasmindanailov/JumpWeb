@props([
    'width' => 12,
    'height' => 10,
])

{{-- Check canónico (listas de «incluido», pasos completados). Trazo del proyecto
     (viewBox 12×10, stroke 2). `currentColor` + `aria-hidden` + `focusable="false"`
     (decorativo: el texto adyacente da el significado). --}}
<svg {{ $attributes->merge(['class' => 'check-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 12 10" fill="none"
     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    <path d="M1 5l3.5 3.5L11 1.5" />
</svg>
