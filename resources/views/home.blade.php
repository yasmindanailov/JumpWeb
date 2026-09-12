{{-- ⚠️ `noindex` lo decide el CONTROLADOR, no esta plantilla: es cierto en las tres puertas de auth
     —`/registro`, `/login`, `/recuperar-contrasena`— y falso en la home y en `/entradas`, que son la
     misma vista. Sustituye al efecto lateral que tenía el prop `auth-modal` (`AccountDoor`). --}}
<x-layout :title="$site['tagline'] ?? __('landing.footer.tag')" :full-title="$site['seo_title'] ?? null" :noindex="$noindex ?? false" :has-hero="true">
@php
    // Horario del parque data-driven (#207): misma fuente que las reservas (opening_hours +
    // temporadas + fechas especiales), agrupado para mostrar.
    $schedule = app(\App\Domain\Content\Services\ScheduleDisplay::class);
    // La entradilla de «Visítanos» sale del MISMO servicio (`#487`): dice cuántos horarios hay y
    // cuáles, en vez de afirmar un horario concreto que otra instalación no tendría.
    $scheduleLede = $schedule->weeklyLede();
    // ⚠️ Aquí se calculaban `$totalSqm`, `$totalRides`, `$totalZones` y `$totalLabels`, la tira de
    // cifras de la sección de zonas. `[DECIDIDO owner, 2026-08-31]` (`#302`): **las tarjetas de zona
    // y las cifras, fuera**. Se van con su consumidor, igual que sus claves de idioma y su CSS.
@endphp

