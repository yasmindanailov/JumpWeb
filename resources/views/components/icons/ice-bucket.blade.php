{{-- Cubo de refrescos · `ui/cubo` del artboard `Iconos PJP` (§02 SET UI).
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
    <rect x="6.6" y="0.8" width="2.8" height="4.2" rx="1.2" />
    <rect x="13.8" y="0.8" width="2.8" height="4.2" rx="1.2" />
    <rect x="2.2" y="5.4" width="19.6" height="2.8" rx="1.4" />
    <path d="M3.8 9.4h16.4l-1.6 10.6a2.4 2.4 0 0 1-2.4 2.1H7.8a2.4 2.4 0 0 1-2.4-2.1z" />
</svg>
