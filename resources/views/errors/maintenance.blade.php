{{--
    Página de mantenimiento de SITIO ENTERO (#218, item 2) — se sirve con estado 503.

    Deliberadamente STANDALONE (no usa <x-layout>): un «sitio caído» no debe arrastrar Livewire,
    Alpine, los modales de auth ni el sidecart de compra, ni ofrecer un nav hacia páginas que
    también están caídas. Mínima, robusta y on-brand (misma tipografía + CSS + color de marca).
    `noindex` para que el aviso no se indexe. El contacto (tel/email) sale de los Ajustes (`$site`),
    para que el visitante pueda llegar al parque aunque la web esté en mantenimiento.
--}}
@php
    $locale = app()->getLocale();
    $brandName = $site['name'] ?? config('app.name');
    $phone = trim((string) ($site['phone'] ?? ''));
    $phoneTel = preg_replace('/\s+/', '', $phone);
    $email = trim((string) ($site['email'] ?? ''));
    $message = \App\Domain\Platform\Services\MaintenanceSettings::siteMessage($locale);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('site.maintenance.title') }} · {{ $brandName }}</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=bricolage-grotesque:400,600,700,800|space-grotesk:400,500,600,700|jetbrains-mono:400,500">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) }}">
    {{-- Color de marca white-label (#213): `ThemeSettings` es defensivo y no lanza ni sin BD. --}}
    <style id="jj-theme">:root{ {{ \App\Domain\Content\Services\ThemeSettings::cssRootDeclarations() }} }</style>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
</head>
<body class="maint">
    <main class="maint__wrap">
        <div class="maint__card">
            <span class="maint__brand">{{ $brandName }}<span class="maint__brand-dot" aria-hidden="true">.</span></span>
            <div class="eyebrow">{{ __('site.maintenance.eyebrow') }}</div>
            <h1 class="maint__title">{{ __('site.maintenance.title') }}</h1>
            <p class="maint__body">{{ $message }}</p>

            @if ($phoneTel !== '' || $email !== '')
                <div class="maint__contact">
                    <span class="maint__contact-label">{{ __('site.maintenance.contact') }}</span>
                    <div class="maint__contact-row">
                        @if ($phoneTel !== '')
                            <a href="tel:{{ $phoneTel }}" class="btn btn--zone btn--lg">{{ $phone }}</a>
                        @endif
                        @if ($email !== '')
                            <a href="mailto:{{ $email }}" class="btn btn--lg">{{ $email }}</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </main>
</body>
</html>
