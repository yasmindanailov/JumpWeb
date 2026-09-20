{{-- ══ EL ANFITRIÓN MÍNIMO de los TEXTOS LEGALES · lo que el producto sirve SIN paquete ══════════
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4, `DECISIONES #655`). Sirve las cinco rutas legales
     (`legal.*`) con la página del panel: el titular, sus secciones con los datos fiscales ya interpolados
     (`LegalIdentity`, `#206`) y la vuelta a la portada. Sin entradilla: un texto legal no tiene frase que
     lo presente, y `page-head` no pinta un párrafo vacío. Es marcado del PRODUCTO (`AnfitrionLegalTest`).

     ⚠️ La `<meta description>` sale del primer párrafo real y no del título (`MetaDescription`, `#653`):
     es la misma regla con la que `/api/v1/legal/documents/{clave}` publica su `summary`. --}}
@php
    $legalSections = is_array($page->tr('body')) ? $page->tr('body') : [];
    $legalIntro = collect($legalSections)->pluck('p')->filter()->first();
    $legalMeta = \App\Domain\Platform\Services\MetaDescription::fromText(\App\Domain\Content\Services\LegalIdentity::interpolate($legalIntro))
        ?? $page->tr('title');
@endphp
<x-layout :title="$page->tr('title')" :description="$legalMeta">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        <x-site.page-head :title="$page->tr('title')" />

        <div class="page__body">
            @foreach ($legalSections as $section)
                @if (! empty($section['h']))
                    <h2 class="page__h2">{{ $section['h'] }}</h2>
                @endif
                <p>{{ \App\Domain\Content\Services\LegalIdentity::interpolate($section['p'] ?? '') }}</p>
            @endforeach
        </div>

        <a href="{{ url('/') }}" class="page__back" data-tap>{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
