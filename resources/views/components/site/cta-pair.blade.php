@props(['place' => 'nav'])

{{-- **EL PAR DE CTA — UN SOLO COMPONENTE PARA SUS TRES SITIOS** (`#225`, 2026-08-28).

     Una mitad expandida y la otra colapsada a su icono; el primer clic en la colapsada la expande
     y colapsa a la otra, el segundo actúa. El estado lo publica `$store.ctaPair` y es UNO para
     toda la página: los tres sitios son la misma decisión, no tres decisiones parecidas.

     ⚠️⚠️ **Existe porque ya había DOS copias y divergieron** (`#223`): la barra de móvil medía
     100 px de alto contra 54, con chip de 64×42 contra 30×26 y las dos mitades naranjas. Aquello
     se arregló compartiendo el CSS. Al llegar el TERCER sitio —el CTA de la primera pantalla— la
     misma lección aplica al MARCADO: tres copias de tres ramas de sesión cada una no se mantienen
     iguales solas. Aquí hay una.

     ▶ **`place` decide COLOCACIÓN y COPY, y nada más.** La forma la pone `.cta-pair` (site.css).

     | `place` | dónde | copy |
     |---|---|---|
     | `nav`  | el racimo de la cabecera | perfil `nav` |
     | `hero` | la primera pantalla, abajo a la derecha | perfil `nav` |
     | `bar`  | la barra flotante de móvil | perfil `bar` |

     ⚠️⚠️ **El hero usa el copy del NAV a propósito, y es la razón de que exista el relevo.** Al
     bajar, el CTA del hero se funde con el de la cabecera: si el rótulo o el subtítulo cambiaran a
     mitad del cruce, se leerían dos textos distintos durante 300 ms y el relevo dejaría de parecer
     el mismo botón moviéndose para parecer dos botones peleándose.

     ⚠️ **Los dos perfiles de copy NO son un descuido y por eso no se unifican.** El nav OMITE el
     subtítulo cuando no hay precio —dejaría una línea vacía en una fila apretada— y la barra lo
     SUSTITUYE por «Cumpleaños online», porque ahí sí hay sitio y un botón mudo es peor. Las dos
     conductas están fijadas por casos propios en `HomePageTest`; unificarlas sería cambiar
     producto, no limpiar código.

     ⚠️⚠️ **UN BOTÓN QUE CAMBIA DE SIGNIFICADO AL PULSARLO SE PULSA POR ERROR**: el nombre
     accesible dice qué hace AHORA, no a dónde lleva. Colapsado se llama «cambiar a…».

     ⚠️ **Y sin JavaScript el doble paso no existe, a propósito**: las tres ramas son `<a href>` de
     verdad —`/entradas`, `/registro`, la URL del parque y `/mi-cuenta` son PUERTAS—, así que sin JS
     cada una navega a su destino de una sola pulsación, y el nombre accesible SERVIDO es el de
     ACTUAR. Alpine lo sustituye por el de «cambiar» solo en la que quede colapsada. --}}

@php
    $sitios = [
        'nav'  => ['racimo' => 'nav__pair',      'buy' => 'nav-cta-med',                      'alt' => 'nav-cta-ghost',                     'copy' => 'nav'],
        'hero' => ['racimo' => 'hero__pair',     'buy' => 'hero-cta-med',                     'alt' => 'hero-cta-ghost',                    'copy' => 'nav'],
        'bar'  => ['racimo' => 'book-bar__pair', 'buy' => 'book-bar__cta book-bar__cta--buy', 'alt' => 'book-bar__cta book-bar__cta--alt', 'copy' => 'bar'],
    ];
    // Un `place` desconocido sería un racimo sin colocación —invisible o descolocado— y eso se ve
    // en producción, no en un test. Mejor romper aquí.
    abort_unless(isset($sitios[$place]), 500, "cta-pair: `place` desconocido «{$place}»");
    $s = $sitios[$place];
    $esBarra = $s['copy'] === 'bar';

    $site = $site ?? [];
    $etiquetaPrecio = $ctaMinPriceLabel ?? null;
@endphp

