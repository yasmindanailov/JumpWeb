{{-- EL TEXTO (el `texto()` del diseño): el cuerpo, un párrafo por línea, 16/24. Escapado, con la negrita `**así**`
     (`MailDocument::rico()`). --}}
<tr data-bloque="texto"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
@foreach ($b['lineas'] as $i => $linea)
<p class="pjm-body" style="margin:{{ $i ? 12 : 0 }}px 0 0;{{ $correo->ty('texto', 16, 24, 400, $correo->tema->claro('cuerpo')) }}">{!! $correo->rico($linea)['h'] !!}</p>
@endforeach
</td></tr>
