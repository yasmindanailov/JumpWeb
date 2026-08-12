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
    @auth
        @php($acct = app(\App\Support\CustomerAccountContext::class)->for(auth()->user()))
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
            <a href="{{ $acct['pendingFormsCount'] === 1 ? $acct['pendingForms'][0]['url'] : route('account.orders') }}" class="acct__alert">
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
            <a href="{{ route('account.orders') }}" class="acct__btn acct__btn--ghost acct__btn--reservas">
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
            {{-- En el PASO DE IDENTIFICACIÓN del flujo (paso 5: login/registro embebido) este botón se
                 BLOQUEA: el flujo ya pide identificarse abajo, así que abrir el modal de login encima
                 sería redundante. La señal `$store.purchase.identifying` la fija el puente reactivo de
                 purchase.blade (← `$wire.step`). `disabled` corta el click de forma nativa; el guard del
                 `@click` es defensa extra. Fuera de ese paso (o con el flujo cerrado) el botón vale. --}}
            <button type="button" class="acct__btn acct__btn--primary"
                    x-bind:disabled="$store.purchase.identifying"
                    @click="if (! $store.purchase.identifying) { $store.purchase.close(); $store.auth.open('login') }">
                <x-icons.login /> {{ __('account.nav.login') }}
            </button>
            {{-- «Ver mis reservas» también abre el modal de login (invitado) → se bloquea igual que
                 «Iniciar sesión» en el paso de identificación del flujo. --}}
            <button type="button" class="acct__btn acct__btn--ghost"
                    x-bind:disabled="$store.purchase.identifying"
                    @click="if (! $store.purchase.identifying) { $store.purchase.close(); $store.auth.open('login') }">
                {{ __('tickets.my_reservations') }}
            </button>
        </div>
    @endauth
</div>
