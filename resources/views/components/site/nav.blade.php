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
@endphp

<nav class="nav">
    <div class="nav__left">
        <a href="{{ url('/') }}" class="nav__brand">
            <span class="nav__brand-row">
                {{ $site['name'] ?? config('app.name') }}<span class="nav__period" aria-hidden="true"><span class="nav__period-dot"></span><span class="nav__period-block"></span></span>
            </span>
        </a>
        <div class="nav__links">
            {{-- Desplegable "El parque" — secciones de la home (zonas, atracciones, ubicación). --}}
            <div class="nav__dd plan-select" :class="parkOpen && 'plan-select--open'" @click.outside="parkOpen = false">
                <button type="button" class="nav__dd-trigger" @click="parkOpen = !parkOpen; if (parkOpen) servicesOpen = false" :aria-expanded="parkOpen">
                    {{ __('landing.nav.park') }}
                    <x-icons.chevron-down class="nav__dd-chev" :width="9" :height="9" />
                </button>
                <div class="plan-select__panel" role="menu">
                    <div class="plan-select__panel-head">
                        <span class="jj-block jj-block--xs"></span>{{ __('landing.nav.park') }}
                    </div>
                    <ul>
                        @foreach ($parkItems as $i => $item)
                            <li style="--i: {{ $i }}">
                                <a href="{{ $item['url'] }}" role="menuitem">
                                    <div class="plan-select__item-text">
                                        <span class="plan-select__item-t">{{ $item['t'] }}</span>
                                        <span class="plan-select__item-s">{{ $item['s'] }}</span>
                                    </div>
                                    <span class="arrow">
                                        <x-icons.arrow-right :width="14" :height="14" />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Desplegable "Servicios" — cumpleaños + secciones de /servicios. --}}
            <div class="nav__dd plan-select" :class="servicesOpen && 'plan-select--open'" @click.outside="servicesOpen = false">
                <button type="button" class="nav__dd-trigger" @click="servicesOpen = !servicesOpen; if (servicesOpen) parkOpen = false" :aria-expanded="servicesOpen">
                    {{ __('landing.nav.services') }}
                    <x-icons.chevron-down class="nav__dd-chev" :width="9" :height="9" />
                </button>
                <div class="plan-select__panel" role="menu">
                    <div class="plan-select__panel-head">
                        <span class="jj-block jj-block--xs"></span>{{ __('landing.nav.services') }}
                    </div>
                    <ul>
                        @foreach ($servicesItems as $i => $item)
                            <li style="--i: {{ $i }}">
                                <a href="{{ $item['url'] }}" role="menuitem">
                                    <div class="plan-select__item-text">
                                        <span class="plan-select__item-t">{{ $item['t'] }}</span>
                                        <span class="plan-select__item-s">{{ $item['s'] }}</span>
                                    </div>
                                    <span class="arrow">
                                        <x-icons.arrow-right :width="14" :height="14" />
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- Atajos directos: Cumpleaños (destacado) + Entradas. --}}
            @foreach ($simpleLinks as $link)
                <a href="{{ $link['url'] }}">{{ $link['t'] }}</a>
            @endforeach
        </div>
    </div>

    <div class="nav__cta">
        {{-- Jerarquía de CTAs del header (mockup `design_mockup/jerarquia-ctas.html`):
             • Ghost: Crear cuenta — peso bajo (2/5). Findable, no protagonista. Solo guest.
             • Filled medio: Comprar entradas — peso alto (4/5). Anclaje "desde X €" data-driven.
             Sustituye al icono `nav__scan` y al botón `nav__reserve` previos: los handlers
             (`$store.purchase.open()` y —desde el 2026-08-23— `$store.purchase.openAccount()`);
             solo cambia el tratamiento visual y el copy. --}}
        @guest
            {{-- #216: «Registro» del parque. Si hay URL externa configurada (Ajustes → Registro), el
                 botón lleva a ese sistema en una pestaña nueva, con etiqueta + subtítulo por idioma.
                 Si NO hay URL, cae al comportamiento actual: abre el modal de registro interno. --}}
            @if (! empty($site['registration_url']))
                <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener" class="cta-ghost cta-ghost--stack nav-cta-ghost">
                    <span class="cta-ghost__ico"><x-icons.clipboard-check /></span>
                    <span class="cta-ghost__body">
                        <span class="cta-ghost__t">{{ $site['registration_label'] }}</span>
                        @if (! empty($site['registration_subtitle']))
                            <span class="cta-ghost__s">{{ $site['registration_subtitle'] }}</span>
                        @endif
                    </span>
                    <span class="cta-ghost__arrow" aria-hidden="true">→</span>
                </a>
            @else
                {{-- ⚠️⚠️ **Pasa de `<button>` a `<a href>` el 2026-08-23** (`specs/auth-en-cajon.md`
                     §4.5), y el `href` no es decorativo: `/registro` es una PUERTA que sirve la home
                     y abre el cajón en la zona de alta, así que el clic central, «abrir en pestaña
                     nueva» y un navegador sin JS acaban en la misma pantalla por el camino largo. Con
                     el `<button>` de antes esos tres casos no hacían nada.
                     ⚠️ La clase no cambia y el CSS ya la soporta sobre un ancla: la rama de al lado
                     —el registro externo del parque— lleva usándola así desde `#216`. --}}
                <a href="{{ route('registro') }}" class="cta-ghost nav-cta-ghost"
                   x-on:click="$store.purchase.openAccount($event, 'register')">
                    <span class="cta-ghost__ico"><x-icons.clipboard-check /></span>
                    <span class="cta-ghost__t">{{ __('landing.nav.reserve') }}</span>
                    <span class="cta-ghost__arrow" aria-hidden="true">→</span>
                </a>
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
            <button type="button" class="nav__acct" @click="$store.purchase.open()" aria-label="{{ $acctLabel }}">
                <span class="nav__acct-greet">{{ __('account.nav.hello', ['name' => $acct['firstName']]) }}</span>
                <span class="nav__acct-icon" aria-hidden="true">
                    <x-icons.user />
                    @if ($acct['hasPendingForm'])
                        <span class="nav__acct-dot"></span>
                    @endif
                </span>
            </button>
        @endguest

        {{-- `x-data="navCtaReveal"` (ver app.js): si la página marca `data-has-hero`
             en el body, este botón empieza oculto y aparece (animado) cuando el
             CTA "prime" del hero sale del viewport. En páginas sin hero queda
             visible por defecto (sin observer ni CSS oculto).
             Estructura idéntica al `.cta-med` del mockup `design_mockup/jerarquia-ctas.html`:
             icono tear-off + body (título + descripción con anclaje "desde X €") + flecha.
             El subtítulo solo se renderiza cuando hay catálogo (data-driven). --}}
        <button type="button" class="cta-med nav-cta-med" x-data="navCtaReveal"
                @click="$store.purchase.open()"
                aria-label="{{ __('landing.nav.reserve_tickets_aria') }}">
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
            <span class="cta-med__arrow" aria-hidden="true">→</span>
        </button>

        <button class="nav__burger" x-ref="burger" @click="mobileOpen = true" aria-label="{{ __('landing.nav.menu_open') }}">
            <svg width="20" height="14" viewBox="0 0 20 14" fill="none" aria-hidden="true">
                <path d="M1 1h18M1 7h18M1 13h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
        </button>
    </div>
