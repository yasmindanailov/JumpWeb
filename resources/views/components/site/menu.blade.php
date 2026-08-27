@props(['items'])

{{-- **EL MENÚ A PANTALLA COMPLETA** (`docs/specs/armazon-y-menu.md`, tanda 2c·1).

     Sustituye a los dos desplegables de la barra. Adopta la ESTRUCTURA del mockup del segundo
     cliente —lista plana numerada sobre superficie de tinta, revelada con un recorte circular
     desde la hamburguesa— y **ni un valor suyo**: color, canto, foco y sombra salen de los
     tokens de las tandas 1, 2a y la elevación.

     ⚠️⚠️ **Lo único del mockup que NO se copia es cómo se oculta.** Su menú cerrado se esconde
     con `clip-path` y `pointer-events:none`, sin `visibility`, sin `inert` y sin `aria-hidden`
     (medido: cero `inert` en el fichero), así que **sus enlaces siguen en el orden de
     tabulación estando cerrado**. Aquí se oculta con `visibility:hidden`, que es el mecanismo
     que el cajón de móvil ya tenía y cuyo porqué está escrito ahí: `aria-hidden` dejaba
     foco-fantasma. El recorte circular se queda para la ANIMACIÓN, no para el estado.

     ▶ **Es el segundo consumidor de la tanda 1**, después del hero: declara
     `data-surface="ink"` y con eso los siete tokens de superficie se re-escopan solos. No hay
     ni un color escrito a mano.

     ▶ **La lista es PLANA y la sigue mandando la BD** (`[DECIDIDO owner]`, spec §4.2): la
     compone `site.nav` a partir de las mismas fuentes que tenía la barra, con los servicios que
     el panel marca con «sale en el menú». Aquí solo se pinta.

     ⚠️ **El número es DECORACIÓN**: va `aria-hidden`, y el nombre accesible del enlace es su
     título. Si el número entrara en el nombre, un lector de pantalla anunciaría «cero uno
     zonas». --}}

<div class="menu" data-surface="ink"
     :class="menuOpen && 'menu--open'"
     @keydown.escape.window="menuOpen = false"
     @keydown="trapMenu($event)">

    {{-- Trama de puntos: la única textura que el sistema del cliente admite sobre tinta, y aquí
         se dibuja con un token de color, no con un literal. --}}
    <div class="menu__grain" aria-hidden="true"></div>

    <div class="menu__inner" role="dialog" aria-modal="true"
         aria-label="{{ __('landing.nav.menu_label') }}" x-ref="menuPanel">

        <ul class="menu__list">
            @foreach ($items as $i => $item)
                <li class="menu__item" style="--i: {{ $i }}">
                    <a href="{{ $item['url'] }}" @click="menuOpen = false">
                        <span class="menu__n" aria-hidden="true">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="menu__t">{{ $item['t'] }}</span>
                        @if (! empty($item['s']))
                            <span class="menu__s">{{ $item['s'] }}</span>
                        @endif
                        <span class="menu__arrow" aria-hidden="true">
                            <x-icons.arrow-right :width="20" :height="20" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Fila de cápsulas secundarias (`[DECIDIDO owner]`, spec §5): acceder + idioma + redes.
             ⚠️ El idioma sube aquí ADEMÁS de quedarse en el pie: con la barra retirada, quien
             está a mitad de página no debería tener que bajar hasta el final para cambiarlo.
             ⚠️ Y en móvil «Acceder» dejará de ser secundario: cuando el racimo pierda el botón
             de registro (tanda 2c·4), ésta es la única puerta de la cuenta. --}}
        <div class="menu__foot">
            <ul class="menu__chips">
                <li>
                    @guest
                        {{-- El `href` no es decorativo: `/login` es una PUERTA que sirve la home y
                             abre el cajón en la zona de acceso, así que el clic central, «abrir en
                             pestaña nueva» y un navegador sin JS acaban en la misma pantalla por el
                             camino largo. Mismo trato que el CTA de alta de la barra. --}}
                        <a href="{{ route('login') }}" class="menu__chip"
                           x-on:click="menuOpen = false; $store.purchase.openAccount($event, 'login')">
                            {{ __('account.nav.login') }}
                        </a>
                    @else
                        <button type="button" class="menu__chip"
                                @click="menuOpen = false; $store.purchase.open()">
                            {{ __('landing.footer.account_link') }}
                        </button>
                    @endguest
                </li>

                {{-- Selector de idioma: MISMO patrón que el del pie (`.lang-dd`), sin la variante
                     `--up` porque aquí se abre hacia abajo. Los idiomas salen de `SiteLocales`,
                     que es la fuente única — el pie tenía su propia copia de la lista y **dos
                     listas de idiomas es cómo se acaba ofreciendo uno que la otra no reconoce**
                     (lo dice el docblock de esa clase, y aquí se respeta). --}}
                <li>
                    <div class="lang-dd" x-data="{ open: false }"
                         :class="open && 'lang-dd--open'"
                         @click.outside="open = false"
                         @keydown.escape.window="open = false">
                        <button type="button" class="lang-dd__trigger menu__chip"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                                aria-haspopup="menu"
                                aria-label="{{ __('landing.footer.language') }}">
                            {{ \App\Domain\Platform\Services\SiteLocales::NAMES[app()->getLocale()] ?? strtoupper(app()->getLocale()) }}
                            <svg class="chev" width="9" height="9" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                                <path d="M2 4l3 3 3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <div class="lang-dd__panel" role="menu">
                            @foreach (\App\Domain\Platform\Services\SiteLocales::SUPPORTED as $l)
                                @php($isActive = app()->getLocale() === $l)
                                <a href="{{ route('lang.switch', $l) }}"
                                   class="{{ $isActive ? 'active' : '' }}"
                                   role="menuitem"
                                   hreflang="{{ $l }}"
                                   @if ($isActive) aria-current="true" @endif>
                                    <span>{{ strtoupper($l) }}</span><span class="name">{{ \App\Domain\Platform\Services\SiteLocales::NAMES[$l] ?? strtoupper($l) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </li>

                {{-- Redes: solo si la instalación las tiene configuradas. Las URLs llegan ya
                     saneadas por `safeExternalUrl` desde el composer global (`SEC-07`): el marcado
                     no puede ser el sitio donde se confía en una URL editable. El `?? '#'` del
                     composer significa «no configurada», así que ese valor NO pinta cápsula. --}}
                @foreach (['instagram' => 'Instagram', 'tiktok' => 'TikTok'] as $key => $label)
                    @if (! empty($site[$key]) && $site[$key] !== '#')
                        <li>
                            <a href="{{ $site[$key] }}" class="menu__chip" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                        </li>
                    @endif
                @endforeach
            </ul>

            {{-- **El eslogan a rotulador** (`[DECIDIDO owner, 2026-08-27]`: «la idea es 1:1 al
                 mockup»). Es el mismo texto y la misma fuente que el del hero, y sale de la MISMA
                 clave: repetirlo en otra clave sería dos copys que se separan solos.

                 ⚠️ **Su auditoría lo marcaba como repetido** (`T-02`: «Permanent Marker aparece dos
                 veces — hero y pie del menú; máx. una por página»). No lo incumple, y el motivo es
                 geométrico: **el menú es `inset: 0` y tapa el hero entero al abrirse**, así que los
                 dos nunca están en pantalla a la vez. La norma habla de por PANTALLA. --}}
            <p class="menu__slogan">{{ __('landing.hero.kicker') }}</p>
        </div>
    </div>
</div>
