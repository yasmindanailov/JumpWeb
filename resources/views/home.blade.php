{{-- ⚠️ `noindex` lo decide el CONTROLADOR, no esta plantilla: es cierto en las tres puertas de auth
     —`/registro`, `/login`, `/recuperar-contrasena`— y falso en la home y en `/entradas`, que son la
     misma vista. Sustituye al efecto lateral que tenía el prop `auth-modal` (`AccountDoor`). --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="$noindex ?? false" :has-hero="true">
@php
    // Horario del parque data-driven (#207): misma fuente que las reservas (opening_hours +
    // temporadas + fechas especiales), agrupado para mostrar.
    $schedule = app(\App\Domain\Content\Services\ScheduleDisplay::class);
    $totalSqm = number_format($zones->sum('area_sqm'), 0, ',', '.');
    $totalRides = $zones->sum('rides_count');
    $totalZones = $zones->count();
    $totalLabels = __('landing.zones.total_labels');
@endphp

<div x-data="landing">
    {{-- Lote 11 (a11y): skip-link al contenido (1.er focusable, antes del nav) + landmark <main>.
         La home era la única página pública sin <main> (las demás ya lo tienen). --}}
    <a href="#main" class="skip-link">{{ __('landing.nav.skip') }}</a>
    <x-site.nav />
    <main id="main">

    {{-- ===================== HERO ===================== --}}
    <header id="top" class="hero hero--full">
        <div class="hero__stage" data-has-video="true">
            <div class="hero__stage-placeholder" aria-hidden="true"></div>
            {{-- Vídeo de fondo del hero (oficial). Servido como MP4 H.264 (universal: Chrome/Firefox/
                 Safari/Android) — el original era HEVC/.mov que Chrome/Firefox NO reproducen. `poster`
                 = primer fotograma para pintado inmediato mientras carga. --}}
            <video class="hero__video" autoplay muted loop playsinline preload="auto"
                   poster="{{ asset('videos/header_poster.jpg') }}?v={{ @filemtime(public_path('videos/header_poster.jpg')) }}" aria-hidden="true">
                <source src="{{ asset('videos/header_hero.mp4') }}?v={{ @filemtime(public_path('videos/header_hero.mp4')) }}" type="video/mp4">
            </video>
            <div class="hero__stage-scrim" aria-hidden="true"></div>
            <span class="hero__stage-label">{{ __('landing.hero.reel') }}</span>
            <div class="hero__stage-content">
                <h1 class="hero__title hero__title--onvideo">
                    <span class="word">{{ __('landing.hero.l1') }}</span>
                    <span class="word"><span class="blink">{{ __('landing.hero.l2') }}</span></span>
                </h1>

                {{-- Estado de apertura (data-driven, `App\Domain\Content\Services\HeroStatus`) DEBAJO del título: «{Día} ·
                     Abierto ahora» / «… · Abrimos en Xh», con un icono de ubicación al final. Sin fondo
                     (texto sobre el vídeo). Enlaza a #info (horario + cómo llegar). No se pinta sin horario. --}}
                @if (! empty($heroStatus))
                    <a href="#info"
                       class="hero__chip hero__chip--onvideo"
                       title="{{ __('landing.hero.status_link_hint') }}"
                       aria-label="{{ $heroStatus['day'] }} · {{ $heroStatus['status'] }}. {{ __('landing.hero.status_link_hint') }}">
                        <span class="hero__chip-text">{{ $heroStatus['day'] }} · {{ $heroStatus['status'] }}</span>
                        <x-icons.pin class="hero__chip-pin" />
                    </a>
                @endif

                <div class="hero__stage-bottom">
                    {{-- CTA "prime" del hero (mockup `design_mockup/jerarquia-ctas.html`,
                         peso 5/5). Variante `--onvideo` invierte el fondo a cream sobre el
                         vídeo oscuro para máxima legibilidad. El anclaje "desde X € · sin
                         colas" es data-driven: si el catálogo aún no tiene precios, cae al
                         fallback `cta_buy_no_price` (solo "sin colas"). El handler
                         `$store.purchase.open()` se conserva exacto (#66). --}}
                    <a href="{{ route('entradas') }}" @click.prevent="$store.purchase.open()"
                       class="cta-prime cta-prime--onvideo">
                        <span class="cta-prime__ico"><x-icons.ic-e2 :width="54" :height="35" /></span>
                        <span class="cta-prime__body">
                            <span class="cta-prime__t">{{ __('landing.hero.cta_buy') }}</span>
                            <span class="cta-prime__s">
                                @if (! empty($ctaMinPriceLabel))
                                    {{ __('landing.hero.cta_buy_from', ['amount' => $ctaMinPriceLabel]) }}
                                @else
                                    {{ __('landing.hero.cta_buy_no_price') }}
                                @endif
                            </span>
                        </span>
                        <span class="cta-prime__arrow" aria-hidden="true">→</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <x-site.marquee />

    {{-- ===================== ZONAS ===================== --}}
    <section id="zones" class="section wrap">
        <div class="zones__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.zones.eyebrow') }}</div>
                <h2 class="zones__title">{{ __('landing.zones.title') }}<br /><em>{{ __('landing.zones.title_em') }}</em></h2>
            </div>
            <p class="zones__intro">{{ __('landing.zones.intro') }}</p>
        </div>

        <div class="zone-stats">
            @foreach ([[$totalSqm, $totalLabels[0]], [$totalRides, $totalLabels[1]], [$totalZones, $totalLabels[2]]] as $i => $t)
                @if ($i > 0)<span class="jj-block jj-block--sm" aria-hidden="true"></span>@endif
                <div class="zone-stats__item">
                    <span class="zone-stats__num">{{ $t[0] }}</span>
                    <span class="zone-stats__label">{{ $t[1] }}</span>
                </div>
            @endforeach
        </div>

        <div class="zone-intro">
            @foreach ($zones as $zone)
                @if ($zone->image)
                    {{-- Card de zona CON foto (patrón «Foto integrada en la tarjeta» del mockup
                         `Ejemplos Imagenes Secciones.html`): banda de foto arriba + color de zona y
                         datos abajo. Fondo = color de la zona (white-label, vía ThemeSettings). --}}
                    <a class="zone-photo-card" href="#rides"
                             style="background: {{ \App\Domain\Content\Services\ThemeSettings::colorForAccent($zone->color, $zone->accent) }}; --on-brand: {{ \App\Domain\Content\Services\ThemeSettings::onBrand(\App\Domain\Content\Services\ThemeSettings::colorForAccent($zone->color, $zone->accent)) }}"
                             @click.prevent="goToRides('{{ $zone->accent }}')"
                             aria-label="{{ $zone->tr('name') }} · {{ __('landing.zones.see_rides') }}">
                        <img class="zone-photo-card__photo" src="{{ asset($zone->image) }}"
                             alt="{{ $zone->tr('name') }}" loading="lazy">
                        <div class="zone-photo-card__body">
                            <span class="zone-photo-card__tag">{{ $zone->tr('age_label') }} · {{ $zone->tr('age_range') }}</span>
                            <h3 class="zone-photo-card__name">{{ $zone->tr('name') }}</h3>
                            <p class="zone-photo-card__sub">{{ $zone->tr('subtitle') }}</p>
                            <div class="zone-photo-card__meta">
                                <div><span class="v">{{ number_format($zone->area_sqm, 0, ',', '.') }} m²</span><span class="l">{{ __('landing.zones.surface') }}</span></div>
                                <div><span class="v">{{ $zone->rides_count }}</span><span class="l">{{ __('landing.zones.rides') }}</span></div>
                            </div>
                            <span class="zone-photo-card__cta">{{ __('landing.zones.see_rides') }} <x-icons.arrow-right class="arrow" :width="14" :height="14" /></span>
                        </div>
                    </a>
                @else
                    {{-- Fallback: card de zona sin foto (diseño actual con número de marca de fondo). --}}
                    {{-- ⚠️ El color va INLINE, no en una clase `--{accent}` (`DECISIONES #138`): esas
                         reglas solo existían para `jump` y `kids`, y una zona con otro acento se
                         quedaba sin color en silencio. Lo compone `ThemeSettings::zoneStyle()`. --}}
                    <a class="zone-intro__card" href="#rides"
                       style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                       @click.prevent="goToRides('{{ $zone->accent }}')"
                       aria-label="{{ $zone->tr('name') }} · {{ __('landing.zones.see_rides') }}">
                        <div class="zone-intro__bg">{{ $zone->tr('name') }}</div>
                        <div class="zone-intro__top">
                            <span class="zone-intro__tag">{{ $zone->tr('age_label') }} · {{ $zone->tr('age_range') }}</span>
                        </div>
                        <div style="position:relative; z-index:1">
                            <h3 class="zone-intro__name">{{ $zone->tr('name') }}</h3>
                            <p class="zone-intro__sub">{{ $zone->tr('subtitle') }}</p>
                            <p class="zone-intro__copy">{{ $zone->tr('description') }}</p>
                        </div>
                        <div class="zone-intro__meta" style="position:relative; z-index:1">
                            <div><span class="v">{{ number_format($zone->area_sqm, 0, ',', '.') }} m²</span><span class="l">{{ __('landing.zones.surface') }}</span></div>
                            <div><span class="v">{{ $zone->rides_count }}</span><span class="l">{{ __('landing.zones.rides') }}</span></div>
                        </div>
                        <span class="zone-intro__cta" style="position:relative; z-index:1">{{ __('landing.zones.see_rides') }} <x-icons.arrow-right class="arrow" :width="14" :height="14" /></span>
                    </a>
                @endif
            @endforeach
        </div>
    </section>

    {{-- ===================== ATRACCIONES ===================== --}}
    <section id="rides" class="section wrap">
        <div class="rides__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.rides.eyebrow') }}</div>
                <h2 class="rides__title">{{ __('landing.rides.title') }}<br /><em style="font-style:normal; color:var(--zone-1)">{{ __('landing.rides.title_em') }}</em></h2>
            </div>
            <p>{{ __('landing.rides.intro') }}</p>
        </div>

        <div class="rides__controls">
            <div class="zone-tabs">
                @foreach ($zones as $zone)
                    <button class="zone-tab" :class="zone==='{{ $zone->accent }}' && 'active'" @click="setZone('{{ $zone->accent }}')">{{ __('landing.rides.zone_tab') }} {{ $zone->tr('name') }}</button>
                @endforeach
            </div>
            <div class="slider-nav">
                <button class="slider-arrow" @click="scrollSlider(-1)" aria-label="{{ __('landing.nav.slider_prev') }}"><x-icons.arrow-left :width="16" :height="16" /></button>
                <button class="slider-arrow" @click="scrollSlider(1)" aria-label="{{ __('landing.nav.slider_next') }}"><x-icons.arrow-right :width="16" :height="16" /></button>
            </div>
        </div>

        @foreach ($zones as $zone)
            <div class="slider" x-ref="slider_{{ $zone->accent }}" data-zone="{{ $zone->accent }}" data-color="{{ \App\Domain\Content\Services\ThemeSettings::colorForAccent($zone->color, $zone->accent) }}" x-show="zone==='{{ $zone->accent }}'" @scroll="updateProgress()" @if (! $loop->first) style="display:none" @endif>
                @foreach ($zone->attractions as $ride)
                    <article class="ride-card{{ $ride->is_special ? ' ride-card--special' : '' }}{{ $complements->isPurchasable($ride) ? ' ride-card--sellable' : '' }}">
                        <div class="ride-card__viz">
                            @if ($ride->tr('badge'))
                                <span class="ride-card__badge">{{ $ride->tr('badge') }}</span>
                            @endif
                            @if ($ride->image)
                                <img class="ride-card__img" src="{{ asset($ride->image) }}" alt="{{ $ride->tr('name') }}" loading="lazy">
                            @else
                                <span class="ride-card__placeholder">Foto — {{ \Illuminate\Support\Str::lower($ride->tr('name')) }}</span>
                            @endif
                        </div>
                        <h3 class="ride-card__name">{{ $ride->tr('name') }}</h3>
                        <span class="ride-card__age">{{ $ride->tr('age') }}</span>
                        <p class="ride-card__desc">{{ $ride->tr('description') }}</p>
                        {{-- Atracción de pago (#228): si su complemento es comprable en esta zona, precio
                             + CTA «Comprar» que abre la cesta en Entradas, posicionada en esta zona. --}}
                        @if ($complements->isPurchasable($ride))
                            <div class="ride-card__buy">
                                <div class="ride-card__price">@if ($ride->ticketType?->priceVaries())<span class="ride-card__from">{{ __('landing.pricing.from') }}</span>@endif{{ $ride->ticketType?->euros() }}<span class="cents">,{{ $ride->ticketType?->cents() }}</span><span class="eur">€</span></div>
                                <button type="button" class="btn ride-card__cta" aria-label="{{ __('landing.rides.buy') }} · {{ $ride->tr('name') }}" @click="$store.purchase.openWith({ type: 'zone', slug: '{{ $zone->slug }}' })">{{ __('landing.rides.buy') }}</button>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endforeach

        <div class="slider-progress">
            <div class="slider-progress__bar" :style="{ left: (progressLeft*100)+'%', width: (progressWidth*100)+'%' }"></div>
        </div>
    </section>

    <x-site.marquee />

    {{-- ===================== PRECIOS ===================== --}}
    <section id="pricing" class="section wrap">
        <div class="rides__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.pricing.eyebrow') }}</div>
                <h2 class="rides__title">{{ __('landing.pricing.title') }}<br /><em style="font-style:normal; color:var(--zone-1)">{{ __('landing.pricing.title_em') }}</em></h2>
            </div>
            <p>{{ __('landing.pricing.intro') }}</p>
        </div>
        <x-site.ticket-prices :tickets="$tickets" :zones="$zones" />
    </section>

    {{-- ===================== CUMPLEAÑOS (#231) ===================== --}}
    {{-- El componente pinta sus propias secciones con `.wrap` (no envolver en otro). En la
         landing SIN tarjeta de invitación (showInvite=false): solo un enlace sutil a /cumpleanos. --}}
    @if ($packages->isNotEmpty())
        <x-site.events-section :packages="$packages" :show-invite="false" :level="2" />
    @endif

    {{-- ===================== INFO ===================== --}}
    <section id="info" class="section wrap">
        <div class="rides__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.info.eyebrow') }}</div>
                <h2 class="rides__title">{{ __('landing.info.title') }} <em style="font-style:normal; color:var(--zone-1)">{{ __('landing.info.title_em') }}</em></h2>
            </div>
        </div>
        <div class="info__grid">
            <div class="info-card">
                <h3>{{ __('landing.info.hours_title') }}</h3>
                <div class="hours">
                    @forelse ($schedule->weeklyRows() as $row)
                        <span class="day {{ $row['is_today'] ? 'today' : '' }}">{{ $row['is_today'] ? '→ ' : '' }}{{ $row['label'] }}</span>
                        <span class="time {{ $row['is_today'] ? 'today' : '' }}">{{ $row['time'] }}</span>
                    @empty
                        <span class="day">{{ __('landing.info.hours_tbd') }}</span>
                        <span class="time">—</span>
                    @endforelse
                    @foreach ($schedule->seasons() as $season)
                        <span class="day {{ $season['is_current'] ? 'today' : '' }}">{{ $season['is_current'] ? '→ ' : '' }}{{ $season['name'] }} <small style="color:var(--fg-mute); font-weight:400">({{ $season['range'] }})</small></span>
                        <span class="time {{ $season['is_current'] ? 'today' : '' }}">{{ $season['time'] }}</span>
                    @endforeach
                </div>

                @php $specialDates = $schedule->upcomingSpecialDates(); @endphp
                @if (! empty($specialDates))
                    <div style="margin-top:18px; padding-top:16px; border-top:1px solid var(--line)">
                        <h4 style="font-size:14px; font-weight:700; margin:0 0 10px">{{ __('landing.info.special_dates_title') }}</h4>
                        <div class="hours">
                            @foreach ($specialDates as $sd)
                                <span class="day">{{ $sd['date'] }}</span>
                                <span class="time" style="{{ $sd['is_closed'] ? 'color:var(--zone-1)' : '' }}">{{ $sd['detail'] }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="map-card">
                {{-- Mapa de Google embebido si está configurado (Configuración → URL de inserción); si no,
                     el pin decorativo. Bloqueo previo (#219): el iframe solo carga con consentimiento de
                     la categoría «mapa»; si no, placeholder con botón «Cargar mapa». --}}
                <x-site.consent-frame category="maps" :src="$site['maps_embed']"
                    :title="__('landing.info.address_title')"
                    wrapper-style="position:absolute; inset:0"
                    frame-style="width:100%; height:100%; border:0"
                    referrerpolicy="no-referrer-when-downgrade" allowfullscreen>
                    <span class="map-pin"></span>
                </x-site.consent-frame>
                <div style="position:relative; z-index:1; background:var(--bg-card); padding:20px 24px; border-radius:var(--r); border:1px solid var(--line); max-width:340px">
                    <h3 style="margin:0; font-family:var(--font-display); font-size:32px; letter-spacing:-0.03em; font-weight:800">{{ __('landing.info.address_title') }}</h3>
                    <p style="margin:10px 0 16px; color:var(--fg-mute); font-size:14px; line-height:1.6">
                        {{ $site['address1'] ?? '' }}<br />
                        {{ $site['address2'] ?? '' }}<br />
                        {{ __('landing.info.parking') }}
                    </p>
                    <a href="{{ $site['maps'] ?? '#' }}" class="btn btn--ghost btn--sm">{{ __('landing.info.directions') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== NORMAS ===================== --}}
    <section class="section wrap">
        <div class="rides__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.rules.eyebrow') }}</div>
                <h2 class="rides__title">{{ __('landing.rules.title') }}<br /><em style="font-style:normal; color:var(--zone-1)">{{ __('landing.rules.title_em') }}</em></h2>
            </div>
        </div>
        <div class="rules-layout">
            <div class="rules-layout__media">
                <img src="{{ asset('images/historia-seguridad.png') }}"
                     alt="{{ __('landing.rules.title') }} {{ __('landing.rules.title_em') }}" loading="lazy">
            </div>
            <div class="rules-vslider" tabindex="0" aria-label="{{ __('landing.rules.eyebrow') }}">
                @foreach ($rules as $rule)
                    <div class="rule">
                        <div class="rule__icon">!</div>
                        <span class="rule__name">{{ $rule->tr('name') }}</span>
                        <span class="rule__desc">{{ $rule->tr('description') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== GALERÍA ===================== --}}
    @php
        // Galería de polaroids con FOTOS REALES de la clienta (sobrantes del catálogo de
        // atracciones + cumpleaños + plano del parque). Es el FALLBACK del feed social: si hay
        // un embed de IG/TikTok configurado (`$site['social_feed']`), el `<x-site.consent-frame>`
        // prioriza el feed y estas polaroids no se muestran. Los tags son hashtags de marca/zona
        // (universales, no se traducen). file_exists evita un 404 si falta una foto.
        $gallery = array_values(array_filter([
            ['src' => 'images/attractions/cumplea_1.webp', 'tag' => '#cumpleaños'],
            ['src' => 'images/attractions/jump_saltos_libre.webp', 'tag' => '#jump'],
            ['src' => 'images/attractions/cumple_2.webp', 'tag' => '#cumpleaños'],
            ['src' => 'images/attractions/kids_toboganes.webp', 'tag' => '#kids'],
            ['src' => 'images/attractions/jump_circuito.webp', 'tag' => '#jump'],
            ['src' => 'images/attractions/park_jump.webp', 'tag' => '#salta'],
            ['src' => 'images/attractions/cumple_3.webp', 'tag' => '#cumpleaños'],
            ['src' => 'images/attractions/kids_obstaculos.webp', 'tag' => '#kids'],
            ['src' => 'images/attractions/jump_obstaculos.webp', 'tag' => '#jump'],
            ['src' => 'images/attractions/cumple_4.webp', 'tag' => '#cumpleaños'],
            ['src' => 'images/attractions/kids_toboganes_bolas.webp', 'tag' => '#kids'],
            ['src' => 'images/attractions/jump_obstaculos2.webp', 'tag' => '#jump'],
        ], fn (array $g): bool => file_exists(public_path($g['src']))));
    @endphp
    <section id="gallery" class="section wrap">
        <div class="rides__head">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.gallery.eyebrow') }}</div>
                <h2 class="rides__title" style="font-size:clamp(48px, 6vw, 96px)">{{ __('landing.gallery.title') }} <em style="font-style:normal; color:var(--zone-1)">{{ __('landing.gallery.title_em') }}</em></h2>
            </div>
            <p>{{ __('landing.gallery.intro') }}</p>
        </div>
        {{-- Feed social (#215): widget de IG/TikTok (SnapWidget/LightWidget) con las últimas
             publicaciones. La URL llega ya saneada y validada contra la allowlist por
             `SocialEmbed::clean` (en el composer) → el iframe solo carga un origen permitido.
             Bloqueo previo (#219): solo carga con consentimiento de la categoría «redes sociales»
             (transfiere datos a su proveedor); si no, la galería estática de polaroids. --}}
        <x-site.consent-frame category="social" :src="$site['social_feed']"
            :title="__('landing.gallery.title').' '.__('landing.gallery.title_em')"
            wrapper-class="social-embed"
            frame-style="width:100%;border:0;border-radius:18px;min-height:480px"
            referrerpolicy="no-referrer" scrolling="no" allowtransparency="true">
            <div class="gallery-marquee">
                <div class="gallery-marquee__track">
                    @foreach (array_merge($gallery, $gallery) as $g)
                        <figure class="polaroid">
                            <div class="polaroid__img">
                                <img src="{{ asset($g['src']) }}" alt="{{ $site['name'] }} · {{ ltrim($g['tag'], '#') }}" loading="lazy">
                                <span class="polaroid__tag">{{ $g['tag'] }}</span>
                            </div>
                        </figure>
                    @endforeach
                </div>
            </div>
        </x-site.consent-frame>
    </section>

    {{-- ===================== FAQ ===================== --}}
    <section class="section wrap">
        <div class="faq">
            <div>
                <div class="eyebrow" style="margin-bottom:16px">{{ __('landing.faq.eyebrow') }}</div>
                <h2 class="rides__title" style="font-size:clamp(48px, 6vw, 96px)">{{ __('landing.faq.title') }}<br /><em style="font-style:normal; color:var(--zone-1)">{{ __('landing.faq.title_em') }}</em></h2>
            </div>
            <div class="faq__list">
                @foreach ($faqs as $i => $faq)
                    <div class="faq__item" :class="faqOpen==={{ $i }} && 'open'">
                        <button type="button" class="faq__q"
                                @click="faqOpen = faqOpen==={{ $i }} ? -1 : {{ $i }}"
                                :aria-expanded="faqOpen==={{ $i }} ? 'true' : 'false'"
                                aria-controls="faq-answer-{{ $i }}">{{ $faq->tr('question') }}<span class="ico" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg></span></button>
                        <div class="faq__a" id="faq-answer-{{ $i }}">{{ $faq->tr('answer') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        {{-- Datos estructurados FAQPage (invisible): Google puede mostrar estas preguntas como
             desplegable enriquecido en el resultado. --}}
        <x-site.faq-json-ld :faqs="$faqs" />
    </section>

    {{-- ===================== RESERVE CTA ===================== --}}
    <section id="reserve" class="reserve wrap">
        <h2>
            {{ __('landing.reserve.title') }}<br />
            <span class="stroke">{{ __('landing.reserve.stroke') }}</span>
            <span class="fill">{{ __('landing.reserve.fill') }}</span>
        </h2>
        <p>{{ __('landing.reserve.copy') }}</p>
        <div class="reserve__actions">
            <a href="{{ route('entradas') }}" @click.prevent="$store.purchase.open()" class="btn btn--zone btn--lg">{{ __('landing.reserve.cta') }}<x-icons.arrow-right :width="16" :height="16" /></a>
            @if ($site['has_phone'])
                <a href="tel:{{ $site['phone_tel'] }}" class="btn btn--ghost btn--lg">{{ __('landing.reserve.cta2') }} {{ $site['phone'] }}</a>
            @endif
        </div>
    </section>
    </main>

    <x-site.footer />
</div>
</x-layout>
