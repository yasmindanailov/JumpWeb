@props(['title' => null, 'fullTitle' => null, 'description' => null, 'authModal' => null, 'hasHero' => false, 'noindex' => false])
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
    <meta name="robots" content="{{ ($authModal || $noindex) ? 'noindex, nofollow' : 'index, follow' }}">

    {{-- Favicon / icono de la web: SVG (navegadores modernos) + PNG (iOS/legacy). --}}
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-64.png') }}" sizes="64x64" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

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
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=bricolage-grotesque:400,600,700,800|space-grotesk:400,500,600,700|jetbrains-mono:400,500">

    {{-- CSS del mockup servido tal cual (sin minificar, copia exacta) --}}
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) }}">
    {{-- Color de marca white-label (Fase 7.10 iter.2): sobreescribe los tokens de color del
         mockup con los valores editables en el panel. Va DESPUÉS de landing.css para ganar al
         `:root` por defecto. `--zone-1` = marca global (lo genérico de la web); `--jump-1`/
         `--kids-1` = color de cada zona (tarjetas de la sección «Zonas»). El acento de la
         sección «Atracciones» se aplica scoped a `#rides` desde `app.js`. --}}
    <style id="jj-theme">:root{ {{ \App\Domain\Content\Services\ThemeSettings::cssRootDeclarations() }} }</style>
    {{-- Spinner de marca (copia del mockup, estático; ver docs/UI-SPINNER.md) --}}
    <link rel="stylesheet" href="{{ asset('css/spinner.css') }}?v={{ @filemtime(public_path('css/spinner.css')) }}">
    {{-- Estilos propios añadidos (no tocan el CSS del mockup) --}}
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ @filemtime(public_path('css/site.css')) }}">
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
<body data-auth-modal="{{ $authModal }}"
      data-purchase-open="{{ (request()->routeIs('entradas') || \App\Http\Sidebar\SidebarEntry::peek()->pending()) ? '1' : '' }}"
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

    {{-- Modales de autenticación (Fase 4). Solo para visitantes no autenticados. --}}
    @guest
        <div x-data="a11yPanel('$store.auth.modal')" x-cloak x-show="$store.auth.modal" class="modal"
             @keydown.escape.window="$store.auth.close()" @keydown="trap($event)">
            <div class="modal__backdrop" @click="$store.auth.close()"></div>
            <div class="modal__panel" role="dialog" aria-modal="true">
                <button type="button" class="modal__close" @click="$store.auth.close()" aria-label="{{ __('account.close') }}">&times;</button>
                <div x-show="$store.auth.modal === 'register'">
                    @livewire('auth.register')
                </div>
                <div x-show="$store.auth.modal === 'login'" x-cloak>
                    @livewire('auth.login')
                </div>
                <div x-show="$store.auth.modal === 'forgot'" x-cloak>
                    @livewire('auth.forgot-password')
                </div>
            </div>
        </div>
    @endguest

    {{-- Sidebar de compra de entradas (Fase 5.2): asistente paso a paso sobre la página actual. --}}
    <div x-data="a11yPanel('$store.purchase.isOpen')" x-cloak class="sidecart" :class="$store.purchase.isOpen && 'is-open'"
         x-init="$store.purchase.isOpen && document.body.classList.add('no-scroll')"
         @keydown.escape.window="$store.purchase.close()" @keydown="trap($event)">
        <div class="sidecart__backdrop" @click="$store.purchase.close()"></div>
        {{-- Sidebar v2: el «modo» del flujo (catalog/booking/cart/result) lo fija el componente de
             compra desde `$wire.step` (ver purchase.blade.php). La clase `is-{modo}` minimiza la
             cuenta y posiciona el footer, replicando el `.side.is-booking` del mockup. --}}
        <aside class="sidecart__panel" :class="'is-' + $store.purchase.mode" role="dialog" aria-modal="true" aria-label="{{ __('tickets.title') }}">
            <header class="sidecart__head">
                <span class="sidecart__title">{{ __('tickets.title') }}</span>
                <button type="button" class="sidecart__close" @click="$store.purchase.close()" aria-label="{{ __('account.close') }}">&times;</button>
            </header>
            {{-- Bloque de cuenta del sidebar (#221): saludo, «mis reservas», próxima reserva y aviso
                 de formulario pendiente con sesión; «Iniciar sesión» + «Mis reservas» sin ella. --}}
            <livewire:site.account-context />
            <div class="sidecart__body">
                {{-- Fase 4 · paso 4.1: los dos motores conviven tras `sidebar.engine`, para poder
                     COMPARARLOS en vivo y volver atrás sin desplegar (`sidebar-spa.md` §4.9, CE-1).
                     El default y el fallback son Livewire: el fallback no puede ser el motor en
                     construcción.

                     ⚠️ Lo que NO se bifurca son `@livewireStyles`/`@livewireScripts`: los modales de
                     auth y `account-context` son Livewire en los dos modos — y **Alpine lo trae
                     Livewire**, así que retirarlo dejaría al cajón SPA sin el store que lo abre. --}}
                @if (\App\Domain\Platform\Services\SidebarSettings::usesSpa())
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
                    @php($sidebarEntry = \App\Http\Sidebar\SidebarEntry::consume())
                    <div id="sidecart-spa" data-boot="{{ json_encode([
                        'outcome' => $sidebarEntry->outcome,
                        'orderCode' => $sidebarEntry->orderCode,
                        'messages' => __('tickets'),
                        'ui' => __('ui'),
                    ], JSON_UNESCAPED_UNICODE) }}"></div>
                @else
                    <livewire:tickets.purchase lazy />
                @endif
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

    {{-- Widget flotante «caja de regalo» de ofertas (#270). Solo se pinta si hay ofertas activas
         (`$offers` del composer). Va al final del <body>, fuera del `x-data="landing"` (su x-data
         propio `offersWidget`); su store `offers` coordina con la book-bar (que cede al abrirse). --}}
    <x-site.offers-widget />

    @livewireScripts
</body>
</html>
