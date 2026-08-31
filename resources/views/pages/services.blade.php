{{-- Página /servicios — diseño v2 «Editorial XL» (mockup design_mockup/pagina-servicios-v2.*),
     ahora DATA-DRIVEN (#256, modelo A): cada fila editorial es un `LandingService` (entidad CMS,
     gestionable en el panel) en vez de `lang/services.php`. Lo COMERCIAL (precio/complementos) se
     lee EN VIVO del `ticketType` vinculado; si el servicio tiene un pack comprable → precio + CTA
     «Reservar» (abre el sidebar, deep-link `show-packs`), si no → CTA «Pedir información» (/contacto).
     Cada fila conserva su anchor estable (`slug`): el nav enlaza a `/servicios#slug` y los tests lo
     verifican. El hero (eyebrow/título/intro), la banda «Otros eventos» y las etiquetas siguen en
     `lang/services.php` (chrome de página). Con 0 servicios la página NO rompe: hero + «Otros
     eventos» (degradación editorial/contacto, §9). La palabra grande sobre la imagen es el
     `accent_word` (contextual), no una enumeración. --}}
@php
    $other = __('services.other');
    // Hero: resalta `title_accent` (subcadena exacta de `title`) con `.blink`, fiel al mockup v2.
    // Se escapa todo; si el fragmento no aparece en el título, queda el título plano escapado.
    $svcTitle = __('services.title');
    $svcAccent = (string) __('services.title_accent');
    $svcTitleHtml = ($svcAccent !== '' && str_contains($svcTitle, $svcAccent))
        ? str_replace(e($svcAccent), '<span class="blink">'.e($svcAccent).'</span>', e($svcTitle))
        : e($svcTitle);
