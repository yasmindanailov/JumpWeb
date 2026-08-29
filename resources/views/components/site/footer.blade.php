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
    {{-- El envoltorio existe para la VELA: un `::after` dentro de un contenedor con scroll
         viajaría con el contenido. Su porqué completo está junto a la regla. --}}
    <div class="foot__links-wrap">
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
    </div>

    <div class="foot__bottom">
        {{-- ⚠️⚠️ **El selector de idioma SALE de aquí otra vez** (`#253`, `[DECIDIDO owner]`: «quita
             el selector de idioma del footer, ya lo tenemos en el menú»). Vuelve así a la postura de
             `#205`, que `#233` había revertido para parecerse al mockup.
             ▶ **No es solo una pieza menos**: el pie es la mitad de la composición del punto
             estático del cierre, y a 1440×900 esa composición se pasaba **40 px** de la ventana. Lo
             que se retira aquí es alto que se recupera allí. Medido en `#253`.

             ❗❗ **Lo que NO se va es el `<noscript>`, y esto es lo importante**: el desplegable del
             menú se abre con Alpine, así que **sin JavaScript el idioma se quedaría sin ninguna
             puerta**. Estas tres anclas son esa puerta. No las ve nadie con JS y son lo único que
             hay sin él — el mismo recurso que usan el reintento de pago y el marco de
             consentimiento. Retirarlas «porque el selector ya no está» sería quitar la salida y la
             señal a la vez. --}}
        <div class="foot__bottom-left">
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

        {{-- ⚠️⚠️ **EL BLOQUE LEGAL ES UNA TIRA QUE SE DESLIZA** (`#264`, `[DECIDIDO owner]`), el
             mismo patrón que `#252` dio a los destinos de arriba. El envoltorio no es decorativo:
             existe para la VELA, porque un `::after` dentro del carril viajaría con el contenido
             (su porqué entero está junto a la regla).
             ▶ **El motivo es táctil**: a 390 px estos seis eslabones envolvían en tres renglones de
             14 px de alto. Apilarlos a 44 hacía crecer el pie 54 px; en una tira miden 44 y el pie
             ENCOGE 34. --}}
        <span class="foot__legal-wrap">
        <span class="foot__legal">
            @foreach ($legalLabels as $i => $item)
                <a href="{{ $legalUrls[$i] ?? url('/') }}">{{ $item }}</a>
            @endforeach
            {{-- Enlace permanente para revisar/revocar el consentimiento de cookies (art. 7.3 RGPD,
                 #219): reabre el panel de preferencias. Es un botón porque actúa sobre el store Alpine. --}}
            <button type="button" class="foot__cookie-config" @click="$store.cookies.openPanel()">{{ __('cookies.banner.manage_link') }}</button>
        </span>
        </span>
    </div>
</footer>
