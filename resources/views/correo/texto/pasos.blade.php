{{-- La gemela de texto de los pasos: su título y cada paso numerado, con su botón y su dirección debajo. --}}
{!! implode("\n", array_merge(
    $b['titulo'] !== null && $b['titulo'] !== '' ? [$b['titulo']] : [],
    array_map(static fn ($p, $i) => ($i + 1).'. '.$correo->rico($p['texto'])['t']."\n   ".$p['boton'].': '.$p['url'], $b['pasos'], array_keys($b['pasos'])),
)) !!}
