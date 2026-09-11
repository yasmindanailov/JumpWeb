@props(['items'])

{{-- **EL MENÚ A PANTALLA COMPLETA** (`docs/specs/armazon-y-menu.md`, tanda 2c·1).

     Sustituye a los dos desplegables de la barra. Adopta la ESTRUCTURA del mockup del segundo
     cliente —lista plana numerada sobre superficie de tinta, revelada con un recorte circular
     desde la hamburguesa— y **ni un valor suyo**: color, canto, foco y sombra salen de los
     tokens de las tandas 1, 2a y la elevación.

     ⚠️⚠️ **Lo único del mockup que NO se copia es cómo se oculta.** Su menú cerrado se esconde
     con `clip-path` y `pointer-events:none`, sin `visibility`, sin `inert` y sin `aria-hidden`
     (medido: cero `inert` en el fichero), así que **sus enlaces siguen en el orden de
     tabulación estando cerrado**. Aquí se oculta con `visibility:hidden`, que es el mecanismo
     que el cajón de móvil ya tenía y cuyo porqué está escrito ahí: `aria-hidden` dejaba
     foco-fantasma. El recorte circular se queda para la ANIMACIÓN, no para el estado.

     ▶ **Es el segundo consumidor de la tanda 1**, después del hero: declara
     `data-surface="ink"` y con eso los siete tokens de superficie se re-escopan solos. No hay
     ni un color escrito a mano.

     ▶ **La lista es PLANA y la sigue mandando la BD** (`[DECIDIDO owner]`, spec §4.2): la
     compone `site.nav` a partir de las mismas fuentes que tenía la barra, con los servicios que
     el panel marca con «sale en el menú». Aquí solo se pinta.

     ⚠️ **El número es DECORACIÓN**: va `aria-hidden`, y el nombre accesible del enlace es su
     título. Si el número entrara en el nombre, un lector de pantalla anunciaría «cero uno
     zonas». --}}

