{{-- LA CABECERA (el `cabecera()` del diseño): la franja del parque si la hoja la declara (cuatro colores; sin hoja,
     ninguna), el logotipo, la chapa con el hecho del asunto y el titular. Sustituye al saludo: lo primero que se lee es el
     estado y el titular (`#503`).
     El logotipo, el de la instalación (`public/img/client-logo@4x.png`, gitignorado: es del cliente) con el nombre del
     NEGOCIO como `alt`; sin fichero, el nombre en texto. Lleva la UTM del correo y su marca de envío (`EmailUtm`). --}}
@php
    $t = $correo->tema;
    $franja = $t->franja();
    [$tonoFondo, $tonoLetra] = $correo->tono($b['tono']);
@endphp
<tr data-bloque="cabecera"><td style="padding:0;">
@if ($franja !== [])
<table {!! $correo::TABLA !!} width="100%"><tr>@foreach ($franja as $color)<td height="4" bgcolor="{{ $color }}" style="height:4px;line-height:4px;font-size:4px;background:{{ $color }};">&nbsp;</td>@endforeach</tr></table>
@endif
<table {!! $correo::TABLA !!} width="100%"><tr><td class="pjm-pad" style="padding:24px 24px {{ $b['aire'] }}px;">
@if ($correo->logoImagen !== null)
<a href="{{ $correo->logoUrl }}" target="_blank" style="display:inline-block;text-decoration:none;"><img class="pjm-strong" src="{{ $correo->logoImagen }}" width="136" alt="{{ $correo->pie->nombre }}" style="display:block;width:136px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;{{ $correo->ty('texto', 14, 18, 700, $t->claro('fuerte')) }}"></a>
@else
<a class="pjm-strong" href="{{ $correo->logoUrl }}" target="_blank" style="text-decoration:none;{{ $correo->ty('titular', 22, 26, 900, $t->claro('fuerte'), 'letter-spacing:-0.3px;') }}">{{ $correo->pie->nombre }}</a>
@endif
{!! $correo->hueco(26) !!}
@if ($b['chapa'] !== null)
<table {!! $correo::TABLA !!} style="border-collapse:separate;"><tr><td class="pjm-tono-{{ $b['tono'] }}" bgcolor="{{ $tonoFondo }}" style="background:{{ $tonoFondo }};border-radius:{{ $t->radio('pildora') }}px;padding:5px 12px 5px 10px;">
<table {!! $correo::TABLA !!}><tr><td valign="middle" style="padding-right:7px;"><table {!! $correo::TABLA !!} style="border-collapse:separate;"><tr><td class="pjm-tono-{{ $b['tono'] }}-p" width="8" height="8" bgcolor="{{ $tonoLetra }}" style="width:8px;height:8px;background:{{ $tonoLetra }};border-radius:{{ $t->radio('pildora') }}px;font-size:0;line-height:0;">&nbsp;</td></tr></table></td>
<td class="pjm-tono-{{ $b['tono'] }}-t" valign="middle" style="{{ $correo->ty('texto', 13, 18, 700, $tonoLetra) }}">{{ $b['chapa'] }}</td></tr></table>
</td></tr></table>
{!! $correo->hueco(14) !!}
@endif
@if ($b['titulo'] !== '')
<h1 class="pjm-h1 pjm-strong" style="margin:0;{{ $correo->ty('titular', 30, 34, 900, $t->claro('fuerte'), 'letter-spacing:-0.6px;') }}">{{ $b['titulo'] }}</h1>
@endif
</td></tr></table>
</td></tr>