@endphp
<x-layout :title="__('services.meta.title')" :description="__('services.meta.description')">
<div x-data="landing">
    <x-site.nav />

    <main id="main" class="svc-main">
        {{-- Hero: eyebrow + título XL + intro + índice de anclas a cada servicio --}}
        <header class="svc-hero wrap">
            <span class="eyebrow">{{ __('services.eyebrow') }}</span>
            <h1 class="svc-hero__title">{!! $svcTitleHtml !!}</h1>
            <p class="svc-hero__intro">{{ __('services.intro') }}</p>
            @if ($services->isNotEmpty())
                <nav class="svc-hero__index" aria-label="{{ __('services.title') }}">
                    @foreach ($services as $service)
                        <a href="#{{ $service->slug }}" class="svc-hero__jump" data-tap>{{ $service->tr('title') }}</a>
                    @endforeach
                </nav>
            @endif
        </header>

        {{-- **LA CINTA `C3`** (`specs/idioma-visual-heredado.md`, T2). Sustituye a la marquesina
             heredada del cliente antiguo: banda de tinta a sangre completa, girada, con los
             títulos y un punto de color entre ellos, moviéndose despacio.

             ▶ **De su `C3` se toma la FORMA, no el contenido ni la fuente**, y las dos cosas están
             razonadas:
             · El CONTENIDO sigue saliendo del panel (`LandingService`), porque el sitio es
               data-driven y su cinta lleva un eslogan fijo escrito a mano en el mockup.
             · La FUENTE **no** es la de rotulador, aunque su `C3` la use: su propio paquete dice
               de `--font-accent` «Guiño: **el eslogan, nada más**», y su auditoría `T-02` limita
               Permanent Marker a **una por página** — `/servicios` ya gasta la suya en el menú
               (medido). Los títulos van en la de rótulo, que es la que ya usaban.

             ⚠️ `aria-hidden`: es decoración. Los mismos títulos son enlaces reales en el índice
             del hero, así que un lector de pantalla no pierde nada y no se repiten. --}}
        @if ($services->isNotEmpty())
            <div class="brand-band" aria-hidden="true">
                <div class="brand-band__inner">
                    <div class="brand-band__track">
                        {{-- Duplicado: el bucle desplaza el 50 % y tiene que volver a un fotograma
                             idéntico. ⚠️ El punto va DENTRO del bucle a propósito —es separador de
                             ítem, no una textura de pantalla—, y por eso no lo caza la guarda de
                             decoración por pantalla: no está en su lista de texturas. --}}
                        @foreach (array_merge($services->all(), $services->all()) as $service)
                            <span class="brand-band__item">{{ $service->tr('title') }}</span>
                            <span class="brand-band__dot"></span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Filas editoriales: una por servicio, full-bleed alternadas (par = flip + banda) --}}
        @if ($services->isNotEmpty())
            <div class="svc-ed2">
                @foreach ($services as $service)
                    @php
                        $flip = $loop->iteration % 2 === 0;
                        $zoneLabel = $service->tr('zone_label');
                        $pack = $service->isPurchasable() ? $service->ticketType : null;
                    @endphp
                    <section id="{{ $service->slug }}"
                             class="svc-ed2__row @if ($flip) svc-ed2__row--flip svc-ed2__row--band @endif">
                        <div class="wrap svc-ed2__inner">
                            <div class="svc-ed2__body">
                                <span class="svc-ed2__kicker">{{ __('services.service_label') }} · {{ $zoneLabel }}</span>
                                <h2 class="svc-ed2__title">{{ $service->tr('title') }}</h2>
                                <p class="svc-ed2__desc">{{ $service->tr('body') }}</p>
                                <dl class="svc-spec">
                                    @foreach ($service->tr('specs') ?? [] as $spec)
                                        <div class="svc-spec__row">
                                            <dt class="svc-spec__label">{{ $spec['label'] }}</dt>
                                            <dd class="svc-spec__val">{{ $spec['value'] }}</dd>
                                        </div>
                                    @endforeach
                                    @if ($zoneLabel)
                                        <div class="svc-spec__row">
                                            <dt class="svc-spec__label">{{ __('services.zone_label') }}</dt>
                                            <dd class="svc-spec__val">{{ $zoneLabel }}</dd>
                                        </div>
                                    @endif
                                </dl>

                                {{-- Tabla de tarifas de grupo (informativa, data-driven): solo si el servicio define
                                     `price_table` (hoy «Excursiones de colegio»). Pestañas de zona (Kids/Jump) +
                                     tablitas por duración con columnas L–V / finde. SOLO presentación; reserva por teléfono. --}}
                                @if (! empty($service->price_table['zones'] ?? null))
                                    @php($fmt = fn (int $c): string => $c % 100 === 0 ? intdiv($c, 100).' €' : \App\Domain\Platform\Services\Money::format($c))
                                    @php($unit = $service->price_table['unit'] ?? 'kids')
                                    <div class="svc-rates" x-data="{ rz: 0 }">
                                        <span class="svc-rates__title">{{ __('services.rates.title') }}</span>
                                        {{-- Pestañas de zona SOLO si hay >1 zona (mismo criterio que el switcher de precios y los
                                             tabs de cumpleaños). Con UNA sola zona (p. ej. Empresas = solo Jump) NO hay toggle: se
                                             pinta su panel directamente.
                                             ⚠️ El tinte va INLINE desde `#138`: las reglas `.zone-tab--{accent}` solo existían para
                                             `jump` y `kids`, así que cualquier otro acento se quedaba sin color. Se resuelve UNA vez
                                             por acento —consulta la BD— y no dentro de los bucles. --}}
                                    @php($zoneStyles = collect($service->price_table['zones'])
                                        ->pluck('accent')->filter()->unique()
                                        ->mapWithKeys(fn (string $a): array => [$a => \App\Domain\Content\Services\ThemeSettings::zoneStyleForAccent($a)])
                                        ->all())
                                        @if (count($service->price_table['zones']) > 1)
                                            <div class="zone-tabs" role="tablist" aria-label="{{ __('services.rates.title') }}">
                                                @foreach ($service->price_table['zones'] as $z => $zone)
                                                    <button type="button" role="tab" class="zone-tab" data-tap
                                                            style="{{ $zoneStyles[$zone['accent'] ?? ''] ?? '' }}"
                                                            :class="rz === {{ $z }} && 'active'"
                                                            :aria-selected="rz === {{ $z }} ? 'true' : 'false'"
                                                            @click="rz = {{ $z }}">{{ $zone['label'] }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @foreach ($service->price_table['zones'] as $z => $zone)
                                            {{-- ⚠️ El estilo se compone en PHP: Blade no compila un `@endif` pegado a un
                                                 carácter de palabra, y el `@if` suelto rompe la vista compilada. --}}
                                            @php($panelStyle = ($zoneStyles[$zone['accent'] ?? ''] ?? '').($loop->first ? '' : 'display:none'))
                                            <div class="svc-rates__panel" role="tabpanel"
                                                 style="{{ $panelStyle }}"
                                                 x-show="rz === {{ $z }}">
                                                @foreach ($zone['durations'] as $dur)
                                                    <table class="svc-rates__table">
                                                        <caption><span class="svc-rates__cap">{{ $zone['label'] }} {{ intdiv((int) $dur['minutes'], 60) }}H</span></caption>
                                                        <thead>
                                                            <tr>
                                                                <th scope="col">{{ __('services.rates.group') }}</th>
                                                                <th scope="col">{{ __('services.rates.weekday') }}</th>
                                                                <th scope="col">{{ __('services.rates.weekend') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($dur['tiers'] as $tier)
                                                                <tr>
                                                                    <th scope="row">{{ __('services.rates.'.$unit, ['count' => $tier['size']]) }}</th>
                                                                    <td>{{ $fmt((int) $tier['weekday']) }}</td>
                                                                    <td>{{ $fmt((int) $tier['weekend']) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @endforeach
                                            </div>
                                        @endforeach
                                        <p class="svc-rates__note">{{ __('services.rates.note_'.$unit) }}</p>
                                    </div>
                                @endif

                                {{-- Pack comprable vinculado (#256): complementos reales del pivote (lectura en vivo).
                                     Solo si el pack es realmente comprable (coherencia #226); si no, no se muestra. --}}
                                @if ($pack)
                                    <div class="svc-ed2__addons">
                                        <x-site.product-addons :product="$pack" :is-pack="true" />
                                    </div>
                                @endif

                                <div class="svc-ed2__foot">
                                    @if ($pack)
                                        <span class="svc-ed2__price">
                                            <span class="from">{{ __('landing.pricing.from') }}</span>
                                            <span class="val">{{ $pack->euros() }},{{ $pack->cents() }}€</span>
                                            <span class="per">{{ $pack->tr('period_label') }}</span>
                                        </span>
                                        {{-- Deep-link al sidebar (mismo cableado que la card de cumpleaños): abre el
                                             catálogo en la pestaña «Servicios» (packs); el pack es vendible+zona operativa. --}}
                                        <button type="button" class="svc-cta svc-cta--book"
                                                @click="$store.purchase.openWith({ type: 'packs' })">
                                            {{ __('landing.pricing.book') }}
                                            <x-icons.arrow-right :width="15" :height="15" />
                                        </button>
                                    @else
                                        <a href="{{ route('contacto') }}" class="svc-cta svc-cta--info">
                                            {{ __('services.cta_contact') }}
                                            <x-icons.arrow-right :width="15" :height="15" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                            <div class="svc-ed2__media">
                                <span class="svc-ed2__big" aria-hidden="true">{{ $service->tr('accent_word') }}</span>
                                <div class="svc-photo">
                                    @if ($service->image)
                                        <img class="svc-photo__img" src="{{ asset($service->image) }}" alt="{{ $service->tr('title') }}" loading="lazy">
                                    @endif
                                    <span class="tag tag--senal tag--punteada svc-photo__tag">{{ $zoneLabel }}</span>
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        {{-- Banda final «Otros eventos» (catch-all). Conserva el anchor `eventos` (contrato del nav). --}}
        <section class="wrap">
            <div id="{{ $other['anchor'] }}" class="svc-other">
                <div class="svc-other__head">
                    <h2 class="svc-other__title">{{ $other['title'] }}</h2>
                    <p class="svc-other__copy">{{ $other['body'] }}</p>
                </div>
                <div class="svc-other__cta">
                    <a href="{{ route('contacto') }}" class="svc-cta svc-cta--info">
                        {{ __('services.cta_contact') }}
                        <x-icons.arrow-right :width="15" :height="15" />
                    </a>
                </div>
            </div>
        </section>
    </main>

    <x-site.footer />
</div>
</x-layout>