<div class="menu" data-surface="ink"
     :class="menuOpen && 'menu--open'"
     @keydown.escape.window="menuOpen = false"
     @keydown="trapMenu($event)">

    {{-- Trama de puntos: la única textura que el sistema del cliente admite sobre tinta, y aquí
         se dibuja con un token de color, no con un literal. --}}
    <div class="grain" aria-hidden="true"></div>

    {{-- ⚠️ **Dos manchas de marca al fondo, y son HUECOS, no dibujos** (`#228`, del mockup). El
         producto pone el sitio, el tamaño, la opacidad y el COLOR —de los tokens de marca—; la
         FORMA la trae el paquete de instalación en `--deco-blob-a` / `--deco-blob-b`. Sin
         paquete no se pinta nada: la máscara por defecto es transparente, así que el hueco
         desaparece en vez de dejar dos rectángulos de color.
         ▶ Es la misma línea que el logotipo y el icono (`INSTALACION-CLIENTE.md` §4): este repo
         es el PRODUCTO y no lleva la marca de ningún cliente. --}}
    <div class="menu__blob menu__blob--a" aria-hidden="true"></div>
    <div class="menu__blob menu__blob--b" aria-hidden="true"></div>


    {{-- ⚠️ **La VISTA PREVIA de la columna lateral se alimenta de los MISMOS ítems** (`#228`), no
         de una segunda lista: si fueran dos fuentes, un destino nuevo aparecería en la lista y no
         en la vista, o al revés, y nadie se enteraría hasta verlo. `mira` es el índice del ítem
         señalado; arranca en 0 para que la tarjeta no nazca vacía. --}}

    {{-- **LA IMAGEN DE LA COLUMNA: un hueco POR INSTALACIÓN** (`#341`, `[DECIDIDO owner]`: «las que
         sean, que no esté vacío»).

         ⚠️⚠️ **Desde `#521` es la ÚNICA imagen de la columna, y no es un descuido.** Hasta entonces las
         ZONAS y los servicios del CMS traían foto propia, pero salieron del menú con la decisión del
         owner —los destinos son el inventario de páginas y las secciones de la portada—, y **ninguno
         de ésos tiene una imagen que sea SUYA en el modelo**: inventarles una asociación sería quemar
         el catálogo de un cliente en el producto. Por eso aquí ya no hay rama «foto propia»: una rama
         que nunca se ejecuta es una mentira esperando a que alguien la crea.

         ▶ Es el CUARTO hueco por instalación, con las mismas tres piezas que el logotipo, el icono y el
         kit (`INSTALACION-CLIENTE.md` §4): fichero del cliente, gitignorado y excluido del `--delete`
         del despliegue. **Sin fichero no se pinta nada**: el suelo del producto es el fondo rayado,
         que es lo que el mockup usa donde aún no hay foto.

         ⚠️ La marca de tiempo hace de cache-buster, igual que en `site.brand`: sin ella, sustituir
         la foto en el servidor no se vería hasta que caducara la caché del navegador. --}}
    @php($respaldoMenu = @filemtime(public_path('img/client-menu.webp')))
    @php($respaldo = $respaldoMenu ? asset('img/client-menu.webp').'?v='.$respaldoMenu : null)

    {{-- ⚠️ La imagen se resuelve AQUÍ y no en la plantilla: así «qué imagen le toca a este destino»
         se decide en UN sitio, y el marcado sigue preguntando por una sola cosa (`vistas[mira].img`).
         Un `||` dentro del `:src` habría dejado la regla repartida entre PHP y Alpine. --}}
    @php($vistas = collect($items)->map(fn ($it) => [
        't' => $it['t'],
        's' => $it['s'] ?? '',
        'img' => $respaldo,
    ])->values()->all())

    <div class="menu__inner" role="dialog" aria-modal="true"
         aria-label="{{ __('landing.nav.menu_label') }}" x-ref="menuPanel"
         x-data="{ mira: 0, vistas: @js($vistas) }">

        {{-- ── LAS DOS COLUMNAS (`#228`, del mockup) ────────────────────────────────────────────
             La lista a la izquierda y una columna de 320 px a la derecha. **No es decoración: es
             lo que hace legible la lista.** Con el menú a todo el ancho, un destino corto como
             «Empresas» deja su subtítulo flotando a 900 px del título y la fila se lee como dos
             cosas sueltas. Acotando la lista a su columna, número, título y subtítulo vuelven a
             ser una unidad.
             ⚠️ La columna **desaparece por debajo de 1100 px** —el mismo corte que el racimo—, y
             ahí la lista recupera todo el ancho, que es lo que hace el mockup. --}}
        {{-- ❗❗❗ **LOS DOS GRUPOS DEL MENÚ** (`DECISIONES #477`, carril de diseño Fase 2 · T2a,
             `[DECIDIDO owner]`). El menú separa lo que te lleva **DENTRO de la portada** de lo que
             te lleva a **otra página**, que es lo que el marco aprobado del canvas pide y lo que el
             visitante necesita saber ANTES de pulsar: bajar por la misma página y cambiar de página
             no son el mismo gesto.
             ▶ **Sustituye a la lista PLANA de `#211`**, que era decisión del owner y él mismo
             reabrió con el canvas delante.

             ⚠️⚠️ **El grupo se DEDUCE de la URL, no es un campo nuevo**, y por eso la lista la sigue
             mandando la BD sin migración ni panel: un destino es «sección» si apunta a la portada
             con ancla, y «página» en cualquier otro caso. Un dato que se puede derivar de un hecho
             no se guarda: guardarlo abre la puerta a que los dos digan cosas distintas.

             ⚠️⚠️ **Y con esto se resuelven solas las DOS CRUCES que el canvas dejaba pendientes**
             —Tarifas y Cumpleaños son sección Y página a la vez—: el menú ya enlazaba a su
             **página** (`route('precios')`, `route('cumpleanos')`), no a su ancla, así que caen en
             «página» sin que nadie tenga que elegir. *La pregunta no se contesta: se disuelve al
             mirar a qué enlaza de verdad.*

             ⚠️ **El índice `$i` sigue siendo el GLOBAL**, no el de dentro de su grupo: es la clave
             con la que la vista previa de al lado sabe qué destino está mirando (`vistas[mira]`).
             Renumerar por grupo haría que señalar el segundo destino de «páginas» enseñara la foto
             del segundo de «secciones», y no fallaría nada. --}}
        {{-- ⚠️⚠️ **TRES trampas de Blade pagadas aquí, y la tercera fue este mismo comentario.**
             (1) Esta plantilla ya abre arriba un bloque PHP en su forma con paréntesis, así que un
             cierre de bloque **cierra AQUÉL** y no el propio: salió «Undefined variable $grupos» en
             53 casos. (2) La forma con paréntesis **no admite una closure multilínea**: el
             compilador corta donde no debe y la plantilla deja de publicar sus destinos.
             (3) ⚠️⚠️ **Y citar las directivas EN PROSA dentro de un comentario las COMPILA**, así que
             la primera versión de este aviso abrió un bloque que se tragó media plantilla y dejó el
             `x-data` sin compilar. Es la trampa de `#307`, que ya avisaba de que *«volvió a caer en
             él el comentario escrito para advertirlo»* — tercera vez en el repo. **Aquí no se
             escribe ninguna directiva con su arroba: se describen con palabras.**
             ▶ Por eso el grupo se calcula **donde se componen los ítems** (`nav.blade.php`) y aquí
             solo se agrupa por una clave, que es una expresión de una línea. El segundo argumento
             preserva las claves: el índice global es lo que la vista previa necesita para saber qué
             se está mirando. --}}
        @php($grupos = collect($items)->groupBy('grupo', true))

        <div class="menu__cols">
            <div class="menu__col-list">
                {{-- El ORDEN es fijo y del sistema —primero lo de esta página, luego lo que te saca
                     de ella—, no el que devuelva la agrupación: con `groupBy` el orden lo decide
                     cuál aparezca antes en la lista, y eso cambia con la BD. --}}
                @foreach (['section', 'page'] as $clave)
                    @php($delGrupo = $grupos->get($clave, collect())->all())
                    @continue (empty($delGrupo))
                    {{-- El rótulo es Etiqueta del sistema: mono, mayúsculas, `.16em`. --}}
                    <p class="menu__group" id="menu-group-{{ $clave }}">{{ __('landing.nav.menu_group.'.$clave) }}</p>
                    {{-- ⚠️ **Sin modificador por grupo, y es deliberado**: una clase COMPUESTA
                         (`menu__list--` más una variable) no la ve ningún inventario de CSS —la
                         trampa de `#287`— y aquí no aportaría nada, porque los dos grupos se pintan
                         igual: lo que los distingue es su rótulo y su flecha. --}}
                    <ul class="menu__list" aria-labelledby="menu-group-{{ $clave }}">
                        @foreach ($delGrupo as $i => $item)
                            <li class="menu__item" style="--i: {{ $i }}">
                                {{-- ⚠️ `mouseenter` **y** `focus`: la vista previa tiene que seguir
                                     también a quien navega con teclado, o la columna se queda
                                     contando algo que no es lo que el usuario está mirando. --}}
                                {{-- ⚠️ La página en la que estás SALE en la lista y va marcada (`#521`):
                                     el grupo se llama «Páginas» y no «Otras páginas» justamente para
                                     que eso sea cierto. Quitarla dejaría la lista cambiando de forma
                                     de una página a otra. --}}
                                <a href="{{ $item['url'] }}" @click="menuOpen = false"
                                   @if (! empty($item['current'])) aria-current="page" @endif
                                   @mouseenter="mira = {{ $i }}" @focus="mira = {{ $i }}">
                                    {{-- Los números «01…» de cada destino se RETIRARON (`[DECIDIDO owner, 2026-09-01]`,
                                         lanzamiento): eran decoración y el owner los quiso fuera. --}}
                                    <span class="menu__t">{{ $item['t'] }}</span>
                                    @if (! empty($item['s']))
                                        <span class="menu__s">{{ $item['s'] }}</span>
                                    @endif
                                    {{-- ⚠️ La flecha DICE el grupo: hacia abajo si te lleva dentro de
                                         esta misma página, hacia la derecha si te saca de ella. Es la
                                         misma distinción del rótulo, dicha en el sitio donde el ojo ya
                                         está mirando al pulsar. --}}
                                    <span class="menu__arrow" aria-hidden="true">
                                        @if ($clave === 'section')
                                            <x-icons.chevron-down :width="20" :height="20" />
                                        @else
                                            <x-icons.arrow-right :width="20" :height="20" />
                                        @endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </div>

            <aside class="menu__aside">
                {{-- ⚠️⚠️ **`aria-hidden` y no es un descuido**: esta tarjeta REPITE el título y el
                     subtítulo del destino que ya está en la lista, a la que el lector de pantalla
                     acaba de llegar. Anunciarla otra vez sería leer cada destino dos veces. Es
                     un eco VISUAL de lo que el ratón está señalando, y para eso no hace falta
                     texto accesible propio. --}}
                <div class="menu__preview" aria-hidden="true">
                    {{-- La foto sale del CMS y **solo la tienen los servicios**; el resto cae al
                         fondo rayado, que es lo que el mockup usa donde aún no hay foto. --}}
                    <template x-if="vistas[mira] && vistas[mira].img">
                        {{-- ⚠️ `aria-hidden` ADEMÁS del `alt=""`: la tarjeta entera ya está oculta al lector, pero
                             `SeoTest` exige que cada imagen diga por sí misma si es contenido o decoración.
                             Tiene razón — un `alt=""` a secas no distingue «decorativa» de «se me olvidó». --}}
                        <img class="menu__preview-img" :src="vistas[mira].img" alt="" aria-hidden="true">
                    </template>
                    <div class="menu__preview-body">
                        {{-- ⚠️ El sombrerete es el NÚMERO del destino, no una etiqueta inventada:
                             el mockup pone ahí una etiqueta por sección que nosotros no tenemos en
                             ninguna tabla, y rellenarla con un texto fijo sería fingir un dato. --}}
                        <span class="menu__preview-n" x-text="String(mira + 1).padStart(2, '0')"></span>
                        <span class="menu__preview-t" x-text="vistas[mira] ? vistas[mira].t : ''"></span>
                        <span class="menu__preview-s" x-text="vistas[mira] ? vistas[mira].s : ''"></span>
                    </div>
                </div>

                {{-- Los DATOS de siempre: si estamos abiertos, el teléfono y cómo llegar. Aquí sí
                     hay enlaces de verdad, así que este bloque NO va oculto al lector. --}}
                <div class="menu__facts">
                    @if (! empty($heroStatus))
                        <p class="menu__fact menu__fact--now">
                            <span class="menu__dot" aria-hidden="true"></span>
                            {{ $heroStatus['day'] }} · {{ $heroStatus['status'] }}
                        </p>
                    @endif
                    @if (! empty($site['has_phone']))
                        <a class="menu__fact" href="tel:{{ $site['phone_tel'] }}">
                            {{-- ⚠️ Era `devices` —una pantalla y un portátil— junto a un NÚMERO DE
                                 TELÉFONO: el dibujo no decía lo que el enlace hace. El set del
                                 artboard trae `ui/movil` y es exactamente esto (`#257`). --}}
                            <x-icons.phone :width="18" :height="18" />{{ $site['phone'] }}
                        </a>
                    @endif
                    <a class="menu__fact" href="{{ url('/#info') }}" @click="menuOpen = false">
                        <x-icons.pin :width="18" :height="18" />{{ $site['city'] ?: __('landing.nav.park_items.info.t') }}
                    </a>
                </div>
            </aside>
        </div>

        {{-- Fila de cápsulas secundarias (`[DECIDIDO owner]`, spec §5): acceder + idioma + redes.
             ⚠️ El idioma sube aquí ADEMÁS de quedarse en el pie: con la barra retirada, quien
             está a mitad de página no debería tener que bajar hasta el final para cambiarlo.
             ⚠️ Y en móvil «Acceder» dejará de ser secundario: cuando el racimo pierda el botón
             de registro (tanda 2c·4), ésta es la única puerta de la cuenta. --}}
        <div class="menu__foot">
            <ul class="menu__chips">
                <li>
                    @guest
                        {{-- El `href` no es decorativo: `/login` es una PUERTA que sirve la home y
                             abre el cajón en la zona de acceso, así que el clic central, «abrir en
                             pestaña nueva» y un navegador sin JS acaban en la misma pantalla por el
                             camino largo. Mismo trato que el CTA de alta de la barra. --}}
                        <a href="{{ route('login') }}" class="menu__chip"
                           x-on:click="menuOpen = false; $store.purchase.openAccount($event, 'login')">
                            {{ __('account.nav.login') }}
                        </a>
                    @else
                        @if ($site['sales_online'])
                        <button type="button" class="menu__chip"
                                @click="menuOpen = false; $store.purchase.open()">
                            {{ __('landing.footer.account_link') }}
                        </button>
                        @else
                        {{-- Compra online cerrada: el chip lleva a la cuenta (login), no al catálogo. --}}
                        <a href="{{ route('login') }}" class="menu__chip"
                           x-on:click="menuOpen = false; $store.purchase.openAccount($event, 'login')">
                            {{ __('landing.footer.account_link') }}
                        </a>
                        @endif
                    @endguest
                </li>

                {{-- Selector de idioma: MISMO patrón que el del pie (`.lang-dd`), sin la variante
                     `--up` porque aquí se abre hacia abajo. Los idiomas salen de `SiteLocales`,
                     que es la fuente única — el pie tenía su propia copia de la lista y **dos
                     listas de idiomas es cómo se acaba ofreciendo uno que la otra no reconoce**
                     (lo dice el docblock de esa clase, y aquí se respeta). --}}
                {{-- ⚠️ **`:up` aquí también** (`#253`): esta cápsula vive en la fila INFERIOR del
                     menú, así que un panel que se abra hacia abajo se sale de la pantalla. Medido
                     con el ojo del owner y luego con la sonda: **38 px fuera a 1920×1080 y 54 a
                     1280×900**. Es el mismo motivo por el que el pie lo llevaba, y ahora que el
                     selector vive solo aquí es aquí donde hace falta. --}}
                <li>
                    <x-site.lang-switch trigger="menu__chip" :up="true" />
                </li>

                {{-- Redes: solo si la instalación las tiene configuradas. Las URLs llegan ya
                     saneadas por `safeExternalUrl` desde el composer global (`SEC-07`): el marcado
                     no puede ser el sitio donde se confía en una URL editable. El `?? '#'` del
                     composer significa «no configurada», así que ese valor NO pinta cápsula. --}}
                @foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok'] as $key => $label)
                    @if (! empty($site[$key]) && $site[$key] !== '#')
                        <li>
                            <a href="{{ $site[$key] }}" class="menu__chip" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>

            {{-- **El eslogan a rotulador** (`[DECIDIDO owner, 2026-08-27]`: «la idea es 1:1 al
                 mockup»). Es el mismo texto y la misma fuente que el del hero, y sale de la MISMA
                 clave: repetirlo en otra clave sería dos copys que se separan solos.

                 ⚠️ **Su auditoría lo marcaba como repetido** (`T-02`: «Permanent Marker aparece dos
                 veces — hero y pie del menú; máx. una por página»). No lo incumple, y el motivo es
                 geométrico: **el menú es `inset: 0` y tapa el hero entero al abrirse**, así que los
                 dos nunca están en pantalla a la vez. La norma habla de por PANTALLA. --}}
            <p class="menu__slogan">{{ __('landing.hero.kicker') }}</p>
        </div>
    </div>
</div>
