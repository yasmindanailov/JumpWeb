{{-- LA SECCIÓN (el `seccion()` del diseño): un titular y una frase —«Si cambian los planes»—, con sus enlaces por nombre;
     con botón, uno claro debajo. Abre con su filete. --}}
@php($t = $correo->tema)
<tr data-bloque="seccion"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@if ($correo::conRaya($b))
@include('correo.piezas.filete'){!! $correo->hueco(24) !!}
@endif
<h2 class="pjm-strong" style="margin:0 0 8px;{{ $correo->ty('texto', 18, 24, 700, $t->claro('fuerte')) }}">{{ $b['titulo'] }}</h2>
<p class="pjm-body" style="margin:0;{{ $correo->ty('texto', 16, 24, 400, $t->claro('cuerpo')) }}">{!! $correo->rico($b['texto'])['h'] !!}</p>
@if ($b['boton'] !== null)
{!! $correo->hueco(14) !!}@include('correo.piezas.boton', ['texto' => $b['boton']['texto'], 'url' => $b['boton']['url'], 'secundario' => true])
@endif
</td></tr>
