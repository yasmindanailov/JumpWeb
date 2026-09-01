@php
    use Filament\Support\Icons\Heroicon;

    /** @var array{color: string, icon: Heroicon, heading: ?string, body: ?string, note: ?string}|null $semaphore */
@endphp

<div class="gate">
    <header class="gate-head">
        <div>
            <h1 class="gate-head__title">{{ __('admin.puerta.validar.title') }}</h1>
            <p class="gate-head__intro">{{ __('admin.puerta.validar.intro') }}</p>
        </div>
        <a href="{{ url('/admin') }}" class="gate-head__back">{{ __('admin.puerta.validar.back_to_panel') }}</a>
    </header>

    {{-- Búsqueda. Fase 6 · subsistema A: el lector de carnés es un «keyboard wedge» — teclea el token y
         un Enter en ESTE input, así que escanear y teclear son el mismo formulario (§9.2 A·5). --}}
    <form wire:submit="search" class="gate-search">
        <label for="input" class="fi-sr-only">
            {{ $canViewProfile ? __('admin.puerta.validar.input_placeholder_card') : __('admin.puerta.validar.input_placeholder') }}
        </label>
        <div class="gate-search__row">
            <input
                id="input"
                type="text"
                wire:model="input"
                placeholder="{{ $canViewProfile ? __('admin.puerta.validar.input_placeholder_card') : __('admin.puerta.validar.input_placeholder') }}"
                autocomplete="off"
                autofocus
                inputmode="text"
                class="gate-search__input"
                {{-- ▶ EL CURSOR VIVE AQUÍ. Tres momentos, y cada uno es un caso distinto:

                     1. `x-init` — al abrir la pantalla. El `autofocus` de arriba ya lo intenta, pero
                        depende de que el navegador lo honre; esto lo hace determinista.
                     2. `focus.window` / `visibilitychange` — **al VOLVER desde otro programa**. Es el
                        caso que `autofocus` NO cubre: el empleado se va al TPV y regresa, la página
                        no se recarga —solo recupera el foco de ventana— y el cursor se había
                        quedado fuera. Sin esto hay que ir a pinchar el campo antes de cada escaneo.
                        ⚠️ Con `preventScroll` a propósito: si estaba leyendo la ficha del cliente
                        más abajo, recuperar el cursor no debe arrastrarle de vuelta arriba. El
                        lector escribe igual en un campo enfocado aunque no esté a la vista.
                     3. `gate-input-cleared` — tras cada búsqueda válida, que lo emite `search()`.

                     Cada listener es una mitad muda sin la otra: si se cae el `dispatch()` del
                     componente, el (3) no se entera y nada falla a la vista. `GateKioskTest` los
                     asevera por separado. --}}
                {{-- SIN `x-data`: un componente Livewire YA es un componente Alpine, así que `x-init`
                     y `x-on` funcionan sin declarar ámbito, y abrir uno propio sobre un elemento con
                     `wire:model` es meterse en un cruce que no hace falta.
                     ⚠️ Nota honesta para el que venga: llegué a escribir aquí que «medido, con
                     `x-data` la búsqueda dejaba de abrir ninguna ficha». **Era falso.** Lo que
                     pasaba es que mis propios sondeos habían agotado el limitador de búsquedas
                     TECLEADAS por hora (`puerta.lookup_rate_limited`) y la pantalla contestaba
                     «demasiadas búsquedas». El limitador funcionaba; el diagnóstico, no. --}}
                x-init="$el.focus()"
                x-on:focus.window="$el.focus({ preventScroll: true })"
                x-on:visibilitychange.document="document.hidden || $el.focus({ preventScroll: true })"
                x-on:gate-input-cleared.window="$el.focus()"
            />
            <x-filament::button type="submit" size="lg" :icon="Heroicon::OutlinedMagnifyingGlass" class="gate-search__btn">
                <span wire:loading.remove wire:target="search">{{ __('admin.puerta.validar.button') }}</span>
                <span wire:loading wire:target="search">{{ __('admin.puerta.validar.button_loading') }}</span>
            </x-filament::button>
        </div>
    </form>

    @if ($result !== null && $semaphore !== null)
        <div class="gate-result" wire:key="result-{{ uniqid() }}" role="status" aria-live="polite">
            {{-- EL SEMÁFORO, UN solo componente (§9.7 C·5). Antes eran 8 tarjetas que repetían el mismo
                 continente con la paleta escrita a mano en cada una. El tono lo decide
                 `GateSemaphore`, no la plantilla. Los `data-gate-*` de los estados del carné se
                 conservan: los miran los tests y los guiones headless. --}}
            <x-filament::callout
                :color="$semaphore['color']"
                :icon="$semaphore['icon']"
                :heading="$semaphore['heading']"
                class="gate-callout"
                data-gate-status="{{ $result['status'] }}"
                {{-- Marcas de estado que ya miraban los guiones headless y `/root/e2e`: se CONSERVAN.
                     `null` = atributo ausente (lo descarta `ComponentAttributeBag`); dentro de la
                     etiqueta de un componente no cabe un `@if`, y por eso van así y no con directivas. --}}
                :data-gate-card-revoked="$result['status'] === \App\Livewire\Admin\Puerta\ValidarRegistro::STATUS_CARD_REVOKED ? '' : null"
                :data-gate-card-unknown="$result['status'] === \App\Livewire\Admin\Puerta\ValidarRegistro::STATUS_CARD_UNKNOWN ? '' : null"
                :data-gate-lookup-limited="$result['status'] === \App\Livewire\Admin\Puerta\ValidarRegistro::STATUS_LOOKUP_LIMITED ? '' : null"
            >
                {{-- ⚠️⚠️ EL CUERPO VA EN `description`, NO EN EL SLOT POR DEFECTO. MEDIDO (2026-08-28):
                     `x-filament::callout` **no imprime su `$slot` por defecto** —solo `heading`, `description`,
                     `footer` y `controls`— así que la frase que explica el estado al empleado se
                     perdía entera. En los tres estados que hablan de la BÚSQUEDA
                     (`invalid_input` · `rate_limited` · `lookup_limited`) el `heading` es `null` a
                     propósito y el cuerpo era el ÚNICO texto: el callout salía con un icono, el eco
                     de lo tecleado y NI UNA PALABRA. `GateSemaphoreTest` no lo veía porque asevera
                     sobre el DATO que devuelve `GateSemaphore::for()`, no sobre lo PINTADO; la guarda
                     que sí lo ve es `test_every_semaphore_state_paints_its_body`.
                     Van en `<span>` y no en `<p>` porque `.fi-callout-description` YA es un `<p>`
                     (`vendor/filament/support/.../callout.blade.php`) y un `<p>` dentro de otro es
                     HTML inválido: el navegador cierra el primero y la nota se sale de la caja.
                     `theme.css` les da `display:block`. --}}
                <x-slot name="description">
                    @if ($semaphore['body'] !== null)
                        <span class="gate-callout__body">{{ $semaphore['body'] }}</span>
                    @endif
                    @if ($semaphore['note'] !== null)
                        <span class="gate-callout__note" data-gate-outdated>{{ $semaphore['note'] }}</span>
                    @endif
                </x-slot>

                <x-slot name="footer">
                    @if (! empty($result['query']))
                        {{-- Eco del dato que TECLEÓ el empleado (o «Carné escaneado»), no un dato sacado
                             del User: por eso puede enseñarse aunque el semáforo sea de los que no
                             revelan nada (§4.6). --}}
                        <p class="gate-callout__query" data-gate-query title="{{ $result['query'] }}">
                            <span class="gate-callout__query-label">{{ __('admin.puerta.validar.searched_for') }}</span>
                            <span class="gate-callout__query-value">{{ $result['query'] }}</span>
                        </p>
                    @endif
                </x-slot>
            </x-filament::callout>

            {{-- Fase 6 · subsistema A — LA FICHA (`specs/identidad-qr-puerta.md` §4.6, §4.8, §9.2 A·6/A·7).
                 Solo llega aquí con `puerta.profile`. Dos relojes en el navegador —el velo por inactividad
                 (60 s) y el cierre al TTL— que son Alpine y NO son la garantía: la ficha caduca en el
                 SERVIDOR (`ensureFresh()`). Cualquier clic o tecla reinicia los dos. --}}
            @if ($profile !== null)
                @php
                    $waiver = $profile['waiver'];
                @endphp
                <section
                    x-data="{
                        veiled: false, veilTimer: null, closeTimer: null,
                        arm() {
                            clearTimeout(this.veilTimer); clearTimeout(this.closeTimer); this.veiled = false;
                            this.veilTimer = setTimeout(() => { this.veiled = true }, 60000);
                            this.closeTimer = setTimeout(() => { $wire.clear() }, {{ (int) $profile['ttl_minutes'] * 60000 }});
                        }
                    }"
                    x-init="arm()"
                    x-on:click.window="arm()"
                    x-on:keydown.window="arm()"
                    class="gate-profile"
                    data-gate-profile
                    data-gate-via="{{ $profile['via'] }}"
                >
                    <div x-show="veiled" x-cloak class="gate-veil" data-gate-veil>
                        {{ __('admin.puerta.validar.profile.veil') }}
                    </div>

                    <div class="gate-profile__body" x-bind:class="{ 'gate-profile__body--veiled': veiled }">
                        {{-- 1 · Identidad: nombre, cómo se abrió la ficha y los dos estados que se leen
                             de un vistazo (exención y QR). --}}
                        <header class="gate-id">
                            <div class="gate-id__main">
                                <p class="gate-id__eyebrow">{{ __('admin.puerta.validar.profile.title') }}</p>
                                <h2 class="gate-id__name" data-gate-holder>{{ $profile['holder_name'] }}</h2>
                            </div>
                            <div class="gate-id__badges">
                                {{-- «Cómo se abrió la ficha» no es adorno: un escaneo es alto volumen y
                                     legítimo; una búsqueda TECLEADA es la que convierte la puerta en un
                                     oráculo y la que lleva limitador propio (§4.6·1). Que el empleado lo
                                     vea es la mitad visible de ese contrapeso. --}}
                                <x-filament::badge
                                    :color="$profile['via'] === 'card' ? 'primary' : 'gray'"
                                    :icon="$profile['via'] === 'card' ? Heroicon::OutlinedQrCode : Heroicon::OutlinedMagnifyingGlass"
                                    data-gate-via-badge="{{ $profile['via'] }}"
                                >
                                    {{ __('admin.puerta.validar.profile.via_'.$profile['via']) }}
                                </x-filament::badge>

                                @if ($profile['visit_registered_today'])
                                    <x-filament::badge color="success" :icon="Heroicon::OutlinedCheckCircle" data-gate-visit-badge>
                                        {{ __('admin.puerta.validar.profile.visit_registered') }}
                                    </x-filament::badge>
                                @endif
                            </div>
                        </header>

                        <div class="gate-grid">
                        {{-- ⚠️ DOS COLUMNAS DE VERDAD, no una rejilla (#234). Con `grid` las dos
                             columnas COMPARTEN las alturas de fila: medido, «Hoy» (98 px) vivía en la
                             fila de «Exención» (187) y dejaba **89 px vacíos** debajo antes de «Otros
                             días». Ninguna propiedad de rejilla arregla eso —la fila es la fila—, así
                             que cada columna es su propia pila y solo el reparto horizontal es grid. --}}
                        <div class="gate-col" data-gate-col="main">
                            {{-- 2 · HOY (en plural): los tres estados en voz alta (§4.6). --}}
                            <x-filament::section
                                :heading="__('admin.puerta.validar.profile.today')"
                                :icon="Heroicon::OutlinedTicket"
                                icon-color="primary"
                                compact
                                class="gate-grid__wide"
                            >
                                @if ($profile['today_reservations'] === [])
                                    <p class="gate-empty" data-gate-today-empty>{{ __('admin.puerta.validar.profile.today_empty') }}</p>
                                @else
                                    <ul class="gate-res-list" data-gate-today>
                                        @foreach ($profile['today_reservations'] as $r)
                                            @include('livewire.admin.puerta.partials.reservation', ['r' => $r])
                                        @endforeach
                                    </ul>
                                @endif
                            </x-filament::section>

                            {{-- 5 · La ventana ±N, en segundo plano: «tiene reserva, pero otro día» NO es
                                 «no tiene nada» (§4.6, estado 2). --}}
                            @if ($profile['window'] !== [])
                                <x-filament::section
                                    :heading="__('admin.puerta.validar.profile.window', ['days' => $profile['window_days']])"
                                    :description="$profile['today_reservations'] === [] ? __('admin.puerta.validar.profile.window_note') : null"
                                    :icon="Heroicon::OutlinedCalendarDays"
                                    icon-color="gray"
                                    compact
                                    secondary
                                    class="gate-grid__wide gate-window"
                                >
                                    <ul class="gate-res-list" data-gate-window>
                                        @foreach ($profile['window'] as $r)
                                            @include('livewire.admin.puerta.partials.reservation', ['r' => $r])
                                        @endforeach
                                    </ul>
                                </x-filament::section>
                            @endif

                        </div>

                        <div class="gate-col" data-gate-col="side">
                            {{-- 1b · EXENCIÓN. Tarjeta propia y no solo una píldora: es el dato por el
                                 que existía esta pantalla y el que decide si el cliente salta. Una
                                 versión ANTERIOR del texto NO frena a nadie (§4.8): se señala en ámbar
                                 y se deja pasar. --}}
                            <x-filament::section
                                :heading="__('admin.puerta.validar.profile.waiver_section')"
                                :icon="Heroicon::OutlinedClipboardDocumentCheck"
                                :icon-color="! $waiver['enabled'] ? 'gray' : ($waiver['signed'] ? ($waiver['outdated'] ? 'warning' : 'success') : 'warning')"
                                compact
                                class="gate-waiver"
                            >
                                @if (! $waiver['enabled'])
                                    <p class="gate-empty" data-gate-waiver="disabled">{{ __('admin.puerta.validar.profile.waiver_disabled') }}</p>
                                @elseif ($waiver['signed'])
                                    <p class="gate-fact" data-gate-waiver="{{ $waiver['outdated'] ? 'outdated' : 'current' }}">
                                        <x-filament::badge :color="$waiver['outdated'] ? 'warning' : 'success'" size="lg">
                                            {{ __('admin.puerta.validar.profile.waiver_signed', ['date' => \Illuminate\Support\Carbon::parse($waiver['accepted_on'])->format('d/m/Y')]) }}
                                        </x-filament::badge>
                                    </p>
                                    @if ($waiver['outdated'])
                                        <p class="gate-hint">{{ __('admin.puerta.validar.profile.waiver_outdated') }} — {{ __('admin.waiver.gate_outdated') }}</p>
                                    @endif
                                @else
                                    <p class="gate-fact" data-gate-waiver="missing">
                                        <x-filament::badge color="warning" size="lg">{{ __('admin.puerta.validar.profile.waiver_missing') }}</x-filament::badge>
                                    </p>
                                    {{-- `#336` — **DOS caminos, y los separa que haya ACEPTACIÓN RETENIDA.**
                                         · La aceptó al registrarse y solo le falta verificar el correo → el
                                           operador puede DAR FE con la persona delante, en un gesto.
                                         · No aceptó nada → el flujo de siempre: pásale la tablet. El operador
                                           confirma una aceptación que existe; nunca la inventa. --}}
                                    @if ($profile['waiver']['pending_acceptance'] ?? false)
                                        <p class="gate-hint">{{ __('admin.puerta.validar.profile.waiver_pending_hint') }}</p>
                                        @if ($profile['waiver_declare_stale'] ?? false)
                                            <p class="gate-hint" data-gate-waiver-stale>{{ __('admin.puerta.validar.profile.waiver_declare_stale') }}</p>
                                        @endif
                                        <x-filament::button
                                            wire:click="declareWaiver"
                                            {{-- ⚠️ Sin fecha de aceptación: NO hay columna que la guarde, y ponerla
                                                 desde `created_at` del titular sería afirmar como hecho algo
                                                 derivado. Se nombra a la persona, que es lo que el operador
                                                 tiene delante. --}}
                                            wire:confirm="{{ __('admin.puerta.validar.profile.waiver_declare_confirm', ['name' => $profile['holder_name'] ?? '']) }}"
                                            size="lg" color="warning" data-gate-declare-waiver>
                                            {{ __('admin.puerta.validar.profile.waiver_declare') }}
                                        </x-filament::button>
                                    @else
                                        <p class="gate-hint">{{ __('admin.puerta.validar.registered_no_waiver_cta') }}</p>
                                    @endif
                                @endif
                            </x-filament::section>


                            {{-- 3 · Menores a cargo: NOMBRE de pila, edad y estado de la exención
                                 (§4.6 fila 3, revisado en `#236`).
                                 ⚠️⚠️ **CORRECCIÓN**: hasta `#236` aquí NO iba el nombre, por
                                 minimización. Lo cambió el owner por un motivo que la versión
                                 anterior no resolvía: con tres niños y una firma que falta,
                                 «7 años ✗» no dice a CUÁL, y el empleado no puede trabajar.
                                 ▶ Lo que sigue fuera, y es estructural: **los apellidos y el
                                 correo**. `GateProfileData` no tiene campo de apellidos, así que
                                 esta plantilla no puede ser el sitio por donde entren —igual que
                                 antes no podía serlo para el nombre—, y `ValidarRegistroProfileTest`
                                 lo vigila metiendo apellidos en el estado. --}}
                            <x-filament::section
                                :heading="__('admin.puerta.validar.profile.minors')"
                                :icon="Heroicon::OutlinedUserGroup"
                                icon-color="gray"
                                compact
                            >
                                @if ($profile['dependents'] === [])
                                    <p class="gate-empty" data-gate-minors-empty>{{ __('admin.puerta.validar.profile.minors_empty') }}</p>
                                @else
                                    <ul class="gate-minors" data-gate-minors>
                                        @foreach ($profile['dependents'] as $m)
                                            <li class="gate-minor" data-gate-minor data-gate-minor-name="{{ $m['name'] ?? '' }}" data-gate-minor-age="{{ (int) $m['age'] }}" data-gate-minor-waiver="{{ $m['waiver'] ?? 'unknown' }}">
                                                {{-- `#236`: NOMBRE de pila y edad. Los apellidos no llegan hasta aquí —el DTO no los
                                                     trae—, así que esta plantilla no puede ser el sitio por donde entren. --}}
                                                <span class="gate-minor__name">{{ $m['name'] ?? '' }}</span>
                                                <span class="gate-minor__age">{{ __('admin.puerta.validar.profile.minor', ['age' => (int) $m['age']]) }}</span>
                                                {{-- `#320`: solo la EXCEPCIÓN lleva pastilla (ver el gemelo
                                                     en `partials/reservation.blade.php`). --}}
                                                @if (\App\Domain\Identity\Services\WaiverStatus::minorStateIsNoteworthy($m['waiver']))
                                                    <x-filament::badge size="xs" :color="$m['waiver'] === 'outdated' ? 'warning' : 'danger'">
                                                        {{ __('admin.puerta.validar.profile.minor_waiver_'.$m['waiver']) }}
                                                    </x-filament::badge>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </x-filament::section>

                            {{-- Los menores INVITADOS de las reservas de HOY
                                 (`specs/waiver-por-reserva.md` §4.11, `#337`): niños que NO son
                                 menores a cargo de este titular y a los que un adulto sin cuenta
                                 autorizó desde el enlace del pedido.
                                 ⚠️ Sección APARTE de la de menores a cargo, y no es cosmética: son
                                 personas distintas con un régimen distinto —éstas son puntuales,
                                 cuelgan del PEDIDO y su firmante no es el titular—, y mezclarlas
                                 haría creer al operador que este adulto responde por todas.
                                 ⚠️ Se pinta SOLO si hay alguno: una sección vacía en la ficha de un
                                 cliente normal sería ruido en la pantalla que más se mira.
                                 ▶ Apellidos fuera, como en la de arriba: el DTO no los trae. --}}
                            @if (($profile['guest_minors'] ?? []) !== [])
                                <x-filament::section
                                    :heading="__('admin.puerta.validar.profile.guest_minors')"
                                    :icon="Heroicon::OutlinedTicket"
                                    icon-color="gray"
                                    compact
                                >
                                    <ul class="gate-minors" data-gate-guest-minors>
                                        @foreach ($profile['guest_minors'] as $g)
                                            <li class="gate-minor" data-gate-guest-minor data-gate-guest-minor-name="{{ $g['name'] ?? '' }}" data-gate-guest-minor-age="{{ (int) $g['age'] }}" data-gate-guest-minor-waiver="{{ $g['waiver'] ?? 'unknown' }}">
                                                <span class="gate-minor__name">{{ $g['name'] ?? '' }}</span>
                                                <span class="gate-minor__age">{{ __('admin.puerta.validar.profile.minor', ['age' => (int) $g['age']]) }}</span>
                                                <span class="gate-minor__age">{{ $g['order_code'] ?? '' }}</span>
                                                {{-- `#320`: solo la EXCEPCIÓN lleva pastilla. --}}
                                                @if (\App\Domain\Identity\Services\WaiverStatus::minorStateIsNoteworthy($g['waiver']))
                                                    <x-filament::badge size="xs" :color="$g['waiver'] === 'outdated' ? 'warning' : 'danger'">
                                                        {{ __('admin.puerta.validar.profile.minor_waiver_'.$g['waiver']) }}
                                                    </x-filament::badge>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </x-filament::section>
                            @endif

                            {{-- ⚠️ AQUÍ IBA LA TARJETA DE «VISITA» (#234, `[DECIDIDO owner]`): se retira
                                 HASTA QUE EXISTA JUMPPOINTS, que es lo único que da sentido a acreditar
                                 una visita, y entonces se decide bien dónde y cómo va.
                                 ❗ **Consecuencia, y es de datos, no de pantalla**: era el ÚNICO sitio
                                 desde el que se registraba una visita, así que a partir de ahora
                                 `customer_visits` deja de crecer. La maquinaria sigue entera y probada
                                 —`registerVisit()`, el hecho idempotente por día y `GateVisitsTest`—:
                                 lo que falta es su botón. Ficha en `DEUDA.md` y en
                                 `specs/lealtad-jumppoints.md` §9. --}}
                        </div>

                        </div>

                        <p class="gate-expires">{{ __('admin.puerta.validar.profile.expires', ['minutes' => $profile['ttl_minutes']]) }}</p>
                    </div>
                </section>
            @endif

            <div class="gate-foot">
                <button type="button" wire:click="clear" class="gate-foot__btn">
                    {{ __('admin.puerta.validar.new_search') }}
                </button>
            </div>
        </div>
    @endif
</div>
