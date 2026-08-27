{{-- **LA MARCA DEL SITIO** — el hueco por donde entra el logotipo de una instalación.

     Tres piezas, como el paquete de tema (`DECISIONES #143`), y con dos parece que funciona:
     el fichero **no se versiona** (es del cliente, no del producto), se carga **si existe**, y
     `deploy.sh` lo **excluye del `rsync --delete`** — sin esa exclusión el primer despliegue lo
     borra y la marca vuelve a ser texto, en silencio.

     ▶ **El suelo es el nombre en la fuente de rótulo**, que es lo que el producto sabe pintar sin
     saber nada del cliente. Un logotipo es marca, y la marca no vive en este repo.

     ⚠️ **El nombre accesible NO cambia entre las dos formas.** Con imagen, el `alt` lleva el
     nombre del sitio; sin ella, lo lleva el texto. Un logotipo con `alt` vacío convertiría el
     enlace a la portada en un enlace sin nombre — y es el único enlace que TODA página tiene.

     ⚠️ `@filemtime` hace las DOS cosas en una sola llamada a disco —existencia y cache-busting—:
     devuelve `false` si no está, así que no hace falta un `file_exists` aparte. Mismo recurso que
     el layout con `client.css`. --}}
@props(['name'])

@php($clientLogo = @filemtime(public_path('img/client-logo.svg')))

@if ($clientLogo)
    <img class="nav__brand-logo"
         src="{{ asset('img/client-logo.svg') }}?v={{ $clientLogo }}"
         alt="{{ $name }}" />
@else
    <span class="nav__brand-row">{{ $name }}<span class="nav__period" aria-hidden="true"><span class="nav__period-dot"></span><span class="nav__period-block"></span></span></span>
@endif
