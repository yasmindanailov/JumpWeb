{{-- La gemela de texto del texto: una línea por párrafo, sin los asteriscos de la negrita. --}}
{!! implode("\n", array_map(static fn ($l) => $correo->rico($l)['t'], $b['lineas'])) !!}
