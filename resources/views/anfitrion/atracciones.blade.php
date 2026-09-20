{{-- ══ EL ANFITRIÓN MÍNIMO de /atracciones · lo que el producto sirve SIN paquete de instancia ═══
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4, `DECISIONES #657`). La página de PlayJump (artboard
     `Atracciones PJP` 1a/1c, `#481` del carril de diseño) vive en su instancia; esto es lo que queda en el
     producto: el armazón, una pestaña y un panel por zona CON ATRACCIONES, la cifra de cada zona con su
     paleta compuesta, la rejilla de fichas con su foto, su qué-es y sus dos chips, y la nota de acceso del
     parque. Sin arte: ni fachada, ni pegatina de zona. Es marcado del PRODUCTO (`AnfitrionAtraccionesTest`).

     ⚠️⚠️ **La identidad de una zona en el marcado es `slug` y NUNCA `accent`** (`#295`): dos zonas pueden
     compartir acento —medido: `kids`, `cap` y `cap2`— y tres paneles con el mismo `id` abren a la vez. Esa
     guarda (`ZoneIdentityIsUniqueTest`) ya se quedó dos veces sin sujeto al retirarse la pieza que miraba;
     aquí conserva el suyo, y por eso el anfitrión mantiene los tres sitios: `id` de panel, `id` de pestaña
     y su `aria-controls`.

     ⚠️ Lo que se pinta aquí es EXACTAMENTE el contrato de vista (`InstanceViews::CONTRATO_DE_VISTAS`):
     `zones`, `total`, `active` y lo que reparte el composer. Una instancia que pinte lo mismo con su
     diseño no necesita saber nada más. --}}
@php
    // La `<meta description>` (SEO, INVISIBLE): las atracciones reales. CÓMO se escribe el resumen —el
    // separador y el tope— es regla del producto (`MetaDescription`, `#653`); aquí solo el respaldo.
    $ridesMeta = \App\Domain\Platform\Services\MetaDescription::fromNames($zones->flatMap->attractions->map(fn ($a) => $a->tr('name')))
        ?? __('landing.attractions.title');
@endphp
<x-layout :title="__('landing.attractions.title')" :description="$ridesMeta">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="page page--rides wrap">
        <x-site.page-head :title="__('landing.attractions.title')" :lede="__('landing.attractions.intro', ['count' => $total])" />

        @if ($zones->isNotEmpty())
            {{-- El estado arranca en la zona que la URL pide (`?zona=`), ya resuelta y SANEADA por el
                 controlador: aquí no se vuelve a decidir. --}}
            <div class="rides-page" x-data="{ zone: @js($active) }">
                <div class="rides-page__head">
                    {{-- ⚠️ Con una sola zona no se pinta la pestaña: un control de una opción no elige nada. --}}
                    @if ($zones->count() > 1)
                        <div class="tabset rides-page__tabs" role="tablist" aria-label="{{ __('landing.attractions.zone_tablist') }}">
                            @foreach ($zones as $zone)
                                <button type="button" class="tabset__tab" role="tab"
                                        id="rides-tab-{{ $zone->slug }}"
                                        aria-controls="atracciones-{{ $zone->slug }}"
                                        :class="zone === @js($zone->slug) && 'is-active'"
                                        :aria-selected="zone === @js($zone->slug) ? 'true' : 'false'"
                                        @click="zone = @js($zone->slug)">{{ $zone->tr('name') }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="rides-page__counts">
                        @foreach ($zones as $zone)
                            {{-- La cifra es DATO del panel, y su color sale de la paleta compuesta de la
                                 zona (`zoneStyle()` emite `--zone-1`, `--zone-2` y `--zone-ink` de una vez).
                                 ⚠️ Sin tramo de edad la frase cae a la variante que nombra la zona: una
                                 frase con el hueco vacío diría «atracciones para », que es peor que callar. --}}
                            <p class="rides-zone__count"
                               style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                               x-show="zone === @js($zone->slug)" @if ($zone->slug !== $active) x-cloak @endif>
                                <span class="rides-zone__n">{{ $zone->attractions->count() }}</span>
                                <span class="rides-zone__phrase">{{ $zone->tr('age_range')
                                    ? __('landing.attractions.count_phrase', ['age' => $zone->tr('age_range')])
                                    : __('landing.attractions.count_phrase_plain', ['zone' => $zone->tr('name')]) }}</span>
                            </p>
                        @endforeach
                    </div>
                </div>

                @foreach ($zones as $zone)
                    {{-- ⚠️ `x-cloak` va en las que NO llegan activas, no en «todas menos la primera»: con
                         `?zona=kids` la primera es Jump, y ocultar por posición la pintaría un instante. --}}
                    <section class="rides-zone" role="tabpanel"
                             id="atracciones-{{ $zone->slug }}"
                             @if ($zones->count() > 1) aria-labelledby="rides-tab-{{ $zone->slug }}" @endif
                             style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                             x-show="zone === @js($zone->slug)" @if ($zone->slug !== $active) x-cloak @endif>

                        {{-- ⚠️ `<ul>` y no `<div>`: son las atracciones de esa zona, o sea una lista, y el
                             lector de pantalla anuncia cuántas hay antes de recorrerlas. --}}
                        <ul class="rides-grid" role="list">
                            @foreach ($zone->attractions as $ride)
                                <li class="ride-tile">
                                    {{-- ⚠️ Sin foto NO se reserva hueco. La ruta la resuelve el modelo
                                         (`Attraction::imageUrl()`, `#657`), que es donde vive la regla. --}}
                                    @if ($foto = $ride->imageUrl())
                                        <img class="ride-tile__img" src="{{ $foto }}"
                                             alt="{{ $ride->tr('name') }}" loading="lazy" decoding="async">
                                    @endif
                                    <p class="ride-tile__name">{{ $ride->tr('name') }}</p>
                                    {{-- Qué es (`#586`): sin esto, «Barredora» o «Bee Jump» no dicen nada. --}}
                                    @if ($ride->tr('description'))
                                        <p class="ride-tile__desc">{{ $ride->tr('description') }}</p>
                                    @endif
                                    {{-- Los dos chips: la edad de la atracción y su distintivo, que es el
                                         único sitio del producto que pinta `attractions.badge` (`#586`). --}}
                                    @if ($ride->tr('age') || $ride->tr('badge'))
                                        <p class="ride-tile__chips">
                                            @if ($ride->tr('age'))
                                                <span class="ride-tile__age">{{ $ride->tr('age') }}</span>
                                            @endif
                                            @if ($ride->tr('badge'))
                                                <span class="ride-tile__badge">{{ $ride->tr('badge') }}</span>
                                            @endif
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif

        {{-- EL PIE: la nota de acceso del parque.
             ⚠️⚠️ Es la MISMA nota que la sección 01 de la portada (`site.zones_access`, del panel desde
             `#587`), no una copia: se dice igual en todas las superficies. Sin nota no hay pie. --}}
        @if ($site['zones_access'] ?? null)
            <div class="page__foot">
                <p class="page__foot-rule">{{ $site['zones_access'] }}</p>
            </div>
        @endif

        <x-site.link-bands />
    </main>

    <x-site.footer />
</div>
</x-layout>
