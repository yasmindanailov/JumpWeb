{{-- Tarta · `ui/tarta` del artboard `Iconos PJP` (§02 SET UI).
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
    <circle cx="12" cy="2.2" r="1.4" />
    <rect x="10.9" y="4" width="2.2" height="4.4" rx="1.1" />
    <path d="M6.6 9h10.8a2 2 0 0 1 2 2v1.6H4.6V11a2 2 0 0 1 2-2z" />
    <path d="M4.2 14.2h15.6a2.4 2.4 0 0 1 2.4 2.4v2.8a2.4 2.4 0 0 1-2.4 2.4H4.2a2.4 2.4 0 0 1-2.4-2.4v-2.8a2.4 2.4 0 0 1 2.4-2.4z" />
</svg>
