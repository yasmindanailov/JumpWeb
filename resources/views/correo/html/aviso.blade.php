{{-- EL AVISO (el `aviso()` del diseño, InfoCallout en correo): lo que hay que saber, sin alarma, en su tono. El molde de
     hoy le da título y texto (`notice()`): el título, en negrita encima. Su icono llega con la R1b. --}}
@php
    $t = $correo->tema;
    [$fondo] = $correo->tono($b['tono']);
@endphp
<tr data-bloque="aviso"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<table {!! $correo::TABLA !!} width="100%" style="border-collapse:separate;"><tr><td class="pjm-tono-{{ $b['tono'] }}" bgcolor="{{ $fondo }}" style="background:{{ $fondo }};border-radius:{{ $t->radio('md') }}px;padding:14px 16px;">
@if ($b['titulo'] !== '')
<p class="pjm-strong" style="margin:0 0 4px;{{ $correo->ty('texto', 15, 22, 700, $t->claro('fuerte')) }}">{{ $b['titulo'] }}</p>
@endif
<p class="pjm-strong" style="margin:0;{{ $correo->ty('texto', 15, 22, 400, $t->claro('fuerte')) }}">{!! $correo->rico($b['texto'])['h'] !!}</p>
</td></tr></table>
</td></tr>
