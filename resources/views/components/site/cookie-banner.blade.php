{{--
    Banner de consentimiento de cookies (#219, rediseño #222 sobre el mockup
    `design_mockup/Banner Cookies.html`). UNA tarjeta abajo-izquierda que se EXPANDE en sitio:
      · Capa 1 (compacta): aviso + «Aceptar» / «Rechazar» / «Configurar» en IGUALDAD (misma clase y
        peso; Guía AEPD mayo-2024). Ninguno usa el color de marca. Sin preselección.
      · Capa 2 (preferencias): por finalidad REAL, con toggles y enlace a la política. Reabrible desde
        el pie (revocar = art. 7.3 RGPD).
    Estado desde `data-*` del body (servidor); store `cookies` (`ui/cookie-consent.js`) refleja la UI y
    persiste por POST.

    T3a de la analítica (`specs/analitica.md` §4.3): las finalidades se RECORREN desde
    `CookieConsent::OPTIONAL` —cuatro: mapa y reseñas, redes, análisis identificado, publicidad— con sus
    textos en `cookies.panel.<categoría>_title/_desc`; la tarjeta se ve solo si `showing` (primera decisión
    pendiente o panel reabierto, y NUNCA con el cajón de compra delante: espera a que se cierre); al abrir el
    panel el foco va al título (anuncio accesible al reaparecer); y `consent_shown` se cuenta al enseñarse.

    `<div x-data class="cookie-consent-root">` es OBLIGATORIO y va al final del <body>, fuera del
    `x-data="landing"` de las páginas: sin un x-data propio, Alpine no retira el x-cloak (invisible)
    ni engancha los @click. La tarjeta lleva su PROPIO x-data para el estado local de los toggles.
--}}
@php($consentCategories = \App\Domain\Identity\Services\CookieConsent::OPTIONAL)
<div x-data class="cookie-consent-root">
    <aside class="cookie" role="region" aria-label="{{ __('cookies.banner.aria') }}"
           :class="{ 'is-open': $store.cookies.panel }"
           x-show="$store.cookies.showing" x-cloak x-transition
           x-data="{ local: {} }"
           x-effect="$store.cookies.panel && (local = { ...$store.cookies.prefs })"
           x-init="$watch('$store.cookies.showing', (on) => { if (on) $store.cookies.noteShown(); if (on && $store.cookies.panel) $nextTick(() => $refs.title && $refs.title.focus()); }); $store.cookies.showing && $store.cookies.noteShown()">
        <div class="cookie__pad">
            {{-- Cerrar: solo en preferencias/reapertura (la 1.ª capa exige una decisión). --}}
            <button type="button" class="cookie__x" x-show="$store.cookies.panel" x-cloak
                    @click="$store.cookies.closePanel()" aria-label="{{ __('account.close') }}">&times;</button>

            <div class="cookie__head">
                <span class="cookie__chip"><x-icons.cookie /></span>
                <span class="cookie__eyebrow">{{ __('cookies.banner.eyebrow') }}</span>
            </div>

            {{-- `tabindex="-1"`: recibe el foco al reabrir el panel desde el pie, y así el lector de pantalla
                 anuncia que la tarjeta ha vuelto (§4.3: «anuncio accesible al reaparecer»). --}}
            <h2 class="cookie__title" tabindex="-1" x-ref="title">{{ __('cookies.banner.title') }}</h2>
            {{-- ⚠️ **Este enlace se queda SIN `data-tap` a propósito** (`#264`): va EN LÍNEA dentro
                 del párrafo, y un objetivo de 44 px de alto sobre una línea de 18 se comería el
                 renglón de arriba y el de abajo — texto que se lee y se selecciona, no se pulsa.
                 WCAG exime justamente a los enlaces en línea dentro de un bloque de texto (2.5.5 y
                 2.5.8, «inline»). Es la única excepción de la tanda y es de norma, no de descuido. --}}
            <p class="cookie__body">
                {{ __('cookies.banner.text') }}
                <a href="{{ route('legal.cookies') }}">{{ __('cookies.banner.policy') }}</a>
            </p>

            {{-- ───────── Capa 1: compacta ───────── --}}
            <div class="cookie__compact" x-show="!$store.cookies.panel">
                {{-- IGUALDAD (AEPD): los tres comparten EXACTAMENTE la misma clase/peso; ninguno se
                     resalta — ni con el relleno de acción (`.btn` pelado) ni con el de tinta
                     (`.btn--ink`). ⚠️ La frase ya no cita una variante concreta a propósito: citaba
                     `.btn--zone`, que `#551` retiró del producto, y una prueba legal no puede
                     apoyarse en el nombre de una clase que puede desaparecer. --}}
                <div class="cookie__actions">
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.rejectAll()">{{ __('cookies.banner.reject') }}</button>
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.acceptAll()">{{ __('cookies.banner.accept') }}</button>
                </div>
                <button type="button" class="cookie__config" data-tap @click="$store.cookies.openPanel()">{{ __('cookies.banner.configure') }}</button>
            </div>

            {{-- ───────── Capa 2: preferencias ───────── --}}
            <div class="cookie__prefs" x-show="$store.cookies.panel" x-cloak>
                {{-- Necesarias: siempre activas, no togglables (exentas; la medición de audiencia propia va aquí). --}}
                <div class="pref">
                    <div class="pref__txt">
                        <span class="pref__name">{{ __('cookies.panel.necessary_title') }}</span>
                        <span class="pref__desc">{{ __('cookies.panel.necessary_desc') }}</span>
                        <span class="pref__lock">{{ __('cookies.panel.always_on') }}</span>
                    </div>
                    <span class="ck-tgl is-locked" aria-disabled="true"></span>
                </div>

                {{-- Una finalidad por categoría de `CookieConsent::OPTIONAL`, en su orden. --}}
                @foreach ($consentCategories as $category)
                    <div class="pref" data-consent-category="{{ $category }}">
                        <div class="pref__txt">
                            <span class="pref__name">{{ __('cookies.panel.'.$category.'_title') }}</span>
                            <span class="pref__desc">{{ __('cookies.panel.'.$category.'_desc') }}</span>
                        </div>
                        <button type="button" class="ck-tgl" data-tap :class="{ 'is-on': local.{{ $category }} }" @click="local.{{ $category }} = !local.{{ $category }}"
                                :aria-pressed="local.{{ $category }} ? 'true' : 'false'" aria-label="{{ __('cookies.panel.'.$category.'_title') }}"></button>
                    </div>
                @endforeach

                {{-- Igualdad también en la 2.ª capa: misma clase/peso para los tres. --}}
                <div class="cookie__prefs-actions">
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.rejectAll()">{{ __('cookies.panel.reject_all') }}</button>
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.savePanel(local)">{{ __('cookies.panel.save') }}</button>
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.acceptAll()">{{ __('cookies.panel.accept_all') }}</button>
                </div>
                <a class="cookie__policy" data-tap href="{{ route('legal.cookies') }}">{{ __('cookies.panel.policy_link') }}</a>
            </div>
        </div>
    </aside>
</div>
