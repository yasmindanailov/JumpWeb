{{-- EL CÓDIGO de un solo uso (el `codigo()` del diseño; el 8 del zip (6), la R1c): grande, en la familia mono y en dos grupos
     de tres con guion (lo da `LoginCodes::shown()`), para leerlo de un vistazo y escribirlo sin errores. Es la ACCIÓN de su
     correo: no lleva botón. Sobre el sutil, como el QR del diseño. Su etiqueta sale tal cual (`*_label`); su nota es un
     párrafo y pasa por `rico()`. --}}
@php($t = $correo->tema)
<tr data-bloque="codigo"><td class="pjm-pad" style="padding:0 24px {{ $b['aire'] }}px;">
<table {!! $correo::TABLA !!} width="100%" class="pjm-subtle" bgcolor="{{ $t->claro('sutil') }}" style="background:{{ $t->claro('sutil') }};border:1px solid {{ $t->claro('filete') }};border-radius:{{ $t->radio('lg') }}px;border-collapse:separate;">
<tr><td align="center" style="padding:26px 20px 24px;">
@if ($b['etiqueta'] !== '')
<p class="pjm-muted" style="margin:0;{{ $correo->ty('texto', 14, 20, 700, $t->claro('apagado')) }}">{{ $b['etiqueta'] }}</p>
@endif
<p class="pjm-strong" data-codigo style="margin:0;padding-top:10px;{{ $correo->ty('mono', 40, 46, 500, $t->claro('fuerte'), 'letter-spacing:6px;font-variant-numeric:tabular-nums;white-space:nowrap;') }}">{{ $b['codigo'] }}</p>
@if ($b['nota'] !== null)
<p class="pjm-body" style="margin:14px 0 0;{{ $correo->ty('texto', 15, 22, 400, $t->claro('cuerpo')) }}">{!! $correo->rico($b['nota'])['h'] !!}</p>
@endif
</td></tr></table>
</td></tr>
