{{-- Retroceder · DERIVADO de `ui/flecha-der` del artboard `Iconos PJP` (§02 SET UI).
     El artboard **no dibuja la flecha izquierda**: dibuja una y el par se obtiene por espejo. Aquí
     se hace con un `transform`, no recalculando coordenadas a mano — el espejo es exacto por
     construcción y no hay ninguna cifra que transcribir mal.
     ⚠️ La escala es de módulo 1, así que el grosor del trazo no cambia con el espejo. --}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"
     aria-hidden="true" focusable="false">
    <g transform="translate(24 0) scale(-1 1)">
        <path d="M13.6 6.4 19.2 12l-5.6 5.6z" />
        <path d="M4.6 12h9.4" fill="none" />
    </g>
</svg>
