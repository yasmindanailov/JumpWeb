{{-- La gemela de texto de la lista: su título y una línea por dato, con su guion. --}}
{!! implode("\n", array_merge(
    $b['titulo'] !== null && $b['titulo'] !== '' ? [$b['titulo']] : [],
    array_map(static fn ($l) => '- '.$correo->rico($l['texto'])['t'], $b['lineas']),
)) !!}
