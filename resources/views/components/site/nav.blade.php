@props(['sections' => []])
@php
    // ── LOS DESTINOS DEL MENÚ SON EL INVENTARIO DEL CANVAS (`DECISIONES #521`, carril de diseño
    // Fase 3 · T3a·1, `[DECIDIDO owner, 2026-09-11]`) ──────────────────────────────────────────────
    // Dos listas y ninguna más: las SECCIONES de la portada —solo cuando la página que pinta el menú
    // ES la portada, que es quien las pasa por `sections`— y las PÁGINAS del inventario, en todas.
    // Las compone `SiteDestinations`, que es también la fuente del PIE: con una lista cada uno, un
    // destino nuevo aparecería en uno y no en el otro sin que nada avisara.
    //
    // ⚠️⚠️ **Aquí vivían las ZONAS, los atajos y los SERVICIOS del panel** (`#256`, `#341`), y se van
    // por decisión del owner con la regla del canvas delante: *«un enlace que no está en el
    // inventario es relleno»*. Las zonas se eligen en la sección 01 y los servicios son UNA página.
    // Con ellos se retira del panel el interruptor «Sale en el menú», que ya no gobernaría nada, y el
    // composer deja de hacer dos consultas por petición que solo servían aquí.
    //
    // ⚠️⚠️ **Y se cierra un defecto que no avisaba**: en una página INTERIOR el grupo «En esta página»
    // listaba las secciones DE LA PORTADA —medido en `/precios`: JUMP, KIDS, Atracciones y
    // Ubicación, las cuatro llevando fuera de ella—. Una interior no tiene secciones, así que ese
    // grupo no se pinta (`Layout Paginas PJP`: «sin el grupo "Esta página" mientras la página no
    // tenga secciones»).
    //
    // ── El GRUPO de cada destino se sigue DEDUCIENDO de la URL (`DECISIONES #477`) ─────────────────
    // Un destino es «sección» si apunta a la portada con ancla, y «página» en cualquier otro caso. No
    // es un campo, ni de la BD ni del panel: un dato derivable de un hecho no se guarda.
    $grupoDeUrl = static function (string $url): string {
        $partes = parse_url($url);
        $ruta = $partes['path'] ?? '/';

        return ($ruta === '/' || $ruta === '') && ! empty($partes['fragment']) ? 'section' : 'page';
    };

    $menuItems = [];
    foreach (array_merge($sections, \App\Domain\Content\Services\SiteDestinations::pages(request()->route()?->getName())) as $item) {
        $menuItems[] = $item + ['grupo' => $grupoDeUrl($item['url'])];
    }

    // Los mismos destinos repartidos en sus dos grupos, en el ORDEN del sistema —primero lo de esta
    // página, luego lo que te saca de ella—. Lo lee el cajón de móvil; el menú agrupa por su cuenta
    // porque necesita el índice GLOBAL de cada destino para su vista previa.
    $menuGroups = [
        'section' => array_values(array_filter($menuItems, static fn (array $i): bool => $i['grupo'] === 'section')),
        'page' => array_values(array_filter($menuItems, static fn (array $i): bool => $i['grupo'] === 'page')),
    ];
@endphp

{{-- **El salto al contenido, y va aquí a propósito** (`specs/armazon-y-menu.md` §1.8): existía
     en 1 de las 12 vistas —la home— y las otras once repiten el mismo bloque de navegación sin
     ofrecer forma de saltarlo. Al vivir en el componente, entra en las doce y **no se puede
     olvidar en la siguiente página pública que se cree**. Tiene que ser el PRIMER focusable, así
     que va antes que el racimo. --}}
<a href="#main" class="skip-link">{{ __('landing.nav.skip') }}</a>

{{-- ❗ **EL SUELO SIN JAVASCRIPT DEL ARMAZÓN** (2c·8, `#216`). En la portada el armazón nace
     oculto y lo destapa `--nav-p`, que publica `heroChoreo`. **Sin JavaScript nadie lo publica**, y
     un sitio cuya primera pantalla no tiene ni logotipo ni menú ni botón de comprar no es una
     degradación aceptable: es un sitio roto.
     ▶ `<noscript>` es la única forma de decir «esto solo si NO hay JS» sin un script que lo diga,
     y su `<style>` gana por especificidad y orden. Mismo recurso que el selector de idioma del
     menú (`#205`).
     ⚠️ Va aquí y no en el layout: quien tiene el problema es este componente, y el que lo lea
     tiene que ver el remedio al lado del mecanismo que lo causa. --}}
<noscript><style>body[data-has-hero] .nav__left,body[data-has-hero] .nav__cta{opacity:1;transform:none;pointer-events:auto}</style></noscript>

{{-- Con el menú abierto el armazón sube POR ENCIMA de él (la hamburguesa es la forma de cerrarlo)
     y declara superficie de TINTA, que es lo que hace legible su contenido sobre el menú sin
     escribir un solo color a mano: los siete tokens de superficie se re-escopan solos. --}}
