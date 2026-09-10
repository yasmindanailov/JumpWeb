{{-- Subcard de producto para los correos transaccionales (mejora visual #251).

     Maquetada con TABLAS y estilos INLINE a propósito: los clientes de email (Gmail, Outlook)
     no respetan CSS externo/`<style>` de forma fiable, y el slot del correo pasa por el parser
     Markdown — una tabla con estilos inline es lo único robusto en todas las bandejas. El badge
     usa el color de la ZONA del producto (data-driven). ⚠️ NO LLEVA ICONO: el sistema del canvas no usa
     emojis en ningún correo, y un SVG no sobrevive a Gmail ni a Outlook. Lo que identifica el
     producto es el filete superior en el color de su ZONA, que sí es data-driven. La rinde `App\Domain\Booking\Services\EmailProductCard`,
     que cada notificación inyecta con UNA línea — sin reescribir el correo.

     Props: $color (hex de zona), $title, $meta?, $sub?, $addons (string[]), $price?

     ⚠️ EL PRODUCTO CANCELADO VA EN HUMO, NO EN UN GRIS MÁS FLOJO. Medido el 2026-09-10 sobre la
     tarjeta blanca: el gris cálido que había daba **2,98** y el Humo Claro con el que se sustituyó
     al vestir daba **2,61** — los dos por debajo de cualquier umbral. Lo que dice «cancelado» es el
     TACHADO y el rótulo que va debajo, no un gris ilegible: rebajar el texto por debajo del umbral
     no comunica «inerte», comunica «no se ve». Con Humo son **5,49** en claro y **6,35** en oscuro.
     ▶ Lo cazó la guarda del modo oscuro, no la vista: el color no tenía par en tinta. --}}
<table class="product-card" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:18px 0;border:1px solid #D6D8D4;border-top:3px solid {{ $color }};border-radius:16px;background:#FFFFFF;border-collapse:separate;">
<tr><td style="padding:14px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>

<td valign="top">
<div style="font-family:Arial,Helvetica,sans-serif;font-weight:700;font-size:15px;color:{{ ($cancelled ?? false) ? '#626A72' : '#101418' }};line-height:1.3;{{ ($cancelled ?? false) ? 'text-decoration:line-through;' : '' }}">{{ $title }}</div>
@if($cancelled ?? false)<div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#626A72;margin-top:2px;text-transform:uppercase;letter-spacing:0.03em;">{{ __('account.orders.item_cancelled') }}</div>@endif
@if(!empty($meta))<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#626A72;margin-top:2px;">{{ $meta }}</div>@endif
@if(!empty($sub))<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#626A72;margin-top:1px;">{{ $sub }}</div>@endif
@foreach($addons as $addon)<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#626A72;margin-top:3px;">+ {{ $addon }}</div>@endforeach
</td>
@if(!empty($price))<td valign="top" align="right" style="padding-left:10px;white-space:nowrap;font-family:Arial,Helvetica,sans-serif;font-weight:700;font-size:15px;color:#101418;">{{ $price }}</td>@endif
</tr></table>
</td></tr>
</table>
