@props(['url'])
{{--
    Header del email: wordmark "Jumpingjump" + la MARCA de la landing (el bloque del nav) en
    color de marca, ESTÁTICA (sin la animación `jjLogoHop` de la web — los clientes de email no
    ejecutan animaciones de forma fiable y muchos las bloquean). Sin imagen ni SVG a propósito
    (Gmail/Outlook bloquean imágenes por defecto y eliminan los SVG): el branding se dibuja con
    HTML/CSS inline → se ve siempre, aunque el cliente no cargue recursos externos.

    El slot del MailMessage por defecto es `config('app.name')` (Laravel). Forzamos el wordmark
    para que el header sea siempre coherente. El cuadrado redondeado replica el «block» del logo.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">{{ config('app.name', 'Jumpingjump') }}<span class="brand-dot" style="display:inline-block; width:9px; height:9px; margin-left:3px; border-radius:2px; background: {{ \App\Support\ThemeSettings::brand() }};"></span></a>
</td>
</tr>
