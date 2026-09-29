{{-- Subcard de producto para los correos transaccionales (mejora visual #251).

     Maquetada con TABLAS y estilos INLINE a propósito: los clientes de email (Gmail, Outlook)
     no respetan CSS externo/`<style>` de forma fiable. El filete superior usa el color de la ZONA del producto
     (data-driven). ⚠️ NO LLEVA ICONO: el sistema del canvas no usa emojis en ningún correo, y un SVG no sobrevive a
     Gmail ni a Outlook. La rinde `App\Domain\Booking\Services\EmailProductCard`, que cada notificación inyecta con UNA
     línea — sin reescribir el correo; el documento de la plantilla la pinta en su bloque de marcado.

     ▶ Desde la R1a del rediseño (`correos-rediseno.md` §4.1.1), sus colores son ROLES del correo (`MailTheme`) y cada
     texto lleva su CLASE de rol: el oscuro de la plantilla va por clase, y sin ella esta tarjeta se quedaría blanca con
     letra de tinta en un correo oscuro.

     Props: $color (hex de zona), $title, $meta?, $sub?, $addons (string[]), $price?

     ⚠️ EL PRODUCTO CANCELADO VA EN EL APAGADO, NO EN UN GRIS MÁS FLOJO. Medido el 2026-09-10 sobre la
     tarjeta blanca: el gris cálido que había daba **2,98**. Lo que dice «cancelado» es el TACHADO y el rótulo que va
     debajo, no un gris ilegible: rebajar el texto por debajo del umbral no comunica «inerte», comunica «no se ve». --}}
@php
    $t = \App\Notifications\Support\MailTheme::current();
    $fuente = 'font-family:'.$t->fuente('texto').';';
    [$fuerte, $apagado] = [$t->claro('fuerte'), $t->claro('apagado')];
@endphp
<table class="product-card pjm-line pjm-bg" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid {{ $t->claro('filete') }};border-top:3px solid {{ $color }};border-radius:{{ $t->radio('lg') }}px;background:{{ $t->claro('fondo') }};border-collapse:separate;">
<tr><td style="padding:14px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>

<td valign="top">
<div class="{{ ($cancelled ?? false) ? 'pjm-muted' : 'pjm-strong' }}" style="{{ $fuente }}font-weight:700;font-size:15px;color:{{ ($cancelled ?? false) ? $apagado : $fuerte }};line-height:1.3;{{ ($cancelled ?? false) ? 'text-decoration:line-through;' : '' }}">{{ $title }}</div>
@if($cancelled ?? false)<div class="pjm-muted" style="{{ $fuente }}font-size:11px;font-weight:700;color:{{ $apagado }};margin-top:2px;text-transform:uppercase;letter-spacing:0.03em;">{{ __('account.orders.item_cancelled') }}</div>@endif
@if(!empty($meta))<div class="pjm-muted" style="{{ $fuente }}font-size:13px;color:{{ $apagado }};margin-top:2px;">{{ $meta }}</div>@endif
@if(!empty($sub))<div class="pjm-muted" style="{{ $fuente }}font-size:13px;color:{{ $apagado }};margin-top:1px;">{{ $sub }}</div>@endif
@foreach($addons as $addon)<div class="pjm-muted" style="{{ $fuente }}font-size:13px;color:{{ $apagado }};margin-top:3px;">+ {{ $addon }}</div>@endforeach
</td>
@if(!empty($price))<td class="pjm-strong" valign="top" align="right" style="padding-left:10px;white-space:nowrap;{{ $fuente }}font-weight:700;font-size:15px;color:{{ $fuerte }};">{{ $price }}</td>@endif
</tr></table>
</td></tr>
</table>
