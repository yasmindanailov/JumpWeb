{{-- La gemela de texto de la línea. --}}
{!! implode("\n", array_map(static fn ($l) => $correo->rico($l)['t'], $b['lineas'])) !!}
