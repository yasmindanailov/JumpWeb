{{-- Subcard de producto para los correos transaccionales (mejora visual #251).

     Maquetada con TABLAS y estilos INLINE a propósito: los clientes de email (Gmail, Outlook)
     no respetan CSS externo/`<style>` de forma fiable, y el slot del correo pasa por el parser
     Markdown — una tabla con estilos inline es lo único robusto en todas las bandejas. El badge
     usa el color de la ZONA del producto (data-driven). No usa SVG (Gmail/Outlook los eliminan):
     el icono es un emoji dentro de un badge de color. La rinde `App\Support\EmailProductCard`,
     que cada notificación inyecta con UNA línea — sin reescribir el correo.

     Props: $emoji, $color (hex de zona), $title, $meta?, $sub?, $addons (string[]), $price? --}}
<table class="product-card" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:18px 0;border:1px solid rgba(20,19,15,0.10);border-top:3px solid {{ $color }};border-radius:12px;background:#FFFFFF;border-collapse:separate;">
<tr><td style="padding:14px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
<td valign="top" width="48" style="width:48px;">
<table cellpadding="0" cellspacing="0" role="presentation"><tr><td align="center" valign="middle" style="width:38px;height:38px;background:#ECE5D2;border-radius:10px;font-size:20px;line-height:38px;text-align:center;mso-line-height-rule:exactly;">{{ $emoji }}</td></tr></table>
</td>
<td valign="top" style="padding-left:12px;">
<div style="font-family:'Bricolage Grotesque','Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-weight:700;font-size:15px;color:{{ ($cancelled ?? false) ? '#9A958A' : '#14130F' }};line-height:1.3;{{ ($cancelled ?? false) ? 'text-decoration:line-through;' : '' }}">{{ $title }}</div>
@if($cancelled ?? false)<div style="font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;color:#9A958A;margin-top:2px;text-transform:uppercase;letter-spacing:0.03em;">{{ __('account.orders.item_cancelled') }}</div>@endif
@if(!empty($meta))<div style="font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-size:13px;color:#6B675D;margin-top:2px;">{{ $meta }}</div>@endif
@if(!empty($sub))<div style="font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-size:13px;color:#6B675D;margin-top:1px;">{{ $sub }}</div>@endif
@foreach($addons as $addon)<div style="font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-size:13px;color:#6B675D;margin-top:3px;">+ {{ $addon }}</div>@endforeach
</td>
@if(!empty($price))<td valign="top" align="right" style="padding-left:10px;white-space:nowrap;font-family:'Space Grotesk',-apple-system,BlinkMacSystemFont,Helvetica,Arial,sans-serif;font-weight:700;font-size:15px;color:#14130F;">{{ $price }}</td>@endif
</tr></table>
</td></tr>
</table>
