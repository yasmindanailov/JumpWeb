{{-- La gemela de texto del resguardo: «rótulo: valor», una fila por línea. --}}
{!! implode("\n", array_map(static fn ($r, $v) => $r.': '.$v, array_keys($b['filas']), $b['filas'])) !!}
