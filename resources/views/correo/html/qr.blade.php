{{-- EL QR (el `qr()` del diseño, QrPass en correo): la imagen DENTRO del correo, siempre sobre blanco (`lienzo-qr`, también en
     oscuro: la cámara lee oscuro sobre claro), el código para dictar en mono y su botón —el principal, salvo que el correo
     tenga otro trabajo—. La imagen va INCRUSTADA (`cid:`, `Message::embedData()`): viaja en el correo y se ve sin «cargar
     imágenes». Sin mensaje que la lleve —la vista previa, `render()`—, su texto alternativo en su caja, como el diseño. --}}
@php
    $t = $correo->tema;
    $src = isset($message) ? $message->embedData($b['png'], 'qr.png', 'image/png') : null;
@endphp
<tr data-bloque="qr"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<table {!! $correo::TABLA !!} width="100%" class="pjm-subtle" bgcolor="{{ $t->claro('sutil') }}" style="background:{{ $t->claro('sutil') }};border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('lg') }}px;border-collapse:separate;">
<tr><td align="center" style="padding:28px 20px 26px;">
<table {!! $correo::TABLA !!} style="border-collapse:separate;"><tr><td bgcolor="{{ $t->claro('lienzo-qr') }}" style="background:{{ $t->claro('lienzo-qr') }};border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('md') }}px;padding:14px;">
@if ($src !== null)
<img src="{{ $src }}" width="200" height="200" alt="QR {{ $b['codigo'] }}" style="display:block;width:200px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
@else
<table {!! $correo::TABLA !!} width="200" style="width:200px;max-width:100%;border-collapse:separate;"><tr><td class="pjm-muted" height="200" align="center" valign="middle" style="height:200px;padding:0 10px;border:1px dashed {{ $t->claro('apagado') }};border-radius:{{ $t->radio('md') }}px;{{ $correo->ty('texto', 13, 18, 600, $t->claro('apagado')) }}">QR {{ $b['codigo'] }}</td></tr></table>
@endif
</td></tr></table>
<p class="pjm-strong" style="margin:18px 0 0;{{ $correo->ty('texto', 17, 24, 700, $t->claro('fuerte')) }}">{{ $b['texto'] }}</p>
<p class="pjm-muted" style="margin:8px 0 0;{{ $correo->ty('texto', 14, 22, 400, $t->claro('apagado')) }}">{{ $b['dicta'] }} <span class="pjm-strong" style="white-space:nowrap;{{ $correo->ty('mono', 17, 22, 500, $t->claro('fuerte'), 'letter-spacing:1.4px;') }}">{{ $b['codigo'] }}</span></p>
<div style="padding-top:20px;">@include('correo.piezas.boton', ['texto' => $b['boton'], 'url' => $b['url'], 'secundario' => $b['secundario'], 'centro' => true])</div>
</td></tr></table>
</td></tr>
