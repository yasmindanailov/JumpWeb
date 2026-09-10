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


    {{-- ═══════════════ 02 · CUÁNTO ═══════════════════════════════════════════════════════════
         `DECISIONES #479` · carril de diseño Fase 2 · T2c. Artboard `Precios PJP` 6a (móvil) y
         `Escritorio PJP` 2a (escritorio).

         ⚠️⚠️ **TRES PIEZAS SALEN DE AQUÍ Y LAS TRES REVIERTEN ALGO** (`[DECIDIDO owner, 2026-09-09]`,
         preguntadas con su coste delante):
          · **el friso `slot-tarifas`** (`#309`, «en tarifas pon también una silueta de varias
            personas») — el artboard no lleva ninguna pieza de dibujo en esta sección, así que la
            portada recupera una de sus tres colocaciones;
          · **el CTA «Conoce las reglas para venir»** (`#309`) — en el canvas esta sección no ofrece
            más salida que comprar, y lo que hay que traer vive en la sección 05 «Antes de venir»;
          · **la tarjeta del QR de registro**, que el canvas lleva entera a esa misma 05.
         ❗ **Y el CTA era el ÚNICO enlace a `#rules` de toda la web** (medido). El ancla se queda
         —la sección existe y sigue siendo destino directo— pero **hoy no se llega a ella navegando**:
         o entra en el menú, o espera a la sección 05. Ficha en `DEUDA.md`.

         ⚠️ `/precios` NO cambia: sigue con `<x-site.ticket-prices>` y con sus propios textos. Es una
         PÁGINA, tiene artboard propio (`Precios Pagina PJP`) y se rehace en la Fase 3. --}}
    <section id="pricing" class="section wrap">
        {{-- ⚠️⚠️ **`.sec-head` y no `.rides__head`, y lo dijo la sonda.** La cabecera vieja estiliza
             a sus párrafos por ELEMENTO (`.rides__head > p`), así que el rótulo —que también es un
             `<p>`— salía a 21 px en Hanken en vez de a 12 en mono: un selector con más
             especificidad ganándole a la clase, **con el marcado correcto y sin fallar nada**. Es
             la trampa de cascada de `#314` por otra puerta.
             ▶ `.sec-head` es la cabecera que el canvas cierra para las ocho secciones; las otras
             siete la adoptan al rehacerse y `.rides__head` se retira con la última. --}}
        <div class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.rates.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.rates.title') }}</h2>
            {{-- ⚠️ La entradilla **vende con una cifra**, y la cifra es del catálogo: sin entradas
                 vendibles cae a la variante sin precio, porque un «desde» que no existe miente. --}}
            <p class="sec-head__lede">{{ $ratesFrom === null
                ? __('landing.rates.intro_plain')
                : __('landing.rates.intro', ['from' => $ratesFrom]) }}</p>
        </div>

        <x-site.rate-rail :zones="$rateCards" :special-label="$ratesSpecialLabel" />
    </section>

    {{-- ══ 04 · CUMPLEAÑOS · los dos packs ═════════════════════════════════════════════════════
         Carril de diseño Fase 2 · T2e (`DECISIONES #483`). Artboard `Cumpleanos PJP` **7b** (móvil)
         + `Escritorio PJP` **5a** (escritorio, aprobada el 8 sep).

         ⚠️⚠️ **ES SECCIÓN PROPIA Y NO `<x-site.events-section>`, y eso es deliberado**: aquel
         componente lo comparten la portada y **`/cumpleanos`**, que es una PÁGINA con artboard
         propio (`Cumpleanos Pagina PJP`) y se rehace en la **Fase 3**. Rehacerlo aquí habría
         cambiado esa página desde una tanda de la portada — es exactamente lo que `#479` evitó con
         `/precios` y su `<x-site.ticket-prices>`.
         ▶ Por eso la banda heredada —polaroid, cinta, pegatina y billete— y el **«paso a paso» de
         cinco pasos** salen de la portada y **siguen enteros en `/cumpleanos`**. `[DECIDIDO owner]`:
         el paso a paso no entra (medido: 615 px de los 2.489 que ocupaba la sección en móvil).

         ⚠️ **El ancla `#events` se conserva**: no la enlaza nada dentro del repo —el menú y el pie
         van a `route('cumpleanos')`, medido— pero una URL con ancla puede estar repartida fuera. --}}
    @if ($partyCards !== [])
        <section id="events" class="section wrap">
            {{-- ⚠️⚠️ **AQUÍ HUBO UNA CABECERA SOBRE FOTO A SANGRE Y SE RETIRÓ** (`[DECIDIDO owner]`,
                 `#484`). El artboard la dibuja —es la única sección que la lleva— pero la foto que la
                 instalación tiene en `zones.image` para cumpleaños es **el comedor vacío**: filas de
                 mesas y sillas, sin tarta y sin niños. No dice «cumpleaños», y el propio artboard lo
                 tenía fichado como pendiente del dueño («foto del cumple montado»).
                 ▶ La sección abre con **la cabecera común de las ocho** (`.sec-head`), que es lo que
                 el canvas cierra. Medido: la cabecera pasa de 549 a **73 px** en escritorio y la
                 sección de 1.620 a 1.145.
                 ⚠️ **No queda un mecanismo dormido**: si algún día entra una foto de un cumple
                 montado, volver a la cabecera sobre foto es una decisión, no un efecto lateral de
                 subir una imagen al panel. --}}
            <div class="sec-head">
                <p class="sec-head__eyebrow">{{ __('landing.events.eyebrow') }}</p>
                <h2 class="sec-head__title">{{ __('landing.events.section_title') }}</h2>
                <p class="sec-head__lede">{{ $partyFrom === null
                    ? __('landing.events.section_intro_plain')
                    : __('landing.events.section_intro', ['from' => $partyFrom]) }}</p>
            </div>

            {{-- EL RELOJ DE LAS DOS HORAS (`[DECIDIDO owner]`: **dentro en las dos superficies**,
                 contra el artboard, que lo apaga en móvil por presupuesto de pantalla).

                 ❗❗ **NO REPARTE, y ésa es toda la pieza.** Las dos horas son para todo —merienda,
                 tarta y saltos— y **no hay hora para nada**: si meriendan rápido, saltan más. Los
                 tres tramos con sus minutos que había antes contaban un horario que no existe, y
                 *un diagrama de tramos promete horario aunque la letra diga lo contrario*. Se
                 dibuja el TOTAL entero con las tres cosas encima.
                 ⚠️ **La duración sale del catálogo**; sin ella el reloj no se pinta, porque su
                 titular es la duración. --}}
            @if ($partyDuration)
                <div class="party__clock" data-surface="ink">
                    <div class="party__clock-said">
                        <h3 class="party__clock-title">{{ __('landing.events.clock_title', ['duration' => $partyDuration]) }}</h3>
                        <p class="party__clock-rule">{{ __('landing.events.clock_rule') }}</p>
                        <p class="party__clock-rule">{{ __('landing.events.clock_monitor') }}</p>
                    </div>
                    {{-- ⚠️ `aria-hidden`: las tres cápsulas y el filete son el DIBUJO de lo que la
                         frase de al lado ya dice. Anunciarlas repetiría la regla en desorden. --}}
                    <div class="party__clock-rail" aria-hidden="true">
                        <ul class="party__clock-caps" role="list">
                            <li>{{ __('landing.events.clock_a') }}</li>
                            <li>{{ __('landing.events.clock_b') }}</li>
                            <li>{{ __('landing.events.clock_c') }}</li>
                        </ul>
                        <span class="party__clock-line"></span>
                    </div>
                </div>
            @endif

            {{-- LAS DOS TARJETAS. `[DECIDIDO owner]`: **la tarjeta entera es el enlace y lleva a
                 `/cumpleanos`**, no al cajón. Su razón la escribe el canvas: *«en la landing va la
                 promesa, no la lista»* — un cumple se decide comparando, y la comparativa vive en
                 la página.
                 ⚠️⚠️ **Consecuencia declarada**: con esto la portada se queda **sin ninguna puerta
                 que abra el cajón posicionado** —la de zona ya se perdió en `#482`—; siguen el CTA
                 del hero y la barra flotante, que abren sin nada elegido. Ficha en `DEUDA.md`.
                 ⚠️ **Alternan SUPERFICIE, no color de zona**: la primera en papel y la segunda en
                 tinta, como las dos tarjetas de la sección 01. Pintarlas con la paleta de la zona
                 sería la **grieta 01** que el propio canvas nos reportó. --}}
            <ul class="party__packs" role="list">
                @foreach ($partyCards as $card)
                    {{-- ⚠️⚠️ **LA SEGUNDA TARJETA DECLARA `data-surface="ink"`, y sin eso se ve
                         MAL sin que nada falle.** Medido antes de ponerlo: el punto de la viñeta
                         salía en `rgb(16,20,24)` sobre un fondo `rgb(26,31,37)` —o sea tinta
                         sobre tinta, **invisible**— y los términos en el gris del PAPEL sobre un
                         fondo oscuro. Pintar el fondo de una tarjeta no cambia sus tokens: eso
                         lo hace el mecanismo de SUPERFICIE (`#192`), que aquí flipa `--fg`,
                         `--fg-mute`, `--line` y `--money` de golpe.
                         ⚠️ Y va por POSICIÓN en la vista y no en el CSS, porque es un hecho del
                         contenido —son exactamente dos tarjetas y alternan— y no una regla de
                         estilo que se pueda leer al revés.

                         ❗❗❗ **VA EN LA TARJETA Y NO EN EL `<li>`, y ponerlo mal dejaba un
                         RECUADRO DETRÁS** (`#484`, lo vio el owner). `[data-surface]` no solo
                         declara la superficie: **la PINTA** (`background: var(--bg)`). Con el
                         atributo en el `<li>`, ese contenedor se volvía un rectángulo de tinta
                         pura **con radio 0** y exactamente la misma caja que la tarjeta, así que
                         asomaba por las cuatro esquinas redondeadas. *Declarar una superficie no
                         es solo cambiar tokens: es pintar.* --}}
                    <li class="party__pack-item">
                        {{-- ⚠️⚠️ **SIN ANCLA, y es una decisión medida.** El artboard enlaza a
                             `#cumple-kids` / `#cumple-jump`, pero `/cumpleanos` **no emite esas
                             anclas**: lo que tiene son `#bd-panel-<id>`, que son paneles de
                             pestaña ocultos con `x-show`. Enlazar ahí sería el ancla muerta que
                             `#482` acaba de fichar de `#478` (`/precios#zona-<slug>`, cero
                             destinos). ▶ Y los dos packs viven en la MISMA zona (`cumpleanos`,
                             medido), así que un ancla por zona daría el mismo destino dos veces.
                             ▶ Cuando la Fase 3 rehaga `/cumpleanos`, el sitio de esto es un
                             `?pack=` que el servidor honre — el mecanismo de `/atracciones`. --}}
                        <a class="party-card" @if ($loop->even) data-surface="ink" @endif
                           href="{{ route('cumpleanos') }}">
                            <span class="party-card__chip">{{ $card['name'] }}</span>
                            @if ($card['age'])
                                <span class="party-card__age">{{ $card['age'] }}</span>
                            @endif

                            {{-- ⚠️ **El tope de TRES lo declara la VISTA**: el panel decide QUÉ
                                 incluye el pack y el diseño CUÁNTAS caben — la regla que `#293`
                                 dejó escrita para las normas de la portada. Con las cinco que
                                 esta instalación tiene hoy, dos repiten lo que la tarjeta ya
                                 dice (la duración y la edad). --}}
                            @if ($card['features'] !== [])
                                <span class="party-card__list">
                                    @foreach (array_slice($card['features'], 0, 3) as $feature)
                                        <span class="party-card__feat">
                                            <span class="party-card__dot" aria-hidden="true"></span>
                                            <span>{{ $feature }}</span>
                                        </span>
                                    @endforeach
                                </span>
                            @endif

                            <span class="party-card__money">
                                <span class="party-card__terms">
                                    {{-- ⚠️ **La especial va ENTERA, nunca como recargo** (regla
                                         dura del canvas): no es plana entre los dos packs —+2 en
                                         Kids y +4 en Jump—, así que un recargo obligaría al
                                         cliente a recordar cuál le toca. --}}
                                    @if ($card['special'])
                                        <span class="party-card__special">{{ $card['special'] }} {{ __('landing.events.special_suffix') }}</span>
                                    @endif
                                    <span class="party-card__terms-line">{{ __('landing.events.reserve_terms', [
                                        'min' => $card['min'], 'max' => $card['max'], 'deposit' => $card['deposit'],
                                    ]) }}</span>
                                </span>
                                {{-- EL SELLO girado −6°, con la cifra y su unidad. Es la misma
                                     pieza que el sello de precio de la tarjeta de zona. --}}
                                <span class="party-card__seal">
                                    <span class="party-card__price">{{ $card['price'] }}&nbsp;€</span>
                                    <span class="party-card__unit">{{ __('landing.events.per_child') }}</span>
                                </span>
                            </span>

                            {{-- ⚠️ **El rótulo NO interpola el nombre del pack**, y no es pereza:
                                 el catálogo escribe «Pack Cumpleaños KIDS», así que saldría «Ver
                                 el cumple Pack Cumpleaños KIDS». Recortar el prefijo común sería
                                 una ADIVINANZA —lo que `#479` prohibió expresamente al partir el
                                 nombre de una tarifa: «una comprobación, no una adivinanza»—.
                                 ▶ Y no hace falta: **la tarjeta entera es el enlace**, así que su
                                 nombre accesible ya empieza por la chapa con el nombre del pack. --}}
                            <span class="party-card__go">
                                <span>{{ __('landing.events.see_pack') }}</span>
                                <x-icons.arrow-right class="arrow" :width="16" :height="16" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            {{-- El pie: los días de la especial UNA vez, y la edad mezclada en una línea. --}}
            @if ($ratesSpecialLabel && collect($partyCards)->contains(fn ($c) => $c['special'] !== null))
                <x-site.special-rate-note />
            @endif
            <p class="party__mixed">{{ __('landing.events.mixed_note') }}</p>

            {{-- EL BLOQUE DE COMPLEMENTOS, con el molde compartido con las tarifas
                 (`[DECIDIDO owner]`, `#483`: *«es el mismo formato y diseño que los complementos
                 de las entradas; simplemente mostramos los complementos disponibles para los
                 cumpleaños sin repetirse»*).
                 ⚠️ Aquí el alcance es LOS DOS packs a la vez —se ven juntos, al revés que las
                 tarifas, donde cada zona tiene el suyo— y la deduplicación es **por ID**: las dos
                 «Hora extra de sala» son productos distintos con precios distintos. --}}
            <x-site.addons-rail :products="$packages"
                                :title="__('landing.events.addons_title')"
                                :lede="__('landing.events.addons_intro')" />
    </section>
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
            {{-- ⚠️ El RÓTULO vuelve, y no contradice a `#303`. Aquélla retiró la etiqueta genérica
                 («ZONAS», que repetía el titular); ésta dice **a quién va dirigida** la sección, que
                 es información que el titular no da. Es el molde del canvas: rótulo en Etiqueta ·
                 titular en Display L · la regla debajo. --}}
            <p class="zones__eyebrow">{{ __('landing.zones.eyebrow') }}</p>
            <div class="zones__titulo">
                <x-site.ilu clave="slot-zonas" class="zones__mancha" />
                <h2 class="zones__title">{{ __('landing.zones.title') }}</h2>
            </div>
            {{-- ⚠️⚠️ **La REGLA va entera y en una frase.** Es la del parque —manda la edad, y la
                 altura desempata— y partirla en dos líneas la convierte en dos reglas, que es justo
                 lo que la sección existe para evitar: que el visitante llegue a la puerta sin saber
                 cuál manda. Sustituye a la entradilla larga, cuya última frase («Elige el tuyo»)
                 tenía como sujeto el selector de pestañas que esta tanda retira. --}}
            <p class="zones__rule">{{ __('landing.zones.rule') }}</p>
        </div>

        {{-- ══ LAS DOS TARJETAS DE ZONA ═══════════════════════════════════════════════════════
             `DECISIONES #478` · Fase 2 · T2b. **La tarjeta ENTERA es el enlace, sin botón** (marco
             aprobado del canvas), y lleva a la TARIFA de esa zona: elegir zona y ver su precio es
             un solo recorrido.
             ⚠️⚠️ **Esto no reintroduce el defecto de `#295`**, que era tener dos superficies
             haciendo la MISMA elección: aquéllas saltaban a la sección donde unas pestañas volvían
             a elegir zona. Aquí la tarjeta **navega** y las pestañas de tarifas **eligen tarifa** —
             son eslabones del mismo camino, no dos puertas a lo mismo.
             ⚠️ Todo el contenido sale de la BD (`ZoneCards`): nombre, descripción y edad son campos
             traducibles de `zones`, la altura sus dos columnas nuevas, y el precio, del catálogo por
             los mismos métodos que usa el resto de la landing. **Lo que no hay, no se pinta.** --}}
        <ul class="zone-cards" role="list">
            @foreach ($zoneCards as $i => $card)
                <li class="zone-cards__item">
                    {{-- ⚠️⚠️ **Las tarjetas ALTERNAN superficie, y eso es un mecanismo, no el color de
                         esta marca.** El artboard pinta una en papel y otra en tinta, y `data-surface`
                         es el interruptor que el producto ya tiene (`tema-por-instalacion.md`): con
                         dos zonas sale exactamente el mockup, y con tres o con una sigue teniendo
                         sentido. Pintarlas con el color de la zona sería la grieta 01 del propio
                         canvas —el color de un DATO decidiendo el aspecto de un componente—. --}}
                    <a class="zone-card" href="{{ route('precios') }}#zona-{{ $card['slug'] }}"
                       data-zone="{{ $card['slug'] }}"
                       @if ($card['tint']) style="--zone-tint: {{ $card['tint'] }}; --zone-tint-op: {{ $card['tintOpacity'] }};" @endif>

                        {{-- ── LA FOTO, a 16:9, con el SELLO asomando por su borde ─────────────
                             ⚠️ El hueco existe aunque no haya foto: sin él, el sello —que va
                             ANCLADO a su borde inferior— se quedaría flotando sobre el texto. El
                             fondo es el mismo relleno oscuro que usa el artboard mientras no hay
                             imagen, no un hueco vacío.
                             ⚠️ **`zones.image` recupera consumidor aquí**: se quedó sin ninguno en
                             `#302` y era una de las tres salidas anotadas en `DEUDA.md`. --}}
                        <div class="zone-card__viz">
                            @if ($card['image'])
                                {{-- ⚠️ `aria-hidden` y `alt` vacío: la foto es DECORACIÓN. El nombre de la zona,
                                     su edad y qué hay dentro ya están en texto justo debajo, así que
                                     describirla otra vez sería leer la tarjeta dos veces. --}}
                                <img src="{{ $card['image'] }}" alt="" aria-hidden="true" loading="lazy" decoding="async" width="800" height="450">
                            @endif

                            {{-- El SELLO de precio. ⚠️ Solo si la zona tiene entrada vendible: sin
                                 precio no se pinta un sello vacío ni un «consultar». --}}
                            @if ($card['from'] !== null)
                                <p class="zone-card__seal">
                                    <span class="zone-card__from">{{ __('landing.zones.from') }}</span>
                                    <span class="zone-card__price">{{ $card['from'] }}</span>
                                    @if ($card['special'] !== null)
                                        <span class="zone-card__special">{{ $card['special'] }} {{ $card['specialLabel'] }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        {{-- ── EL CUERPO: una ESCALA DE ESTATURA, no un bloque de texto ────────
                             ❗❗❗ **Ésta es la idea de la sección y sin ella la tarjeta solo se
                             parece al mockup.** El cuerpo se parte en dos por la frontera de altura,
                             y **el velo de color tiñe SOLO el tramo que le toca a esta zona**: la de
                             «desde 1,30 m» colorea de la línea hacia arriba, y la de «hasta 1,30 m»
                             de la línea hacia abajo. Al otro lado queda el hueco con el nombre de la
                             vecina. Por eso las dos tarjetas se leen juntas como **una sola escala**.
                             ⚠️ El ORDEN de los bloques sale del lado, y el lado sale de qué columna
                             lleva el umbral (`height_min_cm` o `height_max_cm`) — nunca de adivinar
                             qué zona es. --}}
                        <div class="zone-card__body"
                             @if ($card['heightAxis'])
                                 data-axis data-side="{{ $card['heightAxis']['side'] }}"
                             @endif>
                            @if ($card['heightAxis'])
                                {{-- El EJE: la línea con sus dos extremos. ⚠️ `aria-hidden` porque es
                                     el DIBUJO de una regla que el texto de al lado ya dice; y una
                                     escala de estatura no se recorre con un lector de pantalla. --}}
                                <div class="zone-card__axis" aria-hidden="true">
                                    <span class="zone-card__axis-top">
                                        <b>{{ __('landing.zones.axis_label') }}</b>
                                        <i>{{ $zoneAxisCeiling }}</i>
                                    </span>
                                    <span class="zone-card__axis-zero">{{ $zoneAxisFloor }}</span>
                                </div>
                            @endif

                            {{-- El hueco de la VECINA, al otro lado de la línea. Va antes o después
                                 del bloque teñido según el lado, y por eso está dos veces: es la
                                 misma pieza en dos sitios, no dos piezas. --}}
                            @if ($card['heightAxis'] && $card['heightAxis']['side'] === 'below' && $card['heightAxis']['neighbour'])
                                <p class="zone-card__neighbour">{{ __('landing.zones.above_is', ['zone' => $card['heightAxis']['neighbour']]) }}</p>
                                <p class="zone-card__border"><span class="zone-card__chip">{{ $card['heightAxis']['label'] }}</span></p>
                            @endif

                            <div class="zone-card__text">
                                {{-- El VELO. ⚠️⚠️ **Aquí el color de zona SÍ va, y no contradice a
                                     `#436`**: aquélla sacó `--zone-*` de los CONTROLES —la grieta 01
                                     del canvas era el botón de comprar teñido por un dato— y lo dejó
                                     «para lo que IDENTIFICA una zona». Esto es exactamente eso, y
                                     además **dice cuánto mide**: el color ocupa su tramo. --}}
                                <span class="zone-card__tint" aria-hidden="true"></span>
                                <span class="zone-card__inner">
                                    <h3 class="zone-card__name">{{ $card['name'] }}</h3>
                                    <span class="zone-card__who">
                                        <span>{{ $card['age'] }}</span>
                                        @if ($card['height'])
                                            <span>{{ $card['height'] }}</span>
                                        @endif
                                        @if ($card['description'])
                                            <span class="zone-card__what">{{ $card['description'] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </div>

                            @if ($card['heightAxis'] && $card['heightAxis']['side'] === 'above' && $card['heightAxis']['neighbour'])
                                <p class="zone-card__border"><span class="zone-card__chip">{{ $card['heightAxis']['label'] }}</span></p>
                                <p class="zone-card__neighbour">{{ __('landing.zones.below_is', ['zone' => $card['heightAxis']['neighbour']]) }}</p>
                            @endif
                        </div>

                        {{-- ⚠️ **No es un botón**: la tarjeta entera ya es el enlace, y meter un
                             control dentro de un `<a>` es marcado inválido además de dos dianas para
                             el mismo destino. Es la afordancia que dice a dónde lleva. --}}
                        <span class="zone-card__go">
                            {{ __('landing.zones.see_zone', ['zone' => $card['name']]) }}
                            <x-icons.arrow-right :width="18" :height="18" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- ══ 03 · QUÉ HAY DENTRO · el mosaico de cinco ═══════════════════════════════════════════
         Carril de diseño Fase 2 · T2d (`DECISIONES #482`). Artboard `Juegos PJP` **6a** (móvil) +
         `Escritorio PJP` **4b** (escritorio, aprobada el 8 sep).

         ⚠️⚠️ **AQUÍ VIVÍA EL CARRUSEL DE LAS 23**, con su selector de zona, sus flechas y su barra
         de progreso. Se retira entero: la portada enseña **cinco** y las 23 viven en
         **`/atracciones`** (`#481`), que es la capa 3 del sistema de contenido —«quien llega aquí ha
         querido llegar»— y el único sitio donde caben con su edad.

         ❗❗ **LA CHAPA DEL «18 MÁS» NO ENTRA, y no es un olvido**: el artboard la tiene detrás de un
         interruptor **apagado** desde el recorte de presupuesto del 7 sep, igual que la chapa de
         zona de `#480`. En su lugar entra lo que la propia deuda pedía: **la cifra en la entradilla**
         y **una sola puerta** debajo del mosaico.

         ⚠️ **El ancla `#rides` se queda**: la enlazan el menú y el pie (medido: `/#rides` en
         `nav.blade.php` y en `footer.blade.php`). Lo que cambia es lo que encuentra quien llega. --}}
    <section id="rides-section" class="section wrap">
        <div id="rides" class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.rides.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.rides.title') }}</h2>
            {{-- ⚠️ La entradilla **abre con la cifra**, y la cifra es DATO: es lo que sustituye a la
                 chapa retirada. Sin ella la sección enseña cinco fotos y no dice cuántas hay. --}}
            <p class="sec-head__lede">{{ __('landing.rides.intro', ['count' => $ridesTotal]) }}</p>
        </div>

        @if ($rideMosaic !== [])
            {{-- EL MOSAICO. La geometría la manda la rejilla y cada celda dice su papel con un
                 `data-`, no con una clase por posición: el papel lo decide el dominio y la hoja solo
                 lo viste. --}}
            <ul class="mosaic" role="list">
                @foreach ($rideMosaic as $celda)
                    @if ($celda['papel'] === 'velada')
                        {{-- ⚠️⚠️ **UNA VELADA NO ES CONTENIDO: ES TEXTURA.** Pierde el nombre, deja
                             de ser enlace y sale del árbol de accesibilidad — es la condición con la
                             que el velo entró en el sistema («lo que se oculta no puede ser un
                             destino, y un nombre a medio velo se queda sin contraste»). --}}
                        <li class="mosaic__cell" data-papel="velada" aria-hidden="true">
                            @if ($celda['ride']->image)
                                <img class="mosaic__img" src="{{ asset($celda['ride']->image) }}" alt=""
                                     aria-hidden="true" loading="lazy" decoding="async">
                            @endif
                            <span class="mosaic__veil"></span>
                        </li>
                    @else
                        {{-- ⚠️ **La foto lleva a `/atracciones`** (`[DECIDIDO owner]`), y llega con la
                             zona de esa atracción ya elegida: la página la lee de `?zona=`, que es lo
                             único que funciona sin JavaScript. No abre ficha flotante — se descartó a
                             propósito: una sola forma de profundizar. --}}
                        <li class="mosaic__cell" data-papel="{{ $celda['papel'] }}">
                            <a class="mosaic__link" href="{{ route('atracciones', ['zona' => $celda['zone']->slug]) }}">
                                {{-- ⚠️ **`alt=""` CON `aria-hidden`, y es la forma canónica, no un atajo**: el
                                     nombre de la atracción y su zona están en la banda de al lado, dentro
                                     del mismo enlace. Un `alt` con el nombre lo diría dos veces; un `alt=""`
                                     a secas deja a quien no ve sin saber que eso es una imagen decorativa.
                                     Lo vigila `SeoTest::test_content_images_have_non_empty_alt`. --}}
                                @if ($celda['ride']->image)
                                    <img class="mosaic__img" src="{{ asset($celda['ride']->image) }}"
                                         alt="" aria-hidden="true" loading="lazy" decoding="async">
                                @endif
                                {{-- La banda de tinta: el nombre y, debajo, su zona. La zona va en
                                     blanco pleno porque sobre una banda al 82 % el gris secundario
                                     no llega —es la regla del sistema, no un ajuste—. --}}
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

        {{-- LA PUERTA. Una sola, y es la única entrada a `/atracciones` desde la portada.
             ⚠️ Sin atracciones no se pinta: una puerta a una página vacía no es una puerta. --}}
        @if ($ridesTotal > 0)
            <p class="rides__door">
                <a class="rides__door-link" href="{{ route('atracciones') }}">
                    {{ __('landing.rides.door', ['count' => $ridesTotal]) }}
                    <x-icons.arrow-right class="arrow" :width="16" :height="16" />
                </a>
            </p>
        @endif
    </section>

    {{-- ══ 05 · ANTES DE VENIR · el registro ES el QR ═══════════════════════════════════════════
         Carril de diseño Fase 2 · T2f (`DECISIONES #485`). Artboards `Antes de Venir PJP` **2a**
         (móvil) + `Escritorio PJP` **3a** (escritorio, rehecha por dentro el 8 sep).

         ❗❗❗ **NO ES UNA SECCIÓN NUEVA: SUSTITUYE A LA DE NORMAS.** Aquélla (`#309`) decía las dos
         mismas cosas —registrarse y traer calcetines— más un asomo de cuatro normas. `[DECIDIDO
         owner, 2026-09-10]`: las cuatro normas **se van** y queda el enlace, que es lo que dibuja
         el canvas. Con eso `/normas` gana una entrada desde la portada y se **cierra la ficha de
         `DEUDA.md`** que `#480` abrió al retirar el único enlace a `#rules` (medido entonces y
         vuelto a medir ahora: cero `href="#rules"` en todo el repo).

         ▶ **Y va aquí y no donde estaba** (`[DECIDIDO owner]`: «donde la pone el canvas»). El canvas
         ordena 01…08 y 05 va **después de las secciones de producto y antes de Visítanos y Dudas**;
         la de normas vivía DESPUÉS de Visítanos. Es un salto de un puesto, y no toca el resto del
         orden que `#314` decidió.

         ⚠️⚠️ **EL ACTA DEL CANVAS DESCRIBE UNA PIEZA QUE SU ARTBOARD TIENE APAGADA, y van CUATRO**
         (`#480` la chapa de zona, `#482` la del «18 más», `#483` el reloj y el aviso INFO). Aquí es
         **la chapa de «lo que ve el empleado»**: vive tras el interruptor `conChapaEmpleado`,
         **apagado por defecto** desde el recorte de presupuesto del 7 sep (la sección pasa de 1.180
         a 726 px, medido por el propio artboard). *Esa fuente se relee antes de cada tanda y
         mientras dura, y sin creerse el acta.*

         ▶ **En ESCRITORIO la chapa vuelve**, y el motivo es aritmético y suyo: ahí no ocupa alto
         —va en la columna de al lado, que sin ella se queda vacía—. Medido por el canvas: 722 px con
         chapa en escritorio contra 726 sin ella en móvil.

         ⚠️⚠️ **EL BLOQUE ES DE TINTA EN LAS DOS SUPERFICIES, y eso NO se pudo decidir a ojo.**
         `[data-surface]` no solo cambia tokens: **PINTA** (`background: var(--bg)`, la lección que
         `#484` pagó), así que una superficie **no puede depender del ancho de la ventana**. Entre
         las dos fuentes manda la más nueva: `Escritorio PJP` 3a se rehízo el **8 sep** con la idea
         que ordena la sección —*«la sección ES el código: en una página de papel, un bloque de tinta
         es lo más importante de la pantalla, y 05 dejaba su pieza principal de chapa lateral»*—,
         mientras que el móvil 2a es del **7 sep** y nunca se actualizó a ella. Lo que sí se respeta
         del móvil es su recorte, que el canvas declara expresamente aparte: *«el interruptor de
         móvil no se toca: son dos superficies y dos decisiones»*.

         ⚠️ **Medido antes de decidirlo, porque el argumento del presupuesto aquí es más flojo que en
         el canvas**: la sección que sustituye pesa **1.108 px en móvil**, así que traer también las
         tres filas dejaría la portada casi igual (−88 px) en vez de −382. Se sigue al canvas, y la
         cifra queda escrita para que el owner pueda revertirlo con el número delante — que es lo que
         hizo en `#483` con el reloj. --}}
    <section id="before" class="section wrap">
        <div class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.before.eyebrow') }}</p>
            <h2 class="sec-head__title">{{ __('landing.before.title') }}</h2>
            <p class="sec-head__lede">{{ __('landing.before.lede') }}</p>
        </div>

        {{-- EL BLOQUE: el objeto a un lado y lo que abre al otro. --}}
        <div class="before__code" data-surface="ink">
            {{-- ── EL OBJETO ────────────────────────────────────────────────────────────────────
                 ⚠️ **El código se enseña dentro de un MÓVIL** (`Escritorio PJP` 3a, 8 sep): *«el
                 sitio del código es el teléfono, y así la sección contesta sola el "¿tengo que
                 imprimirlo?" sin gastar una línea»*. En móvil el marco del teléfono se retira —
                 dibujar un teléfono dentro de un teléfono no dice nada— y queda su pantalla, que es
                 exactamente la tarjeta blanca del artboard de móvil. --}}
            <div class="before__object">
                {{-- ⚠️ **El objeto y su nombre van juntos**, y por eso hay un envoltorio: en teléfono
                     el código y el texto se ponen al LADO (como el artboard de móvil) y ahí lo que
                     se empareja con el texto es el código **con su nombre debajo**, no el código
                     suelto. En escritorio se apilan los tres. --}}
                <div class="before__id">
                    <div class="before__device">
                        <span class="before__ear" aria-hidden="true"></span>
                        <div class="before__screen">
                            <x-site.sample-qr :label="__('landing.before.qr_aria')" />
                        </div>
                        <span class="before__chin" aria-hidden="true"></span>
                    </div>
                {{-- ⚠️⚠️ **El nombre va FUERA del recuadro blanco, y el artboard escribe por qué**:
                     dentro se comería la **zona de silencio**, que es lo único que ese margen está
                     ahí para dar. Aquí el código no se escanea, pero la anatomía se copia entera o
                     no se copia: la pieza tiene que seguir valiendo el día que enseñe uno de verdad.
                     ⚠️ **«Mi QR» es el nombre del PRODUCTO** (`[DECIDIDO owner, 2026-09-10]`): es
                     exactamente como se llama en la cuenta (`account.card.title`), y el mismo objeto
                     no puede llamarse de dos maneras. El canvas escribe «Mi Play Jump QR», que es
                     marca del cliente y no entra en el producto (`DECISIONES #1`). --}}
                    <p class="before__name">
                        {{ __('landing.before.qr_name') }}
                        <span class="before__sample">{{ __('landing.before.qr_sample') }}</span>
                    </p>
                </div>
                <p class="before__where">{{ __('landing.before.qr_where') }}</p>
            </div>

            {{-- ── LO QUE EL CÓDIGO LLEVA ───────────────────────────────────────────────────────
                 ⚠️⚠️ **El sujeto es el VISITANTE, no el empleado**, y es una regla del canvas que
                 costó una reescritura: *«lo que ve el empleado es su trabajo, no la ventaja del
                 cliente»*. Por eso el titular es «Un código para todo» y no «lo que ve el empleado»,
                 y por eso las tres filas dicen **lo que el código lleva siempre** —tus reservas, tu
                 firma, tus hijos— y no una reserva concreta.
                 ⚠️ **Las tres filas y el titular NO se pintan en móvil** (el recorte del 7 sep). El
                 remate con el ✓ sí: es la frase que sostiene la sección. --}}
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

        {{-- ── LA EXCEPCIÓN ─────────────────────────────────────────────────────────────────────
             ⚠️ **Va en PAPEL y fuera del bloque a propósito**: los calcetines son *«lo único que no
             cabe en el código»*, o sea la excepción — meterlos dentro los convertiría en una cuarta
             cosa que el código lleva.
             ⚠️⚠️ **La frase NO lleva el precio, y no es un olvido.** El producto **no sabe cuál de
             sus complementos son «los calcetines»**: identificarlo por su icono sería usar un campo
             de PRESENTACIÓN como identidad, que es exactamente el defecto de `accent` que `#295` y
             `#301` pagaron dos veces. ▶ Y no hace falta: el precio ya se publica en esta misma
             página, en el carril de complementos de la sección 02 (medido: «+2 € cada uno»). --}}
        <p class="before__socks">
            <span class="before__i" aria-hidden="true"><x-icons.info :width="24" :height="24" /></span>
            <span><strong>{{ __('landing.before.socks_lead') }}</strong> {{ __('landing.before.socks_text') }}</span>
        </p>

        {{-- ⚠️⚠️ **La línea del niño invitado es DATO, no copia fija.** Ofrece el justificante que
             `#400`/`#401` construyeron, y eso lo decide el catálogo: `ticket_types.guardian_authorization`.
             Con los productos de hoy ninguno lo ofrece (medido: 0 de N), así que la línea no se
             pinta — *prometer un enlace que el catálogo no emite sería el ancla muerta que `#482`
             acaba de fichar*. El día que el operador lo active en un producto, sale sola. --}}
        @if ($guestWaiverOffered)
            <p class="before__guest">{{ __('landing.before.guest_text') }}</p>
        @endif

        {{-- ── LAS DOS SALIDAS · cero relleno de acción ─────────────────────────────────────────
             ⚠️ **Ninguna de las dos es un botón de acción**, y es regla de la sección en el canvas:
             *«cero naranja, cero relleno de acción»*. Aquí no se compra.
             ⚠️⚠️ **Las TRES ramas del registro son las de `<x-site.cta-pair>`**, y duplicarlas es
             menos malo que inventar una cuarta: sería un camino al alta que nadie más mantiene
             (`#309`, que ya lo escribió al construir la sección que ésta sustituye).
             ▶ **Con sesión NO se ofrece crear cuenta** —lo cazó una guarda del nav en su día— sino
             **ver el QR**, que es de lo que habla la sección: abre el cajón en su zona `card`
             (`specs/identidad-qr-puerta.md` §9.6). El `href` se conserva como suelo sin JS. --}}
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

    {{-- ⚠️⚠️ **AQUÍ ESTABA LA SECCIÓN DE NORMAS Y SE HA RETIRADO** (`#485`, Fase 2 · T2f). No se
         ha perdido: **la sustituye la sección 05 «Antes de venir»**, que está más arriba y dice
         las dos mismas cosas —registrarse y traer calcetines— con el diseño del canvas.
         ▶ Lo que SÍ se va con ella es **el asomo de cuatro normas** (`[DECIDIDO owner,
         2026-09-10]`, con la consecuencia delante): la portada deja de enseñarlas y `/normas`
         se alcanza por el enlace de la sección nueva y por el pie.
         ⚠️ **Y con ella se van sus DOS ranuras de dibujo** (`slot-normas-registro` y
         `slot-normas-calcetines`): una ranura vive exactamente lo que vive su consumidor, que es
         la quinta vez que `IllustrationKit::SLOTS` encoge por esa regla. La portada vuelve a
         gastar UNA de sus tres colocaciones de dibujo (`#292`).
         ⚠️ **El ancla `#rules` desaparece**, y se midió antes: cero `href="#rules"` en todo el
         repo —el único lo retiró `#479`—, así que no deja ningún enlace roto. --}}

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
                {{-- ⚠️ Sin talla en línea (`#479`): era un TERCER tamaño de titular de sección
                     escrito a mano —48→96 frente a los 48→108 de la clase— y un `style` gana a la
                     hoja siempre, así que la cabecera de Dudas quedaba fuera del sistema sin que
                     nada lo dijera. El nivel es Display L, como en las otras siete. --}}
                <h2 class="rides__title">{{ __('landing.faq.title') }}</h2>
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
                {{-- ❗ **EL ESLOGAN A ROTULADOR, EN EL CIERRE** (`DECISIONES #477`, `[DECIDIDO owner]`).
                     El marco aprobado del canvas lo manda al cierre **y** al menú, o sea una vez por
                     SUPERFICIE y no una vez por página — y las dos superficies nunca se ven a la vez,
                     porque el menú es `inset: 0` y tapa la portada entera (el mismo razonamiento que
                     ya está escrito en `menu.blade.php`).
                     ⚠️ Va **antes** del titular y no después: es el guiño que presenta la última
                     pantalla antes de comprar, no un pie de página del bloque.
                     ⚠️ Comparte la clave con el menú (`landing.hero.kicker`) a propósito: es el MISMO
                     eslogan, y tenerlo en dos claves invita a que un día digan cosas distintas. --}}
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