<div x-data="landing">
    {{-- El salto al contenido ya NO se pinta aquí: lo sirve `<x-site.nav>` para las DOCE vistas
         (armazón · tanda 2c·2). Aquí solo quedaba porque la home fue la primera en tenerlo. --}}
    {{-- La portada es la única página con SECCIONES a las que bajar, y las pasa ella (`#521`): las
         demás no pasan nada y su menú se queda solo con «Páginas». --}}
    <x-site.nav :sections="$menuSections" />
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


    {{-- ══ 01 · PARA QUIÉN · las dos zonas ═════════════════════════════════════════════════════
         ❗❗❗ **EL ORDEN DE LAS OCHO SECCIONES LO MANDA EL MOCKUP, Y ESTA ES LA PRIMERA**
         (`DECISIONES #495`). Va **01 Para quién · 02 Cuánto · 03 Qué hay dentro · 04 Cumpleaños ·
         05 Antes de venir · 06 Reseñas · 07 Visítanos · 08 Dudas**, verificado contra `Portada PJP`
         —los ocho rótulos salen en ese orden de documento, con control de que ningún
         `position: absolute` los recoloque— y contra la numeración que `Marco Portada PJP` lleva en
         datos (`01 Zonas · 03 Qué hay dentro · 05 Antes de venir · 07 Visítanos · 08 Dudas`).

         ⚠️⚠️ **Esto REVIERTE el orden de `#314`** (`[DECIDIDO owner, 2026-09-01]`: «entradas →
         cumpleaños → el parque → ubicación → normas → dudas»), y no es una contradicción: aquella
         decisión es del carril de diseño ANTERIOR, y `#469` adoptó el canvas entero. Queda escrito
         para que nadie lo lea como un descuido.

         ⚠️ **Las cabeceras van numeradas 01–08 a propósito**: puestas en orden, un bloque descolocado
         se ve de un vistazo. Esta era la única sin número y por eso costaba saber que iba primera.

         ── Lo que ya decía este bloque, y sigue vigente ──────────────────────────────────────── --}}
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
            {{-- 📜 **AQUÍ VIVÍA LA MANCHA `B1·02`** (ranura `slot-zonas`, `#302`→`#309`), y se
                 retira en `#496`: **el artboard `Zonas PJP` no lleva ninguna pieza decorativa**
                 —medido: cero manchas, siluetas, tramas y frisos—, igual que `Portada PJP`. Era el
                 último resto del carril anterior; `#479` y `#485` ya se habían llevado las otras
                 tres por la misma razón. --}}
            {{-- ⚠️ El RÓTULO vuelve, y no contradice a `#303`. Aquélla retiró la etiqueta genérica
                 («ZONAS», que repetía el titular); ésta dice **a quién va dirigida** la sección, que
                 es información que el titular no da. Es el molde del canvas: rótulo en Etiqueta ·
                 titular en Display L · la regla debajo. --}}
            <p class="zones__eyebrow">{{ __('landing.zones.eyebrow') }}</p>
            {{-- ⚠️ El envoltorio `.zones__titulo` SE QUEDA aunque la mancha se haya ido: existía
                 como contexto de apilamiento para ella, pero también es lo que separa el titular
                 del rótulo y de la regla en la columna. Retirarlo es maquetación, no limpieza. --}}
            <div class="zones__titulo">
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

    {{-- ══ 02 · CUÁNTO · el carril de tarifas ══════════════════════════════════════════════════
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

         ⚠️ `/precios` NO cambió con esta sección: es una PÁGINA, tiene artboard propio
         (`Precios Pagina PJP`) y se rehízo en la Fase 3 (`#531`), con su tabla por zona. --}}
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

        {{-- ══ ¿Y YO QUÉ HAGO MIENTRAS? · la puerta del bar ═══════════════════════════════════
             `DECISIONES #536`, `[DECIDIDO owner]`. Artboard `Juegos PJP` turno **7a**.

             ▶ **La pregunta no es adorno: es lo que convierte la tarjeta en respuesta.** El canvas:
             *«dentro hay cuatro cosas y no dos —Kids, Jump, los juegos de fuera y el bar— y abre una
             pregunta que no estaba en las nueve: voy con dos hijos y solo salta uno, ¿qué hago yo
             mientras? El bar es su respuesta»*. Sin la pregunta escrita, la tarjeta es un aviso
             suelto al final de una sección de juegos.

             ⚠️⚠️ **VIENE SOLA, Y EL ARTBOARD LA DIBUJA EN PAREJA.** Su otra mitad son «los juegos de
             fuera» —garra de peluches y billar—, y **no entra**: no hay dato detrás de eso en ningún
             sitio del producto, así que escribirla sería clavar el contenido de PlayJump en JumpWeb,
             que es justo lo que el filtro de `rediseno-desde-canvas.md` §2 impide. Entra el día que
             tenga dónde guardarse. Ficha en `DEUDA.md`.

             ⚠️ **Solo si el bar está publicado** (tiene nombre en el panel): es el mismo predicado
             que decide su ruta, el menú y el pie. `#482` retiró esta tarjeta porque `/bar` no
             existía; hoy existe, y si el panel no la ha nombrado sigue sin existir.
             ⚠️ **Cero naranja**, y aquí no es estética: el bar está fuera del modelo de reserva
             (`[DECIDIDO owner]`), así que su puerta no puede vestirse de compra. --}}
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

    {{-- ══ 04 · CUMPLEAÑOS · los dos packs ═════════════════════════════════════════════════════
         Carril de diseño Fase 2 · T2e (`DECISIONES #483`). Artboard `Cumpleanos PJP` **7b** (móvil)
         + `Escritorio PJP` **5a** (escritorio, aprobada el 8 sep).

         ⚠️⚠️ **ES SECCIÓN PROPIA Y NO `<x-site.events-section>`, y eso es deliberado**: aquel
         componente lo comparten la portada y **`/cumpleanos`**, que es una PÁGINA con artboard
         propio (`Cumpleanos Pagina PJP`) y se rehace en la **Fase 3**. Rehacerlo aquí habría
         cambiado esa página desde una tanda de la portada — es exactamente lo que `#479` evitó con
         `/precios`, que esperó a su propia tanda (`#531`).
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
                {{-- ⚠️ Desde `#528` es un COMPONENTE: `/cumpleanos` lo pinta también, y el artboard
                     de la página dice que va «copiado del marcado de 04, no redibujado». --}}
                <x-site.party-clock :duration="$partyDuration" />
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
                             anclas**, y enlazar ahí sería el ancla muerta que `#482` fichó de
                             `#478` (`/precios#zona-<slug>`, cero destinos). ▶ Y desde `#528` ya
                             no hace falta: la página COMPARA los packs lado a lado, así que no
                             hay un pack «al que llegar» — la tarjeta lleva a la comparativa. --}}
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

            {{-- El pie: los días de la especial UNA vez.
                 ❗❗ **LA NOTA DE EDADES MEZCLADAS SE FUE A `/cumpleanos`** (`[DECIDIDO owner,
                 2026-09-10]`, `#498`): *«genera ruido, algo debe irse y dejarlo para la página de
                 cumpleaños»*. Aquí eran TRES bloques de texto seguidos bajo las tarjetas —los días,
                 las edades mezcladas y la cabecera del carril— y el caso de dos edades es un detalle
                 que se resuelve en recepción, no un argumento de la portada.
                 ⚠️⚠️ **Se MUEVE, no se borra**: medido, esa frase **solo existía aquí**, así que
                 quitarla sin más la habría hecho desaparecer del sitio entero — y el suplemento
                 mixto es una feature que cobra dinero (`specs/cumple-mixto.md`). Quien lleva niños de
                 dos edades tiene que enterarse en alguna parte. --}}
            @if ($ratesSpecialLabel && collect($partyCards)->contains(fn ($c) => $c['special'] !== null))
                <x-site.special-rate-note />
            @endif

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

    {{-- ══ 06 · RESEÑAS · una opinión a la vez ═════════════════════════════════════════════
         `DECISIONES #490` · carril de diseño Fase 2 · T2i·a. Artboards `Resenas PJP` 2a (móvil) y
         `Escritorio PJP` 5b (escritorio, aprobado el 8 sep).

         ❗❗❗ **LA VISTA NO SABE DE DÓNDE VIENEN LAS OPINIONES, y eso es el diseño.** Lee un solo
         contrato (`Content\Contracts\SocialProof`) y pinta lo que le den. El día que entre Google no
         se toca este marcado: se cambia el binding del composition root por el decorador que aplica
         la cascada. `#136` fijó la línea —la landing consume DATOS, no proveedores— y la spec añade
         el porqué: un respaldo escrito como condicional en la plantilla acaba con **una rama sin
         cubrir, y la rama sin cubrir de un respaldo es la que solo corre cuando algo va mal**.

         ❗❗❗ **LA CHAPA DEL 4,8 NO ESTÁ, Y NO ES QUE FALTE.** `$rating` vale `null` porque la media
         **solo existe si viene de Google**: componerla con opiniones propias sería atribuirle a
         Google un número que Google no ha dado. Hoy además hay otro motivo medido — el parque tiene
         **una** reseña en Google, por debajo del umbral de 10 que el owner fijó, así que publicar
         «5,0 · 1 reseña» restaría en vez de sumar.
         ▶ Cuando haya `$rating`, la chapa entra AQUÍ sin tocar el resto.

         ❗❗ **Y por eso el carril se pinta en MÓVIL** (`[DECIDIDO owner, 2026-09-10]`). El artboard
         de móvil lo tiene apagado desde el recorte del 7 sep, pero lo apagó **porque la chapa ya
         cargaba la sección**: sin chapa, ese motivo desaparece y la sección se quedaría con una
         cabecera que no presenta nada.

         ⚠️⚠️ **La ENTRADILLA depende de la fuente y no es un texto fijo.** La del artboard dice «No
         las elegimos nosotros: son las que Google pone primero», que es lo que hace creíble a
         Google — y sobre opiniones propias **sería falso**: éstas sí las elige el parque.

         ⚠️ **El texto NO se recorta.** El artboard lo corta a cuatro líneas y ofrece «leer entera en
         Google»; una opinión propia **no tiene entera en ningún sitio**, así que recortarla
         escondería texto sin dónde ir a buscarlo — lo contrario de «un enlace no promete lo que no
         esconde». Se enseña completa y el panel las mantiene cortas.

         ⚠️ **El avatar es una INICIAL, no una foto**, como en el artboard. Con opiniones propias no
         hay foto de nadie que pedir, y así la pieza ya está lista para el día de Google: la foto de
         un tercero solo entra con consentimiento (`RGPD-05`) y ahí `avatarUrl` deja de ser `null`.

         ❗❗❗ **CON CERO OPINIONES LA SECCIÓN ENTERA NO SE PINTA** —ni rótulo, ni titular, ni caja—,
         que es la regla dura del sistema, y el propio artboard dibuja ese estado: «la sección no se
         pinta y la portada pasa de Antes de venir a Visítanos». --}}
    @if ($socialProof->isNotEmpty())
        {{-- ❗❗❗ **DE DÓNDE VIENE CADA MITAD, RESUELTO UNA VEZ** (`#494`). La chapa y las opiniones
             **no vienen de la misma fuente** —la cifra se sirve sin consentimiento y las reseñas
             no—, así que el caso frecuente es el CRUCE: chapa de Google sobre opiniones propias.
             Tres piezas dependen de esto y ninguna puede deducirlo por su cuenta. --}}
        @php($opinionesDeGoogle = $socialProof->contains(fn ($o) => $o->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE))
        @php($cifraDeGoogle = $socialRating?->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE)
        <section id="reviews" class="section wrap">
            <div class="rev-sec{{ $socialRating ? ' rev-sec--scored' : '' }}">
                <div class="sec-head">
                    <p class="sec-head__eyebrow">{{ __('landing.reviews.eyebrow') }}</p>
                    {{-- ⚠️ La mancha va ANCLADA AL TITULAR, no a la cabecera entera, y es la lección
                         de `#303`: colocada contra el bloque completo, su sitio depende de cuánto
                         texto tenga la entradilla, y el día que crece la mancha **cae sobre el
                         párrafo**. Su propia nota del artboard dice lo mismo: «detrás de la PRIMERA
                         PALABRA, nunca detrás de todo el bloque». --}}
                    <div class="sec-head__lockup">
                        <x-site.ilu clave="slot-resenas" class="sec-head__mancha" />
                        <h2 class="sec-head__title">{{ __('landing.reviews.title') }}</h2>
                    </div>
                    {{-- ⚠️⚠️ **La entradilla sigue a la fuente de las OPINIONES, no a la de la
                         chapa — y las dos pueden no coincidir.** La cifra se sirve sin
                         consentimiento y las reseñas no, así que el caso más frecuente es
                         justamente ése: chapa de Google encima de opiniones propias. Atada a la
                         chapa, la sección decía «no las elegimos nosotros» **sobre una opinión que
                         sí elegimos**. Lo vio la captura, no la suite. --}}
                    <p class="sec-head__lede">{{ $socialProof->first()?->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE
                        ? __('landing.reviews.lede_google')
                        : __('landing.reviews.lede_own') }}</p>
                </div>

                @if ($socialRating)
                    {{-- ══ LA CHAPA DE LA CIFRA ═══════════════════════════════════════════════
                         ❗❗ **Solo existe si viene de Google.** Componerla con opiniones propias
                         daría un número real —la media de lo que el parque escribió de sí mismo— y
                         publicarlo aquí lo haría pasar por la nota de Google.
                         ⚠️⚠️ **`data-surface="ink"` y no solo un fondo oscuro**: declarar la
                         superficie cambia los tokens **y PINTA** (la lección de `#484`). Sin el
                         atributo, la cifra y el gris saldrían con los valores de papel sobre tinta.
                         ⚠️ **No pide consentimiento**: la trae nuestro servidor, no lleva autor ni
                         foto y no es dato personal. La reseña sí, por su avatar. --}}
                    <div class="rev-score" data-surface="ink">
                        <p class="rev-score__num">
                            <span class="rev-score__val">{{ number_format($socialRating->value, 1, ',', '.') }}</span>
                            <span class="rev-score__of">{{ __('landing.reviews.out_of') }}</span>
                        </p>
                        {{-- ⚠️⚠️ **El recorte va por CAJA, nunca a lo largo de la fila**: el
                             interletraje se come la décima y las cinco se leerían llenas. Cada
                             estrella es su propia caja de 24 y su relleno es un porcentaje de ella. --}}
                        <p class="rev-score__stars" role="img"
                           aria-label="{{ __('landing.reviews.score_aria', ['value' => number_format($socialRating->value, 1, ',', '.')]) }}">
                            @for ($e = 1; $e <= 5; $e++)
                                @php($lleno = max(0, min(1, $socialRating->value - $e + 1)))
                                <span class="rev-score__star" aria-hidden="true">
                                    <span class="rev-score__star-off">&#9733;</span>
                                    <span class="rev-score__star-on" style="width: {{ round($lleno * 100, 2) }}%"><span>&#9733;</span></span>
                                </span>
                            @endfor
                        </p>
                        {{-- El recuento va en TEXTO y no en mono: es el segundo argumento de la
                             sección, y la letra mono es etiqueta, no argumento. --}}
                        <p class="rev-score__count">{{ trans_choice('landing.reviews.count', $socialRating->count, ['n' => number_format($socialRating->count, 0, ',', '.')]) }}</p>
                        {{-- ❗❗❗ **LA ATRIBUCIÓN OBLIGATORIA** (`#494`): la cifra es dato de Places y
                             esta vista no enseña ningún mapa de Google, que es exactamente el
                             supuesto de *«you must include the Google logo»*. Va DENTRO de la chapa
                             porque es lo que acredita —la chapa, no las opiniones de al lado, que
                             suelen ser propias—.
                             ▶ **Va debajo de las estrellas y ahí se queda** (`[owner, 2026-09-10]`).
                             ⚠️ Variante BLANCA porque la chapa es `data-surface="ink"`. Si algún día
                             esta caja deja de ir sobre tinta, hay que cambiar también el fichero: el
                             logotipo no sigue al tema, lo elige el marcado.
                             ⚠️ **Solo es enlace si hay ficha adonde ir.** `Rating::url` es opcional,
                             y un respaldo a `maps.google.com` llevaría al mapa del mundo en vez de a
                             la ficha del parque: prometería una comprobación que no cumple, que es
                             justo la confianza que esta sección viene a dar. --}}
                        @if ($socialRating->url)
                            <a class="rev-score__src" href="{{ $socialRating->url }}"
                               target="_blank" rel="noopener noreferrer nofollow">
                                <x-site.google-attribution surface="ink" />
                            </a>
                        @else
                            <p class="rev-score__src"><x-site.google-attribution surface="ink" /></p>
                        @endif
                    </div>
                @endif

                {{-- ⚠️ `x-data` con el número de opiniones dentro: el carril tiene que saber dónde
                     acaba para deshabilitar la flecha, y ese número lo sabe el servidor. --}}
                <div class="rev" x-data="{ i: 0, n: {{ $socialProof->count() }} }"
                     @keydown.left.prevent="i = Math.max(0, i - 1)"
                     @keydown.right.prevent="i = Math.min(n - 1, i + 1)">
                    @foreach ($socialProof as $k => $op)
                        {{-- ⚠️⚠️ **La primera nace con `is-on` puesto POR EL SERVIDOR, y ése es el
                             suelo sin JavaScript.** Con `x-show` + `x-cloak` —lo primero que se me
                             ocurrió— la sección se queda **vacía** sin JS y parpadea en blanco
                             mientras Alpine arranca. Con la clase servida, sin JS se lee la primera
                             opinión y los controles simplemente no hacen nada. Es el mismo patrón
                             que el acordeón de Dudas. --}}
                        <article class="rev__card{{ $k === 0 ? ' is-on' : '' }}"
                                 :class="i === {{ $k }} && 'is-on'">
                            <div class="rev__who">
                                {{-- ⚠️⚠️ **La foto es OBLIGATORIA cuando la reseña es de Google** (R3:
                                     «you must always credit the author»), y por eso llega solo con
                                     consentimiento — cargarla es una petición del visitante a
                                     `lh3.googleusercontent.com`. Sin `avatarUrl` va la inicial, que
                                     es lo que el artboard dibuja y lo que sirve para las propias.
                                     ⚠️ `referrerpolicy` para no filtrarle a Google la URL de la
                                     página desde la que se pide la foto. --}}
                                @if ($op->avatarUrl)
                                    <img class="rev__ini rev__ini--photo" src="{{ $op->avatarUrl }}" alt=""
                                         width="56" height="56" loading="lazy" decoding="async"
                                         referrerpolicy="no-referrer" aria-hidden="true">
                                @else
                                    <span class="rev__ini" aria-hidden="true">{{ $op->initial() }}</span>
                                @endif
                                <div class="rev__id">
                                    {{-- ❗❗ **El enlace al perfil es la TERCERA pata de la atribución
                                         que R3 exige** —«avatar, name, **and profile link**»— y hasta
                                         `#494` faltaba, aunque el dato ya viajaba en la misma
                                         respuesta de Places. ⚠️ El nombre sigue siendo texto plano
                                         cuando no hay perfil: una opinión propia no tiene ficha en
                                         ninguna parte y subrayarla prometería una que no existe. --}}
                                    <p class="rev__author">
                                        @if ($op->authorUrl)
                                            {{-- ⚠️ El nombre accesible dice ADÓNDE lleva: sin él un
                                                 lector de pantalla anuncia «Ana G., enlace» y no hay
                                                 forma de saber que sale del sitio. --}}
                                            <a href="{{ $op->authorUrl }}" target="_blank"
                                               aria-label="{{ __('landing.reviews.author_on_google', ['name' => $op->author]) }}"
                                               rel="noopener noreferrer nofollow">{{ $op->author }}</a>
                                        @else
                                            {{ $op->author }}
                                        @endif
                                    </p>
                                    @if ($op->when)
                                        <p class="rev__when">{{ $op->when }}</p>
                                    @endif
                                </div>
                                {{-- ⚠️⚠️ **La marca y la nota comparten columna, y ese es todo el
                                     cambio de maquetación**: sin logotipo la columna solo lleva las
                                     estrellas y la fila queda **idéntica** a como estaba, así que una
                                     opinión propia no paga nada por esto. Es también el orden del
                                     widget de Google: quién lo dice, y debajo cuánto puntúa. --}}
                                @if ($op->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE || $op->rating)
                                    <div class="rev__mark">
                                        {{-- ❗❗❗ **Cuelga de la FUENTE del dato, nunca de «hay
                                             avatar» ni de «hay enlace».** Es lo que impide que una
                                             opinión del parque salga vestida de Google, y el dato lo
                                             DICE para que la vista no lo deduzca. --}}
                                        @if ($op->source === \App\Domain\Content\Contracts\Testimonial::SOURCE_GOOGLE)
                                            <x-site.google-attribution surface="paper" />
                                        @endif
                                        @if ($op->rating)
                                            {{-- ⚠️ La nota va como IMAGEN con nombre accesible: cinco
                                                 glifos sueltos los lee un lector de pantalla como
                                                 «estrella estrella estrella…», que no dice la nota. --}}
                                            <p class="rev__stars" role="img"
                                               aria-label="{{ trans_choice('landing.reviews.stars', $op->rating, ['n' => $op->rating]) }}">
                                                <span class="rev__stars-on" aria-hidden="true">{{ str_repeat('★', $op->rating) }}</span><span
                                                      class="rev__stars-off" aria-hidden="true">{{ str_repeat('★', 5 - $op->rating) }}</span>
                                            </p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            {{-- ❗❗ **EL TEXTO SE LEE ENTERO AQUÍ** (`[DECIDIDO owner, 2026-09-10]`).
                                 Antes se recortaba a cuatro líneas y la única salida era irse a
                                 Google; ahora el recorte lo abre un «Ver más» **en la propia
                                 página**, que es donde el visitante está.
                                 ⚠️ Y eso **no incumple R4**: la prohibición es alterar el contenido
                                 del usuario, y el texto servido está completo desde el principio —
                                 el `line-clamp` solo lo tapa. Lo que se retira es la promesa de que
                                 hay que irse a otro sitio para leerlo.
                                 ⚠️⚠️ **El botón se decide MIDIENDO, no contando caracteres.** Con un
                                 umbral de longitud, una opinión de 140 caracteres con palabras
                                 largas se corta y no ofrece abrirla, y otra de 160 con palabras
                                 cortas ofrece abrir lo que ya se ve entero. `cortado` compara el
                                 alto real contra el visible. --}}
                            {{-- ⚠️⚠️ **`mide()` existe porque ahora puede haber DOS párrafos** —el
                                 traducido y el original— y el botón «Ver más» se decide midiendo el
                                 alto real. Sin volver a medir al cambiar de uno a otro, el botón se
                                 ofrecería (o se escondería) según el largo del texto que ya **no** se
                                 está leyendo: una reseña corta en español y larga en inglés lo
                                 enseñaría al revés, y no fallaría nada. --}}
                            <div x-data="{
                                     abierto: false,
                                     original: false,
                                     cortado: false,
                                     mide() {
                                         this.$nextTick(() => {
                                             const p = (this.original && this.$refs.orig) ? this.$refs.orig : this.$refs.txt;
                                             this.cortado = p.scrollHeight > p.clientHeight + 1;
                                         });
                                     },
                                 }"
                                 x-init="mide()">
                                {{-- ⚠️ UN solo atributo `class`: el servidor lo pinta recortado —ése
                                     es el suelo sin JavaScript— y Alpine solo AÑADE `--open`. Dos
                                     atributos `class` en el mismo elemento son HTML inválido y el
                                     navegador se queda con el primero. --}}
                                <p class="rev__text rev__text--clamp" x-ref="txt"
                                   x-show="!original"
                                   :class="abierto && 'rev__text--open'">{{ $op->text }}</p>
                                @if ($op->isTranslated())
                                    {{-- ❗❗❗ **EL ORIGINAL, Y POR QUÉ VA SERVIDO Y OCULTO.** La política
                                         permite dar acceso al texto sin traducir y ésta es la forma
                                         barata: viaja en el marcado y Alpine solo decide cuál se ve.
                                         ⚠️ **`x-cloak` es lo que hace que sin JavaScript NO se vean
                                         los dos textos seguidos** (`x-show` sin Alpine no oculta
                                         nada). Sin JS se lee el traducido, sale el aviso —que es lo
                                         obligatorio— y el original sigue estando a un clic en Google,
                                         que es la salida que ya existe en el marcado servido.
                                         ⚠️ `lang` con el idioma real: es lo que hace que un lector de
                                         pantalla cambie de voz en vez de leer español con fonética
                                         inglesa. --}}
                                    <p class="rev__text rev__text--clamp" x-ref="orig" x-cloak
                                       x-show="original" lang="{{ $op->originalText->language }}"
                                       :class="abierto && 'rev__text--open'">{{ $op->originalText->text }}</p>
                                    {{-- ❗❗ **El aviso es OBLIGATORIO**: *«Make end users aware when a
                                         review has been translated from its original language»*. Y no
                                         es un borde — medido el 2026-09-10, las dos reseñas del parque
                                         están en español, así que en inglés y en francés Google
                                         devuelve las dos traducidas: dos de los tres idiomas del
                                         sitio. ⚠️ Va como TEXTO servido, no dentro del botón: sin
                                         JavaScript el botón no se pinta y el aviso tiene que salir
                                         igual. --}}
                                    <p class="rev__xlat">
                                        <span class="rev__xlat-note">{{ $op->originalText->languageName()
                                            ? __('landing.reviews.translated_from', ['lang' => $op->originalText->languageName()])
                                            : __('landing.reviews.translated') }}</span>
                                        <button type="button" class="rev__xlat-swap" x-cloak
                                                @click="original = !original; mide()"
                                                x-text="original ? @js(__('landing.reviews.see_translation')) : @js(__('landing.reviews.see_original'))"></button>
                                    </p>
                                @endif
                                <div class="rev__acts">
                                    {{-- ⚠️ `x-cloak` porque sin JavaScript no hay nada que abrir: el
                                         texto se queda recortado y ofrecer un botón muerto sería
                                         peor. El suelo sin JS es la salida a Google, que sí existe
                                         en el marcado servido. --}}
                                    <button type="button" class="rev__toggle" x-cloak x-show="cortado || abierto"
                                            @click="abierto = !abierto"
                                            x-text="abierto ? @js(__('landing.reviews.read_less')) : @js(__('landing.reviews.read_more'))"></button>
                                    @if ($op->url)
                                        {{-- ⚠️ **La salida a Google NO es opcional cuando la reseña es
                                             suya**: R3 exige acreditar al autor y su enlace es parte
                                             de esa acreditación. No desaparece al abrir el texto. --}}
                                        <a class="rev__more" href="{{ $op->url }}"
                                           target="_blank" rel="noopener noreferrer nofollow">
                                            <span>{{ __('landing.reviews.see_on_google') }}</span>
                                            <span class="arrow" aria-hidden="true">&rarr;</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach

                    {{-- Los controles solo existen si hay más de una: un punto solo son 48 px para
                         no decir nada — la misma regla que el pliegue de las fechas especiales
                         (`#487`).

                         ❗❗ **LAS FLECHAS SE RETIRAN** (`[DECIDIDO owner, 2026-09-10]`, `#494`): el
                         carril se recorre con los puntos.
                         ⚠️⚠️ **No cuesta accesibilidad, y por eso se pudo hacer**: cada punto ya es
                         un `<button>` con su nombre («Ver la opinión 2»), así que sigue habiendo
                         recorrido por teclado y por lector de pantalla, y el contenedor conserva su
                         `keydown` de flechas. *Retirar un control solo es gratis si lo que hacía lo
                         sigue haciendo otro.* --}}
                    @if ($socialProof->count() > 1)
                        <div class="rev__nav">
                            <div class="rev__dots">
                                @foreach ($socialProof as $k => $op)
                                    <button type="button" class="rev__dot"
                                            aria-label="{{ __('landing.reviews.go', ['n' => $k + 1]) }}"
                                            x-bind:aria-current="i === {{ $k }} ? 'true' : 'false'"
                                            @click="i = {{ $k }}"><span aria-hidden="true"></span></button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ❗❗ **LA POLÍTICA DE RESEÑAS DE GOOGLE** (`[DECIDIDO owner, 2026-09-10]`, `#494`).
                     Su documentación lo pide —*«Inform end users of Google's review policy when
                     displaying reviews and average rating»*— y la frase es **la suya**: *«Reviews
                     aren't verified by Google, but Google checks for and removes fake content when
                     it's identified»*.

                     ❗❗❗ **Y esto es lo que cierra la petición de «verificado por Google»**: eso no se
                     puede escribir, porque Google dice literalmente lo contrario de sus propias
                     reseñas. Lo que da veracidad no es afirmar una verificación que nadie hace: es
                     decir de dónde viene el dato y en qué condiciones.

                     ⚠️⚠️ **Sale si hay ALGO de Google en la sección, y la condición son las DOS
                     fuentes por separado**: la chapa y las opiniones no vienen de la misma —el caso
                     frecuente es el cruce— y con solo chapa la frase sigue haciendo falta, porque la
                     política nombra expresamente la valoración media.
                     ⚠️ **Nombra a Google dentro de la frase a propósito.** Con opiniones propias
                     debajo, una redacción impersonal («no verificamos las opiniones») las alcanzaría
                     también y diría algo falso del contenido del parque. --}}
                @if ($cifraDeGoogle || $opinionesDeGoogle)
                    <p class="rev-sec__policy">{{ __('landing.reviews.google_policy') }}</p>
                @endif
            </div>
        </section>
    @endif

    {{-- ══ 07 · VISÍTANOS · tres tarjetas ══════════════════════════════════════════════════════
         Carril de diseño Fase 2 · T2g (`DECISIONES #487`). Artboards `Visitanos PJP` **7b** (móvil)
         + `Escritorio PJP` **3b**.

         ── Lo que ya decía este bloque, y sigue vigente ──────────────────────────────────────── --}}
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
        {{-- ⚠️ **`.sec-head` y no `.rides__head`**: es la cabecera que el canvas cierra para las ocho
             secciones (`#479`). La vieja no tenía ni rótulo ni entradilla.
             ⚠️⚠️ **La entradilla se DERIVA del horario** y no es un texto fijo: el canvas escribe
             «Abrimos todos los días…», que es cierto en esta instalación y **falso en cualquiera que
             cierre un día**. El porqué, en `ScheduleDisplay::weeklyLede()`. --}}
        <div class="sec-head">
            <p class="sec-head__eyebrow">{{ __('landing.info.eyebrow') }}</p>
            <div class="sec-head__lockup">
                <x-site.ilu clave="slot-visitanos" class="sec-head__mancha" />
                <h2 class="sec-head__title">{{ __('landing.info.title') }}</h2>
            </div>
            @if ($scheduleLede)
                <p class="sec-head__lede">{{ $scheduleLede }}</p>
            @endif
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

    {{-- ══ 08 · DUDAS · el acordeón del sistema ════════════════════════════════════════════
         `DECISIONES #488` · carril de diseño Fase 2 · T2h. Artboards `Dudas PJP` 1a (móvil) y
         `Escritorio PJP` 5c (escritorio, aprobado el 8 sep).

         ▶ **La forma es el acordeón de `Componentes` 06 tal cual**, no un dibujo propio: tarjeta
         blanca con borde de Línea y radio 16, una fila por duda con su filete, y el «+» en un
         círculo de papel. `[DECIDIDO owner]` sobre las dos opciones del artboard: **1a**, las
         cuatro dentro del acordeón — 1b saca la de la reserva a la vista, cuesta 78 px MÁS
         teniendo una duda MENOS dentro, y **no tiene escritorio dibujado**, así que la superficie
         dependería del ancho de la ventana (la lección de `#485`).

         ❗❗ **TODAS CERRADAS al cargar, y NO es una divergencia con el sistema**: la tabla de
         reglas de `Componentes` 06 ya lo dice —«la primera abierta al cargar en una FAQ de
         PÁGINA, todas cerradas en la PORTADA»—, y su motivo es que con cuatro filas el índice
         entero es la respuesta a «¿está mi duda?», mientras una abierta empuja las demás fuera
         del pulgar. ⚠️ El producto arrancaba en `faqOpen: 0`.

         ❗❗❗ **CERO SALIDA al final, a propósito**: el cierre está pegado debajo con el teléfono
         y el pie con el correo. `doc/reglas.md`: preguntar vive en el cierre, así que Dudas no
         repite el canal. Ni CTA, ni enlace, ni relleno de acción.

         ❗❗❗ **CON EL PANEL VACÍO LA SECCIÓN ENTERA NO SE PINTA** —ni rótulo, ni titular, ni caja
         vacía—, que es regla dura del sistema para toda sección cuyo contenido pone el panel. Sin
         el `@if`, una instalación sin preguntas publicaba una cabecera que no presenta nada y una
         tarjeta de 0 filas. ⚠️ El `<x-site.faq-json-ld>` va DENTRO de la sección a propósito: si
         no hay preguntas tampoco hay `FAQPage` que declarar. --}}
    @if ($faqs->isNotEmpty())
        <section id="faq" class="section wrap">
            <div class="faq-sec">
                {{-- ⚠️ **`.sec-head` y no un `<h2>` suelto**: es la cabecera que el canvas cierra
                     para las ocho secciones (`#479`). La vieja no tenía ni rótulo ni entradilla, y
                     su titular decía «Dudas», que es el RÓTULO — el titular es una frase. --}}
                <div class="sec-head">
                    <p class="sec-head__eyebrow">{{ __('landing.faq.eyebrow') }}</p>
                    <div class="sec-head__lockup">
                        <x-site.ilu clave="slot-dudas" class="sec-head__mancha" />
                        <h2 class="sec-head__title">{{ __('landing.faq.title') }}</h2>
                    </div>
                    <p class="sec-head__lede">{{ __('landing.faq.lede') }}</p>
                </div>
                <div class="faq">
                    @foreach ($faqs as $i => $faq)
                        <div class="faq__item" :class="faqOpen==={{ $i }} && 'open'">
                            {{-- ⚠️ Sin `data-tap`: el pulsable mide **64 px de alto por el ancho
                                 entero**, 16 por encima del mínimo de 48, así que el pseudo
                                 centrado de `#264` no tiene nada que ampliar y solo añadiría una
                                 capa que se solapa con la de la fila de al lado. --}}
                            <button type="button" class="faq__q"
                                    @click="faqOpen = faqOpen==={{ $i }} ? -1 : {{ $i }}"
                                    :aria-expanded="faqOpen==={{ $i }} ? 'true' : 'false'"
                                    aria-controls="faq-answer-{{ $i }}">
                                <span class="faq__p">{{ $faq->tr('question') }}</span>
                                {{-- ⚠️⚠️ **El signo son DOS iconos del set, no uno girado.** El
                                     artboard dibuja «+» y «–»; girar el «+» 45° da una «×», que
                                     significa cerrar y no plegar. Y son iconos y no los caracteres
                                     de texto que el artboard escribe porque el set es un mecanismo
                                     del producto (`#475`) y su geometría no depende de la fuente
                                     que cargue. El de fuera decide cuál se ve, sin JavaScript. --}}
                                <span class="faq__sign" aria-hidden="true">
                                    <x-icons.plus class="faq__sign-i faq__sign-i--mas" :width="16" :height="16" />
                                    <x-icons.minus class="faq__sign-i faq__sign-i--menos" :width="16" :height="16" />
                                </span>
                            </button>
                            {{-- Dos envoltorios a propósito (auditoría M8, `#434`): el acordeón anima
                                 `grid-template-rows` 0fr → 1fr y no `max-height`, que animaba layout y era
                                 un TOPE de 240 px sobre respuestas que escribe el panel. El de fuera
                                 (`.faq__a-in`) recorta y NO lleva relleno; el de dentro lleva el aire.
                                 ⚠️ El artboard muestra y oculta el NODO; aquí se anima, que es lo mismo
                                 en «sin tope de alto» y además respeta `prefers-reduced-motion`. --}}
                            <div class="faq__a" id="faq-answer-{{ $i }}"><div class="faq__a-in"><p class="faq__a-p">{{ $faq->tr('answer') }}</p></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
            {{-- Datos estructurados FAQPage (invisible): Google puede mostrar estas preguntas como
                 desplegable enriquecido en el resultado. --}}
            <x-site.faq-json-ld :faqs="$faqs" />
        </section>
    @endif

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

    {{-- El pie ofrece, además del inventario, las secciones de la portada: las MISMAS del menú (`#522`).
         Y aquí va sobre PAPEL, el fondo de la página (`[DECIDIDO owner, 2026-09-11]`, `#523`): la
         portada termina en la tarjeta de TINTA del cierre, y un pie de tinta se fundía con ella. --}}
    <x-site.footer :sections="$menuSections" surface="paper" />

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
