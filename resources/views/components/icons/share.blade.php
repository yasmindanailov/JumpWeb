{{-- Compartir · `ui/compartir` del artboard `Iconos PJP` (§02 SET UI).
     Rejilla **24**, área viva 20, masa mínima 3, **un solo color** por `currentColor`. Es la
     anatomía que declara el propio artboard (§01) y lo que §06 prohíbe romper: trazo por debajo
     de 3 y duotono dentro del glifo. Talla de trabajo, 24 (§05).
     ⚠️ El dibujo se **copia con un guion desde el artboard**, no se transcribe a mano: en este
     repo transcribir un asset desde el contexto ya corrompió un fichero en silencio
     (`armazon-y-menu.md` §9.7).
--}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} viewBox="0 0 24 24" fill="currentColor"
     aria-hidden="true" focusable="false">
    <circle cx="18" cy="5.8" r="3.2" />
    <circle cx="6" cy="12" r="3.2" />
    <circle cx="18" cy="18.2" r="3.2" />
    <path d="M8.9 10.5l6.3-3.2M8.9 13.5l6.3 3.2" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
</svg>
