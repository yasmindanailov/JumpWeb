{{-- EL MARCADO de paso: el HTML que un correo compone él mismo —el libro del pedido (`EmailBookBlock`), la ficha de
     producto (`EmailProductCard`), el enlace de baja de los dos comerciales—, hasta que la R2 y la C1 les den su bloque.
     Con la tipografía del cuerpo alrededor (un texto suelto no puede caer en la fuente del navegador) y el ROL de enlace
     en cada `<a>` sin estilo (`MailDocument::marcado()`). --}}
<tr data-bloque="marcado"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<div class="pjm-body" style="{{ $correo->ty('texto', 15, 22, 400, $correo->tema->claro('cuerpo')) }}">{!! $correo->marcado($b['html']) !!}</div>
</td></tr>
