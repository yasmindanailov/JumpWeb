@php
    // Meta description (SEO, INVISIBLE en la página): primer párrafo real del cuerpo legal —
    // interpolado (datos fiscales #206), sin etiquetas y acotado— en vez de repetir el título.
    // Cae al título si el cuerpo está vacío.
    $legalSections = is_array($page->tr('body')) ? $page->tr('body') : [];
    $legalIntro = collect($legalSections)->pluck('p')->filter()->first();
    $legalMeta = $legalIntro
        ? \Illuminate\Support\Str::limit(strip_tags(\App\Domain\Content\Services\LegalIdentity::interpolate($legalIntro)), 155)
        : $page->tr('title');
@endphp
<x-layout :title="$page->tr('title')" :description="$legalMeta">
<div x-data="landing">
    <x-site.nav />

    <main class="page wrap">
        <div class="page__head">
            <div class="eyebrow">{{ __('site.legal_eyebrow') }}</div>
            <h1 class="page__title">{{ $page->tr('title') }}</h1>
        </div>

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

        <a href="{{ url('/') }}" class="page__back">{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
