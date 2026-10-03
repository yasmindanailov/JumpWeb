{{-- La gemela de texto de la sección: su titular, su frase y, si lo lleva, el botón con su dirección. --}}
{!! $b['titulo'] !!}
{!! $correo->rico($b['texto'])['t'] !!}{!! $b['boton'] !== null ? "\n".$b['boton']['texto'].': '.$b['boton']['url'] : '' !!}
