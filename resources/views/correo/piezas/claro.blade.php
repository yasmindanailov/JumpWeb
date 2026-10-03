{{-- EL ENLACE CLARO (el `claro()` del diseño, Button quiet): una secundaria de 44 px en píldora, con su icono en línea —«Cómo
     llegar», «Añadir al calendario»—. La clase `pjm-strong` además de `pjm-quiet`: en oscuro cambian el fondo Y la letra.
     Recibe `texto`, `url` e `icono`. --}}
@php($t = $correo->tema)
<a class="pjm-quiet pjm-strong" href="{{ $url }}" target="_blank" style="display:inline-block;margin:0 8px 8px 0;padding:11px 16px;border:1px solid {{ $t->claro('callado-borde') }};border-radius:{{ $t->radio('pildora') }}px;background:{{ $t->claro('callado') }};text-decoration:none;white-space:nowrap;{{ $correo->ty('texto', 14, 20, 700, $t->claro('fuerte')) }}">{!! $correo->icono($icono, 16, enLinea: true) !!}{{ $texto }}</a>
