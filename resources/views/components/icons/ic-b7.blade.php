@props([
    'size' => 48,
])

{{-- Cañón de confeti — icono de marca (set v2, B7). Momento «reserva confirmada»: recula y
     dispara trocitos (acento --zone-1). En reduced-motion queda en pose final. Capa base
     `.icon` + animación en public/css/site.css. Decorativo → aria-hidden. --}}
<span {{ $attributes->class('icon ic-b7') }} aria-hidden="true">
    <svg viewBox="0 0 40 40" width="{{ $size }}" height="{{ $size }}">
        <g class="pop">
            <path d="M 8 32 L 14.5 15.5 L 27.5 24.5 Z" />
            <path d="M 10.6 25.4 L 15.8 29" class="thin" />
            <path d="M 12.55 20.45 L 21.65 26.75" class="thin" />
        </g>
        <rect class="c1 accent-fill" x="26.5" y="9" width="2.4" height="2.4" rx="0.7" />
        <rect class="c2 filled" x="32.5" y="13.5" width="2" height="2" rx="0.6" />
        <circle class="c3 accent-fill" cx="30.5" cy="5.5" r="1.2" />
        <path d="M 22 13.5 L 25 10.5" class="thin" />
        <path d="M 27.5 19 L 31.5 17" class="thin" />
    </svg>
</span>
