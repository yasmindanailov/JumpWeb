@props(['title' => null])
{{--
    Layout ENFOCADO (#263/#264): páginas sin distracciones — sin nav, sin sidecart de compra, sin banner
    de cookies, sin CTA flotante. Solo lo imprescindible. Reutiliza el MISMO sistema de estilos que la
    web (no inventa): carga `landing.css` (tokens + componentes .btn/.eventfields), el color de marca
    white-label (`<style id="jj-theme">`, DESPUÉS de landing.css para ganar) y `site.css` (que incluye
    los estilos `.gf-*` de la hoja del post-form). NO usa Livewire ni `app.js`: la interactividad va con
    JS plano del propio slot (la página es un GET normal, no un morph). Usado por: formulario post-reserva.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- La clase `js` la pone el PROPIO script de la página al FINAL de inicializar (no aquí): así, si
         ese script falla, el documento se queda en `no-js` (fichas abiertas y formulario usable) en vez
         de colapsado e inaccesible (#264-audit, mejora progresiva robusta). --}}

    @php($gfBrand = (isset($site['name']) && $site['name']) ? $site['name'] : config('app.name'))
    <title>{{ $title ? $title.' · '.$gfBrand : $gfBrand }}</title>
    {{-- Página privada (datos de menores): nunca indexar. --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- El MISMO hueco que el layout público: si los dos no salen del componente, una instalación
         pone su icono y esta pantalla sigue con el del producto — sin fallar y sin avisar. --}}
    <x-site.favicon />

    {{-- Mismas fuentes que el resto del sitio. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Las familias son de la INSTALACIÓN (`config/theme.php` → `THEME_FONTS`); el host NO,
         porque la CSP solo permite uno. Ver `Content\Services\ThemeFonts`. --}}
    <link rel="stylesheet" href="{{ \App\Domain\Content\Services\ThemeFonts::stylesheetUrl() }}">

    {{-- MISMO sistema de estilos que la web (tokens + componentes: .btn, .eventfields, .eyebrow…) →
         esta página NO inventa estilos propios; reutiliza los del sitio. landing.css define los
         tokens (`:root`); el tema sobreescribe `--zone-1` (white-label, va DESPUÉS, igual que
         x-layout); site.css trae los componentes + los estilos de la hoja del post-form (`.gf-*`). --}}
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) }}">
    <style id="jj-theme">:root{ {{ \App\Domain\Content\Services\ThemeSettings::cssRootDeclarations() }} }</style>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
</head>
<body class="gf">
    {{ $slot }}
</body>
</html>
