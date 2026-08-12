@props([
    'width' => 16,
    'height' => 16,
])

{{-- Flecha derecha canónica (avanzar / «ir a» / siguiente). Trazo dominante del
     proyecto: viewBox 24, stroke 2.2 (line + punta). `currentColor` + `aria-hidden`
     (decorativa: el texto del enlace/botón ya da el significado) + `focusable="false"`.
     Unifica las copias inline dispersas (events, servicios, compra, nav, slider). La
     familia tipográfica `cta-*__arrow` del header NO usa este componente (rasgo de diseño). --}}
<svg {{ $attributes->merge(['class' => 'arrow-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">
    <line x1="5" y1="12" x2="19" y2="12" />
    <polyline points="12 5 19 12 12 19" />
</svg>
