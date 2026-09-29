{{-- La gemela de texto del aviso: el título y el texto. --}}
{!! $b['titulo'] !== '' ? $b['titulo']."\n" : '' !!}{!! $correo->rico($b['texto'])['t'] !!}
