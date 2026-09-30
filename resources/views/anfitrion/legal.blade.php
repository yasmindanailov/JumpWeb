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

            {{-- EL LISTADO de la política de cookies (`specs/politica-de-cookies.md` §3): lo compone la configuración
                 de la instalación (`CookieInventory`), no el texto guardado —el mismo que viaja en
                 `GET /legal/documents/cookies`—. La herramienta de análisis y las plataformas de anuncios ACTIVAS
                 (T3a·2, T3b·3) son filas suyas, con su empresa responsable y su garantía de transferencia; sus marcas
                 `data-analytics-*` siguen en el bloque. --}}
            @if ($page->slug === 'cookies')
                @php
                    $inventario = \App\Http\Resources\Api\V1\LegalDocumentsResource::inventario();
                    $herramienta = \App\Domain\Platform\Services\Analytics\Drivers::config()['driver'] ?? null;
                    $pixeles = \App\Domain\Platform\Services\Analytics\Pixels::active();
                @endphp
                <section class="page__cookies" data-cookie-inventory
                    @if ($herramienta !== null) data-analytics-tool="{{ $herramienta }}" @endif
                    @if ($pixeles !== []) data-analytics-pixels="{{ implode(',', $pixeles) }}" @endif>
                    <h2 class="page__h2">{{ $inventario['title'] }}</h2>
                    <p>{{ $inventario['intro'] }}</p>
                    @foreach ($inventario['cookies'] as $cookie)
                        <h3 data-cookie="{{ $cookie['key'] }}">{{ $cookie['name'] }} · {{ $cookie['category'] }}</h3>
                        <p>
                            <strong>{{ $inventario['labels']['holder'] }}:</strong> {{ $cookie['holder'] }}<br>
                            <strong>{{ $inventario['labels']['purpose'] }}:</strong> {{ $cookie['purpose'] }}<br>
                            <strong>{{ $inventario['labels']['duration'] }}:</strong> {{ $cookie['duration'] }}<br>
                            <strong>{{ $inventario['labels']['when'] }}:</strong> {{ $cookie['when'] }}
                        </p>
                    @endforeach
                </section>
            @endif
        </div>

        <a href="{{ url('/') }}" class="page__back" data-tap>{{ __('site.back_home') }}</a>
    </main>

    <x-site.footer />
</div>
</x-layout>