</nav>

{{-- Menú móvil (drawer) = overlay accesible (Lote 10): Escape cierra, `trapMobile` atrapa el
     foco (Tab cíclico) dentro del panel, y el componente `landing` bloquea el scroll del fondo +
     pasa/devuelve el foco al abrir/cerrar. Fuera de tab-order y AT cuando cerrado vía CSS
     (`visibility:hidden`), no por `aria-hidden` (que dejaba foco-fantasma). --}}
<div class="mob-menu" :class="mobileOpen && 'mob-menu--open'" :aria-hidden="!mobileOpen"
     @keydown.escape.window="mobileOpen = false" @keydown="trapMobile($event)">
    <div class="mob-menu__backdrop" @click="mobileOpen = false"></div>
    <aside class="mob-menu__panel" role="dialog" aria-modal="true" x-ref="mobPanel">
        <div class="mob-menu__head">
            <span class="nav__brand">
                <span class="nav__brand-row">{{ $site['name'] ?? config('app.name') }}<span class="nav__period" aria-hidden="true"><span class="nav__period-dot"></span><span class="nav__period-block"></span></span></span>
            </span>
            <button class="mob-menu__close" @click="mobileOpen = false" aria-label="{{ __('landing.nav.menu_close') }}">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M3 3l12 12M15 3L3 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
            </button>
        </div>

        {{-- Atajos directos arriba — Cumpleaños y Entradas son los destinos más buscados. --}}
        <ul class="mob-menu__primary">
            @foreach ($simpleLinks as $i => $link)
                <li style="--i: {{ $i }}">
                    <a href="{{ $link['url'] }}" @click="mobileOpen = false">{{ $link['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
                </li>
            @endforeach
        </ul>

        {{-- En móvil aplanamos los desplegables como secciones — más rápido para el pulgar. --}}
        <div class="mob-menu__section">
            <h4>{{ __('landing.nav.park') }}</h4>
            <ul class="mob-menu__secondary">
                @foreach ($parkItems as $i => $item)
                    <li style="--i: {{ $i + 2 }}">
                        <a href="{{ $item['url'] }}" @click="mobileOpen = false">{{ $item['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mob-menu__section">
            <h4>{{ __('landing.nav.services') }}</h4>
            <ul class="mob-menu__secondary">
                @foreach ($servicesItems as $i => $item)
                    <li style="--i: {{ $i + 6 }}">
                        <a href="{{ $item['url'] }}" @click="mobileOpen = false">{{ $item['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
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
                    <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener" class="btn btn--zone" @click="mobileOpen = false">{{ $site['registration_label'] }}<x-icons.arrow-right :width="15" :height="15" /></a>
                @else
                    {{-- Mismo cambio que el CTA de escritorio, y aquí el `href` importa aún más: en
                         móvil el «abrir en pestaña nueva» es un gesto habitual. Se cierra el cajón de
                         navegación ANTES de abrir el de la cuenta, o quedarían dos superpuestos. --}}
                    <a href="{{ route('registro') }}" class="btn btn--zone"
                       x-on:click="mobileOpen = false; $store.purchase.openAccount($event, 'register')">{{ __('landing.nav.reserve') }}<x-icons.arrow-right :width="15" :height="15" /></a>
                @endif
            </div>
        @endguest
    </aside>
</div>
