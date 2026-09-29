{{-- EL RESGUARDO de filas (el `resguardo()` del diseño, solo con sus filas): rótulo → valor, de `EmailSlip`. El de
     campos —la hoja del calendario, la hora grande, qué, el importe, el número y sus enlaces— llega con la R2, cuando
     `EmailSlip` los dé. --}}
@php($t = $correo->tema)
<tr data-bloque="resguardo"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<table {!! $correo::TABLA !!} width="100%" class="pjm-line" style="border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('lg') }}px;border-collapse:separate;">
<tr><td style="padding:10px 18px 12px;"><table {!! $correo::TABLA !!} width="100%">
@foreach ($b['filas'] as $rotulo => $valor)
<tr><td class="pjm-body" valign="top" style="padding:4px 0;{{ $correo->ty('texto', 15, 22, 400, $t->claro('cuerpo')) }}">{{ $rotulo }}</td><td class="pjm-strong" align="right" valign="top" style="padding:4px 0 4px 12px;{{ $correo->ty('texto', 15, 22, 700, $t->claro('fuerte'), 'font-variant-numeric:tabular-nums;') }}">{{ $valor }}</td></tr>
@endforeach
</table></td></tr>
</table>
</td></tr>
