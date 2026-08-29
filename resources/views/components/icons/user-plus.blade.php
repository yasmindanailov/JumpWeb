{{-- Crear cuenta · `ui/registro` del artboard `Iconos PJP` (§02 SET UI).
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
<svg {{ $attributes->merge(['width' => 24, 'height' => 24, 'class' => 'user-plus-ico']) }} viewBox="0 0 24 24" fill="currentColor"
     aria-hidden="true" focusable="false">
    <circle cx="9" cy="8" r="3.6" />
    <path d="M2.6 19.4a6.4 6.4 0 0 1 12.8 0 1.2 1.2 0 0 1-1.2 1.2H3.8a1.2 1.2 0 0 1-1.2-1.2z" />
    <rect x="17.4" y="15.4" width="2.8" height="6" rx="1.4" />
    <rect x="15.8" y="17" width="6" height="2.8" rx="1.4" />
</svg>