<div class="cta-pair {{ $s['racimo'] }}"
     :class="[$store.ctaPair.mode === 'account' && 'cta-pair--account',
              ! $store.ctaPair.touched && 'cta-pair--invita']">

    {{-- ── MITAD A · COMPRAR. Va PRIMERA, y eso es del mockup ───────────────────────────────────
         Medido en `Landing PJP Modos`: su racimo es `[Reservar] [Registrarse] [Menú]`, con el
         botón de tinta a la izquierda del blanco. No es simetría: es el orden de lectura, el de
         más peso primero. Y arranca EXPANDIDA, que es la jerarquía —comprar cuesta un gesto y la
         cuenta dos—, no un valor por defecto cualquiera. --}}
    <a href="{{ route('entradas') }}" class="cta-med {{ $s['buy'] }}"
       aria-label="{{ $esBarra ? __('landing.hero.cta_buy') : __('landing.nav.reserve_tickets_aria') }}"
       :aria-label="$store.ctaPair.mode === 'buy' ? @js($esBarra ? __('landing.hero.cta_buy') : __('landing.nav.reserve_tickets_aria')) : @js(__('landing.nav.cta_switch_buy'))"
       @click.prevent="$store.ctaPair.mode === 'buy' ? $store.purchase.open() : $store.ctaPair.show('buy')">
        <span class="cta-med__ico"><x-icons.ic-e2 :width="28" :height="18" /></span>
        <span class="cta-med__body">
            <span class="cta-med__t">{{ __('landing.nav.cta_buy') }}</span>
            @if (! empty($etiquetaPrecio))
                <span class="cta-med__s">{{ __('landing.nav.cta_buy_from', ['amount' => $etiquetaPrecio]) }}</span>
            @elseif ($esBarra)
                {{-- Sin catálogo vendible la BARRA no se queda muda: dice lo único reservable hoy.
                     El nav, en cambio, omite la línea — ver la tabla de arriba. --}}
                <span class="cta-med__s">{{ __('landing.hero.cta_buy_no_price') }}</span>
            @endif
        </span>
        {{-- ⚠️ Aquí NO va una flecha: el CTA fijo del mockup no la lleva (`#213`). El botón ya dice
             a dónde va con su rótulo y su icono, y una flecha de más en el elemento más repetido de
             la web es ruido en las doce vistas. --}}
    </a>

    {{-- ── MITAD B · LA CUENTA. Colapsada al cargar, y FANTASMA ──────────────────────────────────
         Relleno de tarjeta, no de tinta: es lo que la distingue de la otra mitad de un vistazo.
         El envoltorio existe porque el ARO de la invitación necesita algo posicionado alrededor. --}}
    @guest
        @if (! empty($site['registration_url']))
            {{-- El parque tiene su propio trámite de registro de acceso (URL externa del panel):
                 es un FORMULARIO, no un alta de cuenta, y por eso su glifo es el portapapeles. --}}
            <span class="cta-pair__alt">
                <span class="cta-pair__alt-ring" aria-hidden="true"></span>
                <a href="{{ $site['registration_url'] }}" target="_blank" rel="noopener"
                   class="cta-ghost cta-ghost--stack {{ $s['alt'] }}"
                   aria-label="{{ $site['registration_label'] }}"
                   :aria-label="$store.ctaPair.mode === 'account' ? @js($site['registration_label']) : @js(__('landing.nav.cta_switch_signup'))"
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
            {{-- Sin trámite externo, esta rama CREA UNA CUENTA, y por eso lleva la pareja de `user`:
                 el glifo sigue al DESTINO, no a la posición del botón. --}}
            <span class="cta-pair__alt">
                <span class="cta-pair__alt-ring" aria-hidden="true"></span>
                <a href="{{ route('registro') }}" class="cta-ghost {{ $s['alt'] }}"
                   aria-label="{{ __('landing.nav.reserve') }}"
                   :aria-label="$store.ctaPair.mode === 'account' ? @js(__('landing.nav.reserve')) : @js(__('landing.nav.cta_switch_signup'))"
                   x-on:click.prevent="$store.ctaPair.mode === 'account' ? $store.purchase.openAccount($event, 'register') : $store.ctaPair.show('account')">
                    <span class="cta-ghost__ico"><x-icons.user-plus /></span>
                    <span class="cta-ghost__body">
                        <span class="cta-ghost__t">{{ __('landing.nav.reserve') }}</span>
                        @if ($esBarra)
                            <span class="cta-ghost__s">{{ __('landing.nav.cta_switch_signup_sub') }}</span>
                        @endif
                    </span>
                </a>
            </span>
        @endif
    @else
        {{-- Con sesión: abre el área de cliente. Un puntito de AVISO —el mismo amarillo con el que
             el panel dice «tienes un formulario pendiente»— si lo hay; sin aviso NO hay punto, que
             la ausencia ya significa reposo y no gasta un color de estado.
             ⚠️ El rótulo visible es «Mi cuenta», FIJO: «Hola, Marta» y «Hola, Wilhelmina» cambian
             el ancho con cada visitante. El saludo se queda en el nombre accesible, y el visible es
             PREFIJO del accesible, que es lo que exige «label in name» (WCAG 2.5.3). --}}
        @php($acct = app(\App\Domain\Identity\Services\CustomerAccountContext::class)->for(auth()->user()))
        @php($acctLabel = __('account.nav.hello', ['name' => $acct['firstName']]).($acct['hasPendingForm'] ? ' · '.__('account.nav.pending_form') : ''))
        <span class="cta-pair__alt">
            <span class="cta-pair__alt-ring" aria-hidden="true"></span>
            {{-- ⚠️ `nav__acct` es el marcador de «el chip de cuenta DE LA CABECERA» y por eso va
                 solo ahí: hay casos que lo cuentan para distinguir invitado de cliente con sesión,
                 y en los otros dos sitios contarían de más. Las otras dos mitades se identifican
                 por su clase de colocación. --}}
            <a href="{{ route('account') }}" class="cta-ghost {{ $s['alt'] }}{{ $place === 'nav' ? ' nav__acct' : '' }}"
               aria-label="{{ __('landing.footer.account_link') }} · {{ $acctLabel }}"
               :aria-label="$store.ctaPair.mode === 'account' ? @js(__('landing.footer.account_link').' · '.$acctLabel) : @js(__('landing.nav.cta_switch_account'))"
               x-on:click.prevent="$store.ctaPair.mode === 'account' ? $store.purchase.open() : $store.ctaPair.show('account')">
                <span class="cta-ghost__ico cta-pair__acct-icon">
                    <x-icons.user />
                    @if ($acct['hasPendingForm'])
                        <span class="cta-pair__acct-dot"></span>
                    @endif
                </span>
                <span class="cta-ghost__body">
                    <span class="cta-ghost__t">{{ __('landing.footer.account_link') }}</span>
                    @if ($esBarra)
                        <span class="cta-ghost__s">{{ __('landing.nav.cta_account_sub') }}</span>
                    @endif
                </span>
            </a>
        </span>
    @endguest
</div>
