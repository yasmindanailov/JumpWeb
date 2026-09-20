@php
    // Meta description (SEO, INVISIBLE en la página): primer párrafo real del cuerpo legal —
    // interpolado (datos fiscales #206)— en vez de repetir el título. Cae al título si el cuerpo
    // está vacío. ⚠️ Sin etiquetas y acotado lo deja `MetaDescription` (`#653`): la misma regla con
    // la que `/api/v1/legal/documents/{clave}` publica su `summary`. Aquí solo el respaldo.
    $legalSections = is_array($page->tr('body')) ? $page->tr('body') : [];
    $legalIntro = collect($legalSections)->pluck('p')->filter()->first();
    $legalMeta = \App\Domain\Platform\Services\MetaDescription::fromText(\App\Domain\Content\Services\LegalIdentity::interpolate($legalIntro))
        ?? $page->tr('title');
@endphp
<x-layout :title="$page->tr('title')" :description="$legalMeta">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page wrap">
        {{-- La cabecera del armazón (T3a·3), sin entradilla: un texto legal no tiene frase que lo
             presente, y el componente no pinta un párrafo vacío. --}}
        <x-site.page-head :title="$page->tr('title')" />

        {{-- El aviso de borrador solo se muestra en las páginas legales aún NO revisadas; las de
             contenido definitivo (ver `Page::REVIEWED_LEGAL_SLUGS`) lo ocultan. --}}
        @unless (in_array($page->slug, \App\Domain\Content\Models\Page::REVIEWED_LEGAL_SLUGS, true))
            <p class="page__note">{{ __('site.legal_draft_notice') }}</p>
        @endunless

        @php $sections = is_array($page->tr('body')) ? $page->tr('body') : []; @endphp
        <div class="page__body">
            @foreach ($sections as $section)
                @if (! empty($section['h']))
                    <h2 class="page__h2">{{ $section['h'] }}</h2>
                @endif
                {{-- Interpola los datos fiscales del titular desde la configuración (#206). --}}
                <p>{{ \App\Domain\Content\Services\LegalIdentity::interpolate($section['p'] ?? '') }}</p>
            @endforeach
        </div>

        <a href="{{ url('/') }}" class="page__back" data-tap>{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
