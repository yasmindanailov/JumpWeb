@props([
    'width' => 18,
    'height' => 18,
])

{{-- Glifo «crear cuenta» del set (armazón · tanda 2c·3).

     ⚠️ **Es la PAREJA de `user`, y eso no es estética: lo declara el sistema del cliente.** Su
     hoja de iconos dice, con estas palabras, que entrar y darse de alta no son lo mismo y que
     el segundo es «la convención, sin invención ninguna: misma cabeza y mismos hombros con el
     más separado abajo a la derecha». Aquí se respeta: cabeza y hombros IDÉNTICOS a `user`, y
     el más aparte.

     ⚠️ **Y NO sustituye al portapapeles en todos los casos.** El botón de la esquina sirve a dos
     destinos distintos según la instalación: si el parque tiene su propio **trámite de registro
     de acceso** (una URL externa), eso es un formulario y su glifo sigue siendo el portapapeles;
     si no lo tiene, el botón **crea una cuenta** y ese es éste. El icono sigue al DESTINO, no a
     la posición del botón. `armazon-y-menu.md` §4.7. --}}
<svg {{ $attributes->merge(['class' => 'user-plus-ico']) }}
     width="{{ $width }}" height="{{ $height }}" viewBox="0 0 24 24" fill="none"
     aria-hidden="true" focusable="false">
    <circle cx="10" cy="8" r="3.4" stroke="currentColor" stroke-width="1.7" />
    <path d="M3 19.5c0-3.4 3.1-5.6 7-5.6 1.3 0 2.5.25 3.5.68" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
    <path d="M18 14.5v6M15 17.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
</svg>
