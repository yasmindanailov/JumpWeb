{{-- **EL CTA DOBLE DE MÓVIL** (`docs/specs/armazon-y-menu.md` §4.9 y §13, armazón · tanda 2c·4).

     `[DECIDIDO owner, 2026-08-27]`: la barra de abajo deja de ser un botón y pasa a ser **el CTA
     doble del mockup** — uno expandido con su subtítulo y el otro colapsado a solo icono; pulsar
     el colapsado lo expande y colapsa al otro; pulsar el expandido **actúa**.

     ⚠️⚠️ **Y desde el 2026-08-28 (`#223`) NO es «un botón parecido al del nav»: es EL MISMO
     COMPONENTE** (`[DECIDIDO owner]`). Aquí vivía `.cta-prime`, una pieza propia que tenía que
     parecerse a `.cta-med`/`.cta-ghost` y no se parecía en nada — **medido en el navegador a
     390 px: 100 px de alto contra 54, chip de icono de 64×42 contra 30×26, y las DOS mitades
     naranjas** cuando en el nav la colapsada es un fantasma de tarjeta. No era un defecto de
     ajuste: eran dos componentes que alguien tenía que mantener sincronizados a mano, y no se
     mantuvieron. Ahora la barra **no aporta forma, solo COLOCACIÓN**: qué mitad se estira y que
     el racimo ocupe el ancho del pulgar. Todo lo demás —altura, chip, tipografía, relleno,
     intercambio e invitación— lo pone `.cta-pair`, que es el mismo que pinta el nav.

     ▶ **Consecuencia de sistema, y es deliberada**: con `.cta-prime` retirado, el rol de ACCIÓN
     (`theme.action`, `#209`) **ya no pinta esta barra**. El naranja del cliente se queda en
     `.btn`, `.cartbar` y los avisos; el CTA del armazón es tinta en las doce vistas, arriba y
     abajo. Es lo mismo que `#213` decidió para el nav, aplicado al hermano que quedaba fuera.

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
     :class="[visible && 'book-bar--on', $store.ctaPair.mode === 'account' && 'book-bar--signup']"
     x-effect="document.body.classList.toggle('book-bar-visible', visible)">
    {{-- `cta-pair` = el COMPONENTE (forma y coreografía, compartido con el nav).
         `book-bar__pair` = la COLOCACIÓN (ancho de pulgar, cuál se estira).
         Los dos modificadores de estado son los del componente, no los del sitio: si fueran
         `book-bar--…` habría que duplicar cada regla de intercambio y volveríamos al problema. --}}
    <div class="cta-pair book-bar__pair"
         :class="[$store.ctaPair.mode === 'account' && 'cta-pair--account',
                  ! $store.ctaPair.touched && 'cta-pair--invita']">

        {{-- Mitad A · COMPRAR. Expandida al cargar. Mismo marcado que `nav-cta-med`. --}}
        <a href="{{ route('entradas') }}"
           class="cta-med book-bar__cta book-bar__cta--buy"
           aria-label="{{ __('landing.hero.cta_buy') }}"
           :aria-label="$store.ctaPair.mode === 'buy' ? '{{ __('landing.hero.cta_buy') }}' : '{{ __('landing.nav.cta_switch_buy') }}'"
           @click.prevent="$store.ctaPair.mode === 'buy' ? $store.purchase.open() : ($store.ctaPair.show('buy'))">
            <span class="cta-med__ico"><x-icons.ic-e2 :width="28" :height="18" /></span>
            <span class="cta-med__body">
                <span class="cta-med__t">{{ __('landing.nav.cta_buy') }}</span>
                <span class="cta-med__s">
                    @if (! empty($ctaMinPriceLabel))
                        {{ __('landing.hero.cta_buy_from', ['amount' => $ctaMinPriceLabel]) }}
                    @else
                        {{ __('landing.hero.cta_buy_no_price') }}
                    @endif
                </span>
            </span>
        </a>

        {{-- Mitad B · la CUENTA. Colapsada al cargar, y **fantasma**: relleno de tarjeta, no de
             tinta — que es lo que la distingue de la otra mitad de un vistazo.
             · Sin sesión y con trámite externo configurado → lleva al sistema del parque, en
               pestaña nueva, y su glifo es el portapapeles: es un FORMULARIO, no un alta.
             · Sin sesión y sin trámite externo → crea una cuenta, con la pareja de `user`.
             · Con sesión → abre el área de cliente.
             Es el mismo reparto que el racimo de la cabecera; aquí solo cambia la colocación.
             El aro de la invitación necesita un envoltorio posicionado, igual que en el nav. --}}
        @guest
            @if (! empty($site['registration_url']))
                <span class="cta-pair__alt">
                    <span class="cta-pair__alt-ring" aria-hidden="true"></span>
                    <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener"
                       class="cta-ghost book-bar__cta book-bar__cta--alt"
                       aria-label="{{ $site['registration_label'] }}"
                       :aria-label="$store.ctaPair.mode === 'account' ? '{{ $site['registration_label'] }}' : '{{ __('landing.nav.cta_switch_signup') }}'"
                       @click="if ($store.ctaPair.mode !== 'account') { $event.preventDefault(); $store.ctaPair.show('account'); }">
                        <span class="cta-ghost__ico"><x-icons.clipboard-check /></span>
                        <span class="cta-ghost__body">
                            <span class="cta-ghost__t">{{ $site['registration_label'] }}</span>
                            @if (! empty($site['registration_subtitle']))
                                <span class="cta-ghost__s">{{ $site['registration_subtitle'] }}</span>
                            @endif
                        </span>
                    </a>
                </span>
            @else
                <span class="cta-pair__alt">
                    <span class="cta-pair__alt-ring" aria-hidden="true"></span>
                    <a href="{{ route('registro') }}"
                       class="cta-ghost book-bar__cta book-bar__cta--alt"
                       aria-label="{{ __('landing.nav.reserve') }}"
                       :aria-label="$store.ctaPair.mode === 'account' ? '{{ __('landing.nav.reserve') }}' : '{{ __('landing.nav.cta_switch_signup') }}'"
                       @click.prevent="$store.ctaPair.mode === 'account' ? $store.purchase.openAccount($event, 'register') : ($store.ctaPair.show('account'))">
                        <span class="cta-ghost__ico"><x-icons.user-plus /></span>
                        <span class="cta-ghost__body">
                            <span class="cta-ghost__t">{{ __('landing.nav.reserve') }}</span>
                            <span class="cta-ghost__s">{{ __('landing.nav.cta_switch_signup_sub') }}</span>
                        </span>
                    </a>
                </span>
            @endif
        @else
            <span class="cta-pair__alt">
                <span class="cta-pair__alt-ring" aria-hidden="true"></span>
                <a href="{{ route('account') }}"
                   class="cta-ghost book-bar__cta book-bar__cta--alt"
                   aria-label="{{ __('landing.footer.account_link') }}"
                   :aria-label="$store.ctaPair.mode === 'account' ? '{{ __('landing.footer.account_link') }}' : '{{ __('landing.nav.cta_switch_account') }}'"
                   @click.prevent="$store.ctaPair.mode === 'account' ? $store.purchase.open() : ($store.ctaPair.show('account'))">
                    <span class="cta-ghost__ico"><x-icons.user /></span>
                    <span class="cta-ghost__body">
                        <span class="cta-ghost__t">{{ __('landing.footer.account_link') }}</span>
                        <span class="cta-ghost__s">{{ __('landing.nav.cta_account_sub') }}</span>
                    </span>
                </a>
            </span>
        @endguest
    </div>
</div>
