{{-- ⚠️ **El prop `authModal` se retiró el 2026-08-23 con el modal** (`DECISIONES #122`). Decía DOS
     cosas a la vez —qué modal abrir y si la página se indexa— y esa segunda, que nadie había puesto
     ahí a propósito, era la que había que rescatar: hoy las tres puertas de auth declaran su
     `noindex` por el prop de abajo, que solo significa una cosa. --}}
@props(['title' => null, 'fullTitle' => null, 'description' => null, 'hasHero' => false, 'noindex' => false])
@php
    // `fullTitle` (si se pasa) es el <title> COMPLETO verbatim (lo usa la home con el «Título web»
    // editable, #215, p. ej. «MI PARQUE - Parque de saltos»). Si no, se compone
    // «<title> · <nombre>» como siempre.
    $pageTitle = $fullTitle ?: ($title ? $title.' · '.config('app.name') : config('app.name'));
    $metaDescription = $description ?: __('landing.hero.tag');
    $canonical = url()->current();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Token CSRF para el POST del consentimiento de cookies (#219), que va por `fetch` sin sesión
         Livewire (controlador plano → funciona también para visitantes anónimos). --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow' }}">

    {{-- Favicon / icono de la web. Sustituible por instalación (`#211`): ver el componente. --}}
    <x-site.favicon />

    {{-- Open Graph / redes sociales --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $fullTitle ?: ($title ?: config('app.name')) }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonical }}">
    {{-- og_image del panel si está configurado; si no, una por defecto generada del sitio (1200×630). --}}
    <meta property="og:image" content="{{ $site['og_image'] ?: asset('og-image.jpg') }}">
    <meta name="twitter:image" content="{{ $site['og_image'] ?: asset('og-image.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Datos estructurados (JSON-LD): marca + negocio local (dirección, teléfono, horario) para
         los resultados enriquecidos de buscadores. Invisible para el visitante; defensivo ante
         campos `[PENDIENTE]`/vacíos (ver App\Domain\Content\Services\StructuredData). --}}
    <x-site.json-ld :site="$site" />

    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Las familias son de la INSTALACIÓN (`config/theme.php` → `THEME_FONTS`); el host NO,
         porque la CSP solo permite uno. Ver `Content\Services\ThemeFonts`. --}}
    <link rel="stylesheet" href="{{ \App\Domain\Content\Services\ThemeFonts::stylesheetUrl() }}">

    {{-- CSS base del producto (sin minificar).
         ⚠️ El encabezado de `site.css` dice «el CSS del mockup se mantiene intacto» y **eso ya no
         es cierto**: `#138`, `#139` y `#143` lo han tocado (la paleta del primer cliente vivía
         dentro). Se conserva el nombre, no la promesa. --}}
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) }}">
    {{-- Color de marca white-label: sobreescribe los tokens de color del `:root` por defecto con los
         valores editables en el panel, y por eso va DESPUÉS de landing.css.
         `--brand`/`--brand-2` = marca global · `--zone-1`/`--zone-2` la siguen · `--on-brand` sale
         por contraste WCAG. **Cada zona re-escopa lo suyo EN LÍNEA** sobre su elemento con
         `ThemeSettings::zoneStyle()`; el acento de «Atracciones» va scoped a `#rides` desde `app.js`.
         ⚠️ Aquí se citaban `--jump-1`/`--kids-1` como «color de cada zona»: los retiró `#139` y este
         comentario los sobrevivió. Las zonas son DATOS y pueden ser dos, cinco o llamarse de otra forma. --}}
    {{-- ⚠️⚠️ **Este bloque se queda en `:root` y NO se scopea al cajón** (`DECISIONES #637`). La T4 lo puso un
         rato en `:root, .sidecart` para que el juez de la hoja del paquete diera cero, y era un error con
         consecuencia real: el tema del panel pasaba a declarar sobre `.sidecart`, que está MÁS CERCA de los
         nodos del cajón que el `:root` donde tematiza `client.css`, así que **dentro del cajón le ganaba al
         tema de la instalación**. Medido con el `client.css` de producción: `--on-brand` pasaba de `#101418`
         (el del cliente) a `#14130F` (el del panel). Y no hacía falta para nada: en la landing del producto no
         se carga `cajon.css`, y en una página ajena este `<style>` ni existe. El juez compensa su propio
         escenario artificial (`huella-maquetacion.mjs`), que para eso es el instrumento y no el producto. --}}
    <style id="jj-theme">:root{ {{ \App\Domain\Content\Services\ThemeSettings::cssRootDeclarations() }} }</style>
    {{-- Spinner de marca (estático, no por Vite: su minificador rompe `backdrop-filter`).
         Su DIBUJO es sustituible por instalación desde `client.css`; ver docs/UI-SPINNER.md §3.bis. --}}
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}?v={{ @filemtime(public_path('css/spinner.css')) }}">
    {{-- Estilos propios del producto sobre la base --}}
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
    {{-- ── EL PAQUETE DE TEMA DE ESTA INSTALACIÓN (`DECISIONES #143`) ────────────────────────────
         Hoja OPCIONAL del cliente. Va la ÚLTIMA de las cuatro a propósito: es lo único que hace
         que redefinir un token gane a lo que trae el producto. Sin este hueco, tokenizar el CSS
         era trabajo que ningún cliente podía usar — no había por dónde entrar.
         ⚠️ `@filemtime` hace de las DOS cosas —existencia y cache-busting— en una sola llamada a
         disco: devuelve `false` si el fichero no está, así que no hay `file_exists` aparte.
         ⚠️ NO se versiona (es del cliente, no del producto) y el `rsync --delete` de `deploy.sh`
         lo EXCLUYE: sin esa exclusión, el primer despliegue se lo llevaría por delante y la web
         volvería al tema del producto en silencio. Ver `INSTALACION-CLIENTE.md` §4. --}}
    @php($clientTheme = @filemtime(public_path('css/client.css')))
    @if ($clientTheme)
        <link rel="stylesheet" href="{{ asset('css/client.css') }}?v={{ $clientTheme }}">
    @endif
    @livewireStyles
    @vite(['resources/js/app.js'])
