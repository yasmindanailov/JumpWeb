@props([
    'width' => 56,
    'height' => 56,
])

{{-- Calcetines antideslizantes — icono de marca (set v2, S1; mockup `design_mockup/Icono
     Calcetines.html`). Par de calcetines en el trazo del sistema (currentColor 1.6); el acento
     `--zone-1` va SOLO en los puntos de agarre (`.grip`) — justo lo que lo hace «antideslizante».
     El par rebota como en la cama elástica y los grips parpadean en secuencia; la pose base = el
     fotograma final, así que en reduced-motion (kill-switch de `.icon`) queda fijo y correcto.
     Capa base `.icon` + animación en `public/css/site.css`. Decorativo → aria-hidden. --}}
<span {{ $attributes->class('icon ic-s1') }} aria-hidden="true">
    <svg viewBox="0 0 40 40" width="{{ $width }}" height="{{ $height }}">
        <g class="sock-a">
            <path d="M 9 8 L 9 23 Q 9 29 13 29 L 19 29 Q 21.5 29 21.5 26 L 15 24 L 15 8 Z" />
            <path d="M 9 11.5 L 15 11.5" class="thin" />
            <path d="M 11 8 L 11 11.2" class="thin" />
            <path d="M 13 8 L 13 11.2" class="thin" />
            <circle class="grip g1" cx="13.6" cy="27.6" r="0.95" />
            <circle class="grip g2" cx="16.2" cy="27.9" r="0.95" />
            <circle class="grip g3" cx="18.8" cy="27.4" r="0.95" />
        </g>
        <g class="sock-b">
            <path d="M 23 8 L 23 23 Q 23 29 27 29 L 33 29 Q 35.5 29 35.5 26 L 29 24 L 29 8 Z" />
            <path d="M 23 11.5 L 29 11.5" class="thin" />
            <path d="M 25 8 L 25 11.2" class="thin" />
            <path d="M 27 8 L 27 11.2" class="thin" />
            <circle class="grip g4" cx="27.6" cy="27.6" r="0.95" />
            <circle class="grip g5" cx="30.2" cy="27.9" r="0.95" />
            <circle class="grip g6" cx="32.8" cy="27.4" r="0.95" />
        </g>
    </svg>
</span>
