@php
    // Estructura del nav (reorganización 2026-05-27): dos desplegables temáticos
    // ("El parque" / "Servicios") + dos atajos directos ("Cumpleaños" / "Entradas").
    // Las URLs apuntan a secciones de la home (#zones, #rides, #info) o a páginas
    // dedicadas (/cumpleanos, /precios, /servicios#...). Los items se traducen a
    // ES/EN/FR via `landing.nav.{park_items,services_items}`.
    // Cada item lleva título (`t`) y descripción corta (`s`) — `s` se renderiza
    // debajo del título en el panel del dropdown para dar contexto antes del clic.
    $parkItems = [
        ['t' => __('landing.nav.park_items.kids.t'), 's' => __('landing.nav.park_items.kids.s'), 'url' => url('/#zones')],
        ['t' => __('landing.nav.park_items.jump.t'), 's' => __('landing.nav.park_items.jump.s'), 'url' => url('/#zones')],
        ['t' => __('landing.nav.park_items.rides.t'), 's' => __('landing.nav.park_items.rides.s'), 'url' => url('/#rides')],
        ['t' => __('landing.nav.park_items.info.t'), 's' => __('landing.nav.park_items.info.s'), 'url' => url('/#info')],
    ];
    // Selector «Servicios»: STATIC + DATA-DRIVEN (#256, modelo A). «Cumpleaños» (→ /cumpleanos) y
    // «Otros eventos» (→ /servicios#eventos) quedan FIJOS; los del medio salen de `LandingService`
    // (show_in_nav) memoizado en el composer (`$navServices`), enlazando a /servicios#slug.
    $servicesItems = array_merge(
        [['t' => __('landing.nav.services_items.birthdays.t'), 's' => __('landing.nav.services_items.birthdays.s'), 'url' => route('cumpleanos')]],
        collect($navServices ?? [])->map(fn ($s) => [
            't' => $s->tr('title'),
            's' => $s->tr('nav_subtitle'),
            'url' => route('servicios').'#'.$s->slug,
        ])->all(),
        [['t' => __('landing.nav.services_items.events.t'), 's' => __('landing.nav.services_items.events.s'), 'url' => route('servicios').'#eventos']],
    );
    $simpleLinks = [
        ['t' => __('landing.nav.events'), 'url' => route('cumpleanos')],   // Cumpleaños (destacado además del item dentro de Servicios)
        ['t' => __('landing.nav.tickets'), 'url' => route('precios')],     // Entradas
    ];

    // **La lista PLANA del menú a pantalla completa** (`specs/armazon-y-menu.md` §4.2,
    // `[DECIDIDO owner]`). Se compone de las MISMAS fuentes que tenía la barra —atajos, «El
    // parque» y «Servicios», con los del CMS por `show_in_nav`— y en el mismo orden que ya
    // usaba el cajón de móvil: primero lo más buscado, luego el parque, luego los servicios.
    //
    // ⚠️ **Se deduplica por el PAR (título, url), no por url**, y la diferencia importa:
    // «Cumpleaños» está a la vez como atajo y como primer servicio —mismo título y misma
    // URL— y en una lista plana sería el mismo destino dos veces; pero «Zona Kids» y «Zona
    // Jump» **comparten ancla** (`/#zones`) y son dos destinos distintos. Deduplicar por URL
    // se habría comido uno de los dos sin avisar.
    $menuItems = [];
    foreach (array_merge($simpleLinks, $parkItems, $servicesItems) as $item) {
        $menuItems[$item['t'].'|'.$item['url']] ??= $item;
    }
    $menuItems = array_values($menuItems);
@endphp

{{-- **El salto al contenido, y va aquí a propósito** (`specs/armazon-y-menu.md` §1.8): existía
     en 1 de las 12 vistas —la home— y las otras once repiten el mismo bloque de navegación sin
     ofrecer forma de saltarlo. Al vivir en el componente, entra en las doce y **no se puede
     olvidar en la siguiente página pública que se cree**. Tiene que ser el PRIMER focusable, así
     que va antes que el racimo. --}}
<a href="#main" class="skip-link">{{ __('landing.nav.skip') }}</a>

{{-- Con el menú abierto el armazón sube POR ENCIMA de él (la hamburguesa es la forma de cerrarlo)
     y declara superficie de TINTA, que es lo que hace legible su contenido sobre el menú sin
     escribir un solo color a mano: los siete tokens de superficie se re-escopan solos. --}}
