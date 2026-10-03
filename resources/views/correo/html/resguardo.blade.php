{{-- EL RESGUARDO (el `resguardo()` del diseño, BookingCard en correo), en sus DOS formas:
     · de CAMPOS (la R2, `BrandedMailMessage::slip()`, lleva `dia`): la hoja del calendario, la hora grande, qué y cuántos,
       el precio y el número; debajo, las filas de dinero (la que queda, en negrita) y dos enlaces claros, «Cómo llegar» y
       «Añadir al calendario»;
     · de FILAS (la R1, `hero()` con su resguardo de `EmailSlip`): rótulo → valor, en los correos que aún no tienen el suyo. --}}
@php($t = $correo->tema)
<tr data-bloque="resguardo"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<table {!! $correo::TABLA !!} width="100%" class="pjm-line" style="border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('lg') }}px;border-collapse:separate;">
@if (isset($b['dia']))
<tr><td style="padding:18px 18px 16px;"><table {!! $correo::TABLA !!} width="100%"><tr>
<td width="64" valign="middle" style="width:64px;">@include('correo.piezas.hoja', ['dia' => $b['dia'], 'chica' => false])</td>
<td width="16" style="width:16px;font-size:0;line-height:0;">&nbsp;</td>
<td valign="middle">
<p class="pjm-strong" style="margin:0;{{ $correo->ty('titular', 28, 30, 900, $t->claro('fuerte'), 'letter-spacing:-0.5px;font-variant-numeric:tabular-nums;') }}">{{ $b['hora'] }}</p>
<p class="pjm-strong" style="margin:0;padding-top:6px;{{ $correo->ty('texto', 16, 22, 700, $t->claro('fuerte')) }}">{{ $b['que'] }}</p>
@if ($b['precio'] !== null)
<p class="pjm-body" style="margin:0;padding-top:2px;{{ $correo->ty('texto', 14, 20, 400, $t->claro('cuerpo')) }}">{{ $b['precio'] }}</p>
@endif
<p class="pjm-muted" style="margin:0;padding-top:4px;{{ $correo->ty('mono', 13, 18, 500, $t->claro('apagado')) }}">{{ $b['codigo'] }}</p>
</td></tr></table></td></tr>
@if ($b['dinero'] !== [])
<tr><td style="padding:0 18px;">@include('correo.piezas.filete')</td></tr>
<tr><td style="padding:10px 18px 12px;"><table {!! $correo::TABLA !!} width="100%">
@foreach ($b['dinero'] as [$rotulo, $valor, $fuerte])
<tr><td class="{{ $fuerte ? 'pjm-strong' : 'pjm-body' }}" style="padding:4px 0;{{ $correo->ty('texto', 15, 22, $fuerte ? 700 : 400, $t->claro($fuerte ? 'fuerte' : 'cuerpo')) }}">{{ $rotulo }}</td><td class="pjm-strong" align="right" style="padding:4px 0 4px 12px;white-space:nowrap;{{ $correo->ty('texto', 15, 22, 700, $t->claro('fuerte'), 'font-variant-numeric:tabular-nums;') }}">{{ $valor }}</td></tr>
@endforeach
</table></td></tr>
@endif
@if ($b['enlaces'] !== [])
<tr><td style="padding:0 18px;">@include('correo.piezas.filete')</td></tr>
<tr><td style="padding:14px 18px 8px;">@foreach ($b['enlaces'] as [$texto, $url, $icono])@include('correo.piezas.claro', ['texto' => $texto, 'url' => $url, 'icono' => $icono])@endforeach</td></tr>
@endif
@else
<tr><td style="padding:10px 18px 12px;"><table {!! $correo::TABLA !!} width="100%">
@foreach ($b['filas'] as $rotulo => $valor)
<tr><td class="pjm-body" valign="top" style="padding:4px 0;{{ $correo->ty('texto', 15, 22, 400, $t->claro('cuerpo')) }}">{{ $rotulo }}</td><td class="pjm-strong" align="right" valign="top" style="padding:4px 0 4px 12px;{{ $correo->ty('texto', 15, 22, 700, $t->claro('fuerte'), 'font-variant-numeric:tabular-nums;') }}">{{ $valor }}</td></tr>
@endforeach
</table></td></tr>
@endif
</table>
</td></tr>
