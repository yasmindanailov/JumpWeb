{{--
    Banner de consentimiento de cookies (#219, rediseño #222 sobre el mockup
    `design_mockup/Banner Cookies.html`). UNA tarjeta abajo-izquierda que se EXPANDE en sitio:
      · Capa 1 (compacta): aviso + «Aceptar» / «Rechazar» / «Configurar» en IGUALDAD (misma clase y
        peso; Guía AEPD mayo-2024). Ninguno usa el color de marca. Sin preselección.
      · Capa 2 (preferencias): por finalidad REAL (Necesarias ON · Mapa Google · Redes), con toggles
        y enlace a la política. Reabrible desde el pie (revocar = art. 7.3 RGPD).
    Estado desde `data-*` del body (servidor); store `cookies` (app.js) refleja la UI y persiste por
    POST. Reusa el store: la tarjeta se ve si `visible` (1.ª decisión pendiente) O `panel` (reapertura).

    `<div x-data class="cookie-consent-root">` es OBLIGATORIO y va al final del <body>, fuera del
    `x-data="landing"` de las páginas: sin un x-data propio, Alpine no retira el x-cloak (invisible)
    ni engancha los @click. La tarjeta lleva su PROPIO x-data para el estado local de los toggles.
--}}
<div x-data class="cookie-consent-root">
    <aside class="cookie" role="region" aria-label="{{ __('cookies.banner.aria') }}"
           :class="{ 'is-open': $store.cookies.panel }"
           x-show="$store.cookies.visible || $store.cookies.panel" x-cloak x-transition
           x-data="{ local: { maps: false, social: false } }"
           x-effect="$store.cookies.panel && (local = { maps: $store.cookies.prefs.maps, social: $store.cookies.prefs.social })">
        <div class="cookie__pad">
            {{-- Cerrar: solo en preferencias/reapertura (la 1.ª capa exige una decisión). --}}
            <button type="button" class="cookie__x" x-show="$store.cookies.panel" x-cloak
                    @click="$store.cookies.closePanel()" aria-label="{{ __('account.close') }}">&times;</button>

            <div class="cookie__head">
                <span class="cookie__chip"><x-icons.cookie /></span>
                <span class="cookie__eyebrow">{{ __('cookies.banner.eyebrow') }}</span>
            </div>

            <h2 class="cookie__title">{{ __('cookies.banner.title') }}</h2>
            <p class="cookie__body">
                {{ __('cookies.banner.text') }}
                <a href="{{ route('legal.cookies') }}">{{ __('cookies.banner.policy') }}</a>
            </p>

            {{-- ───────── Capa 1: compacta ───────── --}}
            <div class="cookie__compact" x-show="!$store.cookies.panel">
                {{-- IGUALDAD (AEPD): los tres comparten EXACTAMENTE la misma clase/peso; ninguno se
                     resalta con el color de marca (`.btn--zone`). --}}
                <div class="cookie__actions">
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.rejectAll()">{{ __('cookies.banner.reject') }}</button>
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.acceptAll()">{{ __('cookies.banner.accept') }}</button>
                </div>
                <button type="button" class="cookie__config" @click="$store.cookies.openPanel()">{{ __('cookies.banner.configure') }}</button>
            </div>

            {{-- ───────── Capa 2: preferencias ───────── --}}
            <div class="cookie__prefs" x-show="$store.cookies.panel" x-cloak>
                {{-- Necesarias: siempre activas, no togglables (exentas). --}}
                <div class="pref">
                    <div class="pref__txt">
                        <span class="pref__name">{{ __('cookies.panel.necessary_title') }}</span>
                        <span class="pref__desc">{{ __('cookies.panel.necessary_desc') }}</span>
                        <span class="pref__lock">{{ __('cookies.panel.always_on') }}</span>
                    </div>
                    <span class="ck-tgl is-locked" aria-disabled="true"></span>
                </div>

                {{-- Mapa (Google Maps). --}}
                <div class="pref">
                    <div class="pref__txt">
                        <span class="pref__name">{{ __('cookies.panel.maps_title') }}</span>
                        <span class="pref__desc">{{ __('cookies.panel.maps_desc') }}</span>
                    </div>
                    <button type="button" class="ck-tgl" :class="{ 'is-on': local.maps }" @click="local.maps = !local.maps"
                            :aria-pressed="local.maps ? 'true' : 'false'" aria-label="{{ __('cookies.panel.maps_title') }}"></button>
                </div>

                {{-- Redes sociales (feed IG/TikTok). --}}
                <div class="pref">
                    <div class="pref__txt">
                        <span class="pref__name">{{ __('cookies.panel.social_title') }}</span>
                        <span class="pref__desc">{{ __('cookies.panel.social_desc') }}</span>
                    </div>
                    <button type="button" class="ck-tgl" :class="{ 'is-on': local.social }" @click="local.social = !local.social"
                            :aria-pressed="local.social ? 'true' : 'false'" aria-label="{{ __('cookies.panel.social_title') }}"></button>
                </div>

                {{-- Igualdad también en la 2.ª capa: misma clase/peso para los tres. --}}
                <div class="cookie__prefs-actions">
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.rejectAll()">{{ __('cookies.panel.reject_all') }}</button>
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.savePanel(local)">{{ __('cookies.panel.save') }}</button>
                    <button type="button" class="btn btn--ghost cookie-btn" @click="$store.cookies.acceptAll()">{{ __('cookies.panel.accept_all') }}</button>
                </div>
                <a class="cookie__policy" href="{{ route('legal.cookies') }}">{{ __('cookies.panel.policy_link') }}</a>
            </div>
        </div>
    </aside>
</div>
