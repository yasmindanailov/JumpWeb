{{-- Bebida · `ui/bebida` del artboard `Iconos PJP` (§02 SET UI).
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
    <path d="M17.6 1l2.4 1.3-4.2 5.4-2.4-1.3z" />
    <rect x="5.4" y="6.6" width="13.2" height="2.8" rx="1.4" />
    <path d="M6.6 10h10.8l-1.3 10.4a2.4 2.4 0 0 1-2.4 2.1h-3.4a2.4 2.4 0 0 1-2.4-2.1z" />
</svg>