<nav class="nav"
     :class="[menuOpen && 'nav--over', navHidden && 'nav--hidden']"
     x-bind:data-surface="menuOpen ? 'ink' : false">
    <div class="nav__left">
        <a href="{{ url('/') }}" class="nav__brand">
            <x-site.brand :name="$site['name'] ?? config('app.name')" />
        </a>
    </div>

    <div class="nav__cta">
        {{-- Jerarquía de CTAs del header (mockup `design_mockup/jerarquia-ctas.html`):
             • Ghost: Crear cuenta — peso bajo (2/5). Findable, no protagonista. Solo guest.
             • Filled medio: Comprar entradas — peso alto (4/5). Anclaje "desde X €" data-driven.
             Sustituye al icono `nav__scan` y al botón `nav__reserve` previos: los handlers
             (`$store.purchase.open()` y —desde el 2026-08-23— `$store.purchase.openAccount()`);
             solo cambia el tratamiento visual y el copy. --}}
        {{-- ── EL PAR: una mitad expandida y la otra colapsada a solo icono (2c·7) ────────────────
             `[DECIDIDO owner, 2026-08-28]`: **como el mockup**. Su CTA fijo no son dos botones
             sueltos sino un PAR: uno ancho y el otro reducido a su icono. El primer clic en el
             colapsado **lo expande y colapsa al otro**; el segundo **actúa**. Y mientras nadie lo
             ha tocado, el colapsado **invita** —asoma y late un aro— para que se descubra.

             ▶ **Comprar arranca expandido, y eso ES la jerarquía**: comprar cuesta un gesto y la
             cuenta dos. Es la misma regla que la barra de móvil de `#205`, y ahora **comparten
             estado de verdad** (`$store.ctaPair`): son la misma decisión y no puede haber dos.

             ⚠️⚠️ **UN BOTÓN QUE CAMBIA DE SIGNIFICADO AL PULSARLO SE PULSA POR ERROR**, así que el
             nombre accesible **dice qué hace AHORA**: colapsado se llama «cambiar a…», no «mi
             cuenta». Sin esto, un lector de pantalla anuncia dos botones que dicen lo mismo y hacen
             cosas distintas — y el segundo no lleva a ninguna parte.
             ⚠️ **Y sin JavaScript el doble paso no existe, a propósito**: las tres ramas son
             `<a href>` de verdad —`/registro`, la URL del parque y `/mi-cuenta` son PUERTAS— así
             que sin JS cada una navega a su destino de una sola pulsación, y el nombre accesible
             servido es el de ACTUAR, que es lo que hacen. Alpine lo sustituye por el de «cambiar»
             solo en la que quede colapsada.
             ⚠️ El aspecto —qué mitad es ancha— lo decide el CSS con DOS clases. El JS no reparte
             anchos: publica estado. Misma regla que el hero (`#195`) y el recorte del menú (`#201`). --}}
        <div class="nav__pair"
             :class="[$store.ctaPair.mode === 'account' && 'nav__pair--account',
                      ! $store.ctaPair.touched && 'nav__pair--invita']">
        @guest
            {{-- #216: «Registro» del parque. Si hay URL externa configurada (Ajustes → Registro), el
                 botón lleva a ese sistema en una pestaña nueva, con etiqueta + subtítulo por idioma.
                 Si NO hay URL, cae al comportamiento actual: abre el modal de registro interno. --}}
            @if (! empty($site['registration_url']))
                <span class="nav__alt">
                    <span class="nav__alt-ring" aria-hidden="true"></span>
                    <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener" class="cta-ghost cta-ghost--stack nav-cta-ghost"
                       aria-label="{{ $site['registration_label'] }}"
                       :aria-label="$store.ctaPair.mode === 'account' ? @js($site['registration_label']) : @js(__('landing.nav.cta_switch_signup'))"
                       @click="if ($store.ctaPair.mode !== 'account') { $event.preventDefault(); $store.ctaPair.show('account'); }">
                        <span class="cta-ghost__ico"><x-icons.clipboard-check /></span>
                        <span class="cta-ghost__body">
                            <span class="cta-ghost__t">{{ $site['registration_label'] }}</span>
                            @if (! empty($site['registration_subtitle']))
                                <span class="cta-ghost__s">{{ $site['registration_subtitle'] }}</span>
                            @endif
                        </span>
                    </a>
                </span>
            @else
                {{-- ⚠️⚠️ **Pasa de `<button>` a `<a href>` el 2026-08-23** (`specs/auth-en-cajon.md`
                     §4.5), y el `href` no es decorativo: `/registro` es una PUERTA que sirve la home
                     y abre el cajón en la zona de alta, así que el clic central, «abrir en pestaña
                     nueva» y un navegador sin JS acaban en la misma pantalla por el camino largo. Con
                     el `<button>` de antes esos tres casos no hacían nada.
                     ⚠️ La clase no cambia y el CSS ya la soporta sobre un ancla: la rama de al lado
                     —el registro externo del parque— lleva usándola así desde `#216`. --}}
                {{-- ⚠️ **El glifo sigue al DESTINO, no a la posición del botón** (tanda 2c·3): esta
                     rama CREA UNA CUENTA, así que lleva la pareja de `user`. La rama de arriba —el
                     trámite de registro de acceso del parque, una URL externa— es un formulario y
                     conserva el portapapeles. Con un solo glifo para las dos, el trámite quedaría
                     etiquetado como si fuera un alta de cuenta. --}}
                <span class="nav__alt">
                    <span class="nav__alt-ring" aria-hidden="true"></span>
                    <a href="{{ route('registro') }}" class="cta-ghost nav-cta-ghost"
                       aria-label="{{ __('landing.nav.reserve') }}"
                       :aria-label="$store.ctaPair.mode === 'account' ? @js(__('landing.nav.reserve')) : @js(__('landing.nav.cta_switch_signup'))"
                       x-on:click.prevent="$store.ctaPair.mode === 'account' ? $store.purchase.openAccount($event, 'register') : $store.ctaPair.show('account')">
                        <span class="cta-ghost__ico"><x-icons.user-plus /></span>
                        <span class="cta-ghost__body">
                            <span class="cta-ghost__t">{{ __('landing.nav.reserve') }}</span>
                        </span>
                    </a>
                </span>
            @endif
        @else
            {{-- Cliente con sesión (#221): la cuenta vive en el bloque del sidebar de compra. El nav
                 conserva un «chip» «Hola, nombre» + icono que abre ese panel; un puntito de acento
                 avisa si hay un formulario de reserva pendiente (#217). El saludo se mantiene también
                 en móvil (compactado). El texto visible «Hola, nombre» ES el nombre accesible del
                 botón (sin aria-label que lo tape, WCAG 2.5.3); el aviso se anuncia con texto sr-only. --}}
            @php($acct = app(\App\Domain\Identity\Services\CustomerAccountContext::class)->for(auth()->user()))
            {{-- #231 p7: en móvil el chip queda solo con el icono (el saludo se oculta por CSS). El
                 nombre accesible lo da el `aria-label` (incluye el aviso de formulario pendiente);
                 el saludo visible es un PREFIJO del aria-label → cumple WCAG 2.5.3 (label in name). --}}
            @php($acctLabel = __('account.nav.hello', ['name' => $acct['firstName']]).($acct['hasPendingForm'] ? ' · '.__('account.nav.pending_form') : ''))
            {{-- ⚠️⚠️ **El saludo visible SE RETIRA** (tanda 2c·3, `[DECIDIDO owner]`): sin barra
                 detrás, el racimo son piezas del mismo tamaño y un chip con texto variable —«Hola,
                 Marta» frente a «Hola, Wilhelmina»— cambia de ancho con cada visitante. El nombre
                 accesible **sigue estando en TEXTO** en el `aria-label`, que es lo que no se puede
                 perder: era el nombre accesible del botón y lo sigue siendo.
                 ⚠️ Y el punto de aviso pasa a **Amarillo Aviso**: es el mismo color con el que el
                 panel pinta «tienes un formulario pendiente», así que cabecera y panel dicen lo
                 mismo con el mismo color. Sin aviso NO HAY PUNTO — la ausencia ya significa
                 reposo y no gasta un color de estado (`armazon-y-menu.md` §4.3). --}}
            {{-- ⚠️⚠️ **Pasa de `<button>` a `<a href>` en la 2c·7, y no es cosmético**: era la única
                 de las tres ramas del par sin suelo sin JavaScript —no hacía NADA—. `/mi-cuenta` es
                 una PUERTA que sirve la home y abre el cajón en la cuenta, así que el clic central,
                 «abrir en pestaña nueva» y un navegador sin JS acaban en la misma pantalla por el
                 camino largo. Mismo trato que las otras dos ramas desde `#122`/`#216`.
                 ⚠️ **El rótulo visible es «Mi cuenta», FIJO, y el saludo se queda en el nombre
                 accesible.** `#204` retiró el saludo visible porque «Hola, Marta» y «Hola,
                 Wilhelmina» cambian el ancho con cada visitante; con un rótulo fijo eso no pasa, y
                 el nombre accesible sigue diciendo de quién es la cuenta. El visible es PREFIJO del
                 accesible, que es lo que exige «label in name» (WCAG 2.5.3). --}}
            <span class="nav__alt">
                <span class="nav__alt-ring" aria-hidden="true"></span>
                <a href="{{ route('account') }}" class="cta-ghost nav-cta-ghost nav__acct"
                   aria-label="{{ __('landing.footer.account_link') }} · {{ $acctLabel }}"
                   :aria-label="$store.ctaPair.mode === 'account' ? @js(__('landing.footer.account_link').' · '.$acctLabel) : @js(__('landing.nav.cta_switch_account'))"
                   x-on:click.prevent="$store.ctaPair.mode === 'account' ? $store.purchase.open() : $store.ctaPair.show('account')">
                    <span class="cta-ghost__ico nav__acct-icon">
                        <x-icons.user />
                        @if ($acct['hasPendingForm'])
                            <span class="nav__acct-dot"></span>
                        @endif
                    </span>
                    <span class="cta-ghost__body">
                        <span class="cta-ghost__t">{{ __('landing.footer.account_link') }}</span>
                    </span>
                </a>
            </span>
        @endguest

        {{-- `x-data="navCtaReveal"` (ver app.js): si la página marca `data-has-hero`
             en el body, este botón empieza oculto y aparece (animado) cuando el
             CTA "prime" del hero sale del viewport. En páginas sin hero queda
             visible por defecto (sin observer ni CSS oculto).
             Estructura idéntica al `.cta-med` del mockup `design_mockup/jerarquia-ctas.html`:
             icono tear-off + body (título + descripción con anclaje "desde X €") + flecha.
             El subtítulo solo se renderiza cuando hay catálogo (data-driven). --}}
        {{-- ⚠️ Mitad A del par. Pasa de `<button>` a `<a href>` por el mismo motivo que la otra
             mitad: `/entradas` es una PUERTA, así que sin JavaScript esto lleva a comprar de una
             sola pulsación en vez de no hacer nada. Y su nombre accesible también dice qué hace
             AHORA: colapsada se llama «cambiar a reservar entradas». --}}
        <a href="{{ route('entradas') }}" class="cta-med nav-cta-med" x-data="navCtaReveal"
           aria-label="{{ __('landing.nav.reserve_tickets_aria') }}"
           :aria-label="$store.ctaPair.mode === 'buy' ? @js(__('landing.nav.reserve_tickets_aria')) : @js(__('landing.nav.cta_switch_buy'))"
           @click.prevent="$store.ctaPair.mode === 'buy' ? $store.purchase.open() : $store.ctaPair.show('buy')">
            <span class="cta-med__ico"><x-icons.ic-e2 :width="28" :height="18" /></span>
            <span class="cta-med__body">
                {{-- Dos labels: el desktop muestra el copy completo + subtítulo de precio,
                     el móvil cae a "Reservar" (1 palabra) porque el espacio del nav no
                     permite el largo. CSS @media controla cuál se ve. --}}
                <span class="cta-med__t cta-med__t--desktop">{{ __('landing.nav.cta_buy') }}</span>
                <span class="cta-med__t cta-med__t--mobile">{{ __('landing.nav.cta_book') }}</span>
                @if (! empty($ctaMinPriceLabel))
                    <span class="cta-med__s">{{ __('landing.nav.cta_buy_from', ['amount' => $ctaMinPriceLabel]) }}</span>
                @endif
            </span>
            {{-- ⚠️ Aquí había una flecha `→`. **El CTA fijo del mockup no la lleva** y se retira con
                 la alineación de `#213`: el botón ya dice a dónde va con su rótulo y su icono, y una
                 flecha de más en el elemento más repetido de la web es ruido en las 12 vistas. --}}
        </a>
        </div>{{-- /.nav__pair --}}

        {{-- **La hamburguesa ABRE Y CIERRA, y enseña una X cuando está abierta.**

             ⚠️⚠️ Hasta aquí hacía `menuOpen = true` a secas, y eso era un defecto de verdad: el menú
             es `inset: 0` y tapa la página entera, así que **con el ratón no había forma de salir**
             — solo `Escape` o pulsar un destino. Lo cazó el owner mirando, no ninguna guarda.
             ▶ Se resuelve como en su mockup: **el mismo botón alterna** (`alternaMenu`) y su dibujo
             pasa a X. Nosotros lo hacemos con el SET de iconos en vez de rotando dos rayas, porque
             el dibujo es uno de los tres mecanismos del tema: `x-icons.close` ya existe y una
             instalación puede sustituirlo.

             ⚠️ El `aria-label` **también alterna**: el del mockup dice «Abrir menú» siempre, incluso
             estando abierto, y eso es lo único que no se copia. Y gana `aria-expanded`, que es lo
             que convierte el botón en un revelador para quien no ve el dibujo. --}}
        <button class="nav__burger" x-ref="burger"
                @click="menuOpen = ! menuOpen"
                :aria-expanded="menuOpen ? 'true' : 'false'"
                :aria-label="menuOpen ? @js(__('landing.nav.menu_close')) : @js(__('landing.nav.menu_open'))"
                aria-label="{{ __('landing.nav.menu_open') }}">
            <x-icons.menu class="nav__burger-ico nav__burger-ico--bars" />
            <x-icons.close class="nav__burger-ico nav__burger-ico--x" />
        </button>
    </div>
</nav>

<x-site.menu :items="$menuItems" />

{{-- Menú móvil (drawer) = overlay accesible (Lote 10): Escape cierra, `trapMenu` atrapa el
     foco (Tab cíclico) dentro del panel, y el componente `landing` bloquea el scroll del fondo +
     pasa/devuelve el foco al abrir/cerrar. Fuera de tab-order y AT cuando cerrado vía CSS
     (`visibility:hidden`), no por `aria-hidden` (que dejaba foco-fantasma). --}}
<div class="mob-menu" :class="menuOpen && 'mob-menu--open'" :aria-hidden="!menuOpen"
     @keydown.escape.window="menuOpen = false" @keydown="trapMenu($event)">
    <div class="mob-menu__backdrop" @click="menuOpen = false"></div>
    <aside class="mob-menu__panel" role="dialog" aria-modal="true" x-ref="mobPanel">
        <div class="mob-menu__head">
            <span class="nav__brand">
                <x-site.brand :name="$site['name'] ?? config('app.name')" />
            </span>
            <button class="mob-menu__close" @click="menuOpen = false" aria-label="{{ __('landing.nav.menu_close') }}">
                <x-icons.close />
            </button>
        </div>

        {{-- Atajos directos arriba — Cumpleaños y Entradas son los destinos más buscados. --}}
        <ul class="mob-menu__primary">
            @foreach ($simpleLinks as $i => $link)
                <li style="--i: {{ $i }}">
                    <a href="{{ $link['url'] }}" @click="menuOpen = false">{{ $link['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
                </li>
            @endforeach
        </ul>

        {{-- En móvil aplanamos los desplegables como secciones — más rápido para el pulgar. --}}
        <div class="mob-menu__section">
            <h4>{{ __('landing.nav.park') }}</h4>
            <ul class="mob-menu__secondary">
                @foreach ($parkItems as $i => $item)
                    <li style="--i: {{ $i + 2 }}">
                        <a href="{{ $item['url'] }}" @click="menuOpen = false">{{ $item['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mob-menu__section">
            <h4>{{ __('landing.nav.services') }}</h4>
            <ul class="mob-menu__secondary">
                @foreach ($servicesItems as $i => $item)
                    <li style="--i: {{ $i + 6 }}">
                        <a href="{{ $item['url'] }}" @click="menuOpen = false">{{ $item['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Solo invitados: el «Registro» del parque. Con sesión iniciada, la cuenta vive en el
             selector «Hola, nombre» de la barra superior (visible también en móvil, #216 punto 4),
             así que el drawer no duplica los enlaces de cuenta. El selector de idioma vive en el footer. --}}
        @guest
            <div class="mob-menu__foot">
                @if (! empty($site['registration_url']))
                    <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener" class="btn btn--zone" @click="menuOpen = false">{{ $site['registration_label'] }}<x-icons.arrow-right :width="15" :height="15" /></a>
                @else
                    {{-- Mismo cambio que el CTA de escritorio, y aquí el `href` importa aún más: en
                         móvil el «abrir en pestaña nueva» es un gesto habitual. Se cierra el cajón de
                         navegación ANTES de abrir el de la cuenta, o quedarían dos superpuestos. --}}
                    <a href="{{ route('registro') }}" class="btn btn--zone"
                       x-on:click="menuOpen = false; $store.purchase.openAccount($event, 'register')">{{ __('landing.nav.reserve') }}<x-icons.arrow-right :width="15" :height="15" /></a>
                @endif
            </div>
        @endguest
    </aside>
</div>
