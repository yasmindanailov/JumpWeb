{{-- Cerrar · `ui/cerrar` del artboard `Iconos PJP` (§02 SET UI).
     Rejilla **24**, área viva 20, masa mínima 3, **un solo color** por `currentColor`. Es la
     anatomía que declara el propio artboard (§01) y lo que §06 prohíbe romper: trazo por debajo
     de 3 y duotono dentro del glifo. Talla de trabajo, 24 (§05).
     ⚠️ El dibujo se **copia con un guion desde el artboard**, no se transcribe a mano: en este
     repo transcribir un asset desde el contexto ya corrompió un fichero en silencio
     (`armazon-y-menu.md` §9.7).
     ▶ **SUSTITUYE** al dibujo anterior, que era de otro idioma: trazo fino, y varios sobre
     otra rejilla. Si este dibujo cambia, la copia del cajón tiene que cambiar con él
     (`SidebarIconParityTest`).
--}}
<svg {{ $attributes->merge(['width' => 24, 'height' => 24]) }} viewBox="0 0 24 24" fill="currentColor"
     aria-hidden="true" focusable="false">
    <rect x="2.6" y="10.55" width="18.8" height="2.9" rx="1.45" transform="rotate(45 12 12)" />
    <rect x="2.6" y="10.55" width="18.8" height="2.9" rx="1.45" transform="rotate(-45 12 12)" />
</svg>
