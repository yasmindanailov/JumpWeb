{{-- EL FILETE (la `regla()` del diseño): una línea de 1 px en el color del filete, con su clase de oscuro. --}}
<table {!! $correo::TABLA !!} width="100%"><tr><td class="pjm-rule" height="1" style="height:1px;line-height:1px;font-size:1px;background:{{ $correo->tema->claro('filete') }};">&nbsp;</td></tr></table>
