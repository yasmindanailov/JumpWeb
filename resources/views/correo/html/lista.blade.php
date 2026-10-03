{{-- LA LISTA (el `lista()` del diseño, ProofList en correo): una línea por dato, con su icono en el CÍRCULO (el mismo en los
     dos modos, como el diseño); la TAREA, en su aro sobre el lienzo y en negrita: lo que falta se ve sin leer. Sin icono,
     el punto. Abre con su filete salvo `raya: false`. Cada línea, con su negrita y sus enlaces por nombre (`rico()`). --}}
@php($t = $correo->tema)
<tr data-bloque="lista"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@if ($correo::conRaya($b))
@include('correo.piezas.filete'){!! $correo->hueco(24) !!}
@endif
@if ($b['titulo'] !== null && $b['titulo'] !== '')
<h2 class="pjm-strong" style="margin:0 0 12px;{{ $correo->ty('texto', 18, 24, 700, $t->claro('fuerte')) }}">{{ $b['titulo'] }}</h2>
@endif
<table {!! $correo::TABLA !!} width="100%">
@foreach ($b['lineas'] as $i => $l)
@php($ancho = $l['icono'] !== null ? 50 : 20)
<tr><td width="{{ $ancho }}" valign="top" style="width:{{ $ancho }}px;padding-top:{{ $i ? 12 : 0 }}px;">
@if ($l['icono'] !== null)
<table {!! $correo::TABLA !!} style="border-collapse:separate;"><tr><td width="36" height="36" align="center" valign="middle" bgcolor="{{ $t->claro($l['tarea'] ? 'lienzo-qr' : 'circulo') }}" style="width:{{ $l['tarea'] ? 32 : 36 }}px;height:{{ $l['tarea'] ? 32 : 36 }}px;background:{{ $t->claro($l['tarea'] ? 'lienzo-qr' : 'circulo') }};border-radius:{{ $t->radio('pildora') }}px;{{ $l['tarea'] ? 'border:2px solid '.$t->claro('punto').';' : '' }}font-size:0;line-height:0;"><table {!! $correo::TABLA !!} align="center"><tr><td>{!! $correo->icono($l['icono'], 18, rol: 'icono-circulo') !!}</td></tr></table></td></tr></table>
@else
<table {!! $correo::TABLA !!} style="border-collapse:separate;margin-top:8px;"><tr><td class="pjm-dot" width="8" height="8" bgcolor="{{ $t->claro('punto') }}" style="width:8px;height:8px;background:{{ $t->claro('punto') }};border-radius:{{ $t->radio('pildora') }}px;font-size:0;line-height:0;">&nbsp;</td></tr></table>
@endif
</td>
<td valign="{{ $l['icono'] !== null ? 'middle' : 'top' }}" class="{{ $l['tarea'] ? 'pjm-strong' : 'pjm-body' }}" style="padding-top:{{ $i ? 12 : 0 }}px;{{ $correo->ty('texto', 16, 23, $l['tarea'] ? 600 : 400, $t->claro($l['tarea'] ? 'fuerte' : 'cuerpo')) }}">{!! $correo->rico($l['texto'])['h'] !!}</td></tr>
@endforeach
</table>
</td></tr>
