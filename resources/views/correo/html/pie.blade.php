{{-- EL PIE (el `pie()` del diseño; brief: «dirección, horario de hoy, teléfono, WhatsApp y correo»), del panel y del
     calendario (`MailPie`), cada línea solo si su dato existe. Con sus iconos (la R1b, `MailDocument::icono()`): la columna de
     28 px del `filaI()` del diseño para el sitio y el horario, y en línea en los tres enlaces; sin máscara, el hueco. «Responde
     a este correo», con la R2 (que responder llegue al parque); por qué lo recibe y la baja de un comercial, con la C1
     (`BrandedMailMessage::commercial()`), bajo el filete y antes de los enlaces de siempre, como el `pie({comercial})` del diseño.
     Y al final, discretos, los cuatro enlaces a la web del pie de siempre (privacidad, condiciones, cookies, contacto):
     el brief no los nombra y se QUEDAN (`[DECIDIDO owner]` 29-09), bajo un filete y en el color apagado, como la línea de
     baja de un comercial en el diseño. Con la UTM y la marca del envío (`MailDocument::legales()`). --}}
@php
    $t = $correo->tema;
    $p = $correo->pie;
    $enlaces = array_filter([
        $p->telefono !== null ? ['phone', $p->telefono, 'tel:'.$p->tel] : null,
        $p->whatsapp !== null ? ['message-circle', 'WhatsApp', 'https://wa.me/'.$p->whatsapp] : null,
        $p->correo !== null ? ['mail', $p->correo, 'mailto:'.$p->correo] : null,
    ]);
@endphp
<tr data-bloque="pie"><td class="pjm-pad" style="padding:0 24px 36px;">
<table {!! $correo::TABLA !!} width="100%" class="pjm-subtle" bgcolor="{{ $t->claro('sutil') }}" style="background:{{ $t->claro('sutil') }};border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('lg') }}px;border-collapse:separate;"><tr><td style="padding:18px 20px 12px;">
@if ($correo->responde !== null)
<table {!! $correo::TABLA !!} width="100%"><tr><td width="28" valign="top" style="width:28px;padding-top:2px;">{!! $correo->icono('reply', 18) !!}</td><td valign="top"><p class="pjm-strong" style="margin:0;{{ $correo->ty('texto', 15, 22, 700, $t->claro('fuerte')) }}">{{ $correo->responde }}</p></td></tr></table>
{!! $correo->hueco(14) !!}@include('correo.piezas.filete'){!! $correo->hueco(14) !!}
@endif
<table {!! $correo::TABLA !!} width="100%">
<tr><td width="28" valign="top" style="width:28px;padding-top:2px;">{!! $correo->icono('map-pin', 18) !!}</td><td valign="top">
<p class="pjm-strong" style="margin:0;{{ $correo->ty('texto', 14, 21, 700, $t->claro('fuerte')) }}">{{ $p->nombre }}</p>
@if ($p->direccion !== null)
<p class="pjm-body" style="margin:0;{{ $correo->ty('texto', 14, 21, 400, $t->claro('cuerpo')) }}">{{ $p->direccion }}</p>
@endif
</td></tr>
@if ($p->hoy !== null)
<tr><td width="28" valign="top" style="width:28px;padding-top:12px;">{!! $correo->icono('clock', 18) !!}</td><td valign="top" style="padding-top:10px;"><p class="pjm-body" style="margin:0;{{ $correo->ty('texto', 14, 21, 400, $t->claro('cuerpo')) }}">{{ $p->hoy }}</p></td></tr>
@endif
</table>
@if ($enlaces !== [])
<p style="margin:4px 0 0;">@foreach ($enlaces as [$icono, $texto, $href])<a class="pjm-link" href="{{ $href }}" target="_blank" style="display:inline-block;margin-right:18px;padding:11px 0;text-decoration:none;white-space:nowrap;{{ $correo->ty('texto', 14, 20, 700, $t->claro('enlace')) }}">{!! $correo->icono($icono, 16, enLinea: true) !!}{{ $texto }}</a>@endforeach</p>
@endif
{!! $correo->hueco($enlaces !== [] ? 4 : 14) !!}
<table {!! $correo::TABLA !!} width="100%"><tr><td class="pjm-rule" height="1" style="height:1px;line-height:1px;font-size:1px;background:{{ $t->claro('filete') }};">&nbsp;</td></tr></table>
@if ($correo->comercial !== null)
<p class="pjm-muted" data-pie="comercial" style="margin:12px 0 0;{{ $correo->ty('texto', 13, 19, 400, $t->claro('apagado')) }}">{!! $correo->rico($correo->comercial)['h'] !!}</p>
@endif
<p class="pjm-muted" style="margin:10px 0 0;{{ $correo->ty('texto', 13, 19, 400, $t->claro('apagado')) }}">@foreach ($correo->legales as $i => [$texto, $href]){!! $i ? '&nbsp;&middot;&nbsp; ' : '' !!}<a class="pjm-muted" href="{{ $href }}" target="_blank" style="color:{{ $t->claro('apagado') }};text-decoration:underline;">{{ $texto }}</a>@endforeach</p>
</td></tr></table>
</td></tr>
