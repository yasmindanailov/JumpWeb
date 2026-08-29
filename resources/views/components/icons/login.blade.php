{{-- Entrar · DERIVADO de `cta/salir` del artboard `Iconos PJP` (§08 RESERVAS Y CUENTA).
     El artboard dibuja **salir**, no entrar: en su sistema son la misma puerta con la flecha al
     revés. Espejo por `transform`, igual que `arrow-left`, para no recalcular a mano dos trayectos
     con arcos dentro. --}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} viewBox="0 0 24 24" fill="currentColor"
     aria-hidden="true" focusable="false">
    <g transform="translate(24 0) scale(-1 1)">
        <path d="M11 3h-5A2.6 2.6 0 0 0 3.4 5.6v12.8A2.6 2.6 0 0 0 6 21h5a1.5 1.5 0 0 0 0-3H6.4V6H11a1.5 1.5 0 0 0 0-3z" />
        <path d="M16.4 6.9 14.3 9l2 2h-5.5a1.5 1.5 0 0 0 0 3h5.5l-2 2 2.1 2.1 5.1-5.6z" />
    </g>
</svg>
