{{-- La gemela de texto del resguardo: el de campos, «fecha · hora», «qué · precio», sus filas, el número y sus enlaces
     con su dirección; el de filas, «rótulo: valor», una por línea. --}}
@if (isset($b['dia']))
{!! implode("\n", array_merge(
    [$b['fecha'].' · '.$b['hora'], $b['que'].($b['precio'] !== null ? ' · '.$b['precio'] : '')],
    array_map(static fn ($f) => $f[0].': '.$f[1], $b['dinero']),
    [$b['codigo']],
    array_map(static fn ($l) => $l[0].': '.$l[1], $b['enlaces']),
)) !!}
@else
{!! implode("\n", array_map(static fn ($r, $v) => $r.': '.$v, array_keys($b['filas']), $b['filas'])) !!}
@endif
