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
    {{-- La tira de marca que remata el pie y sustituye al filete de 1 px que había.
         ⚠️ **El marcado se fue a `<x-site.brand-strip>` en `#226`**: el mockup la usa también en
         el hero de cabecera, y dos copias pegadas de lo mismo divergen. Aquí queda `foot__strip`,
         que es COLOCACIÓN —el aire que deja debajo— y nada más. --}}
    <x-site.brand-strip class="foot__strip" />

    {{-- ⚠️⚠️ **UNA SOLA FILA DE ENLACES** (`#235`, `[DECIDIDO owner]`: «lo quiero una sola línea
         el footer con los enlaces, no 3 columnas con varias filas»). Aquí había una rejilla de
         cuatro columnas —marca + tres listas con título— que ocupaba media pantalla.

         ▶ **El bloque de marca se va con ellas, y eso es 1:1**: el mockup no tiene marca en las
         columnas, la lleva en la fila inferior junto al copyright. Nuestro copyright ya dice el
         nombre del parque, así que no se pierde nada.

         ⚠️ **Los títulos de columna también se van, y no se sustituyen por nada**: en una fila
         única, «El parque / Información / Contacto» serían tres rótulos separando enlaces que se
         leen igual de bien seguidos. Un encabezado que no agrupa nada es ruido.

         ⚠️ **Y NINGÚN enlace se retira sin decirlo.** La fila envuelve si no caben —son 14 con la
         configuración de este cliente y a 1240 px ocupan dos renglones—; quitar destinos es una
         decisión de producto, no de maquetación, y se toma mirando cuáles sobran de verdad. --}}
    <nav class="foot__links" aria-label="{{ __('landing.footer.col_info') }}">
        @foreach (__('landing.footer.links_park') as $i => $link)
            <a href="{{ $parkUrls[$i] ?? url('/') }}">{{ $link }}</a>
        @endforeach
        @foreach (__('landing.footer.links_info') as $i => $link)
            <a href="{{ $infoUrls[$i] ?? url('/') }}">{{ $link }}</a>
        @endforeach
        <a href="{{ route('account') }}">{{ __('landing.footer.account_link') }}</a>
        @if (! empty($site['registration_url']))
            <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener">{{ $site['registration_label'] }}</a>
        @endif
        <a href="{{ route('contacto') }}">{{ __('landing.footer.contact_link') }}</a>
        @if (! empty($site['phone']))<a href="tel:{{ preg_replace('/\s+/', '', $site['phone']) }}">{{ $site['phone'] }}</a>@endif
        @if (! empty($site['email']))<a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>@endif
        <a href="{{ $site['instagram'] ?? '#' }}">Instagram</a>
        <a href="{{ $site['tiktok'] ?? '#' }}">TikTok</a>
    </nav>

    <div class="foot__bottom">
        {{-- ⚠️⚠️ **VUELVE el selector de idioma** (`#233`, `[DECIDIDO owner]`: «el footer tiene
             menos elementos»). `#205` lo retiró de aquí razonando que «dos selectores del mismo
             idioma en la misma página son dos sitios que mantener y uno que se queda atrás» —y era
             verdad **mientras fueran dos copias**. Ahora es UN componente
             (`<x-site.lang-switch>`) usado en dos sitios, así que ese coste no existe; y el mockup
             lo tiene en los dos, en las cápsulas del menú y aquí abajo.
             ▶ Con él, el `<noscript>` de abajo deja de ser la única puerta sin JavaScript… pero
             **no se retira**: el desplegable se abre con Alpine, así que sin JS sigue sin abrirse.
             Los dos siguen haciendo falta y por motivos distintos.

             ❗ **Y con él se va el ÚNICO cambio de idioma que funcionaba SIN JavaScript**: el menú
             se abre con Alpine, así que sin JS no se abre y sus cápsulas no se alcanzan. El resto
             de la navegación sobrevive —las tres columnas de enlaces de aquí arriba son anclas de
             verdad—, pero el idioma se quedaba sin ninguna. Por eso el `<noscript>` de abajo: no
             lo ve nadie con JS, y sin JS es la única puerta. Es el mismo recurso que ya usan el
             reintento de pago y el marco de consentimiento. --}}
        <div class="foot__bottom-left">
            <x-site.lang-switch :up="true" />
            <noscript>
                <ul class="foot__lang-fallback">
                    @foreach ($locales as $l)
                        <li><a href="{{ route('lang.switch', $l) }}" hreflang="{{ $l }}"
                               @if (app()->getLocale() === $l) aria-current="true" @endif>{{ $langNames[$l] }}</a></li>
                    @endforeach
                </ul>
            </noscript>

            {{-- ⚠️⚠️ **El ESLOGAN vuelve aquí, y lo cazó la suite completa** (`#235`). Vivía en el
                 bloque de marca que se retiró al aplanar el pie a una fila, y con él desapareció
                 de la web entera: es un ajuste EDITABLE del panel (`landing.tagline.*`), así que
                 quitarlo en silencio deja un campo que el cliente rellena y no sale en ninguna
                 parte. En una fila única su sitio natural es junto al copyright.
                 ▶ Lo destapó `LandingTextsAndSocialTest`, un caso de AJUSTES —a dos carpetas de
                 distancia de lo que se tocó—. Segunda vez en esta sesión que un cambio de armazón
                 rompe algo cuyo nombre de fichero no lo sugería. --}}
            <span class="foot__copy">
                <span class="foot__tag">{{ $site['tagline'] ?? __('landing.footer.tag') }}</span>
                © {{ date('Y') }} {{ \Illuminate\Support\Str::upper($site['name'] ?? config('app.name')) }} — {{ $site['footer_rights'] ?? __('landing.footer.rights') }}
            </span>
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
