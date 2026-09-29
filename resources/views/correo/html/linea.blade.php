{{-- LA LÍNEA (el `linea()` del diseño): lo que va después del botón —el cierre del molde (`outro()`) y la firma PROPIA
     de un correo—, 15/22, en la zona de los detalles. --}}
<tr data-bloque="linea"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@foreach ($b['lineas'] as $i => $linea)
<p class="pjm-body" style="margin:{{ $i ? 10 : 0 }}px 0 0;{{ $correo->ty('texto', 15, 22, 400, $correo->tema->claro('cuerpo')) }}">{!! $correo->rico($linea)['h'] !!}</p>
@endforeach
</td></tr>