<nav class="nav"
     {{-- ⚠️ Enlace por OBJETO y no por array (`#253`): con `[cond && 'clase']` Alpine escribe el
          literal `false` en el atributo cuando la condición no se cumple. Medido: `class="nav
          false"`. No rompe nada —no hay ninguna regla `.false`— pero es una clase inventada en el
          armazón de las doce vistas, y la siguiente persona que la vea va a buscarla. --}}
     :class="{ 'nav--over': menuOpen, 'nav--hidden': navHidden }"
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
        {{-- ⚠️ **El par se fue a `<x-site.cta-pair>` en `#227`.** Vive en TRES sitios —aquí, la
             primera pantalla y la barra flotante de móvil— y son la misma decisión: tres copias de
             tres ramas de sesión cada una no se mantienen iguales solas. `#225` ya pagó esa lección
             con el CSS; esto es la misma con el marcado.
             ▶ `place` decide colocación y copy. Aquí, el racimo de la cabecera. --}}
        <x-site.cta-pair place="nav" />

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
        {{-- ⚠️⚠️ **El dibujo pasa de dos ICONOS a dos RAYAS que rotan** (`#217`,
             `[DECIDIDO owner]`: idéntico al mockup), y eso tiene un coste que hay que decir: aquí
             se pierde el hueco de icono por instalación. `#211` puso `x-icons.menu`/`close`
             justamente porque el dibujo es uno de los tres mecanismos del tema y un cliente puede
             sustituir el fichero; dos rayas de CSS no salen de ningún set. Ficha en `DEUDA.md`.
             ▶ **A cambio, la X se FORMA en vez de aparecer**: dos SVG intercambiados no giran.

             ⚠️ Las rayas van `aria-hidden`: el nombre accesible lo da el `aria-label`, que dice la
             ACCIÓN («Abrir menú» / «Cerrar menú»). La etiqueta VISIBLE dice dónde estás («Menú» /
             «Cerrar») y es más corta — por eso no puede ser el nombre accesible: «Cerrar» a secas
             no dice qué se cierra.
             ⚠️ **Las dos etiquetas se sirven las dos y elige el CSS.** Con Alpine cambiando el
             texto habría un parpadeo en la primera pintura; y sin JavaScript, la que se ve es la
             correcta porque el menú está cerrado. --}}
        <button class="nav__burger" x-ref="burger"
                @click="menuOpen = ! menuOpen"
                :aria-expanded="menuOpen ? 'true' : 'false'"
                :aria-label="menuOpen ? @js(__('landing.nav.menu_close')) : @js(__('landing.nav.menu_open'))"
                aria-label="{{ __('landing.nav.menu_open') }}">
            <span class="nav__burger-bars" aria-hidden="true">
                <span class="nav__burger-bar nav__burger-bar--top"></span>
                <span class="nav__burger-bar nav__burger-bar--bottom"></span>
            </span>
            <span class="nav__burger-label nav__burger-label--closed">{{ __('landing.nav.burger_label') }}</span>
            <span class="nav__burger-label nav__burger-label--open">{{ __('landing.nav.burger_label_open') }}</span>
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

        {{-- ⚠️ El cajón está APAGADO desde `#221` (no retirado: ficha en `DEUDA.md`) y aun así lee
             los MISMOS destinos y los MISMOS grupos que el menú (`#521`): dos navegaciones con
             listas distintas son dos sitios que mantener, y uno se queda atrás sin avisar. --}}
        @foreach ($menuGroups as $clave => $delGrupo)
            @continue (empty($delGrupo))
            <div class="mob-menu__section">
                <h4>{{ __('landing.nav.menu_group.'.$clave) }}</h4>
                <ul class="mob-menu__secondary">
                    @foreach ($delGrupo as $i => $item)
                        <li style="--i: {{ $i }}">
                            <a href="{{ $item['url'] }}" @click="menuOpen = false">{{ $item['t'] }}<x-icons.arrow-right class="arrow" :width="14" :height="14" /></a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- Solo invitados: el «Registro» del parque. Con sesión iniciada, la cuenta vive en el
             selector «Hola, nombre» de la barra superior (visible también en móvil, #216 punto 4),
             así que el drawer no duplica los enlaces de cuenta. El selector de idioma vive en el footer. --}}
        @guest
            <div class="mob-menu__foot">
                @if (! empty($site['registration_url']))
                    <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener" class="btn" @click="menuOpen = false">{{ $site['registration_label'] }}<x-icons.arrow-right :width="15" :height="15" /></a>
                @else
                    {{-- Mismo cambio que el CTA de escritorio, y aquí el `href` importa aún más: en
                         móvil el «abrir en pestaña nueva» es un gesto habitual. Se cierra el cajón de
                         navegación ANTES de abrir el de la cuenta, o quedarían dos superpuestos. --}}
                    <a href="{{ route('registro') }}" class="btn"
                       x-on:click="menuOpen = false; $store.purchase.openAccount($event, 'register')">{{ __('landing.nav.reserve') }}<x-icons.arrow-right :width="15" :height="15" /></a>
                @endif
            </div>
        @endguest
    </aside>
</div>
