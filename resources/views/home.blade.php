{{-- ⚠️ `noindex` lo decide el CONTROLADOR, no esta plantilla: es cierto en las tres puertas de auth
     —`/registro`, `/login`, `/recuperar-contrasena`— y falso en la home y en `/entradas`, que son la
     misma vista. Sustituye al efecto lateral que tenía el prop `auth-modal` (`AccountDoor`). --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="$noindex ?? false" :has-hero="true">
@php
    // Horario del parque data-driven (#207): misma fuente que las reservas (opening_hours +
    // temporadas + fechas especiales), agrupado para mostrar.
    $schedule = app(\App\Domain\Content\Services\ScheduleDisplay::class);
    // ⚠️ Aquí se calculaban `$totalSqm`, `$totalRides`, `$totalZones` y `$totalLabels`, la tira de
    // cifras de la sección de zonas. `[DECIDIDO owner, 2026-08-31]` (`#302`): **las tarjetas de zona
    // y las cifras, fuera**. Se van con su consumidor, igual que sus claves de idioma y su CSS.
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


    {{-- ===================== PRECIOS ===================== --}}
    <section id="pricing" class="section wrap pricing-sec">
        {{-- ▶ **EL FRISO FAMILIAR** (`slot-tarifas`, `#309`): tres poses del artboard compuestas en
             un símbolo, con los pies en la misma línea. `[DECIDIDO owner]`: «en tarifas pon también
             una silueta de varias personas».
             ⚠️ Va DENTRO de la cabecera y anclada a ella —no a la sección— por lo mismo que la
             mancha de zonas: un `top` porcentual colgado de un contenedor que cambia de alto se
             descoloca solo cuando alguien acorta un texto (`#303`). --}}
        <div class="rides__head pricing__head">
            <div>
                <h2 class="rides__title">{{ __('landing.pricing.title') }}</h2>
            </div>
            <p>{{ __('landing.pricing.intro') }}</p>
            <x-site.ilu clave="slot-tarifas" class="pricing__friso" />
        </div>
        {{-- ⚠️ **Sin la nota de calcetines** (`[DECIDIDO owner]`: «quita la card de calcetines
             antideslizantes de ahí»): el dato se va a la sección de normas, con su propio CTA de
             compra. `/precios` la CONSERVA —es la otra vista que usa este componente y allí no hay
             sección de normas que la recoja—, así que la decisión viaja por prop y no borrando el
             componente. --}}
        <x-site.ticket-prices :tickets="$tickets" :zones="$zones" :socks="false" />

        {{-- El puente a las normas. `[DECIDIDO owner]`: «pon un cta, conoce las reglas para venir, y
             al darle clic baja al cliente a la sección de las reglas». Es un ancla dentro de la
             misma página, no una ruta. --}}
        <p class="pricing__rules">
            <a class="btn btn--ghost" href="#rules" data-tap>{{ __('landing.pricing.rules_cta') }}</a>
        </p>
    </section>

    {{-- ===================== CUMPLEAÑOS (#231) ===================== --}}
    {{-- El componente pinta sus propias secciones con `.wrap` (no envolver en otro). En la
         landing SIN tarjeta de invitación (showInvite=false): solo un enlace sutil a /cumpleanos. --}}
    @if ($packages->isNotEmpty())
        <x-site.events-section :packages="$packages" :show-invite="false" :level="2" />
    @endif
    {{-- ============ ZONAS Y SUS JUEGOS (una sola sección) ============ --}}
    {{-- **`[DECIDIDO owner, 2026-08-31]` (`#302`): las tarjetas de zona y la tira de cifras, fuera.**
         Aquí había DOS secciones —`#zones`, con una tarjeta grande por zona cuyo CTA saltaba a la
         otra, y `#rides`, con una barra de pestañas que hacía exactamente la misma elección—. O sea
         **dos selectores de zona en la misma página**, y el de arriba costaba 1.011 px en escritorio
         y 1.831 en móvil (medido).

         ▶ **Queda UNA sección y UN selector**: el toggle, que ahora dice quién es cada zona (icono
         del kit + nombre + edad) en vez de ser solo una palabra.
         ⚠️ **Y queda UNA cabecera, no dos.** Con las tarjetas y las cifras fuera, la de zonas se
         quedaba presentando el vacío y la de atracciones venía detrás con el mismo molde. Sobrevive
         la de ZONAS porque su párrafo acaba literalmente en «Elige el tuyo», que es lo que hace el
         toggle que va justo debajo; la de atracciones solo explicaba la interfaz («pasa de una zona
         a otra con un clic»), que es el texto que `#297` señala como sobrante.

         ⚠️⚠️ **LAS DOS ANCLAS SIGUEN VIVAS Y NO ES UN DETALLE**: `/#zones` lo enlazan 4 sitios
         (menú ×2, pie ×2) y `/#rides` otros 2. `#zones` es la sección; `#rides` envuelve el selector
         y el carrusel, así que «Atracciones» del menú sigue aterrizando en los juegos.
         ▶ Y `#rides` **tiene que envolver a los dos**: `applyZoneAccent()` tiñe ese contenedor con
         la paleta de la zona activa, así que si el ancla se quedara solo en el carrusel las
         pestañas perderían el color de su zona. --}}
    <section id="zones" class="section wrap">
        <div class="zones__head">
            {{-- `B1·02` del kit, en la ranura `slot-zonas`. **Su propia nota manda dónde va**: «la
                 mancha detrás de la PRIMERA PALABRA, nunca detrás de todo el bloque».
                 ⚠️ Va ABSOLUTA y con `z-index: -1` dentro de un contexto de apilamiento propio (el
                 patrón de `#286`): sin paquete el componente no emite nada y aquí no puede quedar
                 hueco reservado. --}}
            {{-- ⚠️⚠️ **La mancha va DENTRO del bloque del titular, no de la cabecera, y eso es lo
                 que la mantiene donde su nota manda.** Estuvo colgando de `.zones__head` con un
                 `top` en PORCENTAJE, y un porcentaje se resuelve contra el ALTO DEL CONTENEDOR: al
                 acortar los titulares (`#303`) la cabecera pasó de 264 a 153 px, el mismo `-20%`
                 valió la mitad y la mancha bajó **11.016 px² sobre el párrafo**.
                 ▶ *Un ajuste sobrevive a la razón que lo justificaba si nadie lo revisa al cambiar
                 lo que hay alrededor.* Anclada al titular, su sitio ya no depende de cuánto texto
                 tenga la sección. --}}
            <div class="zones__titulo">
                <x-site.ilu clave="slot-zonas" class="zones__mancha" />
                <h2 class="zones__title">{{ __('landing.zones.title') }}</h2>
            </div>
            <p class="zones__intro">{{ __('landing.zones.intro') }}</p>
        </div>

        <div id="rides" class="zones__juegos">
            {{-- EL SELECTOR. Antes era una fila de palabras; ahora cada pestaña dice **quién es** la
                 zona: su dibujo del kit, su nombre y su edad.
                 ⚠️⚠️ **La identidad es `slug`, NUNCA `accent`** (`#295`, conservado en `#301`):
                 `accent` AGRUPA —`kids`, `cap` y `cap2` comparten el suyo en datos reales—, así que
                 con él tres pestañas emitían el mismo valor y abrían tres carruseles a la vez.
                 ⚠️ **El dibujo es del CLIENTE y puede no estar**: `<x-site.ilu>` no emite nada si el
                 kit no trae ese `zone-<slug>`, y la pestaña se queda con nombre y edad. Es el modo
                 de fallo elegido en `hueco-ilustracion.md` §7 — invisible, nunca caja vacía.
                 ⚠️ **La edad también puede faltar** (`cap`/`cap2` no la tienen): sin ella no se
                 pinta la línea, en vez de dejar un hueco o un guion. --}}
            <div class="zone-pick" role="tablist" aria-label="{{ __('landing.rides.eyebrow') }}">
                @foreach ($zones as $zone)
                    <button type="button" class="zone-pick__tab" data-tap
                            id="zone-pick-{{ $zone->slug }}"
                            role="tab" aria-controls="rides-{{ $zone->slug }}"
                            :aria-selected="zone==='{{ $zone->slug }}' ? 'true' : 'false'"
                            :class="zone==='{{ $zone->slug }}' && 'active'"
                            @click="setZone('{{ $zone->slug }}')">
                        <x-site.ilu :clave="'zone-'.$zone->slug" class="zone-pick__ilu" />
                        <span class="zone-pick__name">{{ __('landing.rides.zone_tab') }} {{ $zone->tr('name') }}</span>
                        @if ($zone->tr('age_range'))
                            <span class="zone-pick__age">{{ $zone->tr('age_range') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @foreach ($zones as $zone)
                {{-- ⚠️ El carrusel lleva la PALETA YA COMPUESTA (`data-zone-style`, `DECISIONES #139`).
                     `data-color` traía solo el primario, así que el JS tenía que (a) quemar el
                     secundario a la paleta del primer cliente y (b) **repetir la fórmula de contraste
                     de `ThemeSettings::onBrand()`** en JavaScript. Dos definiciones de la misma regla
                     es como empiezan las divergencias que el tema existe para cerrar.
                     ⚠️⚠️ **IDENTIDAD `slug`, PALETA `accent`**: lo que dice *cuál* es este carrusel
                     sale de `slug`, que es único; lo que dice *de qué color va* sigue saliendo de
                     `accent`, porque agrupar es justo su trabajo. --}}
                <div class="slider" x-ref="slider_{{ $zone->slug }}" data-zone="{{ $zone->slug }}"
                     id="rides-{{ $zone->slug }}" role="tabpanel" aria-labelledby="zone-pick-{{ $zone->slug }}"
                     data-color="{{ \App\Domain\Content\Services\ThemeSettings::colorForAccent($zone->color, $zone->accent) }}"
                     data-zone-style="{{ \App\Domain\Content\Services\ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent) }}"
                     x-show="zone==='{{ $zone->slug }}'" @scroll="updateProgress()" @if (! $loop->first) style="display:none" @endif>
                    @foreach ($zone->attractions as $ride)
                        {{-- **LA TARJETA SE QUEDA EN FOTO + TÍTULO + TAG** (`[DECIDIDO owner]`, `#302`).
                             Se van la edad y la descripción: medido, las 23 atracciones tienen foto y
                             descripción, así que el carril era una fila de párrafos de 58 caracteres
                             de media compitiendo con 23 fotos. La sección es VISUAL y adopta esa forma.
                             ⚠️ **El bloque de compra NO se va**, y no es un descuido: es una venta, no
                             una descripción. Hoy lo cumple 1 de 23 (la Tirolina) y retirarlo cerraría
                             un camino de compra sin que nadie lo hubiera pedido. --}}
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
                            {{-- El nombre lleva el PUNTO DE COLOR de la zona, que es lo que su `E1`
                                 describe para esta familia: «sombra dura, borde de tinta y un punto de
                                 color a la derecha». Es un pseudo-elemento, no marcado. --}}
                            <h3 class="ride-card__name">{{ $ride->tr('name') }}</h3>

                            {{-- ⚠️⚠️ **AQUÍ SE PROBÓ A PINTAR LA EDAD Y EL OWNER LO RECHAZÓ**
                                 (2026-09-01): `attractions.age` está en la BD —**19 de las 23** la
                                 declaran— y esta tarjeta no la enseña. **No es un olvido: es `#302`**,
                                 que la dejó en «foto + título + tag», y lo guarda
                                 `ZonesSectionTest::test_the_ride_card_shows_neither_description_nor_age`.
                                 ▶ Y por eso esta tarjeta **no recibe icono**: sin un dato detrás, un
                                 icono repetido 23 veces es decoración dentro de un bucle — lo que el
                                 owner ya rechazó en `#286` con la mancha por tarjeta de precio.
                                 Si alguna vez se retoma, el sitio es éste y el dato ya está. --}}

                            {{-- **EL PIE DE LA TARJETA: siempre hay CTA** (`[DECIDIDO owner]`, `#303`:
                                 «añade un CTA a las cards para que el usuario sepa que tiene que
                                 clicarlo»).

                                 ⚠️⚠️ **NO existe página de detalle de atracción**, así que el clic
                                 tiene que llevar a algo que exista. `[DECIDIDO owner]`: **todas llevan
                                 a reservar la ZONA de esa atracción** —abre el cajón posicionado en
                                 ella—, que es exactamente lo que ya hacía el botón de la única
                                 comprable. Y es coherente con el modelo: **el parque vende por ZONA,
                                 no por atracción**.
                                 ▶ Por eso las dos ramas llaman a la MISMA acción y solo cambian el
                                 rótulo: la comprable enseña además su precio (`#228`).

                                 ⚠️ **La tarjeta NO es un enlace, y el CTA sí.** Envolverla entera en
                                 un `<button>` metería el precio y el badge dentro del nombre
                                 accesible; un botón dentro de una tarjeta pulsable es la otra mitad
                                 de la misma trampa. La afordancia la da el CTA, que es lo que se
                                 pulsa. --}}
                            <div class="ride-card__buy">
                                @if ($complements->isPurchasable($ride))
                                    <div class="ride-card__price">@if ($ride->ticketType?->priceVaries())<span class="ride-card__from">{{ __('landing.pricing.from') }}</span>@endif{{ $ride->ticketType?->euros() }}<span class="cents">,{{ $ride->ticketType?->cents() }}</span><span class="eur">€</span></div>
                                    <button type="button" class="btn ride-card__cta" aria-label="{{ __('landing.rides.buy') }} · {{ $ride->tr('name') }}" @click="$store.purchase.openWith({ type: 'zone', slug: '{{ $zone->slug }}' })">{{ __('landing.rides.buy') }}</button>
                                @else
                                    <button type="button" class="btn btn--ghost ride-card__cta" aria-label="{{ __('landing.rides.book_zone', ['zone' => $zone->tr('name')]) }}" @click="$store.purchase.openWith({ type: 'zone', slug: '{{ $zone->slug }}' })">{{ __('landing.rides.book_zone', ['zone' => $zone->tr('name')]) }} <x-icons.arrow-right class="arrow" :width="14" :height="14" /></button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endforeach

            {{-- EL PIE DEL CARRUSEL: progreso a la izquierda, flechas a la derecha.
                 ⚠️ Las flechas estaban arriba, en una fila que compartían con las pestañas; ahí
                 competían con el selector por la atención. Van con lo que gobiernan.
                 ⚠️ Se ocultan con puntero grueso: en un teléfono el carril se recorre con el dedo y
                 dos botones que repiten un gesto que ya existe son ruido. --}}
            <div class="slider-foot">
                <div class="slider-progress">
                    <div class="slider-progress__bar" :style="{ transform: 'translateX(' + (progressLeft*100) + '%) scaleX(' + progressWidth + ')' }"></div>
                </div>
                <div class="slider-nav">
                    <button class="slider-arrow" @click="scrollSlider(-1)" aria-label="{{ __('landing.nav.slider_prev') }}"><x-icons.arrow-left :width="16" :height="16" /></button>
                    <button class="slider-arrow" @click="scrollSlider(1)" aria-label="{{ __('landing.nav.slider_next') }}"><x-icons.arrow-right :width="16" :height="16" /></button>
                </div>
            </div>
        </div>
    </section>


    {{-- ===================== VISÍTANOS (horarios y ubicación) ===================== --}}
    {{-- ▶ **TRES TARJETAS** (`[DECIDIDO owner, 2026-09-01]`: «quiero un diseño de cards, todo en
         cards en la medida de lo posible; lo siento más organizado y limpio»),
         `specs/idioma-visual-heredado.md` §3.octies.
         Era el caso EXTREMO del molde editorial heredado que diagnosticó `#297`: cuatro
         encabezados —«Visítanos», «Horarios», «Fechas especiales», «Ubicación»— para cuatro
         líneas de dato. Ahora la sección conserva UNO, su `<h2>`, y el resto son las tarjetas.
         ⚠️ El marcado heredado (`.info__grid` + dos `.info-card` simétricas + `.map-card` con una
         caja blanca encima) se retiró AQUÍ con su CSS. `.map-card`/`.map-pin` siguen vivas porque
         las usa `/contacto`; `.info__grid`, `.info-card` y `.hours` se fueron con su único
         consumidor, que era esta sección. --}}
    <section id="info" class="section wrap">
        <div class="rides__head">
            <div>
                <h2 class="rides__title">{{ __('landing.info.title') }}</h2>
            </div>
        </div>
        <x-site.visit :schedule="$schedule" />
    </section>

    {{-- ===================== NORMAS ===================== --}}
    {{-- ▶ **DOS REQUISITOS PEGAJOSOS Y UN ASOMO DE NORMAS** (`[DECIDIDO owner, 2026-09-01]`,
         `#309`, `specs/idioma-visual-heredado.md` §3.nonies). Sustituye al pliego de doce
         pictogramas del cliente ANTIGUO y al carrusel con TODAS las normas, que era la base común
         que `#300` había restaurado a propósito para rediseñar desde ella.

         ▶ **La columna izquierda es lo que hay que HACER antes de venir** —registrarse y traer
         calcetines—, y se queda quieta mientras la derecha se desplaza. No es un adorno: son las
         dos únicas cosas de esta sección sobre las que el visitante puede actuar, y las dos tienen
         su CTA.
         ▶ **La derecha ASOMA las normas y no las agota**: cuatro y un enlace a `/normas`, que es la
         página que las tiene todas y que ya existe.

         ⚠️ **El ancla `#rules` es NUEVA y tiene consumidor**: el CTA de la sección de tarifas
         («Conoce las reglas para venir»). Una sección sin `id` no se puede enlazar, y este ancla
         nace con quien lo usa.
         ⚠️⚠️ **Los CTA reutilizan el mecanismo del producto, con su suelo sin JavaScript**: el de
         registro es `route('registro')` + `openAccount`, el de calcetines es `route('entradas')` +
         el cajón de compra — los mismos dos que usa `<x-site.cta-pair>`. Inventar aquí un tercer
         camino de compra habría creado una ruta que nadie más mantiene. --}}
    <section id="rules" class="section wrap">
        <div class="rides__head">
            <div>
                <h2 class="rides__title">{{ __('landing.rules.title') }}</h2>
            </div>
        </div>
        <div class="rules-2col">
            {{-- ── COLUMNA IZQUIERDA · lo que hay que hacer, y se queda quieta ─────────────── --}}
            <div class="rules-must">
                <article class="rules-must__card">
                    <x-site.ilu clave="slot-normas-registro" class="rules-must__ilu" />
                    <h3 class="rules-must__title">{{ __('landing.rules.register_title') }}</h3>
                    <p class="rules-must__text">{{ __('landing.rules.register_text') }}</p>
                    {{-- ⚠️⚠️ **LAS MISMAS TRES RAMAS QUE `<x-site.cta-pair>`, y no es celo: la primera
                         versión de esta tarjeta ofrecía el ALTA a todo el mundo y una guarda la cazó
                         con razón** (`HomePageTest::test_authenticated_nav_hides_ghost…` asevera que
                         con sesión no se ofrece darse de alta). Aquí van:
                           · registro EXTERNO configurado → su URL, que es el sistema del parque;
                           · con sesión → la cuenta, que es donde se firma la exención;
                           · sin sesión → el alta, con su suelo sin JS en `route('registro')`.
                         Duplicar la lógica de `cta-pair` es feo, pero inventar aquí una cuarta
                         conducta lo es más: sería un camino al registro que nadie más mantiene. --}}
                    @if (! empty($site['registration_url']))
                        <a class="btn btn--ghost btn--sm" href="{{ $site['registration_url'] }}"
                           target="_blank" rel="noopener" data-tap>{{ __('landing.rules.register_cta') }}</a>
                    @elseif (auth()->check())
                        <a class="btn btn--ghost btn--sm" href="{{ route('account') }}" data-tap
                           x-on:click.prevent="$store.purchase.openAccount($event, 'home')">{{ __('landing.rules.register_cta') }}</a>
                    @else
                        <a class="btn btn--ghost btn--sm" href="{{ route('registro') }}" data-tap
                           x-on:click.prevent="$store.purchase.openAccount($event, 'register')">{{ __('landing.rules.register_cta') }}</a>
                    @endif
                </article>

                <article class="rules-must__card">
                    <x-site.ilu clave="slot-normas-calcetines" class="rules-must__ilu" />
                    <h3 class="rules-must__title">{{ __('landing.rules.socks_title') }}</h3>
                    <p class="rules-must__text">{{ __('landing.rules.socks_text') }}</p>
                    {{-- Los calcetines son un COMPLEMENTO de la entrada, no un producto suelto: el
                         CTA lleva a la compra, que es donde se ofrecen. --}}
                    @if ($site['sales_online'])
                    <a class="btn btn--ghost btn--sm" href="{{ route('entradas') }}" data-tap
                       x-on:click.prevent="$store.purchase.open()">{{ __('landing.rules.socks_cta') }}</a>
                    @endif
                </article>
            </div>

            {{-- ── COLUMNA DERECHA · un asomo de las normas ────────────────────────────────── --}}
            <div class="rules-peek">
                {{-- ⚠️ **El tope lo declara la VISTA, no el panel** (mismo criterio que `#292`): el
                     panel decide QUÉ normas y en qué orden; cuántas caben aquí es diseño. Con más,
                     la columna derecha crecería por encima de la izquierda y la pegajosidad
                     dejaría de notarse, que es justo lo que la sección va a enseñar. --}}
                @foreach ($rules->take(4) as $rule)
                    <article class="rules-peek__item">
                        <h3 class="rules-peek__name">{{ $rule->tr('name') }}</h3>
                        <p class="rules-peek__desc">{{ $rule->tr('description') }}</p>
                    </article>
                @endforeach
                <p class="rules-peek__more">
                    <a class="btn btn--ghost" href="{{ route('normas') }}" data-tap>{{ __('landing.rules.all_cta') }}</a>
                </p>
            </div>
        </div>
    </section>

    {{-- ⚠️⚠️ **AQUÍ ESTABA «EN DIRECTO» Y SE HA RETIRADO** (`[DECIDIDO owner, 2026-09-01]`:
         «la sección "en directo" quítala»), `#309`. Era la galería de polaroids con fotos del
         catálogo, o el feed de Instagram/TikTok cuando la instalación configuraba uno.
         ▶ Con ella se van su marcado, su CSS (`.gallery-marquee`, `.polaroid*`) y su enlace del
         pie. **NO se retira la fontanería del feed social** —el ajuste `social.feed_embed_url`, el
         servicio `SocialEmbed` y la categoría de cookies `social`—: el hueco que deja esta sección
         es el que `specs/google-reviews.md` va a ocupar con las reseñas, y desmontarla ahora para
         rehacerla después es churn. ⚠️ Eso deja el ajuste del panel **sin consumidor**, que es el
         defecto que `#304` documentó; está dicho, con sus dos salidas, en `DEUDA.md`. --}}

    {{-- ===================== FAQ ===================== --}}
    <section class="section wrap">
        <div class="faq">
            <div>
                <h2 class="rides__title" style="font-size:clamp(48px, 6vw, 96px)">{{ __('landing.faq.title') }}</h2>
            </div>
            <div class="faq__list">
                @foreach ($faqs as $i => $faq)
                    <div class="faq__item" :class="faqOpen==={{ $i }} && 'open'">
                        <button type="button" class="faq__q" data-tap
                                @click="faqOpen = faqOpen==={{ $i }} ? -1 : {{ $i }}"
                                :aria-expanded="faqOpen==={{ $i }} ? 'true' : 'false'"
                                aria-controls="faq-answer-{{ $i }}">{{ $faq->tr('question') }}<span class="ico" aria-hidden="true"><x-icons.plus :width="14" :height="14" /></span></button>
                        {{-- Dos envoltorios a propósito (auditoría M8, `#434`): el acordeón anima
                             `grid-template-rows` 0fr → 1fr y no `max-height`, que animaba layout y era
                             un TOPE de 240 px sobre respuestas que escribe el panel. El de fuera
                             (`.faq__a-in`) recorta y NO lleva relleno; el de dentro lleva el aire. --}}
                        <div class="faq__a" id="faq-answer-{{ $i }}"><div class="faq__a-in"><p class="faq__a-p">{{ $faq->tr('answer') }}</p></div></div>
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
        {{-- ⚠️⚠️ **EL TOQUE ESCUCHA EN LA TARJETA ENTERA, no en el lienzo** (`#352`,
             `[owner, 2026-09-02]`: *«darle tap a cualquier parte del hero empieza a jugar y puede
             saltar, no solo en la parte inferior»*). El lienzo es una tira pegada al borde inferior
             (`bottom: 0`, alto `--salta-h`), así que antes solo se saltaba ahí.
             ▶ **No hace falta condicionarlo a «pantalla completa»**: `fase` sale de `off` únicamente
             cuando `cierre:abierto` dispara, o sea cuando la tarjeta ya llena la ventana. Un `x-show`
             extra sería una segunda copia de esa condición.
             ▶ `pointerup` va aquí también porque con el DEDO el arranque se decide al levantar, para
             separar un TOQUE de un arrastre para desplazar (ver `sueltaTap` en `app.js`). --}}
        <div class="reserve__box" data-surface="ink"
             x-data="saltaJuego"
             @pointerdown="toca($event)"
             @pointerup="sueltaTap($event)"
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
            {{-- ⚠️ Sin manejador propio desde `#352`: el de la TARJETA lo cubre por burbujeo, y dos
                 oyentes para el mismo gesto darían un salto doble. Conserva su `pointer-events` y su
                 cursor porque es donde la acción se ve. --}}
            <canvas class="salta__lienzo" x-ref="lienzo" aria-hidden="true"
                    :style="fase === 'off' ? 'pointer-events:none' : 'pointer-events:auto;cursor:pointer'"></canvas>

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
                    @if ($site['sales_online'])
                    <button type="button" class="salta__btn salta__btn--ghost"
                            @click="sal(); $store.purchase.open()">{{ __('landing.game.book') }}</button>
                    @elseif ($site['has_phone'])
                    <a class="salta__btn salta__btn--ghost" href="tel:{{ $site['phone_tel'] }}">{{ __('landing.pricing.call') }}</a>
                    @endif
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
