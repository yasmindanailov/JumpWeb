{{-- LOS PASOS (el `pasos()` del diseño): numerados en la hoja (tinta), cada uno con su frase y su botón —el del primero, el
     principal; los demás, claros—. Abren con su filete. El número va en su `span` con la clase de su letra: el oscuro cambia
     el fondo de la celda y la letra a la vez. --}}
@php($t = $correo->tema)
<tr data-bloque="pasos"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@if ($correo::conRaya($b))
@include('correo.piezas.filete'){!! $correo->hueco(24) !!}
@endif
@if ($b['titulo'] !== null && $b['titulo'] !== '')
<h2 class="pjm-strong" style="margin:0 0 16px;{{ $correo->ty('texto', 18, 24, 700, $t->claro('fuerte')) }}">{{ $b['titulo'] }}</h2>
@endif
<table {!! $correo::TABLA !!} width="100%">
@foreach ($b['pasos'] as $i => $p)
<tr><td width="40" valign="top" style="width:40px;padding-top:{{ $i ? 20 : 0 }}px;"><table {!! $correo::TABLA !!} style="border-collapse:separate;"><tr><td class="pjm-tile" width="28" height="28" align="center" valign="middle" bgcolor="{{ $t->claro('hoja') }}" style="width:28px;height:28px;background:{{ $t->claro('hoja') }};border-radius:{{ $t->radio('pildora') }}px;"><span class="pjm-tile-t" style="{{ $correo->ty('texto', 14, 28, 700, $t->claro('hoja-letra')) }}">{{ $i + 1 }}</span></td></tr></table></td>
<td valign="top" style="padding-top:{{ $i ? 20 : 0 }}px;"><p class="pjm-body" style="margin:0 0 12px;padding-top:2px;{{ $correo->ty('texto', 16, 24, 400, $t->claro('cuerpo')) }}">{!! $correo->rico($p['texto'])['h'] !!}</p>@include('correo.piezas.boton', ['texto' => $p['boton'], 'url' => $p['url'], 'secundario' => $i > 0])</td></tr>
@endforeach
</table>
</td></tr>
