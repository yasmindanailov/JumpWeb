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
            {{-- ⚠️ **LA CORTINA VUELVE CON SU SUJETO** (`#253`, `[DECIDIDO owner, 2026-08-29]`: «al
                 vídeo le ponemos la cortina»). `#227` la retiró y tenía razón entonces: `#226`
                 había vaciado el hero y era un velo para hacer legible un texto que ya no estaba.
                 Vuelve el texto, vuelve la cortina — sin ella, un titular claro sobre un vídeo se
                 lee en unos fotogramas y en otros no, que es peor que no leerse nunca. --}}
            <div class="hero__stage-scrim" aria-hidden="true"></div>
            <span class="hero__stage-label">{{ __('landing.hero.reel') }}</span>
            {{-- ⚠️⚠️ **EL HERO SE VACÍA — sin eslogan, sin titular visible, sin botones y sin
                 chip de estado** (`#226`, `[DECIDIDO owner, 2026-08-28]`: «el hero lo quiero
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
            {{-- ⚠️⚠️ **EL HERO RECUPERA SU TITULAR** (`#253`, `[DECIDIDO owner, 2026-08-29]`: «en el
                 hero pondremos un titular, un texto "Activa tu modo diversión" en el centro y al
                 vídeo le ponemos la cortina»). Cierra el paréntesis que `#226` abrió a propósito
                 —«por ahora sin texto, después valoramos cómo lo hacemos»— y con él **se cierra
                 también el agujero de accesibilidad y de SEO** que quedaba: el `<h1>` deja de ser
                 solo para lectores de pantalla y vuelve a estar en la página.
                 ▶ Los tres textos ya existían en los idiomas desde `#226` (`hero.kicker`,
                 `hero.l1`, `hero.l2`): nunca se retiraron, solo se dejaron de pintar.
                 ⚠️ **Centrado, y eso NO es del mockup**: el suyo los alinea abajo a la izquierda.
                 Lo pidió el owner explícitamente («en el centro»), así que queda escrito que es
                 una desviación decidida y no un descuido. El ESTILO del rótulo sí es el suyo:
                 rotulador, girado, en el color de acción. --}}
            <div class="hero__stage-content">
                {{-- ⚠️⚠️ **EL ESLOGAN Y EL TITULAR VAN EN LA MISMA CAJA, y eso es lo que hace que el
                     eslogan se pegue a la esquina superior IZQUIERDA del titular** (`#276`,
                     `[DECIDIDO owner]`: «ponlo pegado al texto DIVERSIÓN en la esquina superior
                     izquierda»).

                     ▶ **Por qué hace falta un envoltorio y no basta una regla.** El bloque del hero
                     va centrado (`align-items: center`, decisión del owner en `#253`), así que el
                     eslogan se centraba sobre el titular: arrancaba por la mitad de la palabra.
                     Alinearlo a la izquierda **del contenedor** lo habría mandado al filo del hero,
                     que es otro sitio. El filo que importa es el del TITULAR, y para conocerlo hay
                     que compartir caja con él: la caja se ajusta al titular y el eslogan se alinea
                     dentro.
                     ⚠️ `max-width: 100%` no es decorativo: sin él la caja mide `max-content` y en
                     un teléfono el titular la sacaría del viewport. --}}
                <div class="hero__headline">
                    <span class="hero__kicker">{{ __('landing.hero.kicker') }}</span>
                {{-- ⚠️ `--onvideo` no es decorativo: es el modificador que hace que el titular
                     **encoja con el hero** (`#220`, `--hero-t-ini` → `--hero-t-end`). Sin él cae
                     en la escala base, que es la del titular gigante de la portada antigua: medido,
                     320 px a 1920 y **558 px de ancho en un teléfono de 390**. --}}
                {{-- ⚠️⚠️ **El segundo renglón es un INTERRUPTOR ENCENDIDO** (`#254`,
                     `[DECIDIDO owner]`: «Diversión ON, y ese ON que sea un toggle que esté
                     activado, respetando el diseño»).
                     ▶ **Es decorativo y por eso NO es un control**: nada se enciende ni se apaga
                     al pulsarlo. Un `<button>` o un `role="switch"` aquí prometería una acción que
                     no existe —y un lector de pantalla anunciaría «interruptor, activado»— así que
                     el dibujo va `aria-hidden` y lo que se lee es el texto del titular, tal cual.
                     ⚠️ El rótulo sale del idioma (`hero.l2`): el dibujo es del producto, la
                     palabra es de la instalación. --}}
                    <h1 class="hero__title hero__title--onvideo">{{ __('landing.hero.l1') }} <span class="hero__switch"><span class="hero__switch-sw" aria-hidden="true"><span class="hero__switch-knob"></span><span class="hero__switch-on">{{ __('landing.hero.l2') }}</span></span><span class="sr-only">{{ __('landing.hero.l2') }}</span></span></h1>
                </div>

                {{-- ⚠️⚠️ **EL CTA DEL HERO ES EL PAR DEL ARMAZÓN, y baja aquí** (`#254`,
                     `[DECIDIDO owner]`: «esos dos botones los quitamos y ponemos debajo el CTA que
                     tenemos en el bottom right, el que se cambia, más grande»).
                     ▶ Los dos botones propios que `#253` devolvió duraban una tanda: eran **una
                     tercera pieza de compra** en la misma pantalla, junto al par de la esquina y a
                     la barra de móvil. Con el par aquí, el relevo con la cabecera sigue siendo *el
                     mismo botón cambiando de sitio* —que es lo que `#227` construyó— y la primera
                     pantalla sigue ofreciendo comprar, que es lo que sostiene que el armazón nazca
                     oculto (`#216`).
                     ⚠️ **En MÓVIL este par no se pinta** y no es un olvido: ahí el CTA ya vive
                     abajo, en la barra flotante, y está en el mismo sitio. --}}
                <div class="hero__pair-slot">
                    <x-site.cta-pair place="hero" />
                </div>
            </div>

        </div>

        {{-- ⚠️ **LA TIRA DE MARCA DEL HERO, y VIAJA** (`#226`, del mockup `Landing PJP Modos`).
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
            <x-site.brand-strip class="brand-strip--wedge hero__strip-box" />
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
                {{-- ⚠️ El separador entre cifras **ya no está en el marcado**: lo pinta el CSS con
                     `.zone-stats__item + .zone-stats__item::before`. Aquí había un `<span>` con
                     `.jj-block`, el cuadrado «foam» del cliente ANTIGUO —sus iniciales daban nombre
                     a la clase—, y su primer sustituto repetía una TEXTURA una vez por fila, que es
                     justo lo que `FacadeDecorationIsPerScreenTest` prohíbe. La guarda lo cazó: un
                     separador es puntuación, y la puntuación es del CSS. --}}
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
                        {{-- La ilustración de la zona, «apoyada en el borde» — literalmente lo que
                             dice su `E3`: «foto arriba con mancha entrando por la esquina y silueta
                             apoyada en el borde». La pose la asigna él en `F10` y no cambia.
                             ⚠️⚠️ Va TAMBIÉN aquí y no solo en el fallback: ésta es la variante que
                             usan las zonas con foto, o sea las reales. Con el dibujo solo en la otra
                             rama, en esta instalación no se veía en NINGUNA parte. --}}
                        <x-site.ilu :clave="'zone-'.$zone->slug" class="zone-photo-card__ilu" />
                        <div class="zone-photo-card__body">
                            <span class="tag tag--senal tag--punteada zone-photo-card__tag">{{ $zone->tr('age_label') }} · {{ $zone->tr('age_range') }}</span>
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
                        {{-- **EL PRIMER CONSUMIDOR DEL HUECO DE ILUSTRACIÓN** (`#257`, cerrada aquí).
                             La clave sale de `zones.slug`, que ya existe: por eso esta pantalla fue la
                             elegida para estrenar el mecanismo —es la única que no exige declarar
                             ninguna ranura nueva—.
                             ⚠️ Sin `client-kit.svg` el componente **no emite nada** y la tarjeta queda
                             exactamente como estaba: el hueco falla hacia invisible a propósito. --}}
                        <x-site.ilu :clave="'zone-'.$zone->slug" class="zone-intro__ilu" />
                        <div class="zone-intro__top">
                            <span class="tag tag--senal tag--punteada zone-intro__tag">{{ $zone->tr('age_label') }} · {{ $zone->tr('age_range') }}</span>
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
                    <button class="zone-tab" data-tap :class="zone==='{{ $zone->accent }}' && 'active'" @click="setZone('{{ $zone->accent }}')">{{ __('landing.rides.zone_tab') }} {{ $zone->tr('name') }}</button>
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
                                <span class="tag tag--senal tag--punteada ride-card__badge">{{ $ride->tr('badge') }}</span>
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
                    <a href="{{ $site['maps'] ?? '#' }}" class="btn btn--ghost btn--sm" data-tap>{{ __('landing.info.directions') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== NORMAS ===================== --}}
    {{-- **T1 del carril de idioma visual** (`specs/idioma-visual-heredado.md`, `[DECIDIDO owner]`:
         «las normas irán sin imagen, solo será texto, un texto simple y un CTA a la página de
         normas… en la landing, lo más importante»).

         ▶ Aquí había un **pliego de doce pictogramas del cliente ANTIGUO** (`images/historia-
         seguridad.png`) junto a un carrusel vertical con TODAS las normas. Dos formas de decir lo
         mismo, una de ellas con arte de otro parque, y ninguna cabía en un teléfono.
         ▶ Ahora: **tres normas** —las tres primeras del panel, que es quien las ordena— en texto
         plano y numerado, y el CTA a `/normas`, que es donde están todas.

         ⚠️ **El TOPE de tres se declara aquí y no en el panel**: el operador decide QUÉ normas hay
         y en qué orden; cuántas caben en la portada es una decisión de diseño, no de contenido. --}}
    <section class="section wrap">
        <div class="rules-lite">
            {{-- `B1·03` del kit: mancha «de lengüetas largas», que es la que su propia nota manda a
                 **esquinas y bordes**. Es la PRIMERA ranura decorativa del producto (`slot-normas`)
                 y nace con su consumidor en el mismo cambio, que es la regla del carril.
                 ⚠️ Va fuera del bucle a propósito: una por PANTALLA, no una por norma. --}}
            <x-site.ilu clave="slot-normas" class="rules-lite__mancha" />

            <div class="rules-lite__head">
                <div class="eyebrow">{{ __('landing.rules.eyebrow') }}</div>
                <h2 class="rides__title">{{ __('landing.rules.title') }}<br /><em style="font-style:normal; color:var(--zone-1)">{{ __('landing.rules.title_em') }}</em></h2>
                <p class="rules-lite__intro">{{ __('landing.rules.intro') }}</p>
            </div>

            <ol class="rules-lite__list">
                @foreach ($rules->take(3) as $rule)
                    <li class="rules-lite__item">
                        <span class="rules-lite__num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="rules-lite__name">{{ $rule->tr('name') }}</span>
                        <span class="rules-lite__desc">{{ $rule->tr('description') }}</span>
                    </li>
                @endforeach
            </ol>

            <a href="{{ route('normas') }}" class="btn btn--ghost rules-lite__cta" data-tap>
                {{ __('landing.rules.cta') }} <x-icons.arrow-right class="arrow" :width="14" :height="14" />
            </a>
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
                        <button type="button" class="faq__q" data-tap
                                @click="faqOpen = faqOpen==={{ $i }} ? -1 : {{ $i }}"
                                :aria-expanded="faqOpen==={{ $i }} ? 'true' : 'false'"
                                aria-controls="faq-answer-{{ $i }}">{{ $faq->tr('question') }}<span class="ico" aria-hidden="true"><x-icons.plus :width="14" :height="14" /></span></button>
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
    {{-- ══ EL HERO DEL CIERRE (`#229`, del mockup `Landing PJP Modos`) ═════════════════════════
         `[DECIDIDO owner]`: «lo mismo en el footer y el hero del footer».

         ▶ **Es la imagen ESPECULAR del hero de cabecera**, y esa simetría es el gesto: el de
         arriba empieza a pantalla completa y encoge hasta ser una tarjeta; éste empieza siendo
         una tarjeta y **crece hasta llenar la pantalla** al llegar al final. Hasta las tallas del
         titular son las mismas al revés — arranca en la talla FINAL del hero de cabecera y acaba
         en su talla INICIAL.

         ⚠️⚠️ **El mockup lo hace con `position: fixed` y anchos calculados en JavaScript; aquí NO.**
         Su `aplicaCierre` mide la tarjeta, la fija a la ventana y le escribe `left`, `width`,
         `height` y `border-radius` en cada fotograma. Nosotros usamos el mismo recurso que el hero
         de cabecera —un envoltorio PEGAJOSO y un recorrido de scroll real— porque el resultado es
         idéntico y el reparto de responsabilidades no: aquí el JavaScript publica **un número**
         (`--cierre-p`) y todo lo demás lo decide el CSS, así que una instalación puede alargar el
         recorrido, cambiar la talla de partida o apagar el crecimiento desde su paquete.
         ▶ Y hay un motivo práctico además del arquitectónico: con `position: fixed` la tarjeta
         sale del flujo y hay que devolverle su hueco a mano (`sec.style.minHeight = r0.height`),
         que es exactamente el tipo de arreglo que se rompe cuando el contenido cambia de alto.

         ⚠️ **El recorrido es altura REAL**, igual que el del hero: son píxeles de scroll que
         existen. Con movimiento reducido se pone a 0 y desaparece, o quedaría una pantalla de
         scroll vacío que nadie sabría por qué está ahí. --}}
    <section id="reserve" class="reserve" x-data="cierreChoreo">
        {{-- ⚠️ **`saltaJuego` vive en la TARJETA, no en el lienzo** (`#235`): el mockup desvanece el
             párrafo y los CTA mientras se juega, y para eso el estado del juego tiene que alcanzar
             a hermanos suyos. Con el `x-data` en el lienzo, la mitad de arriba no se enteraba. --}}
        <div class="reserve__box" data-surface="ink"
             x-data="saltaJuego"
             :class="fase === 'jugando' && 'reserve__box--jugando'">
            {{-- La trama de puntos, la misma que el menú: es la única textura que el sistema
                 admite sobre tinta, y aquí sale del mismo mecanismo. --}}
            <div class="grain" aria-hidden="true"></div>

            {{-- ══ EL LIENZO Y LA UI DEL JUEGO ═══════════════════════════════════════════════════
                 El lienzo hace de ESCENARIO: en reposo es una tira de 150 px con un muñeco en
                 piloto automático; al empezar a jugar crece al 44 % de la ventana, y esa altura ES
                 el zoom (`k = alto / 300`).

                 ⚠️ **El lienzo ARRANCA la partida al tocarlo**, no solo el botón. En la primera
                 versión solo arrancaba desde el botón, así que con teclado había que tabular hasta
                 él y pulsar Enter — que es justo lo que el owner señaló. Ahora el espacio, las
                 flechas, `W`, `Enter` y el toque hacen lo mismo: empezar si no se juega, saltar si
                 se juega. Es lo que hace el mockup con un solo manejador. --}}
            <canvas class="salta__lienzo" x-ref="lienzo" aria-hidden="true"
                    :style="fase === 'off' ? 'pointer-events:none' : 'pointer-events:auto;cursor:pointer'"
                    @pointerdown="toca($event)"></canvas>

            <button type="button" class="salta__invita" x-show="fase === 'listo'"
                    @click="juega()"
                    :aria-label="@js(__('landing.game.aria'))">
                <span class="salta__invita-ico" aria-hidden="true">
                    {{-- ⚠️ Era este MISMO dibujo escrito en línea: `ui/play` del artboard, letra
                         por letra. Al set porque un dibujo suelto en el marcado no lo puede
                         sustituir un cliente (`landing-white-label.md` §4.5). --}}
                    <x-icons.play :width="11" :height="11" />
                </span>
                <span class="salta__invita-t" x-text="tactil ? @js(__('landing.game.play_touch')) : @js(__('landing.game.play'))"></span>
                <span class="salta__rec" x-show="record > 0" x-text="@js(__('landing.game.rec', ['m' => '§'])).replace('§', record)"></span>
            </button>

            {{-- Marcador. `aria-live` para que quien no ve el lienzo sepa cómo va. --}}
            <div class="salta__hud" x-show="fase === 'jugando'" aria-live="polite" aria-atomic="true">
                <span class="salta__chip">
                    <strong x-text="metros">0</strong>
                    <span class="salta__u">{{ __('landing.game.m') }}</span>
                </span>
                <span class="salta__chip salta__chip--band">
                    <span class="salta__aro" aria-hidden="true"></span>
                    <strong x-text="pulseras">0</strong>
                    <span class="sr-only">{{ __('landing.game.bands') }}</span>
                </span>
                <span class="salta__pista">
                    <span x-text="@js(__('landing.game.record', ['m' => '§'])).replace('§', record)"></span>
                    ·
                    <span x-text="tactil ? @js(__('landing.game.hint_touch')) : @js(__('landing.game.hint'))"></span>
                </span>
            </div>

            {{-- Resultado. Su segundo botón es RESERVAR, no «salir»: es el final de la portada y el
                 sitio donde alguien que acaba de jugar está más dispuesto. Lo hace el mockup. --}}
            <div class="salta__fin" x-show="fase === 'fin'" role="status">
                <span class="salta__fin-rec" x-show="nuevoRecord">{{ __('landing.game.newrec') }}</span>
                <span class="salta__fin-m">
                    <strong x-text="metros">0</strong><span class="salta__fin-u"> {{ __('landing.game.m') }}</span>
                    <span class="salta__fin-band"><span class="salta__aro" aria-hidden="true"></span><span x-text="pulseras">0</span></span>
                </span>
                <span class="salta__fin-best" x-text="@js(__('landing.game.record', ['m' => '§'])).replace('§', record)"></span>
                <span class="salta__fin-acts">
                    <button type="button" class="salta__btn" @click="juega()"
                            x-text="tactil ? @js(__('landing.game.again_touch')) : @js(__('landing.game.again'))"></button>
                    <button type="button" class="salta__btn salta__btn--ghost"
                            @click="sal(); $store.purchase.open()">{{ __('landing.game.book') }}</button>
                </span>
            </div>

            {{-- ⚠️ **EL TAG DE LA CIUDAD** (`#235`, del mockup): una pieza de marca girada en la
                 esquina inferior derecha. Es del CLIENTE, así que el producto pone el hueco y el
                 paquete de instalación pone el dibujo (`--deco-tag`). Sin él no se pinta nada:
                 `background-image: none` no dibuja caja, así que aquí no hace falta el truco de la
                 máscara transparente que sí necesitan las manchas del menú. --}}
            <span class="reserve__tag" aria-hidden="true"></span>

            <div class="reserve__body">
                <h2>
                    {{ __('landing.reserve.title') }}<br />
                    <span class="stroke">{{ __('landing.reserve.stroke') }}</span>
                    <span class="fill">{{ __('landing.reserve.fill') }}</span>
                </h2>
                <p>{{ __('landing.reserve.copy') }}</p>
                <div class="reserve__actions">
                    <a href="{{ route('entradas') }}" @click.prevent="$store.purchase.open()" class="reserve__act">{{ __('landing.reserve.cta') }}</a>
                    @if ($site['has_phone'])
                        <a href="tel:{{ $site['phone_tel'] }}" class="reserve__act reserve__act--alt">{{ __('landing.reserve.cta2') }} {{ $site['phone'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
    </main>

    <x-site.footer />

    {{-- ⚠️⚠️ **EL RECORRIDO DEL HERO DEL CIERRE VA AQUÍ, DESPUÉS DEL PIE — y ése era el fallo.**
         (`#233`, corrigiendo a `#229`.) La primera versión lo puso DENTRO de la sección y usó un
         envoltorio pegajoso: la tarjeta crecía **antes** del pie y luego te lo pasabas. El mockup
         hace lo contrario, y es otro gesto: la tarjeta se ancla arriba y **crece mientras el pie
         pasa por detrás**, llenando la pantalla justo al llegar al final del documento.
         ▶ `position: sticky` NO puede hacer eso —solo pega dentro de su propio padre—, así que
         aquí la tarjeta sí se fija a la ventana, como en el mockup. Rechazarlo en `#229` por
         limpieza arquitectónica cambió la coreografía, que era lo único que no se podía cambiar.
         ▶ Es altura REAL: los píxeles de scroll que dura el crecimiento. Con movimiento reducido
         se queda a 0 y no hay hueco. --}}
    <div class="reserve__runway" aria-hidden="true"></div>
</div>
</x-layout>
