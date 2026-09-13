{{-- ══ UN ICONO «DEL PARQUE» DEL KIT ═════════════════════════════════════════════════════════════
     `DECISIONES #582`. Los iconos del set de 24 que son ARTE de la instalación (`#475`) —calcetines,
     altura, saltador…— viajan en `client-kit.svg` como `slot-ico-*` y se pintan aquí.

     ▶ **Heredan el color del texto que acompañan** (`currentColor`), que es lo que el artboard dice de
     todo el set: la clasificación por colores de su hoja es presentación de la hoja, no del producto.
     ▶ **Sin ese dibujo en el kit se pinta el contenido que pase el llamante**, que es su suelo (el aviso
     de los calcetines conserva su «i»), o nada si no pasa ninguno.
     ⚠️ **Una clave `null` es una respuesta**, no un error: permite pedir el icono de una fila de un
     bucle con una clave que DEPENDE de la fila —y que para las demás no haya ninguno—, que es lo único
     que `FacadeDecorationIsPerScreenTest` admite dentro de un bucle. --}}
@props(['clave' => null])

@if ($clave !== null && \App\Domain\Content\Services\IllustrationKit::has($clave))
    <x-site.ilu :clave="$clave" :class="trim('kit-ico '.$attributes->get('class'))" />
@else
    {{ $slot }}
@endif