</head>
{{-- `data-purchase-open` = "1" indica a Alpine que el sidebar de compra debe abrirse al
     cargar la página (`app.js`: `isOpen: document.body.dataset.purchaseOpen === '1'`).
     Dos detonadores: el enlace profundo a `/entradas` (#66) y un desenlace de la vuelta de
     Redsys pendiente de enseñar (#104/#106).
     ⚠️ Se usa `peek()` y NO `consume()`, y la diferencia importa: el componente de compra es
     `lazy`, así que su `mount()` corre en una petición POSTERIOR a este render (medido). Si
     el layout consumiera aquí, el motor se quedaría sin nada que enseñar. Quién consume
     depende del motor, y `Http\Sidebar\SidebarEntry` lo explica en un solo sitio. --}}
{{-- `data-cta-mode`: con qué mitad ARRANCA el par de CTA. Siempre comprar (`[DECIDIDO owner,
     2026-09-11]`, `DECISIONES #523`, revierte `#326`): «Reservar» abierto y el registro —o la cuenta,
     con sesión— plegado e invitando a abrirse. --}}
<body data-cta-mode="buy"
      data-purchase-open="{{ ((request()->routeIs('entradas') && $site['sales_online']) || \App\Http\Sidebar\AccountDoor::isDoor() || \App\Http\Sidebar\SidebarEntry::peek()->pending()) ? '1' : '' }}"
      {{-- La ZONA del área de cliente con la que abrir, cuando se ha entrado por una de las rutas
           que sobreviven a la retirada de `/mi-cuenta/…` (`AccountDoor`). Vacío = no es una puerta.
           ⚠️ Se CONSUME al abrir: si no, cerrar y reabrir el cajón devolvería al cliente a la zona
           una y otra vez — la misma trampa que `SidebarEntry` pagó en 4.0a. --}}
      data-account-zone="{{ \App\Http\Sidebar\AccountDoor::zone() }}"
      {{-- Estado inicial del consentimiento de cookies (#219), calculado por el servidor → lo lee el
           store `cookies` de Alpine (app.js), igual que `purchase`/`auth`. --}}
      data-cookie-enabled="{{ ($cookieBannerEnabled ?? false) ? '1' : '' }}"
      data-cookie-decided="{{ ($cookieConsent['decided'] ?? false) ? '1' : '' }}"
      data-cookie-maps="{{ ($cookieConsent['maps'] ?? false) ? '1' : '' }}"
      data-cookie-social="{{ ($cookieConsent['social'] ?? false) ? '1' : '' }}"
      data-cookie-endpoint="{{ route('cookies.consent') }}"
      @if ($hasHero) data-has-hero="1" @endif>
    {{-- Banner de BYPASS de mantenimiento (#218): si la web está en mantenimiento de sitio y quien
         la ve es personal del panel (admin/staff), se le muestra la web real con este aviso de que
         «solo él la ve». Los visitantes ven la página de mantenimiento (503), no este layout. El
         orden del `&&` hace short-circuit en `auth()->check()` → para visitantes NO se consulta el
         setting (sin coste). --}}
    @if (auth()->check() && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('staff')) && \App\Domain\Platform\Services\MaintenanceSettings::siteInMaintenance())
        <div class="maint-banner" role="status">
            <span class="maint-banner__text">{{ __('site.maintenance.preview_banner') }}</span>
            <a href="{{ url('/admin/configuracion/maintenance') }}" class="maint-banner__link">{{ __('site.maintenance.preview_manage') }} →</a>
        </div>
    @endif

    {{-- Aviso de reservas en pausa (#218, item 3): el banner global se RETIRÓ (2026-06-16, decisión
         clienta) porque empujaba el hero bajo el nav fijo. El aviso vive ahora SOLO en el sidecart de
         compra (`purchase.blade.php` → `showPausedNotice`), con mensaje editable desde el panel. --}}
    @if (session('status') && __('account.status.'.session('status')) !== 'account.status.'.session('status'))
        <div class="flash" role="status" aria-live="polite" x-data="{ show: true }" x-show="show" x-transition
             x-init="setTimeout(() => show = false, 8000)">
            <span>{{ __('account.status.'.session('status')) }}</span>
            <button type="button" class="flash__close" @click="show = false" aria-label="{{ __('account.close') }}">&times;</button>
        </div>
    @endif

    {{ $slot }}

    {{-- ⚠️⚠️ **Aquí vivían los TRES MODALES DE AUTH, y se retiraron el 2026-08-23**
         (`specs/auth-en-cajon.md`, `DECISIONES #122`). Entrar, darse de alta y recuperar la
         contraseña son ahora ZONAS del cajón, así que la gestión del cliente vive por fin en UN solo
         sitio — que es lo que `DECISIONES #66` pedía y el último trozo que le faltaba.
         ▶ Las rutas `/login`, `/registro` y `/recuperar-contrasena` **siguen existiendo** como
         PUERTAS (`Http\Sidebar\AccountDoor`): sirven esta misma página y abren el cajón en su zona.
         `login` además no es opcional — es el destino del middleware `auth` de Laravel.
         ▶ Lo que NO se fue: `/restablecer-contrasena/{token}` y `/email/verificar` siguen siendo
         PÁGINAS, porque se llega a ellas desde un correo y el cajón no es direccionable. --}}

    {{-- Sidebar de compra de entradas (Fase 5.2): asistente paso a paso sobre la página actual. --}}
    {{-- ⚠️ El bloqueo de scroll YA NO se pone aquí (`sidebar-spa.md` §6): lo pide el dueño único desde
         `app.js` al arrancar Alpine, junto con el del modal de auth —que venía abierto sin bloquear
         nada—. Un `x-init` suelto era el sexto escritor de `body.no-scroll`. --}}
    {{-- ⚠️⚠️ **LA CARCASA YA NO LLEVA NI UN ATRIBUTO DE ALPINE** (F4 · T3a, `specs/cajon-empaquetable.md`
         §4.2). Aquí vivían `x-data="a11yPanel(…)"`, `x-cloak`, dos `:class`, tres `@click` y dos
         `@keydown`: la conducta de la carcasa repartida en el marcado. Su dueño único es ahora
         `resources/js/cajon/shell.js`, sin framework, que ADOPTA este marcado: le pone `is-open`, la clase
         `is-{modo}` que publica el motor, el cierre por el telón, por la × y por Escape, y la trampa de foco.
         ▶ Cerrada se oculta por CSS (`.sidecart { visibility: hidden }`), no por `x-cloak`: sin JS no se ve,
         que es lo que tiene que pasar. --}}
    <div class="sidecart">
        <div class="sidecart__backdrop"></div>
        {{-- El «modo» del flujo (catalog/booking/cart/result/account) lo publica el MOTOR y la carcasa lo
             pinta como `is-{modo}`: minimiza el bloque de cuenta y posiciona el footer (`DECISIONES #118`). --}}
        <aside class="sidecart__panel" role="dialog" aria-modal="true" aria-label="{{ __('tickets.title') }}">
            <header class="sidecart__head">
                <span class="sidecart__title">{{ __('tickets.title') }}</span>
                <button type="button" class="sidecart__close" aria-label="{{ __('account.close') }}">&times;</button>
            </header>
            {{-- ⚠️⚠️ **El HUECO del bloque de cuenta** (`specs/account-context-vue.md` §4.1). Hasta el
                 2026-08-23 aquí vivía el componente Livewire `site.account-context`, el ÚLTIMO
                 Livewire que renderizaba este layout; ahora lo pinta Vue y la raíz del cajón lo
                 teletransporta aquí dentro.

                 ⚠️ **La clase `.acct` va en el HUECO y no en lo teletransportado**: `.acct` es el
                 *flex item* del panel —lleva su fondo, su padding y su borde— y además la rejilla de
                 una fila que se colapsa en tres modos. Un envoltorio duplicaría la caja.

                 ⚠️⚠️ **Nace COLAPSADO (`acct--pending`) y con un SUELO dentro**, y las dos cosas son
                 deliberadas. Colapsado, porque hasta que el motor llega no hay nada que enseñar y una
                 franja crema vacía se lee como un fallo. Con suelo, porque si el motor NO llega
                 —red caída, despliegue a media navegación— este formulario es **la única forma que le
                 queda al cliente de cerrar sesión**: medido, `route('logout')` aparece **una sola vez
                 en toda la aplicación** y es ésta. Quien decide cuál de las dos cosas se ve es
                 `account/host.js`, su dueño ÚNICO: `takeOver()` al montar, `reveal()` en el `catch`.

                 ⚠️ El suelo va SIN datos y sin PII a propósito: no es una copia del bloque, es el
                 suelo. --}}
            <div id="sidecart-account" class="acct acct--pending">
                @auth
                    <div class="acct__inner">
                        <div class="acct__cta">
                            <form method="POST" action="{{ route('logout') }}" class="acct__logout-form">
                                @csrf
                                <button type="submit" class="acct__btn acct__btn--primary"><x-icons.logout /> {{ __('account.nav.sign_out') }}</button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
            <div class="sidecart__body">
                {{-- ⚠️ **Lo que no se puede perder aquí es que el bundle de Livewire llegue a la
                     página**, aunque en este cajón ya no quede ningún componente Livewire de compra
                     (4.7·2b·3). **Alpine lo trae Livewire** —`app.js` no lo importa ni lo arranca:
                     usa el global que Livewire expone—, así que sin él se caen a la vez
                     `$store.purchase` (lo que ABRE este cajón desde los once puntos de la landing),
                     `$store.auth` y los tres modales de auth.

                     ⚠️ Pero **hoy llegan DOS fuentes redundantes**, y conviene saberlo antes de
                     tocar: `@livewireScripts` emite incondicionalmente, y la auto-inyección de
                     Livewire lo inyecta igual **si algún componente Livewire se renderizó**.
                     **Medido** (2026-08-21): retirar solo la directiva NO rompe nada; lo fatal es
                     quedarse sin las dos.
                     ⚠️⚠️ **Y desde el 2026-08-23 ese «algún componente» es UNO SOLO** (`#122`): con
                     los tres modales de auth retirados solo queda `account-context`. La redundancia
                     cuelga de un hilo — el día que ése migre a Vue, la directiva será la fuente
                     única. La tabla de verdad y el porqué están en
                     `SidebarMountTest::test_livewire_scripts_are_still_served`, la única aserción de
                     `livewire.js` de la suite. --}}
                    {{-- El hueco del motor SPA. Va VACÍO: el entry se trae con `import()` en la
                         primera apertura del cajón, no con la página (§4.7). Lo que sí viaja aquí es
                         lo que el servidor sabe y el cliente no puede pedir:

                         · `outcome` — en qué quedó el pago. Lo posee `Http\Sidebar\SidebarEntry` y
                           llega ya CONSUMIDO; mirarlo dos veces reabriría el cajón en cada página
                           hasta que caducara la sesión (el fallo que cerró el paso 4.0a).
                         · `messages` — el grupo `tickets` del locale activo (§4.5). La SPA no tiene
                           canal de i18n propio: son 169 claves × 3 locales que hoy salen de `__()`
                           en servidor, y un endpoint para leerlas sería una petición más en el
                           arranque para algo que ya está resuelto al pintar la página.
                         · `userId` — quién es el titular AHORA. La cesta del cajón SPA vive en
                           `localStorage` y lleva su dueño dentro, así que hace falta para purgarla si
                           cambia (`DECISIONES #38(d)`). Viaja con el HTML —antes de que exista ningún
                           fetch, en cada carga de página— porque es la fuente más fiable que hay: el
                           LOGOUT es una navegación completa, y ese es justo el caso que la sesión
                           resolvía sola con `invalidate()` y que `localStorage` no tiene. `null` para
                           el visitante anónimo, que es el valor que la purga compara.
                         · `ui` — el grupo `ui` (hoy, el rótulo del velo de carga). Va SEPARADO de
                           `messages` y no fundido con él porque son dos grupos distintos de `lang/`:
                           aplanarlos aquí crearía una tercera forma del diccionario que no existe en
                           ningún otro sitio. Son 25 bytes. --}}
                    {{-- ⚠️ Aquí se CONSUME, no se mira, y es el matiz que el paso 4.0a dejó
                         anotado: el layout usa `peek()` porque el componente Livewire es `lazy` y
                         su `mount()` corre en una petición POSTERIOR. Con la SPA **el motor es este
                         mismo documento**, así que el que consume es este. `consume()` está
                         memoizado por petición, de modo que el `peek()` del `<body>` de arriba
                         sigue viendo lo suyo y nadie se roba el valor. --}}
                    {{-- ⚠️ **El payload ya no se compone aquí** (F4 · T1, `specs/cajon-empaquetable.md` §4.5): lo
                         compone `Http\Sidebar\SidebarBoot`, que es el MISMO modelo de lectura que sirve la API
                         (`GET /api/v1/cajon/boot` y `cajon/session`) a una página que no pinte Blade. Cada poda clave
                         a clave y su porqué se mudaron con él. Medido: el `data-boot` de siete contextos es idéntico
                         byte a byte antes y después. --}}
                    <div id="sidecart-spa" data-boot="{{ json_encode(\App\Http\Sidebar\SidebarBoot::forCurrentRequest(), JSON_UNESCAPED_UNICODE) }}">
                        {{-- ⚠️ **El velo de carga del cajón, y va DENTRO del hueco a propósito.**
                             El motor se trae con `import()` en la primera apertura (§4.7), así que
                             entre el clic y el primer pintado de Vue hay una descarga: con la caché
                             fría el cajón se abría EN BLANCO. El velo `.jj-loading` que emite la
                             propia SPA no puede taparlo —vive dentro de la app que aún no ha
                             montado—, y `spaLoading` de `app.js` es solo guarda de reentrada.
                             Aquí no hace falta ni una línea de JS para apagarlo: **Vue limpia el
                             contenedor al montar** (`app.mount()` hace `container.textContent = ''`,
                             verificado en el runtime instalado), de modo que este nodo desaparece
                             solo en cuanto el cajón está listo. Si el chunk NO carga,
                             `bootSpaEngine()` lo vacía en su `catch` para no dejar un spinner
                             girando para siempre.
                             Es el mismo marcado que servía el `placeholder()` del componente
                             Livewire `lazy` que se retiró en 4.7·2b·3, y reusa su clase
                             `.purchase-loading`. Lo vigila `SpinnerTest`. --}}
                        <div class="purchase-loading">
                            <span class="jj-spinner-with-label">
                                <x-ui.spinner size="lg" :label="__('ui.loading')" />
                                <span class="jj-spinner-label" aria-hidden="true">{{ __('ui.loading') }}</span>
                            </span>
                        </div>
                    </div>
            </div>
        </aside>
    </div>

    {{-- Banner de consentimiento de cookies (#219). Va para todos los visitantes; su visibilidad la
         gobierna el store `cookies` (habilitado + sin decisión previa). --}}
    <x-site.cookie-banner />

    {{-- Barra flotante de reserva en MÓVIL (jerarquía de CTAs §03): en móvil el CTA «Reservas aquí»
         sale del header y reaparece deslizando desde abajo al hacer scroll. En desktop no se muestra
         (CSS). Va al final del <body>, fuera del `x-data="landing"` de las páginas — su `x-data` propio
         (`mobileBookBar`) gestiona la aparición; el `$store.purchase` es global. --}}
    <x-site.mobile-book-bar />

    {{-- 📜 **AQUÍ SE MONTABA EL WIDGET FLOTANTE DE OFERTAS** (`#270`) y se retira en `#668`
         (F5 · T3), cumpliendo lo que `#631` decidió: *«el sistema de ofertas de hoy —imágenes y texto
         en un icono flotante— se quita; "oferta" es un hecho de precio»*. El descuento que el
         visitante ve hoy sale del catálogo (`was_price_cents`, el «antes» tachado de `#628`), no de
         un CMS de imágenes. Se fue entero: la caja, su carrusel, el store que compartía con la barra
         de móvil, el recurso del panel y su tabla. --}}

    @livewireScripts
</body>
</html>
