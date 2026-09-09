{{-- Tapas · `ui/tapas` del artboard `Iconos PJP` (§02 SET UI).
     Rejilla **24**, área viva 20, masa mínima 3, **un solo color** por `currentColor`. Es la
     anatomía que declara el propio artboard (§01) y lo que §06 prohíbe romper: trazo por debajo
     de 3 y duotono dentro del glifo. Talla de trabajo, 24 (§05).
     ⚠️ El dibujo se **copia con un guion desde el artboard** (`scripts/extraer-iconos.py`), no se
     transcribe a mano: en este repo transcribir un asset desde el contexto ya corrompió un fichero
     en silencio (`armazon-y-menu.md` §9.7).
     ▶ Del lote de trece que cerró el set en `DECISIONES #475`. Si este dibujo cambia, la copia
     del cajón tiene que cambiar con él (`SidebarIconParityTest`).
--}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} viewBox="0 0 24 24" fill="currentColor"
     aria-hidden="true" focusable="false">
    <circle cx="12" cy="3.6" r="1.4" />
    <path d="M12 5.8a8.4 8.4 0 0 1 8.4 8.4H3.6A8.4 8.4 0 0 1 12 5.8z" />
    <rect x="1.8" y="15.8" width="20.4" height="2.6" rx="1.3" />
</svg>
