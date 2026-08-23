{{--
    Bloque de cuenta del sidebar de compra (#221 + rediseño #222 sobre el mockup
    `design_mockup/Sidebar Catalogo.html`, sección «usuario»).

    Convertido a Livewire (2026-06-14): un componente Blade estático no se actualizaba tras el login
    embebido del sidebar (#69, sin recarga) → el saludo quedaba en «Hola, saltador/a». Ahora escucha
    `logged-in` y se re-renderiza en vivo (ver App\Livewire\Site\AccountContext). UN SOLO root `.acct`
    (Livewire lo exige); el modificador `--guest` y el contenido conmutan según la sesión.

    • Invitado → avatar «?» + saludo genérico + «Iniciar sesión» (principal) + «Mis reservas»
      (contorno). Ambos botones abren el modal de login (cierran el sidebar antes, patrón #216).
    • Con sesión → avatar (inicial) + «Hola, nombre» + sub-línea (próxima reserva) + aviso de
      formulario de reserva pendiente (#217) + «Cerrar sesión» (principal) + «Mis reservas» (contorno).
--}}
<div class="acct @guest acct--guest @endguest">
    {{-- ⚠️ Envoltorio INTERNO, y no es decorativo: el bloque se colapsa con la técnica grid
         `1fr → 0fr` (la misma que `.catalog-acc__body`), que anima la altura REAL sin tener que
         medirla. Esa técnica exige que el contenido cuelgue de un hijo con `overflow: hidden` y
         `min-height: 0`. El root `.acct` no puede serlo: Livewire exige UN SOLO root y ahí viven el
         fondo, el padding y el borde que también se colapsan. --}}
    <div class="acct__inner">
    @auth
        @php($acct = app(\App\Domain\Identity\Services\CustomerAccountContext::class)->for(auth()->user()))
        @php($initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($acct['firstName'], 0, 1)))
        <div class="acct__row">
            <span class="acct__avatar" aria-hidden="true">{{ $initial !== '' ? $initial : '·' }}</span>
            <span class="acct__txt">
                <span class="acct__hello">{{ __('account.nav.hello', ['name' => $acct['firstName']]) }}</span>
                {{-- Sidebar v2: tag sutil visible solo cuando la cuenta se minimiza (panel en modo
                     booking/cart); en estado normal queda oculto (CSS). --}}
                <span class="acct__tag">{{ __('account.sidecart.tag') }}</span>
                <span class="acct__sub">
                    @if ($acct['nextReservation'])
                        {{ __('account.sidecart.next', ['date' => $acct['nextReservation']['dateLabel'], 'product' => $acct['nextReservation']['productName']]) }}
                    @else
                        {{ __('account.sidecart.no_upcoming') }}
                    @endif
                </span>
            </span>
        </div>

        @if ($acct['hasPendingForm'])
            {{-- ⚠️ Con VARIOS formularios pendientes el aviso lleva a la lista, y esa lista ya vive
                 dentro del cajón: se atiende sin navegar. Con UNO solo lleva al post-form, que en la
                 tanda 1 sigue siendo una página (`specs/area-cliente.md` §4.7) → `null`, deja navegar.
                 El `href` se conserva SIEMPRE: si el motor aún no ha cargado, el enlace funciona. --}}
            <a href="{{ $acct['pendingFormsCount'] === 1 ? $acct['pendingForms'][0]['url'] : route('account.orders') }}" class="acct__alert"
               x-on:click="$store.purchase.followAccountLink($event, {{ $acct['pendingFormsCount'] === 1 ? 'null' : "'orders'" }})">
                <span class="acct__alert-ico" aria-hidden="true">!</span>
                <span class="acct__alert-text">
                    @if ($acct['pendingFormsCount'] === 1)
                        {{ __('account.sidecart.form_pending_one', ['product' => $acct['pendingForms'][0]['productName']]) }}
                    @else
                        {{ __('account.sidecart.form_pending_many', ['count' => $acct['pendingFormsCount']]) }}
                    @endif
                </span>
                <span class="acct__alert-arrow" aria-hidden="true">→</span>
            </a>
        @endif

        <div class="acct__cta">
            <form method="POST" action="{{ route('logout') }}" class="acct__logout-form">
                @csrf
                <button type="submit" class="acct__btn acct__btn--primary"><x-icons.logout /> {{ __('account.nav.sign_out') }}</button>
            </form>
            {{-- ⚠️ **«Mis reservas» ya no navega: abre el ÁREA DE CLIENTE dentro del cajón**
                 (`specs/area-cliente.md` §4.6, 2026-08-22). El `href` se conserva a propósito y NO es
                 decorativo: hasta que el chunk del motor termina de cargar, `spaHandle` es `null` y el
                 enlace tiene que seguir llevando a algún sitio — un botón que «no falla, no hace nada»
                 es la familia de fallos de `DECISIONES #117`. También es lo que hace que funcione con
                 el clic central o «abrir en pestaña nueva». --}}
            <a href="{{ route('account.orders') }}" class="acct__btn acct__btn--ghost acct__btn--reservas"
               x-on:click="$store.purchase.followAccountLink($event, 'orders')">
                {{ __('tickets.my_reservations') }}
                @if ($acct['upcomingCount'] > 0)<span class="acct__count" aria-hidden="true">{{ $acct['upcomingCount'] }}</span><span class="sr-only">{{ __('account.sidecart.upcoming_count', ['count' => $acct['upcomingCount']]) }}</span>@endif
            </a>
        </div>
    @else
        <div class="acct__row">
            <span class="acct__avatar" aria-hidden="true">?</span>
            <span class="acct__txt">
                <span class="acct__hello">{{ __('account.sidecart.guest_hello') }}</span>
                {{-- Sidebar v2: tag sutil visible solo con la cuenta minimizada (ver rama @auth). --}}
                <span class="acct__tag">{{ __('account.sidecart.tag') }}</span>
                <span class="acct__sub">{{ __('account.sidecart.guest_sub') }}</span>
            </span>
        </div>
        <div class="acct__cta">
            {{-- ⚠️⚠️ **Desde el 2026-08-23 estos dos botones NO cierran el cajón ni abren un modal**
                 (`specs/auth-en-cajon.md` §4.5): identificarse es una ZONA del propio cajón, así que
                 se conmuta de sección y el cliente **no pierde de vista su cesta** — que es justo lo
                 que cerrarlo le hacía perder.
                 ⚠️ Se usa `openAccount()` y no `followAccountLink()` aunque el cajón ya esté abierto,
                 y la diferencia importa: aquél cuelga de la promesa del motor, así que un clic dado
                 **mientras el chunk todavía carga** se atiende igual. `followAccountLink()` se
                 desentiende en esa ventana a propósito, porque sus enlaces tienen `href` que toma el
                 relevo; estos botones no lo tienen —ni les hace falta: viven dentro de un panel que
                 solo existe si el JS corre—, así que ahí el clic no haría NADA (`DECISIONES #117`).
                 ⚠️ El bloqueo durante el PASO 5 del embudo se conserva tal cual: el flujo ya está
                 pidiendo identificarse abajo, y ofrecer lo mismo arriba sigue siendo redundante. --}}
            <button type="button" class="acct__btn acct__btn--primary"
                    x-bind:disabled="$store.purchase.identifying"
                    @click="if (! $store.purchase.identifying) { $store.purchase.openAccount($event, 'login') }">
                <x-icons.login /> {{ __('account.nav.login') }}
            </button>
            {{-- «Ver mis reservas» sin sesión lleva al mismo sitio: primero hay que entrar. --}}
            <button type="button" class="acct__btn acct__btn--ghost"
                    x-bind:disabled="$store.purchase.identifying"
                    @click="if (! $store.purchase.identifying) { $store.purchase.openAccount($event, 'login') }">
                {{ __('tickets.my_reservations') }}
            </button>
        </div>
    @endauth
    </div>
</div>
