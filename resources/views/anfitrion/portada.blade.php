{{-- ══ EL ANFITRIÓN MÍNIMO de la PORTADA · lo que el producto sirve SIN paquete de instancia ══════
     F5 · T2b (`specs/paquete-de-instancia.md` §4.4 y §4.7.bis, `DECISIONES #666`). La portada de
     PlayJump —las ocho secciones del canvas, 1.639 líneas— vive en su instancia; esto es lo que
     queda en el producto: el armazón, **las cinco anclas que el menú y el pie anuncian**, los
     componentes que el producto presta y los datos del contrato de vista. Sin arte: ni fachada, ni
     manchas, ni trío, ni tira de marca, ni vídeo. Es marcado del PRODUCTO (`AnfitrionPortadaTest`).

     ❗❗ **LAS CINCO ANCLAS NO SON DECORACIÓN: SON CONTRATO.** `SiteDestinations::HOME_SECTIONS`
     publica `#zones`, `#rides`, `#before`, `#info` y `#faq` en el menú y en el pie de las DOCE
     vistas. Un ancla a una sección que no está no falla —el navegador se queda donde estaba—, así
     que nadie lo vería: es justo la clase de rotura que sale en la web de un cliente y no aquí.

     ⚠️⚠️ **NO DA POR HECHO QUE HAY VÍDEO** (`#664`). En la portada mudada `data-has-video="true"`
     está escrito a mano desde el commit fundacional, así que el estado «sin vídeo» que el CSS tiene
     diseñado —fondo con los colores de zona y su rótulo— **no se alcanza hoy**. Aquí sí se alcanza:
     una instalación recién montada no tiene vídeo, y el hero tiene que verse igual de bien.

     ⚠️⚠️ **El `.hero__stage` DECLARA su superficie**, y no es decorativo: es el ÚNICO sitio del
     producto donde se declara una (`tema-por-instalacion.md`, tanda 1). Sin `data-surface="ink"`
     los siete tokens no se redefinen, el hero pinta crema sobre crema y **no falla nada, solo deja
     de verse** (`SurfaceScopeTest::test_the_hero_declares_its_surface`).

     ⚠️ **El SENTINEL tampoco es decorativo**: dos comportamientos de COMPRA lo observan con
     `IntersectionObserver` (`navCtaReveal` y `mobileBookBar`, en `resources/js/app.js`), y los dos
     están escritos para que el hero y el botón de comprar nunca sean co-visibles. Es del producto.

     ⚠️ Lo que se pinta aquí es EXACTAMENTE el contrato de vista (`InstanceViews::CONTRATO_DE_VISTAS`):
     una instalación que pinte lo mismo con su diseño no necesita saber nada más. --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="$noindex ?? false" :has-hero="true">
<div x-data="landing">
    {{-- Solo la portada tiene SECCIONES a las que bajar, y es ella quien las pasa (`#521`). --}}
    <x-site.nav :sections="$menuSections" />

    <main id="main">

    {{-- ══ EL HERO ═══════════════════════════════════════════════════════════════════════════════
         El escenario pegajoso y su coreografía son del producto: `heroChoreo` publica UNA custom
         property (`--hero-p`) y **no decide nada de diseño**; los dos estados los define el CSS con
         `calc()`, así que un paquete puede cambiarlos sin tocar una línea de JavaScript. --}}
    <header id="top" class="hero hero--full" x-data="heroChoreo">
        <div class="hero__sticky">
        {{-- ⚠️ `data-has-video` sale del DATO: sin vídeo de la instalación, el estado que el CSS
             tiene diseñado para ese caso es el que se pinta. La portada de una instancia puede
             escribirlo a mano si sabe que siempre lo tiene; el producto no puede. --}}
        <div class="hero__stage" data-surface="ink" data-has-video="false">
            <div class="hero__stage-placeholder" aria-hidden="true"></div>
            <div class="hero__stage-scrim" aria-hidden="true"></div>
            <div class="hero__stage-content">
                <div class="hero__headline">
                    <span class="hero__kicker">{{ __('landing.hero.kicker') }}</span>
                    {{-- ⚠️ El `<h1>` es el ÚNICO de la página y no se va nunca: una portada sin
                         encabezado principal es una regresión de SEO y de accesibilidad. --}}
                    <h1 class="hero__title hero__title--onvideo">{{ __('landing.hero.l1') }} {{ __('landing.hero.l2') }}</h1>
                    <p class="hero__tag">{{ __('landing.hero.tag') }}</p>
                </div>
                {{-- El par de compra del armazón, bajado al hero: es el MISMO botón cambiando de
                     sitio (`#227`), no una tercera pieza de compra. En móvil no se pinta —ahí el CTA
                     ya vive en la barra flotante—, y eso lo decide el componente. --}}
                <div class="hero__pair-slot">
                    <x-site.cta-pair place="hero" />
                </div>
            </div>
        </div>

        {{-- ⚠️ **LA TIRA DE MARCA VIAJA CON EL ESCENARIO, y por eso va DENTRO del pegajoso** y no en
             el `<header>`: arranca como un pelo pegado al filo superior de la página y acaba siendo
             el subrayado del hero. Va DESPUÉS del escenario en el marcado y con `z-index: -1`: si el
             hero crece hasta taparla, pasa por detrás en vez de flotar sobre él.
             ⚠️ Cuánto viaja lo decide el CSS con `--hero-p`, no el JS: misma regla que el resto de la
             coreografía (`#195`), para que una instalación pueda cambiar el recorrido desde su hoja. --}}
        <div class="hero__strip" aria-hidden="true">
            <x-site.brand-strip class="brand-strip--wedge hero__strip-box" />
        </div>
        </div>
        <div class="hero__sentinel" aria-hidden="true"></div>
    </header>

    {{-- ══ 01 · PARA QUIÉN · las zonas ═══════════════════════════════════════════════════════════
         El ancla la anuncian el menú y el pie. El contenido sale ENTERO de `ZoneCards`: nombre,
         descripción, edad, altura y la foto. Lo que no hay, no se pinta. --}}
    <section id="zones" class="section wrap">
        <div class="zones__head">
            <p class="zones__eyebrow">{{ __('landing.zones.eyebrow') }}</p>
            <div class="zones__titulo">
                <h2 class="zones__title">{{ __('landing.zones.title') }}</h2>
            </div>
            <p class="sec-head__lede">{{ __('landing.zones.lede') }}</p>
        </div>

        <ul class="zone-cards" role="list">
            @foreach ($zoneCards as $card)
                <li class="zone-cards__item">
                    {{-- La tarjeta ENTERA es el enlace y lleva a las atracciones de esa zona: meter
                         un control dentro de un `<a>` es marcado inválido y dos dianas para el mismo
                         destino. --}}
                    {{-- ⚠️ El velo de color sale del DATO (`ZoneCards` compone `tint` y su opacidad):
                         es lo único que `#436` dejó teñir con la paleta de una zona —lo que la
                         IDENTIFICA—, después de sacarla de los CONTROLES. --}}
                    <a class="zone-card" href="{{ route('atracciones', ['zona' => $card['slug']]) }}"
                       data-zone="{{ $card['slug'] }}"
                       @if ($card['tint']) style="--zone-tint: {{ $card['tint'] }}; --zone-tint-op: {{ $card['tintOpacity'] }};" @endif>
                        <div class="zone-card__viz">
                            @if ($card['image'])
                                {{-- ⚠️ `alt=""` con `aria-hidden`: la foto es DECORACIÓN — el nombre
                                     de la zona y su edad están en texto justo debajo. --}}
                                <img src="{{ $card['image'] }}" alt="" aria-hidden="true" loading="lazy" decoding="async" width="800" height="450">
                            @endif
                            {{-- El sello dice A QUIÉN le toca la zona, no cuánto cuesta (`#587`).
                                 Con ninguno de los dos datos no se pinta un sello vacío. --}}
                            @if ($card['age'] || $card['height'])
                                <p class="zone-card__seal">
                                    @if ($card['age'])
                                        <span class="zone-card__seal-age">{{ $card['age'] }}</span>
                                    @endif
                                    @if ($card['height'])
                                        <span class="zone-card__seal-height">{{ $card['height'] }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        {{-- ❗❗ **El cuerpo es una ESCALA DE ESTATURA, no un bloque de texto**, y el
                             ORDEN de sus piezas sale del LADO que compone `ZoneCards` —nunca de
                             adivinar qué zona es—: la de «desde 1,30 m» colorea de la línea hacia
                             arriba y la de «hasta 1,30 m» hacia abajo, así que las dos tarjetas se
                             leen juntas como una sola escala. Por eso el bloque de la vecina está
                             dos veces: es la misma pieza en dos sitios, no dos piezas. --}}
                        <div class="zone-card__body"
                             @if ($card['heightAxis'])
                                 data-axis data-side="{{ $card['heightAxis']['side'] }}"
                             @endif>
                            {{-- ⚠️ Los dos extremos de la escala son de la ESCALA y no de una zona:
                                 las dos tarjetas rotulan el MISMO techo, y por eso llegan aparte.
                                 ⚠️ `aria-hidden` porque es el DIBUJO de una regla que el texto de al
                                 lado ya dice. --}}
                            @if ($card['heightAxis'])
                                <div class="zone-card__axis" aria-hidden="true">
                                    <span class="zone-card__axis-top">
                                        <b>{{ __('landing.zones.axis_label') }}</b>
                                        <i>{{ $zoneAxisCeiling }}</i>
                                    </span>
                                    <span class="zone-card__axis-zero">{{ $zoneAxisFloor }}</span>
                                </div>
                            @endif

                            @if ($card['heightAxis'] && $card['heightAxis']['side'] === 'below' && $card['heightAxis']['neighbour'])
                                <p class="zone-card__neighbour">{{ __('landing.zones.above_is', ['zone' => $card['heightAxis']['neighbour']]) }}</p>
                                <p class="zone-card__border"><span class="zone-card__chip">{{ $card['heightAxis']['label'] }}</span></p>
                            @endif

                            <div class="zone-card__text">
                                <span class="zone-card__tint" aria-hidden="true"></span>
                                <span class="zone-card__inner">
                                    <h3 class="zone-card__name">{{ $card['name'] }}</h3>
                                    @if ($card['description'])
                                        <span class="zone-card__who">
                                            <span class="zone-card__what">{{ $card['description'] }}</span>
                                        </span>
                                    @endif
                                </span>
                            </div>

                            @if ($card['heightAxis'] && $card['heightAxis']['side'] === 'above' && $card['heightAxis']['neighbour'])
                                <p class="zone-card__border"><span class="zone-card__chip">{{ $card['heightAxis']['label'] }}</span></p>
                                <p class="zone-card__neighbour">{{ __('landing.zones.below_is', ['zone' => $card['heightAxis']['neighbour']]) }}</p>
                            @endif
                        </div>

                        <span class="zone-card__go">
                            {{ __('landing.zones.see_zone', ['zone' => $card['name']]) }}
                            <x-icons.arrow-right :width="18" :height="18" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- La nota de acceso: dato del parque, escrito en el panel. Sin nota, no hay tarjeta.
             ⚠️ Es la MISMA nota que el pie de `/atracciones` (`site.zones_access`), no una copia. --}}
        @if ($site['zones_access'] ?? null)
            {{-- La «i» es la MISMA marca de información que `/cumpleanos` (`#589`): una sola pieza
                 para decir «ten esto en cuenta», en todas las superficies. --}}
            <aside class="zones__access">
                <span class="party-info__mark" aria-hidden="true">i</span>
                <p class="zones__access-text">{{ $site['zones_access'] }}</p>
            </aside>
        @endif
    </section>

    {{-- ══ 02 · CUÁNTO · el carril de tarifas ════════════════════════════════════════════════════
         El carril entero es un COMPONENTE del producto: la landing no calcula un precio ni decide
         cómo se escribe. La entradilla vende con una cifra del catálogo, y sin entradas vendibles
         cae a la variante sin precio —un «desde» que no existe miente—. --}}
    <section id="pricing" class="section wrap">
        <div class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.rates.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.rates.title') }}</h2>
            <p class="sec-head__lede">{{ $ratesFrom === null
                ? __('landing.rates.intro_plain')
                : __('landing.rates.intro', ['from' => $ratesFrom]) }}</p>
        </div>

        <x-site.rate-rail :zones="$rateCards" :special-label="$ratesSpecialLabel" />
    </section>

    {{-- ══ 03 · QUÉ HAY DENTRO · el mosaico y la puerta ══════════════════════════════════════════
         ⚠️ El recuento sale de lo que la PÁGINA DE AL LADO enseña: la entradilla promete «N
         atracciones dentro» y la puerta dice «ver las N» — si divergen, el cliente cuenta y no le
         salen. Es la misma cuenta que hace `AttractionsController`. --}}
    <section id="rides-section" class="section wrap">
        <div id="rides" class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.rides.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.rides.title') }}</h2>
            <p class="sec-head__lede">{{ __('landing.rides.intro', ['count' => $ridesTotal]) }}</p>
        </div>

        @if ($rideMosaic !== [])
            <ul class="mosaic" role="list">
                @foreach ($rideMosaic as $celda)
                    @if ($celda['papel'] === 'velada')
                        {{-- ⚠️ **Una velada no es contenido: es TEXTURA.** Pierde el nombre, deja de
                             ser enlace y sale del árbol de accesibilidad — lo que se oculta no puede
                             ser un destino. --}}
                        <li class="mosaic__cell" data-papel="velada" aria-hidden="true">
                            @if ($foto = $celda['ride']->imageUrl())
                                <img class="mosaic__img" src="{{ $foto }}" alt=""
                                     aria-hidden="true" loading="lazy" decoding="async">
                            @endif
                            <span class="mosaic__veil"></span>
                        </li>
                    @else
                        <li class="mosaic__cell" data-papel="{{ $celda['papel'] }}">
                            {{-- La foto lleva a `/atracciones` con su zona ya elegida: la página la
                                 lee de `?zona=`, que es lo único que funciona sin JavaScript. --}}
                            <a class="mosaic__link" href="{{ route('atracciones', ['zona' => $celda['zone']->slug]) }}">
                                @if ($foto = $celda['ride']->imageUrl())
                                    <img class="mosaic__img" src="{{ $foto }}"
                                         alt="" aria-hidden="true" loading="lazy" decoding="async">
                                @endif
                                <span class="mosaic__band">
                                    <span class="mosaic__name">{{ $celda['ride']->tr('name') }}</span>
                                    <span class="mosaic__zone">{{ $celda['zone']->tr('name') }}</span>
                                </span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        {{-- La puerta. ⚠️ Sin atracciones no se pinta: una puerta a una página vacía no es puerta. --}}
        @if ($ridesTotal > 0)
            <p class="rides__door">
                <a class="rides__door-link" href="{{ route('atracciones') }}">
                    {{ __('landing.rides.door', ['count' => $ridesTotal]) }}
                    <x-icons.arrow-right class="arrow" :width="16" :height="16" />
                </a>
            </p>
        @endif

        {{-- La puerta del bar. ⚠️⚠️ Sale del INVENTARIO y no de `BarPage::isPublished()` a pelo: con
             `/bar` en mantenimiento el predicado seguía diciendo «publicado» y la portada ofrecía
             una puerta a un 503 mientras el menú y el pie ya la habían retirado. Tres superficies
             con el mismo destino no pueden tener tres criterios. --}}
        @if (filled($barName))
            <div class="rides__aside">
                <h3 class="rides__aside-title">{{ __('landing.rides.aside_title') }}</h3>
                <a class="bar-door" href="{{ route('bar') }}">
                    <span class="bar-door__body">
                        <span class="bar-door__name">{{ $barName }}</span>
                        @if (filled($barLede))
                            <span class="bar-door__lede">{{ $barLede }}</span>
                        @endif
                    </span>
                    <x-icons.arrow-right class="bar-door__arrow" :width="20" :height="20" />
                </a>
            </div>
        @endif
    </section>

    {{-- ══ 04 · CUMPLEAÑOS · los packs ═══════════════════════════════════════════════════════════
         Las tarjetas se componen en el dominio (`PartyCards`) y no aquí: cruzar el pack con su
         tarifa especial, elegir qué edad se publica y escribir los importes es lógica. --}}
    @if ($partyCards !== [])
        <section id="events" class="section wrap">
            <div class="sec-head">
                <p class="sec-head__eyebrow">{{ __('landing.events.eyebrow') }}</p>
                <h2 class="sec-head__title">{{ __('landing.events.section_title') }}</h2>
                <p class="sec-head__lede">{{ $partyFrom === null
                    ? __('landing.events.section_intro_plain')
                    : __('landing.events.section_intro', ['from' => $partyFrom]) }}</p>
            </div>

            <ul class="party__packs" role="list">
                @foreach ($partyCards as $card)
                    <li class="party__pack-item">
                        {{-- ⚠️⚠️ La superficie va en la TARJETA y no en el `<li>`: `[data-surface]`
                             no solo declara, **PINTA** (`background: var(--bg)`), y en el envoltorio
                             deja un recuadro de tinta con radio 0 asomando por las esquinas (`#484`). --}}
                        <a class="party-card" @if ($loop->even) data-surface="ink" @endif
                           href="{{ route('cumpleanos') }}">
                            <span class="party-card__tags">
                                <span class="party-card__chip">{{ $card['name'] }}</span>
                                @if ($card['badge'])
                                    <span class="party-card__badge">{{ $card['badge'] }}</span>
                                @endif
                            </span>
                            @if ($card['age'])
                                <span class="party-card__age">{{ $card['age'] }}</span>
                            @endif

                            {{-- ⚠️ El tope de TRES lo declara la VISTA: el panel decide QUÉ incluye
                                 el pack y el diseño CUÁNTAS caben (`#293`). La duración cuenta aparte
                                 porque sale del catálogo, no de lo que escribe el panel. --}}
                            @if ($card['durationLabel'] || $card['features'] !== [])
                                <span class="party-card__list">
                                    @if ($card['durationLabel'])
                                        <span class="party-card__feat">
                                            <span class="party-card__dot" aria-hidden="true"></span>
                                            <span>{{ __('landing.events.duration_feature', ['duration' => $card['durationLabel']]) }}</span>
                                        </span>
                                    @endif
                                    @foreach (array_slice($card['features'], 0, 3) as $feature)
                                        <span class="party-card__feat">
                                            <span class="party-card__dot" aria-hidden="true"></span>
                                            <span>{{ $feature }}</span>
                                        </span>
                                    @endforeach
                                </span>
                            @endif

                            <x-site.gifts :gifts="$card['gifts']" tag="span" class="party-card__gifts" />

                            <span class="party-card__money">
                                <span class="party-card__terms">
                                    {{-- ⚠️ La especial va ENTERA, nunca como recargo (regla dura del
                                         canvas): no es plana entre packs, así que un recargo obligaría
                                         al cliente a recordar cuál le toca. --}}
                                    @if ($card['special'])
                                        <span class="party-card__special">{{ $card['special'] }} {{ __('landing.events.special_suffix') }}</span>
                                    @endif
                                    <span class="party-card__terms-line">{{ __('landing.events.reserve_terms', [
                                        'min' => $card['min'], 'max' => $card['max'], 'deposit' => $card['deposit'],
                                    ]) }}</span>
                                </span>
                                <span class="party-card__seal">
                                    {{-- ⚠️ El «€» viene CON la cifra desde `PartyCards` (`#661`): aquí
                                         se pegaba a mano y era una regla de escritura de dinero dentro
                                         de una vista. --}}
                                    <span class="party-card__price">{{ $card['price'] }}</span>
                                    <span class="party-card__unit">{{ __('landing.events.per_child') }}</span>
                                </span>
                            </span>

                            <span class="party-card__go">
                                <span>{{ __('landing.events.see_pack') }}</span>
                                <x-icons.arrow-right class="arrow" :width="16" :height="16" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- Los días de la especial, UNA vez y por el componente del producto. --}}
            @if ($ratesSpecialLabel && collect($partyCards)->contains(fn ($c) => $c['special'] !== null))
                <x-site.special-rate-note />
            @endif
        </section>
    @endif

    {{-- ══ 05 · ANTES DE VENIR · el registro ES el QR ═════════════════════════════════════════════
         El ancla la anuncian el menú y el pie. El código de ejemplo lo dibuja un componente del
         producto (`<x-site.sample-qr>`), no un `<img>` de nadie. --}}
    <section id="before" class="section wrap">
        <div class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.before.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.before.title') }}</h2>
            <p class="sec-head__lede">{{ __('landing.before.lede') }}</p>
        </div>

        <div class="before__code" data-surface="ink">
            <div class="before__object">
                <div class="before__id">
                    <div class="before__screen">
                        <x-site.sample-qr :label="__('landing.before.qr_aria')" />
                    </div>
                    {{-- ⚠️ «Mi QR» es el nombre del PRODUCTO (`account.card.title`): el mismo objeto
                         no puede llamarse de dos maneras. --}}
                    <p class="before__name">
                        {{ __('landing.before.qr_name') }}
                        <span class="before__sample">{{ __('landing.before.qr_sample') }}</span>
                    </p>
                </div>
                <p class="before__where">{{ __('landing.before.qr_where') }}</p>
            </div>

            {{-- ⚠️ El sujeto es el VISITANTE, no el empleado: las tres filas dicen lo que el código
                 lleva SIEMPRE —tus reservas, tu firma, tus hijos—, no una reserva concreta. --}}
            <div class="before__what">
                <h3 class="before__what-title">{{ __('landing.before.carries_title') }}</h3>
                <p class="before__what-lede">{{ __('landing.before.carries_lede') }}</p>
                <dl class="before__rows">
                    @foreach (['booking', 'waiver', 'minors'] as $fila)
                        <div class="before__row">
                            <dt class="before__row-key">{{ __('landing.before.rows.'.$fila.'.key') }}</dt>
                            <dd class="before__row-val">{{ __('landing.before.rows.'.$fila.'.val') }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="before__all">
                    <span class="before__tick" aria-hidden="true"><x-icons.check :width="16" :height="16" /></span>
                    <span>{{ __('landing.before.always') }}</span>
                </p>
            </div>
        </div>

        {{-- La excepción: lo único que no cabe en el código.
             ⚠️ La frase NO lleva el precio: el producto **no sabe** cuál de sus complementos son
             «los calcetines», e identificarlo por su icono sería usar un campo de PRESENTACIÓN como
             identidad — el defecto de `accent` que `#295` y `#301` pagaron dos veces. --}}
        <p class="before__socks">
            <span class="before__i" aria-hidden="true"><x-icons.info :width="24" :height="24" /></span>
            <span><strong>{{ __('landing.before.socks_lead') }}</strong> {{ __('landing.before.socks_text') }}</span>
        </p>

        {{-- ⚠️⚠️ La línea del niño invitado es DATO, no copia fija: ofrece el justificante que
             `#400`/`#401` construyeron, y quién lo ofrece lo decide el catálogo
             (`ticket_types.guardian_authorization`). Escribirla siempre prometería un enlace que el
             producto no emite. --}}
        @if ($guestWaiverOffered)
            <p class="before__guest">{{ __('landing.before.guest_text') }}</p>
        @endif

        {{-- Las dos salidas, cero relleno de acción: aquí no se compra.
             ▶ Con sesión NO se ofrece crear cuenta sino VER EL QR, que es de lo que habla la
             sección; el `href` se conserva como suelo sin JavaScript. --}}
        <div class="before__foot">
            <a class="before__rules" href="{{ route('normas') }}">
                <span>{{ __('landing.before.all_rules') }}</span>
                <x-icons.arrow-right class="arrow" :width="16" :height="16" />
            </a>

            @if (! empty($site['registration_url']))
                <a class="btn btn--ghost before__cta" href="{{ $site['registration_url'] }}"
                   target="_blank" rel="noopener">{{ __('landing.before.cta') }}</a>
            @elseif (auth()->check())
                <a class="btn btn--ghost before__cta" href="{{ route('account') }}"
                   x-on:click.prevent="$store.purchase.openAccount($event, 'card')">{{ __('landing.before.cta_account') }}</a>
            @else
                <a class="btn btn--ghost before__cta" href="{{ route('registro') }}"
                   x-on:click.prevent="$store.purchase.openAccount($event, 'register')">{{ __('landing.before.cta') }}</a>
            @endif
        </div>
    </section>

    {{-- ══ 06 · RESEÑAS ══════════════════════════════════════════════════════════════════════════
         ❗❗❗ **La vista NO sabe de dónde vienen las opiniones**: lee un solo contrato
         (`Content\Contracts\SocialProof`) y pinta lo que le den. El día que entre Google no se toca
         este marcado — se cambia el binding por el decorador.
         ❗ **Con cero opiniones la sección entera no se pinta**, ni rótulo ni caja: es la regla dura
         del sistema para toda sección cuyo contenido pone el panel.
         ⚠️ La excepción son las reseñas que solo esperan el PERMISO del visitante (`#592`): ahí
         queda la nota y, en lugar de las tarjetas, un aviso que abre el panel de cookies. --}}
    @php($resenasPorPermiso = $socialProof->isEmpty() && $socialLocked)
    @if ($socialProof->isNotEmpty() || $resenasPorPermiso)
        @php($opinionesDeGoogle = $socialProof->contains(fn ($o) => $o->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE))
        @php($cifraDeGoogle = $socialRating?->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE)
        {{-- ⚠️ `data-nosnippet` (§4.3·10, `#732`): las reseñas son de terceros y **no pueden acabar
             en el fragmento que Google enseña bajo el resultado del parque**. Atribuírselas al sitio
             en un buscador es justo lo que su política llama tergiversar. --}}
        <section id="reviews" class="section wrap" data-nosnippet>
            <div class="rev-sec{{ $socialRating ? ' rev-sec--scored' : '' }}">
                <div class="sec-head">
                    <p class="sec-head__eyebrow">{{ __('landing.reviews.eyebrow') }}</p>
                    <h2 class="sec-head__title">{{ __('landing.reviews.title') }}</h2>
                    {{-- ⚠️⚠️ La entradilla sigue a la fuente de las OPINIONES, no a la de la chapa —y
                         las dos pueden no coincidir—: atada a la chapa, la sección decía «no las
                         elegimos nosotros» sobre una opinión que sí elegimos. --}}
                    {{-- ⚠️⚠️ Y con la FICHA la de Google deja de ser verdad (`#734`): «no las elegimos
                         nosotros» encima de la línea que dice que filtramos por estrellas. La señal es
                         la selección, que solo existe cuando la fuente que responde filtra. --}}
                    <p class="sec-head__lede">{{ $socialSelection
                        ? __('landing.reviews.lede_profile')
                        : (($socialProof->first()?->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE || $resenasPorPermiso)
                            ? __('landing.reviews.lede_google')
                            : __('landing.reviews.lede_own')) }}</p>

                    {{-- ❗❗❗ **LA LÍNEA DEL FILTRO, Y NO ES DISEÑO: ES LA ÓMNIBUS** (T2·6, §4.3·10,
                         `#732`). Enseñar solo las reseñas positivas **sin decirlo** es una práctica
                         engañosa según la directiva 2019/2161, y la sección enseña las de cuatro
                         estrellas o más. Va **siempre que haya filtro**, no detrás de un desplegable
                         y no en letra de aviso legal: donde se ven las tarjetas.
                         ⚠️ `$socialSelection` es `null` cuando la fuente NO filtra —las opiniones
                         propias, o Places— y entonces esto no se pinta: avisar de un filtro que no se
                         está aplicando es peor que callar.
                         ⚠️ Y dice las otras dos cosas que §4.3·10 exige en la misma frase: que nadie
                         verifica que los autores sean clientes, y dónde están todas. --}}
                    @if ($socialSelection)
                        {{-- ⚠️ Los dos enlaces van APARTE y con aspecto de enlace (`#734`): pegados al
                             final de la frase se leían como parte de ella —«…haya venido. Ver todas en
                             Google Escribir una reseña»— y nadie los pulsaba. --}}
                        <p class="rev-sec__disclosure">
                            {{ __('landing.reviews.filtered', ['stars' => $socialSelection->minStars]) }}
                            @if ($socialSelection->allReviewsUrl || $socialSelection->writeReviewUrl)
                                <span class="rev-sec__links">
                                    @if ($socialSelection->allReviewsUrl)
                                        <a href="{{ $socialSelection->allReviewsUrl }}" rel="noopener nofollow" target="_blank">{{ __('landing.reviews.see_all') }}</a>
                                    @endif
                                    @if ($socialSelection->writeReviewUrl)
                                        <a href="{{ $socialSelection->writeReviewUrl }}" rel="noopener nofollow" target="_blank">{{ __('landing.reviews.write') }}</a>
                                    @endif
                                </span>
                            @endif
                        </p>
                    @endif
                </div>

                @if ($socialRating)
                    {{-- La chapa de la cifra. ❗ Solo existe si viene de Google: componerla con
                         opiniones propias daría un número real —la media de lo que el parque escribió
                         de sí mismo— y publicarlo aquí lo haría pasar por la nota de Google. --}}
                    <div class="rev-score" data-surface="ink">
                        <p class="rev-score__num">
                            {{-- ⚠️ El separador lo decide el IDIOMA y no esta vista (`LocalNumber`,
                                 `#651`): escrito a mano, la web inglesa enseñaba «4,8». --}}
                            <span class="rev-score__val">{{ \App\Domain\Platform\Services\LocalNumber::decimal($socialRating->value) }}</span>
                            <span class="rev-score__of">{{ __('landing.reviews.out_of') }}</span>
                        </p>
                        {{-- ⚠️ La ESCALA la dice el producto (`Rating::MAX`, `#662`), no un 5 tecleado. --}}
                        <p class="rev-score__stars" role="img"
                           aria-label="{{ __('landing.reviews.score_aria', ['value' => \App\Domain\Platform\Services\LocalNumber::decimal($socialRating->value)]) }}">
                            @for ($e = 1; $e <= \App\Domain\Content\Contracts\Rating::MAX; $e++)
                                @php($lleno = max(0, min(1, $socialRating->value - $e + 1)))
                                <span class="rev-score__star" aria-hidden="true">
                                    <span class="rev-score__star-off">&#9733;</span>
                                    <span class="rev-score__star-on" style="width: {{ round($lleno * 100, 2) }}%"><span>&#9733;</span></span>
                                </span>
                            @endfor
                        </p>
                        {{-- ⚠️ Con la FICHA la marca es la PALABRA (`#734`, §4.3·10), y va en la propia
                             línea del recuento: «37 opiniones en Google». El logotipo de Maps es de
                             Places, y ponerlo aquí atribuiría a Maps un dato que no es suyo. --}}
                        @php($cifraPorPalabra = $socialRating->attribution === \App\Domain\Content\Contracts\Testimonial::ATTRIBUTION_GOOGLE_WORD)
                        <p class="rev-score__count">{{ trans_choice($cifraPorPalabra ? 'landing.reviews.count_on_google' : 'landing.reviews.count', $socialRating->count, ['n' => \App\Domain\Platform\Services\LocalNumber::count($socialRating->count)]) }}</p>
                        {{-- «A fecha de …» (§4.3·10): la trajo una pasada diaria. Solo si la fuente la
                             DA —Places no—: inventarla sería afirmar algo que no sabemos. --}}
                        @if ($socialRating->asOf)
                            <p class="rev-score__asof">{{ __('landing.reviews.as_of', ['date' => \App\Domain\Platform\Services\DisplayTime::longDate($socialRating->asOf)]) }}</p>
                        @endif
                        {{-- ❗❗❗ **LA ATRIBUCIÓN ES OBLIGATORIA** (`#494`): la cifra es dato de Places
                             y esta vista no enseña ningún mapa de Google, que es exactamente el
                             supuesto de «you must include the Google logo». Va DENTRO de la chapa,
                             que es lo que acredita. ⚠️ Variante BLANCA porque la caja es de tinta:
                             el logotipo no sigue al tema, lo elige el marcado. --}}
                        @if ($cifraPorPalabra)
                            {{-- La palabra ya está en el recuento, y «ver todas» en la línea del filtro. --}}
                        @elseif ($socialRating->url)
                            <a class="rev-score__src" href="{{ $socialRating->url }}"
                               target="_blank" rel="noopener noreferrer nofollow">
                                <x-site.google-attribution surface="ink" />
                            </a>
                        @else
                            <p class="rev-score__src"><x-site.google-attribution surface="ink" /></p>
                        @endif
                    </div>
                @endif

                @if ($resenasPorPermiso)
                    {{-- ⚠️ El botón ABRE EL PANEL y no concede nada por su cuenta: el permiso se da
                         en el mismo sitio que los demás. Al concederlo la página se RECARGA, porque
                         las tarjetas las pinta el servidor. --}}
                    <div class="rev" x-data
                         x-on:cookies-updated.window="$event.detail && $event.detail.maps && window.location.reload()">
                        <div class="rev__lock">
                            <p class="rev__lock-text">{{ __('landing.reviews.locked_text') }}</p>
                            <button type="button" class="btn btn--ghost" x-cloak
                                    @click="$store.cookies.enabled ? $store.cookies.openPanel() : $store.cookies.grant('maps')">{{ __('landing.reviews.locked_btn') }}</button>
                            <noscript><p class="rev__lock-text">{{ __('cookies.frame.noscript') }}</p></noscript>
                        </div>
                    </div>
                @else
                    {{-- ⚠️ El carril sabe cuántas opiniones hay porque ese número lo sabe el
                         servidor: es lo que deshabilita el recorrido al llegar al final. --}}
                    <div class="rev" x-data="{
                             i: 0, n: {{ $socialProof->count() }},
                             ir(k) {
                                 this.i = Math.max(0, Math.min(this.n - 1, k));
                                 const t = this.$refs.track;
                                 if (t && t.children[this.i]) t.scrollTo({ left: t.children[this.i].offsetLeft });
                             },
                         }"
                         @keydown.left.prevent="ir(i - 1)"
                         @keydown.right.prevent="ir(i + 1)">
                        <div class="rev__track" x-ref="track">
                        @foreach ($socialProof as $op)
                            <article class="rev__card">
                                {{-- ⚠️ La ANÓNIMA se pinta como «Usuario de Google» (§4.3·5, `#734`): el
                                     contrato la DECLARA y `author` llega vacío, así que sin esto salía
                                     sin nombre y con un «·» de inicial. --}}
                                @php($autor = $op->anonymous ? __('landing.reviews.anonymous') : $op->author)
                                @php($porPalabra = $op->attribution === \App\Domain\Content\Contracts\Testimonial::ATTRIBUTION_GOOGLE_WORD)
                                <div class="rev__who">
                                    {{-- ⚠️⚠️ La foto es OBLIGATORIA cuando la reseña es de Google (R3:
                                         «you must always credit the author»), y por eso llega solo con
                                         consentimiento. Sin `avatarUrl` va la inicial.
                                         ⚠️ `referrerpolicy` para no filtrarle a Google la URL desde la
                                         que se pide la foto. --}}
                                    @if ($op->avatarUrl)
                                        <img class="rev__ini rev__ini--photo" src="{{ $op->avatarUrl }}" alt=""
                                             width="56" height="56" loading="lazy" decoding="async"
                                             referrerpolicy="no-referrer" aria-hidden="true">
                                    @else
                                        <span class="rev__ini" aria-hidden="true">{{ $op->anonymous ? mb_strtoupper(mb_substr($autor, 0, 1)) : $op->initial() }}</span>
                                    @endif
                                    <div class="rev__id">
                                        <p class="rev__author">
                                            @if ($op->authorUrl)
                                                <a href="{{ $op->authorUrl }}" target="_blank"
                                                   aria-label="{{ __('landing.reviews.author_on_google', ['name' => $op->author]) }}"
                                                   rel="noopener noreferrer nofollow">{{ $op->author }}</a>
                                            @else
                                                {{ $autor }}
                                            @endif
                                        </p>
                                        @if ($op->when)
                                            <p class="rev__when">{{ $op->when }}</p>
                                        @endif
                                    </div>
                                    {{-- ❗❗❗ La marca cuelga de la FUENTE del dato, nunca de «hay
                                         avatar» ni de «hay enlace»: es lo que impide que una opinión
                                         del parque salga vestida de Google. --}}
                                    @if ($op->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE || $op->rating)
                                        <div class="rev__mark">
                                            {{-- Con la ficha la marca es la PALABRA y va al pie, lejos
                                                 de las estrellas (§4.3·10: «sin estrellas pegadas»). --}}
                                            @if ($op->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE && ! $porPalabra)
                                                <x-site.google-attribution surface="paper" />
                                            @endif
                                            @if ($op->rating)
                                                {{-- ⚠️ La nota va como IMAGEN con nombre accesible:
                                                     cinco glifos sueltos se leen «estrella estrella
                                                     estrella…», que no dice la nota. --}}
                                                <p class="rev__stars" role="img"
                                                   aria-label="{{ trans_choice('landing.reviews.stars', $op->rating, ['n' => $op->rating]) }}">
                                                    <span class="rev__stars-on" aria-hidden="true">{{ str_repeat('★', $op->rating) }}</span><span
                                                          class="rev__stars-off" aria-hidden="true">{{ str_repeat('★', \App\Domain\Content\Contracts\Rating::MAX - $op->rating) }}</span>
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div x-data="{ original: false }">
                                    {{-- `dir="auto"`: la escribió un desconocido y puede venir en cualquier
                                         idioma (§4.3·8); los saltos de línea los guarda `pre-line`. --}}
                                    <p class="rev__text" x-show="!original" dir="auto"
                                       @if ($op->language) lang="{{ $op->language }}" @endif>{{ $op->text }}</p>
                                    @if ($op->isTranslated())
                                        {{-- ❗❗❗ **EL ORIGINAL VIAJA EN EL MARCADO, y eso NO es
                                             diseño: es la política de Google.** La licencia permite
                                             dar acceso al texto sin traducir y ésta es la forma
                                             barata —viaja servido y Alpine solo decide cuál se ve—.
                                             ⚠️ `x-cloak` es lo que evita que sin JavaScript se vean
                                             los dos textos seguidos (`x-show` sin Alpine no oculta).
                                             ⚠️ `lang` con el idioma REAL: es lo que hace que un
                                             lector de pantalla cambie de voz. --}}
                                        <p class="rev__text" x-ref="orig" x-cloak
                                           x-show="original" lang="{{ $op->originalText->language }}">{{ $op->originalText->text }}</p>
                                        {{-- ❗❗ El aviso es OBLIGATORIO —«Make end users aware when a
                                             review has been translated from its original language»—
                                             y va como TEXTO servido, nunca dentro del botón: sin
                                             JavaScript el botón no se pinta y el aviso tiene que
                                             salir igual. --}}
                                        <p class="rev__xlat">
                                            <span class="rev__xlat-note">{{ $op->originalText->languageName()
                                                ? __('landing.reviews.translated_from', ['lang' => $op->originalText->languageName()])
                                                : __('landing.reviews.translated') }}</span>
                                            <button type="button" class="rev__xlat-swap" x-cloak
                                                    @click="original = !original"
                                                    x-text="original ? @js(__('landing.reviews.see_translation')) : @js(__('landing.reviews.see_original'))"></button>
                                        </p>
                                    @endif
                                </div>
                                {{-- La respuesta del parque, DEBAJO de la reseña (§4.3·10, `#734`). --}}
                                @if ($op->reply)
                                    <div class="rev__reply">
                                        <p class="rev__reply-label">{{ __('landing.reviews.reply') }}</p>
                                        <p class="rev__reply-text" dir="auto">{{ $op->reply }}</p>
                                    </div>
                                @endif
                                {{-- Las fotos de la reseña (§4.3·6): rutas NUESTRAS, las pone la fuente.
                                     Cuatro como mucho, y el resto se dice: la tarjeta es un carril. --}}
                                @if ($op->photos !== [])
                                    @php($fotos = count($op->photos))
                                    <ul class="rev__photos">
                                        @foreach (array_slice($op->photos, 0, 4) as $k => $foto)
                                            <li><img class="rev__photo" src="{{ $foto }}"
                                                     alt="{{ __('landing.reviews.photo_alt', ['n' => $k + 1, 'total' => $fotos, 'name' => $autor]) }}"
                                                     width="72" height="72" loading="lazy" decoding="async"></li>
                                        @endforeach
                                        @if ($fotos > 4)
                                            <li class="rev__photos-more">+{{ $fotos - 4 }}</li>
                                        @endif
                                    </ul>
                                @endif
                                @if ($porPalabra)
                                    <p class="rev__via">{{ __('landing.reviews.via_google') }}</p>
                                @endif
                                @if ($op->url)
                                    {{-- ⚠️ La salida a Google NO es opcional cuando la reseña es suya:
                                         R3 exige acreditar al autor y su enlace es parte de eso. --}}
                                    <p class="rev__acts">
                                        <a class="rev__more" href="{{ $op->url }}"
                                           target="_blank" rel="noopener noreferrer nofollow">{{ __('landing.reviews.see_on_google') }}</a>
                                    </p>
                                @endif
                            </article>
                        @endforeach
                        </div>

                        {{-- ❗❗ **LOS PUNTOS SON EL RECORRIDO, no un adorno.** Las flechas se
                             retiraron (`[DECIDIDO owner]`, `#494`) y eso solo fue gratis porque cada
                             punto es un `<button>` CON SU NOMBRE: sin ellos el carril solo se
                             recorrería con el ratón. *Retirar un control solo sale gratis si lo que
                             hacía lo sigue haciendo otro.*
                             ⚠️ Con una sola opinión no se pintan: un punto solo son 48 px para no
                             decir nada. --}}
                        @if ($socialProof->count() > 1)
                            <div class="rev__nav">
                                <div class="rev__dots">
                                    @foreach ($socialProof as $k => $op)
                                        <button type="button" class="rev__dot"
                                                aria-label="{{ __('landing.reviews.go', ['n' => $k + 1]) }}"
                                                x-bind:aria-current="i === {{ $k }} ? 'true' : 'false'"
                                                @click="ir({{ $k }})"><span aria-hidden="true"></span></button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- ❗❗ **LA POLÍTICA DE RESEÑAS DE GOOGLE** (`#494`): su documentación la exige al
                     mostrar reseñas **o** la media, y la frase es la suya. Sale si hay ALGO de Google
                     en la sección, y la condición son las DOS fuentes por separado: con solo chapa
                     sigue haciendo falta, porque la política nombra la valoración media.
                     ⚠️ Nombra a Google dentro de la frase a propósito: una redacción impersonal
                     alcanzaría también a las opiniones propias y diría algo falso de ellas. --}}
                @if ($cifraDeGoogle || $opinionesDeGoogle)
                    <p class="rev-sec__policy">{{ __('landing.reviews.google_policy') }}</p>
                @endif
            </div>
        </section>
    @endif

    {{-- ══ 07 · VISÍTANOS ════════════════════════════════════════════════════════════════════════
         La tabla, las fechas próximas y el mapa los resuelve `<x-site.visit>`, que es del producto.
         ⚠️⚠️ La entradilla se DERIVA del horario y no es un texto fijo: «Abrimos todos los días» es
         cierto en esta instalación y **falso en cualquiera que cierre un día**, y por eso llega como
         DATO del controlador (`ScheduleDisplay::weeklyLede()`, `#662`). --}}
    <section id="info" class="section wrap">
        <div class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.info.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.info.title') }}</h2>
            @if ($scheduleLede)
                <p class="sec-head__lede">{{ $scheduleLede }}</p>
            @endif
        </div>
        <x-site.visit />
    </section>

    {{-- ══ 08 · DUDAS · el acordeón ══════════════════════════════════════════════════════════════
         ❗ **Con el panel vacío la sección entera no se pinta**, ni rótulo ni caja de 0 filas.
         ⚠️ El `<x-site.faq-json-ld>` va DENTRO a propósito: sin preguntas tampoco hay `FAQPage` que
         declarar. --}}
    @if ($faqs->isNotEmpty())
        <section id="faq" class="section wrap">
            <div class="faq-sec">
                <div class="sec-head">
                    <p class="sec-head__eyebrow">{{ __('landing.faq.eyebrow') }}</p>
                    <h2 class="sec-head__title">{{ __('landing.faq.title') }}</h2>
                    <p class="sec-head__lede">{{ __('landing.faq.lede') }}</p>
                </div>
                <div class="faq">
                    @foreach ($faqs as $i => $faq)
                        {{-- ⚠️ TODAS cerradas al cargar, y no es una divergencia con el sistema: la
                             tabla de reglas de `Componentes` 06 lo dice —la primera abierta en una FAQ
                             de PÁGINA, todas cerradas en la PORTADA—. --}}
                        <div class="faq__item" :class="faqOpen==={{ $i }} && 'open'">
                            <button type="button" class="faq__q"
                                    @click="faqOpen = faqOpen==={{ $i }} ? -1 : {{ $i }}"
                                    :aria-expanded="faqOpen==={{ $i }} ? 'true' : 'false'"
                                    aria-controls="faq-answer-{{ $i }}">
                                <span class="faq__p">{{ $faq->tr('question') }}</span>
                                {{-- ⚠️⚠️ El signo son DOS iconos del set, no uno girado: girar el «+»
                                     45° da una «×», que significa cerrar y no plegar. El de fuera
                                     decide cuál se ve, sin JavaScript. --}}
                                <span class="faq__sign" aria-hidden="true">
                                    <x-icons.plus class="faq__sign-i faq__sign-i--mas" :width="16" :height="16" />
                                    <x-icons.minus class="faq__sign-i faq__sign-i--menos" :width="16" :height="16" />
                                </span>
                            </button>
                            {{-- Dos envoltorios a propósito: el acordeón anima `grid-template-rows`
                                 0fr → 1fr y no `max-height`, que animaba layout y era un TOPE de 240
                                 px sobre respuestas que escribe el panel. --}}
                            <div class="faq__a" id="faq-answer-{{ $i }}"><div class="faq__a-in"><p class="faq__a-p">{{ $faq->tr('answer') }}</p></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <x-site.faq-json-ld :faqs="$faqs" />
        </section>
    @endif

    {{-- ══ EL CIERRE ═════════════════════════════════════════════════════════════════════════════
         La tarjeta que crece hasta llenar la pantalla al llegar al final: su coreografía la publica
         `cierreChoreo` como UN número (`--cierre-p`) y todo lo demás lo decide el CSS, así que una
         instalación puede alargar el recorrido o apagarlo desde su paquete.
         ⚠️ El juego (`saltaJuego`, `resources/js/app.js`) es MECANISMO del producto y por eso el
         anfitrión lo monta: sin lienzo, el comportamiento se queda sin una sola superficie que lo
         ejercite. Su arte —el tag de ciudad, la trama— es de la instalación y no se pinta aquí. --}}
    <section id="reserve" class="reserve" x-data="cierreChoreo">
        {{-- ⚠️⚠️ El toque escucha en la TARJETA entera y no en el lienzo (`#352`): el lienzo es una
             tira pegada al borde inferior, así que antes solo se saltaba ahí. --}}
        <div class="reserve__box" data-surface="ink"
             x-data="saltaJuego"
             @pointerdown="toca($event)"
             @pointerup="sueltaTap($event)"
             :class="fase === 'jugando' && 'reserve__box--jugando'">

            <canvas class="salta__lienzo" x-ref="lienzo" aria-hidden="true"
                    :style="fase === 'off' ? 'pointer-events:none' : 'pointer-events:auto;cursor:pointer'"></canvas>

            <button type="button" class="salta__invita" x-show="fase === 'listo'"
                    @click="juega()"
                    :aria-label="@js(__('landing.game.aria'))">
                <span class="salta__invita-ico" aria-hidden="true">
                    <x-icons.play :width="11" :height="11" />
                </span>
                <span class="salta__invita-t" x-text="tactil ? @js(__('landing.game.play_touch')) : @js(__('landing.game.play'))"></span>
                <span class="salta__rec" x-show="record > 0" x-text="@js(__('landing.game.rec', ['m' => '§'])).replace('§', record)"></span>
            </button>

            {{-- El marcador. `aria-live` para que quien no ve el lienzo sepa cómo va. --}}
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

            {{-- El resultado. Su segundo botón es RESERVAR y no «salir»: es el final de la portada y
                 el sitio donde alguien que acaba de jugar está más dispuesto. --}}
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
                    @if ($site['sales_online'])
                    <button type="button" class="salta__btn salta__btn--ghost"
                            @click="sal(); $store.purchase.open()">{{ __('landing.game.book') }}</button>
                    @elseif ($site['has_phone'])
                    <a class="salta__btn salta__btn--ghost" href="tel:{{ $site['phone_tel'] }}">{{ __('landing.pricing.call') }}</a>
                    @endif
                </span>
            </div>

            <div class="reserve__body">
                <p class="reserve__slogan">{{ __('landing.hero.kicker') }}</p>
                <h2>
                    {{ __('landing.reserve.title') }}<br />
                    <span class="stroke">{{ __('landing.reserve.stroke') }}</span>
                    <span class="fill">{{ __('landing.reserve.fill') }}</span>
                </h2>
                <p>{{ __('landing.reserve.copy') }}</p>
                <div class="reserve__actions">
                    <a href="{{ $site['sales_online'] ? route('entradas') : ($site['has_phone'] ? 'tel:'.$site['phone_tel'] : route('precios')) }}"
                       @if ($site['sales_online']) @click.prevent="$store.purchase.open()" @endif class="reserve__act">{{ __('landing.reserve.cta') }}</a>
                    @if ($site['has_phone'])
                        <a href="tel:{{ $site['phone_tel'] }}" class="reserve__act reserve__act--alt">{{ __('landing.reserve.cta2') }} {{ $site['phone'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
    </main>

    {{-- El pie ofrece, además del inventario, las secciones de la portada: las MISMAS del menú
         (`#522`). Sobre PAPEL porque la portada termina en la tarjeta de TINTA del cierre. --}}
    <x-site.footer :sections="$menuSections" surface="paper" />

    {{-- ⚠️ El recorrido del cierre va DESPUÉS del pie: la tarjeta se ancla arriba y crece mientras
         el pie pasa por detrás. `position: sticky` no puede hacer eso —solo pega dentro de su propio
         padre—, y es altura REAL: con movimiento reducido se queda a 0 y no hay hueco. --}}
    <div class="reserve__runway" aria-hidden="true"></div>
</div>
</x-layout>
