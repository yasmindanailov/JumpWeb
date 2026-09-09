{{-- Interruptor · `ui/switch` del artboard `Iconos PJP` (§02 SET UI).
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
    <path fill-rule="evenodd" clip-rule="evenodd" d="M8 6h8a6 6 0 0 1 0 12H8A6 6 0 0 1 8 6zm8 2.2a3.8 3.8 0 1 0 0 7.6 3.8 3.8 0 0 0 0-7.6z" />
</svg>
