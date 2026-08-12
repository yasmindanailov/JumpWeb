@props(['url'])
{{--
    Header del email: wordmark del NEGOCIO (data-driven: `business.name` de BD, como el resto
    de la marca — Fase 1 del refactor) + el punto en color de marca, ESTÁTICO (sin la animación
    del logo de la web — los clientes de email no ejecutan animaciones de forma fiable). Sin
    imagen ni SVG a propósito (Gmail/Outlook bloquean imágenes por defecto y eliminan los SVG):
    el branding se dibuja con HTML/CSS inline → se ve siempre.

    Fallback a `config('app.name')` si la instalación aún no configuró `business.name`.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">{{ \App\Models\Setting::value('business.name') ?: config('app.name') }}<span class="brand-dot" style="display:inline-block; width:9px; height:9px; margin-left:3px; border-radius:2px; background: {{ \App\Support\ThemeSettings::brand() }};"></span></a>
</td>
</tr>
