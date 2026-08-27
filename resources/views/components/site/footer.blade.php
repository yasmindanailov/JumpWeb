<footer class="foot wrap">
    @php
        // Enlaces del footer (mismo orden que los textos de lang). Las páginas de
        // precios/cumpleaños/contacto llegan en 3.4.3–3.4.4; de momento van a la home.
        $parkUrls = [url('/#zones'), url('/#zones'), url('/#rides'), url('/#gallery')];
        // Lote 11: «Grupos y empresas» (índice 2) → /servicios (su página real), no /contacto.
        $infoUrls = [route('precios'), route('cumpleanos'), route('servicios'), route('normas')];
        // Orden = `landing.footer.legal`: aviso-legal, privacidad, condiciones, cookies, waiver (#216).
        $legalUrls = [route('legal.aviso-legal'), route('legal.privacidad'), route('legal.condiciones'), route('legal.cookies'), route('legal.waiver')];
        $legalLabels = (array) __('landing.footer.legal');
        // Si el waiver está DESACTIVADO (no se usa, #216 pto.3), se retira del pie. Se localiza por la
        // URL (controlada por código), no por posición, para no depender del orden de las etiquetas i18n.
        if (! \App\Domain\Identity\Services\PuertaSettings::waiverCheckEnabled()) {
            $waiverIdx = array_search(route('legal.waiver'), $legalUrls, true);
            if ($waiverIdx !== false) {
                unset($legalUrls[$waiverIdx], $legalLabels[$waiverIdx]);
            }
        }
        // ⚠️ Estas dos listas estaban QUEMADAS aquí y son una SEGUNDA copia de `SiteLocales`,
        // cuyo propio docblock avisa: «dos listas de idiomas es cómo se acaba ofreciendo uno que
        // la otra no reconoce». Al necesitar el menú del armazón los mismos datos (tanda 2c·1) se
        // devuelven a su fuente única en vez de hacer una tercera.
        $locales = \App\Domain\Platform\Services\SiteLocales::SUPPORTED;
        $langNames = \App\Domain\Platform\Services\SiteLocales::NAMES;
    @endphp
    {{-- Tira de marca («C2 · tiras» del sistema del 2.º cliente): cinco franjas que rematan el
         pie y sustituyen al filete de 1px que había. Los cinco colores salen de `--strip-1..5`,
         que DERIVAN de los dos tokens de marca — el producto no lleva ni un hex y una
         instalación los redefine desde su `client.css`. Es puramente decorativa: `aria-hidden`
         y sin texto, así que no entra en el orden de lectura de un lector de pantalla. --}}
    <div class="foot__strip" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
    </div>

    <div class="foot__grid">
        <div>
            <div class="foot__brand">{{ $site['name'] ?? config('app.name') }}<span class="nav__period" aria-hidden="true"><span class="nav__period-dot"></span><span class="nav__period-block"></span></span></div>
            <p>{{ $site['tagline'] ?? __('landing.footer.tag') }}</p>
        </div>

        <div>
            <h4>{{ __('landing.footer.col_park') }}</h4>
            <ul>
                @foreach (__('landing.footer.links_park') as $i => $link)
                    <li><a href="{{ $parkUrls[$i] ?? url('/') }}" style="opacity:0.9">{{ $link }}</a></li>
                @endforeach
            </ul>
        </div>

        <div>
            <h4>{{ __('landing.footer.col_info') }}</h4>
            <ul>
                @foreach (__('landing.footer.links_info') as $i => $link)
                    <li><a href="{{ $infoUrls[$i] ?? url('/') }}" style="opacity:0.9">{{ $link }}</a></li>
                @endforeach
                {{-- #231 p8: «Mi cuenta» en Información (el enlace «Contacto» se mueve a la columna
                     Contacto). Para invitados, /mi-cuenta redirige al login (comportamiento estándar). --}}
                <li><a href="{{ route('account') }}" style="opacity:0.9">{{ __('landing.footer.account_link') }}</a></li>
                {{-- #216: «Registro» del parque (externo si está configurado). --}}
                @if (! empty($site['registration_url']))
                    {{-- #216 pto.1: el enlace de «Registro» del pie usa el título configurado en Ajustes. --}}
                    <li><a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener" style="opacity:0.9">{{ $site['registration_label'] }}</a></li>
                @endif
            </ul>
        </div>

        <div>
            <h4>{{ __('landing.footer.col_contact') }}</h4>
            <ul>
                {{-- #231 p8: el enlace «Contacto» (página) vive ahora en esta categoría. --}}
                <li><a href="{{ route('contacto') }}" style="opacity:0.9">{{ __('landing.footer.contact_link') }}</a></li>
                @if (! empty($site['phone']))<li><a href="tel:{{ preg_replace('/\s+/', '', $site['phone']) }}" style="opacity:0.9">{{ $site['phone'] }}</a></li>@endif
                @if (! empty($site['email']))<li><a href="mailto:{{ $site['email'] }}" style="opacity:0.9">{{ $site['email'] }}</a></li>@endif
                <li><a href="{{ $site['instagram'] ?? '#' }}" style="opacity:0.9">Instagram</a></li>
                <li><a href="{{ $site['tiktok'] ?? '#' }}" style="opacity:0.9">TikTok</a></li>
            </ul>
        </div>
    </div>

    <div class="foot__bottom">
        {{-- Bloque izquierdo: selector de idioma arriba (más visible) + copyright debajo.
             Convención web (Stripe/Linear/Vercel/GitHub): el selector de idioma vive en el
             lateral izquierdo del bottom row, alineado con el copyright. --}}
        <div class="foot__bottom-left">
            {{-- Reutiliza el patrón `.lang-dd` del proyecto (mismo que el menú de cuenta del
                 nav). Variante `--up` invierte el panel para que se abra HACIA ARRIBA y
                 hacia la DERECHA del trigger (apropiado en footer-izquierda). --}}
            <div class="lang-dd lang-dd--up"
                 x-data="{ open: false }"
                 :class="open && 'lang-dd--open'"
                 @click.outside="open = false"
                 @keydown.escape.window="open = false">
                <button type="button"
                        class="lang-dd__trigger"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-haspopup="menu"
                        aria-label="{{ __('landing.footer.language') }}">
                    {{-- Autonym del idioma actual (cada idioma se nombra a sí mismo: "Español",
                         "English", "Français") — estándar moderno, reconocible para el visitante
                         aunque la página esté en otro idioma. --}}
                    {{ $langNames[app()->getLocale()] }}
                    <svg class="chev" width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                        <path d="M2 4l3 3 3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <div class="lang-dd__panel" role="menu">
                    @foreach ($locales as $l)
                        @php($isActive = app()->getLocale() === $l)
                        <a href="{{ route('lang.switch', $l) }}"
                           class="{{ $isActive ? 'active' : '' }}"
                           role="menuitem"
                           hreflang="{{ $l }}"
                           @if ($isActive) aria-current="true" @endif>
                            <span>{{ strtoupper($l) }}</span><span class="name">{{ $langNames[$l] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <span class="foot__copy">© {{ date('Y') }} {{ \Illuminate\Support\Str::upper($site['name'] ?? config('app.name')) }} — {{ $site['footer_rights'] ?? __('landing.footer.rights') }}</span>
        </div>

        <span class="foot__legal">
            @foreach ($legalLabels as $i => $item)
                <a href="{{ $legalUrls[$i] ?? url('/') }}">{{ $item }}</a>
            @endforeach
            {{-- Enlace permanente para revisar/revocar el consentimiento de cookies (art. 7.3 RGPD,
                 #219): reabre el panel de preferencias. Es un botón porque actúa sobre el store Alpine. --}}
            <button type="button" class="foot__cookie-config" @click="$store.cookies.openPanel()">{{ __('cookies.banner.manage_link') }}</button>
        </span>
    </div>
</footer>
