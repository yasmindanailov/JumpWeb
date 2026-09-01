@props(['url'])
{{--
    Header del email: wordmark del NEGOCIO (data-driven: `business.name` de BD, como el resto
    de la marca — Fase 1 del refactor) + el punto en color de marca, ESTÁTICO (sin la animación
    del logo de la web — los clientes de email no ejecutan animaciones de forma fiable). Sin
    imagen ni SVG a propósito (Gmail/Outlook bloquean imágenes por defecto y eliminan los SVG):
    el branding se dibuja con HTML/CSS inline → se ve siempre.

    Fallback a `config('app.name')` si la instalación aún no configuró `business.name`.
--}}
{{-- Lanzamiento 2026-09-01 (`#325`): si la instalación trae su LOGOTIPO rasterizado
     (`public/img/client-logo@4x.png`, PNG porque los clientes de correo descartan el SVG), la
     cabecera lo enseña con el nombre del negocio como `alt` — quien bloquea imágenes sigue viendo
     el wordmark. Sin el fichero, el texto de siempre. --}}
@php($clientLogoPng = file_exists(public_path('img/client-logo@4x.png')))
<tr>
<td class="header">
@if ($clientLogoPng)
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;"><img src="{{ asset('img/client-logo@4x.png') }}?v={{ @filemtime(public_path('img/client-logo@4x.png')) }}" alt="{{ \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name') }}" width="200" style="display: block; max-width: 200px; height: auto; border: 0;"></a>
@else
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">{{ \App\Domain\Platform\Models\Setting::value('business.name') ?: config('app.name') }}<span class="brand-dot" style="display:inline-block; width:9px; height:9px; margin-left:3px; border-radius:2px; background: {{ \App\Domain\Content\Services\ThemeSettings::brand() }};"></span></a>
@endif
</td>
</tr>
