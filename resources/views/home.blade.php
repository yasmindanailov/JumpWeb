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
    {{-- El salto al contenido ya NO se pinta aquí: lo sirve `<x-site.nav>` para las DOCE vistas
         (armazón · tanda 2c·2). Aquí solo quedaba porque la home fue la primera en tenerlo. --}}
    <x-site.nav />
    <main id="main">

    {{-- ===================== HERO ===================== --}}
    {{-- ⚠️ `heroChoreo` (`resources/js/app.js`) publica UNA custom property, `--hero-p`, con el
         progreso del scroll de 0 a 1. **No decide nada de diseño**: los dos estados del hero —el
         de pantalla completa y el de tarjeta— los define el CSS con `calc()`, así que un paquete
         de instalación puede cambiarlos sin tocar una línea de JavaScript.
         ▶ Sin JS, o con `prefers-reduced-motion`, `--hero-p` se queda en 0 y el hero se ve en su
         estado inicial. Es un estado válido y completo, no una degradación rota. --}}
    <header id="top" class="hero hero--full" x-data="heroChoreo">
        {{-- ⚠️⚠️ `data-surface="ink"` NO es decorativo: es el ÚNICO sitio del producto donde se
             declara una superficie, y es lo que la tanda 1 de `specs/tema-por-instalacion.md`
             construyó para que existiera. Dentro de este envoltorio los siete tokens de
             superficie se redefinen —`--fg` vale claro, `--bg` oscuro, `--fg-mute` el gris de
             tinta— así que TODA regla que cuelgue de aquí se invierte sola, sin modificador.
             Por eso `--onvideo` ha dejado de pintar colores: los pinta el ámbito.
             ▶ Lo que sigue en `--onvideo` NO es superficie: es el TAMAÑO del titular y las
             sombras sobre oscuro. `[data-surface]` nunca iba a cubrir eso. --}}
        {{-- El escenario PEGAJOSO: se queda arriba mientras dura el recorrido, y dentro la
             caja del hero encoge. El margen y el hueco para el nav los pone este envoltorio;
             la caja solo cambia de alto y de ancho. --}}
        <div class="hero__sticky">
        <div class="hero__stage" data-surface="ink" data-has-video="true">
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
            {{-- ⚠️⚠️ **EL HERO SE VACÍA — sin eslogan, sin titular visible, sin botones y sin
                 chip de estado** (`#224`, `[DECIDIDO owner, 2026-08-28]`: «el hero lo quiero
                 **por ahora** sin texto y sin botones, después valoraremos cómo lo hacemos»).
                 La primera pantalla pasa a ser el vídeo y la tira de marca, y nada más.

                 ▶ **Es PROVISIONAL y así se decidió.** Lo que había —eslogan, titular con su
                 coreografía de tamaño (`#220`), los dos botones de `#216` y el chip de estado—
                 está entero en el historial; su CSS se queda **aparcado a propósito**, sin
                 retirar, porque retirarlo convertiría una decisión provisional en un
                 desmantelamiento. Ficha en `docs/DEUDA.md`.

                 ⚠️⚠️ **Y esto REABRE, a sabiendas, el agujero que `#216` cerró**: con el armazón
                 naciendo oculto bajo el hero, la primera pantalla **no ofrece comprar ni
                 navegar**. `#216` le devolvió los botones al hero justamente por eso. El owner
                 lo ha elegido con el efecto delante (opción «hero limpio y armazón oculto, como
                 el mockup»), así que es una decisión, no un descuido — pero **no puede subir a
                 producción sin resolverse**. Es lo primero que hay que mirar al «valorar cómo lo
                 hacemos».

                 ⚠️ **El `<h1>` NO se va: se queda para lectores de pantalla.** Era el ÚNICO de la
                 portada, y una página sin encabezado principal es una regresión de SEO y de
                 accesibilidad que no tiene nada que ver con lo que se pidió. «Sin texto» es una
                 decisión VISUAL; el documento sigue necesitando su título. --}}
            <div class="hero__stage-content">
                <h1 class="sr-only">{{ __('landing.hero.l1') }} {{ __('landing.hero.l2') }}</h1>
            </div>
        </div>

        {{-- ⚠️ **LA TIRA DE MARCA DEL HERO, y VIAJA** (`#224`, del mockup `Landing PJP Modos`).
             Arranca como un pelo de 4 px pegado al filo superior de la página —por encima del
             hero, que a esa altura va a sangre— y al encoger el hero **baja hasta quedar 28 px
             por debajo de la tarjeta**. Empieza siendo el borde de la página y acaba siendo el
             subrayado del hero.

             ▶ Va DENTRO del envoltorio pegajoso y no en el `<header>`: tiene que viajar con el
             escenario, no con el documento. Y va DESPUÉS del escenario en el marcado con
             `z-index: -1`, que es lo que hace el mockup: si el hero crece hasta taparla, la tira
             pasa por detrás en vez de flotar sobre el vídeo.

             ⚠️ **Es decorativa y no toca**: `aria-hidden` (lo pone el componente) y
             `pointer-events: none` en el envase, porque cruza por delante del área del hero.

             ⚠️ Cuánto viaja lo decide el CSS con `--hero-p`, no el JS. Es la misma regla que el
             resto de la coreografía (`#195`): el JavaScript publica UNA custom property y no
             decide diseño, para que una instalación pueda cambiar el recorrido desde su hoja. --}}
        <div class="hero__strip" aria-hidden="true">
            <x-site.brand-strip class="hero__strip-box" />
        </div>
        </div>

        {{-- ⚠️⚠️ **EL SENTINEL, y no es decorativo.** Dos comportamientos de COMPRA lo observan
             con `IntersectionObserver` (`resources/js/app.js`): el CTA «Comprar entradas» del nav
             en escritorio (`navCtaReveal`) y la barra flotante de reserva en móvil
             (`mobileBookBar`). Los dos están escritos para que el hero y el botón de comprar
             nunca sean co-visibles.
             ▶ Hasta hoy la señal era `.hero__stage-bottom`, o sea **el propio CTA del hero**.
             Funcionaba por casualidad: el sitio donde acababa el botón coincidía con el sitio
             donde queríamos que aparecieran los otros. Al retirar ese CTA la señal desaparecía,
             y con la coreografía el stage va `sticky` —dentro de un sticky nada abandona el
             viewport—, así que el momento de ofrecer la compra habría dejado de ser el que
             alguien diseñó. Aquí es un elemento PROPIO, vacío, fuera del `sticky` y al final del
             hero: hace UN trabajo y se puede mover sin tocar ningún botón. `DEUDA.md` (`#194`). --}}
        <div class="hero__sentinel" aria-hidden="true"></div>
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
                            <x-site.zone-metrics :zone="$zone" class="zone-photo-card__meta" />
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
                        <x-site.zone-metrics :zone="$zone" class="zone-intro__meta" style="position:relative; z-index:1" />
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
            {{-- ⚠️ El slider lleva la PALETA YA COMPUESTA (`data-zone-style`, `DECISIONES #139`).
                 `data-color` traía solo el primario, así que el JS tenía que (a) quemar el secundario
                 a la paleta del primer cliente y (b) **repetir la fórmula de contraste de
                 `ThemeSettings::onBrand()`** en JavaScript. Dos definiciones de la misma regla es
                 como empiezan las divergencias que el tema existe para cerrar. --}}
            <div class="slider" x-ref="slider_{{ $zone->accent }}" data-zone="{{ $zone->accent }}"
                 data-color="{{ \App\Domain\Content\Services\ThemeSettings::colorForAccent($zone->color, $zone->accent) }}"
                 data-zone-style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                 x-show="zone==='{{ $zone->accent }}'" @scroll="updateProgress()" @if (! $loop->first) style="display:none" @endif>
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
