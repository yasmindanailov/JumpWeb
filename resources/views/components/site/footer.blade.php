@props(['sections' => [], 'surface' => 'ink', 'closing' => false])
@php
    // ── LOS DESTINOS DEL PIE SON LOS DEL MENÚ (`DECISIONES #521`/`#522`, `[DECIDIDO owner]`) ──────────
    // El inventario de páginas del canvas + la cuenta + —solo en la portada, que es quien las pasa—
    // sus secciones. Salen de `SiteDestinations`, la MISMA fuente que el menú: hasta aquí el pie tenía
    // su propia lista escrita a mano (`links_park` + `links_info`) y un destino podía existir en uno y
    // no en el otro.
    // ⚠️ Con esto salen del pie las REDES y el registro EXTERNO del parque. No es un descuido: el canvas
    // escribe que *«un enlace que no está en el inventario es relleno»*. Las redes siguen en las
    // cápsulas del menú y el registro externo en el par de CTA, que es su puerta en las doce vistas.
    $destinos = array_merge(
        \App\Domain\Content\Services\SiteDestinations::pages(request()->route()?->getName()),
        [['t' => __('landing.footer.account_link'), 'url' => route('account')]],
        $sections,
    );

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

    // Los idiomas salen de `SiteLocales`, la fuente única: dos listas de idiomas es cómo se acaba
    // ofreciendo uno que la otra no reconoce (lo dice el docblock de esa clase).
    $locales = \App\Domain\Platform\Services\SiteLocales::SUPPORTED;
    $langNames = \App\Domain\Platform\Services\SiteLocales::NAMES;

    // El COLOFÓN del marco: «Nombre · Ciudad» y «© año» (`[DECIDIDO owner]`, `#522`). La ciudad se
    // omite si la instalación no la tiene: un «· » colgando dice que falta algo.
    $colofon = trim((string) ($site['name'] ?? config('app.name')));
    if (! empty($site['city'])) {
        $colofon .= ' · '.$site['city'];
    }
@endphp
{{-- **EL PIE DEL MARCO** (`DECISIONES #522`, carril de diseño Fase 3 · T3a·2, `[DECIDIDO owner]`).
     Es el de `Marco Portada PJP` (1e) y `Layout Paginas PJP` (1a · 1b): cinco filas sobre TINTA
     —la tira de marca, los destinos, el contacto con el idioma, lo legal y el colofón—.

     ▶ **Sobre TINTA, y no es gusto**: la regla dura del sistema es *«tinta solo en hero, cierre y
     pie»*, y el pie del producto seguía en papel por herencia (ninguna decisión lo había elegido).
     `data-surface` re-escopa los tokens y además PINTA el fondo, así que la banda va a sangre y la
     columna la pone el `.wrap` de dentro.

     ⚠️⚠️ **EL IDIOMA VUELVE AL PIE, y revierte `#253`** (`[DECIDIDO owner, 2026-09-11]`): tres enlaces
     de verdad en la fila de contacto, siempre visibles. Son lo único que cambia de idioma **sin
     JavaScript** —el selector del menú lo abre Alpine—, así que ya no hace falta el `<noscript>` que
     los escondía: los enlaces visibles SON el suelo sin JS.

     ⚠️ **Cada dato solo si la instalación lo tiene**: sin teléfono no se emite un `tel:` roto a un
     ajuste sin rellenar (`has_phone`/`phone_tel` del composer), y sin correo no hay `mailto:` vacío.

     ⚠️⚠️ **EN LA PORTADA VA SOBRE PAPEL** (`[DECIDIDO owner, 2026-09-11]`, `DECISIONES #523`): la
     portada termina en la tarjeta de TINTA del cierre, y un pie de tinta debajo se fundía con ella
     —en su punto de reposo y, sobre todo, a pantalla completa, donde la banda rellenaba el marco de
     papel de la tarjeta y asomaba la tira por arriba—. La página lo pide con `surface="paper"`; las
     interiores siguen en tinta. Solo se aceptan las dos superficies que existen. --}}
