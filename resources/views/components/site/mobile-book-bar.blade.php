{{-- **EL CTA DOBLE DE MÓVIL** (`docs/specs/armazon-y-menu.md` §4.9, armazón · tanda 2c·4).

     `[DECIDIDO owner, 2026-08-27]`: la barra de abajo deja de ser un botón y pasa a ser **el CTA
     doble del mockup** — uno expandido con su subtítulo y el otro colapsado a solo icono; pulsar
     el colapsado lo expande y colapsa al otro; pulsar el expandido **actúa**.

     ▶ **Comprar sigue siendo UN solo gesto**: arranca expandido. Registrarse cuesta dos, y eso es
     la jerarquía, no un descuido.

     ⚠️⚠️ **UN BOTÓN QUE CAMBIA DE SIGNIFICADO AL PULSARLO ES UN BOTÓN QUE SE PULSA POR ERROR**, y
     por eso el nombre accesible **dice qué hace AHORA**, no a dónde lleva: cuando está colapsado
     se llama «cambiar a…», no «reservar». Sin esto, un lector de pantalla anunciaría dos botones
     que dicen lo mismo y hacen cosas distintas — y el segundo no llevaría a ninguna parte.

     ⚠️ **Y SIN JAVASCRIPT el doble paso no existe, a propósito**: los dos son `<a href>` de verdad,
     así que sin JS cada uno navega a su destino de una sola pulsación. El `href` no es decorativo
     —`/entradas` y `/registro` son PUERTAS que sirven la home y abren el cajón en su zona—, y el
     nombre accesible que se sirve es el de ACTUAR, que es lo que hacen sin JS. Alpine lo
     sustituye por el de «cambiar» solo en el que quede colapsado.

     ⚠️ El aspecto (qué mitad es ancha) lo decide el CSS a partir de UNA clase en el contenedor.
     El JS no reparte anchos: publica el estado y nada más — misma regla que el hero (`#195`) y
     que el recorte del menú (`#201`).

     La visibilidad de la barra —aparece tras el hero, se esconde en el pie, con el cajón abierto,
     con el banner de cookies— no cambia: sigue en `Alpine.data('mobileBookBar')`. --}}
<div class="book-bar" x-data="mobileBookBar"
     :class="[visible && 'book-bar--on', mode === 'signup' && 'book-bar--signup']"
     x-effect="document.body.classList.toggle('book-bar-visible', visible)">
    <div class="book-bar__pair">

        {{-- Mitad A · COMPRAR. Expandida al cargar. --}}
        <a href="{{ route('entradas') }}"
           class="cta-prime book-bar__cta book-bar__cta--buy"
           aria-label="{{ __('landing.hero.cta_buy') }}"
           :aria-label="mode === 'buy' ? '{{ __('landing.hero.cta_buy') }}' : '{{ __('landing.nav.cta_switch_buy') }}'"
           @click.prevent="mode === 'buy' ? $store.purchase.open() : (mode = 'buy')">
            <span class="cta-prime__ico"><x-icons.ic-e2 :width="54" :height="35" /></span>
            <span class="cta-prime__body">
                <span class="cta-prime__t">{{ __('landing.hero.cta_buy') }}</span>
                <span class="cta-prime__s">
                    @if (! empty($ctaMinPriceLabel))
                        {{ __('landing.hero.cta_buy_from', ['amount' => $ctaMinPriceLabel]) }}
                    @else
                        {{ __('landing.hero.cta_buy_no_price') }}
                    @endif
                </span>
            </span>
            <span class="cta-prime__arrow" aria-hidden="true">→</span>
        </a>

        {{-- Mitad B · la CUENTA. Colapsada al cargar.
             · Sin sesión y con trámite externo configurado → lleva al sistema del parque, en
               pestaña nueva, y su glifo es el portapapeles: es un FORMULARIO, no un alta.
             · Sin sesión y sin trámite externo → crea una cuenta, con la pareja de `user`.
             · Con sesión → abre el área de cliente.
             Es el mismo reparto que el racimo de la cabecera; aquí solo cambia el envase. --}}
        @guest
            @if (! empty($site['registration_url']))
                <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener"
                   class="cta-prime book-bar__cta book-bar__cta--alt"
                   aria-label="{{ $site['registration_label'] }}"
                   :aria-label="mode === 'signup' ? '{{ $site['registration_label'] }}' : '{{ __('landing.nav.cta_switch_signup') }}'"
                   @click="if (mode !== 'signup') { $event.preventDefault(); mode = 'signup'; }">
                    <span class="cta-prime__ico"><x-icons.clipboard-check :width="26" :height="26" /></span>
                    <span class="cta-prime__body">
                        <span class="cta-prime__t">{{ $site['registration_label'] }}</span>
                        @if (! empty($site['registration_subtitle']))
                            <span class="cta-prime__s">{{ $site['registration_subtitle'] }}</span>
                        @endif
                    </span>
                    <span class="cta-prime__arrow" aria-hidden="true">→</span>
                </a>
            @else
                <a href="{{ route('registro') }}"
                   class="cta-prime book-bar__cta book-bar__cta--alt"
                   aria-label="{{ __('landing.nav.reserve') }}"
                   :aria-label="mode === 'signup' ? '{{ __('landing.nav.reserve') }}' : '{{ __('landing.nav.cta_switch_signup') }}'"
                   @click.prevent="mode === 'signup' ? $store.purchase.openAccount($event, 'register') : (mode = 'signup')">
                    <span class="cta-prime__ico"><x-icons.user-plus :width="26" :height="26" /></span>
                    <span class="cta-prime__body">
                        <span class="cta-prime__t">{{ __('landing.nav.reserve') }}</span>
                        <span class="cta-prime__s">{{ __('landing.nav.cta_switch_signup_sub') }}</span>
                    </span>
                    <span class="cta-prime__arrow" aria-hidden="true">→</span>
                </a>
            @endif
        @else
            <a href="{{ route('account') }}"
               class="cta-prime book-bar__cta book-bar__cta--alt"
               aria-label="{{ __('landing.footer.account_link') }}"
               :aria-label="mode === 'signup' ? '{{ __('landing.footer.account_link') }}' : '{{ __('landing.nav.cta_switch_account') }}'"
               @click.prevent="mode === 'signup' ? $store.purchase.open() : (mode = 'signup')">
                <span class="cta-prime__ico"><x-icons.user :width="26" :height="26" /></span>
                <span class="cta-prime__body">
                    <span class="cta-prime__t">{{ __('landing.footer.account_link') }}</span>
                    <span class="cta-prime__s">{{ __('landing.nav.cta_account_sub') }}</span>
                </span>
                <span class="cta-prime__arrow" aria-hidden="true">→</span>
            </a>
        @endguest
    </div>
</div>
