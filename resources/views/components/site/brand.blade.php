{{-- **LA MARCA DEL SITIO** — el hueco por donde entra el logotipo de una instalación.

     Tres piezas, como el paquete de tema (`DECISIONES #143`), y con dos parece que funciona:
     el fichero **no se versiona** (es del cliente, no del producto), se carga **si existe**, y
     `deploy.sh` lo **excluye del `rsync --delete`** — sin esa exclusión el primer despliegue lo
     borra y la marca vuelve a ser texto, en silencio.

     ▶ **El suelo es el nombre en la fuente de rótulo**, que es lo que el producto sabe pintar sin
     saber nada del cliente. Un logotipo es marca, y la marca no vive en este repo.

     ⚠️ **El nombre accesible NO cambia entre las tres formas.** Es el único enlace que TODA página
     tiene: dejarlo sin nombre lo convierte en «enlace» a secas para quien navega por voz.

     ══ LA VARIANTE SOBRE TINTA (`#216`) ═══════════════════════════════════════════════════════
     ❗ **Un logotipo de TEXTO se adapta al fondo solo; una IMAGEN no.** Desde `#201` el menú es una
     superficie de tinta a pantalla completa y el armazón entra dentro declarando
     `data-surface="ink"`: con una sola imagen, el logotipo del cliente caía sobre negro con su
     propio contorno oscuro. El suelo de texto nunca tuvo ese problema porque hereda `--fg`.

     ▶ **Se sirven las DOS y elige el CSS**, no el JavaScript ni el servidor: la superficie es una
     decisión de cascada (`[data-surface]`) y el servidor no sabe si el menú está abierto. Cuesta
     una petición más —cacheada— y a cambio el cambio de superficie es instantáneo y funciona sin
     JavaScript.

     ⚠️⚠️ **Y con dos imágenes el nombre accesible se DUPLICA o se PIERDE, según cuál se oculte**:
     `display: none` saca el `alt` del árbol de accesibilidad, así que la que estuviera visible en
     tinta se quedaría muda. Por eso, cuando hay las dos, **ninguna lleva `alt` con texto** —van
     `aria-hidden`— y el nombre lo pone un `sr-only` que está SIEMPRE presente, en las dos
     superficies. Con una sola imagen se conserva el `alt` de siempre: es la forma más simple y no
     hay nada que arbitrar.

     ⚠️ `@filemtime` hace las DOS cosas en una sola llamada a disco —existencia y cache-busting—:
     devuelve `false` si no está, así que no hace falta un `file_exists` aparte. Mismo recurso que
     el layout con `client.css`. --}}
@props(['name'])

@php($clientLogo = @filemtime(public_path('img/client-logo.svg')))
@php($clientLogoInk = @filemtime(public_path('img/client-logo-ink.svg')))

@if ($clientLogo && $clientLogoInk)
    <img class="nav__brand-logo nav__brand-logo--paper"
         src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
         alt="" aria-hidden="true" />
    <img class="nav__brand-logo nav__brand-logo--ink"
         src="{{ asset('img/client-logo-ink.svg') }}?v={{ $clientLogoInk }}"
         alt="" aria-hidden="true" />
    <span class="sr-only">{{ $name }}</span>
@elseif ($clientLogo)
    {{-- ⚠️⚠️ **EL LOGOTIPO SE SIRVE EN LÍNEA, y es una decisión con coste** (`#254`,
         `[DECIDIDO owner]`: «sí, hacemos la animación del logo»).

         ▶ **Un `<img>` no se puede animar por dentro.** El relevo que hace el mockup —la silueta
         entra de un salto y se queda como letra— necesita alcanzar una pieza CONCRETA del dibujo, y
         eso solo existe si el SVG forma parte del documento. El de este cliente ya trae la pieza
         (`id="fig"`, la silueta), así que **no hubo que pedir ningún asset nuevo**: lo que cambia
         es cómo se sirve.

         ⚠️ **Coste medido: ~64 KB de marcado (~15 KB comprimidos) en el armazón de las doce
         vistas**, y se paga en TODAS aunque solo la portada tenga coreografía. Es el precio de la
         animación y el owner lo aceptó con el número delante.

         ⚠️ **`|file_get_contents` sobre `public/img/` no es una plantilla**: el fichero lo pone el
         operador al instalar el paquete, igual que `client.css`. Verificado antes de inlinar que no
         trae `<script>` ni atributos `on*` — un SVG en línea SÍ los ejecutaría, y por `<img>` no.
         `InlineBrandLogoTest` lo vuelve a comprobar en cada suite, que es donde tiene que estar. --}}
    <span class="nav__brand-logo nav__brand-logo--inline" role="img" aria-label="{{ $name }}">
        {!! \App\Domain\Content\Services\InlineSvg::brand(public_path('img/client-logo.svg')) !!}
    </span>
@else
    <span class="nav__brand-row">{{ $name }}<span class="nav__period" aria-hidden="true"><span class="nav__period-dot"></span><span class="nav__period-block"></span></span></span>
@endif