<footer class="foot" data-surface="{{ $surface === 'paper' ? 'paper' : 'ink' }}">
    <div class="foot__inner wrap">
        {{-- ══ EL CIERRE DE LAS INTERIORES VA DENTRO DE LA BANDA (`#526`, T3a·4) ═══════════════════
             Como lo dibuja `Layout Paginas PJP`: una sola banda de tinta que empieza con la tarjeta y
             sigue con el pie. Lo piden las páginas del inventario (`closing`); la portada no, porque
             lleva el suyo, y las legales y las pantallas de servicio tampoco.
             ▶ Y dentro de la banda la barra de móvil se retira sola al llegar el cierre: se aparta
             cuando entra `.foot`, y el cierre ya es `.foot`. --}}
        @if ($closing)
            <x-site.closing />
        @endif

        {{-- La tira de marca: COMPONENTE (`#226`) y aquí su COLOCACIÓN. Va en CUÑA de 22 px y no fina
             de 4 como la dibuja el Layout: es decisión anterior del owner contra el artboard. --}}
        <x-site.brand-strip class="brand-strip--wedge foot__strip" />

        {{-- ⚠️⚠️ **UNA SOLA FILA QUE SE DESLIZA** (`#252`, `[DECIDIDO owner]`): el número de destinos lo
             manda la instalación y con muchos no caben. Con los del inventario, en escritorio suelen
             caber y entonces no hay nada que deslizar — y la vela se APAGA sola (ver su regla). El
             envoltorio existe para la VELA: un `::after` dentro del carril viajaría con el contenido. --}}
        <div class="foot__links-wrap">
            <nav class="foot__links" aria-label="{{ __('landing.footer.col_info') }}">
                @foreach ($destinos as $destino)
                    <a href="{{ $destino['url'] }}" @if (! empty($destino['current'])) aria-current="page" @endif>{{ $destino['t'] }}</a>
                @endforeach
            </nav>
        </div>

        {{-- El CONTACTO y el IDIOMA, en su propia fila y FUERA de cualquier condicional de terceros
             (la regla que salió de «Visítanos»: lo que no depende de un tercero no vive dentro de su
             condicional). --}}
        <div class="foot__contact">
            @if (! empty($site['has_phone']))
                <a class="foot__tel" href="tel:{{ $site['phone_tel'] }}">{{ $site['phone'] }}</a>
            @endif
            @if (! empty($site['email']))
                <a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>
            @endif
            {{-- ⚠️ El nombre accesible de cada idioma es su nombre NATIVO («English») y el rótulo, su
                 código («EN»): el código leído en voz alta no dice nada, y el nombre empieza por el
                 código, que es lo que exige «label in name» (WCAG 2.5.3) para quien dicta por voz. --}}
            <span class="foot__langs" role="group" aria-label="{{ __('landing.footer.language') }}">
                @foreach ($locales as $l)
                    <a href="{{ route('lang.switch', $l) }}" hreflang="{{ $l }}" lang="{{ $l }}"
                       aria-label="{{ $langNames[$l] ?? strtoupper($l) }}"
                       @if (app()->getLocale() === $l) aria-current="true" @endif>{{ strtoupper($l) }}</a>
                @endforeach
            </span>
        </div>

        {{-- ⚠️⚠️ **EL BLOQUE LEGAL ES UNA TIRA QUE SE DESLIZA** (`#264`, `[DECIDIDO owner]`), la misma
             mecánica que los destinos. «Configuración de cookies» es la única vuelta atrás de quien
             dijo que no: el mapa de «Visítanos» y las reseñas viven detrás del consentimiento. --}}
        <div class="foot__legal-wrap">
            <div class="foot__legal">
                @foreach ($legalLabels as $i => $item)
                    <a href="{{ $legalUrls[$i] ?? url('/') }}">{{ $item }}</a>
                @endforeach
                {{-- Enlace permanente para revisar/revocar el consentimiento de cookies (art. 7.3 RGPD,
                     #219): reabre el panel de preferencias. Es un botón porque actúa sobre el store Alpine. --}}
                <button type="button" class="foot__cookie-config" @click="$store.cookies.openPanel()">{{ __('cookies.banner.manage_link') }}</button>
            </div>
        </div>

        {{-- ⚠️ **El colofón NO lleva el lema ni la coletilla** (`[DECIDIDO owner]`, `#522`): el lema
             sigue siendo el `<title>` de la portada y la coletilla, el pie del formulario de invitados. --}}
        <p class="foot__colophon"><span>{{ $colofon }}</span><span>© {{ date('Y') }}</span></p>
    </div>
</footer>
