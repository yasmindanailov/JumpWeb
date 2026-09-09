{{-- Invitaciones · `ui/invitaciones` del artboard `Iconos PJP` (§02 SET UI).
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
    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.6 2.8h9a2 2 0 0 1 2 2v14.4a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V4.8a2 2 0 0 1 2-2zm.8 4.4v2.2h7.4V7.2zm0 4.6v2.2h7.4v-2.2zm0 4.6v2.2h4.6v-2.2z" />
    <path d="M20.4 2.8l.9 2.3 2.3.9-2.3.9-.9 2.3-.9-2.3-2.3-.9 2.3-.9z" />
</svg>
