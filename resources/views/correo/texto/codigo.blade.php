{{-- La gemela de texto del código: «etiqueta: código» y, debajo, su nota. --}}
{!! $b['etiqueta'] !== '' ? $b['etiqueta'].': ' : '' !!}{!! $b['codigo'] !!}{!! $b['nota'] !== null ? "\n".$correo->rico($b['nota'])['t'] : '' !!}
