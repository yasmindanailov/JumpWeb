@php
    use App\Livewire\Admin\Puerta\ValidarRegistro;
@endphp

<div class="w-full">
    {{-- Header --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-950 dark:text-white sm:text-2xl">
                {{ __('admin.puerta.validar.title') }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('admin.puerta.validar.intro') }}
            </p>
        </div>
        <a
            href="{{ url('/admin') }}"
            class="shrink-0 text-sm font-medium text-gray-500 underline-offset-4 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-300"
        >
            {{ __('admin.puerta.validar.back_to_panel') }}
        </a>
    </div>

    {{-- Búsqueda. Fase 6 · subsistema A: el lector de carnés es un «keyboard wedge» — teclea el token y
         un Enter en ESTE input, así que escanear y teclear son el mismo formulario (§9.2 A·5). --}}
    <form wire:submit="search" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
        <label for="input" class="sr-only">
            {{ $canViewProfile ? __('admin.puerta.validar.input_placeholder_card') : __('admin.puerta.validar.input_placeholder') }}
        </label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <input
                id="input"
                type="text"
                wire:model="input"
                placeholder="{{ $canViewProfile ? __('admin.puerta.validar.input_placeholder_card') : __('admin.puerta.validar.input_placeholder') }}"
                autocomplete="off"
                autofocus
                inputmode="text"
                class="block w-full rounded-lg border-0 bg-white px-4 py-3 text-base text-gray-950 ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-amber-600 dark:bg-gray-900 dark:text-white dark:ring-white/10 dark:placeholder:text-gray-500 dark:focus:ring-amber-500"
            />
            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 dark:bg-amber-500 dark:hover:bg-amber-400"
            >
                <span wire:loading.remove wire:target="search">
                    {{ __('admin.puerta.validar.button') }}
                </span>
                <span wire:loading wire:target="search">
                    {{ __('admin.puerta.validar.button_loading') }}
                </span>
            </button>
        </div>
    </form>

    {{-- Resultado: el SEMÁFORO (3 estados con paletas Tailwind directas — green/orange/red — para no
         depender del shell Filament; esta página es minimal/standalone) más los estados del carné. --}}
    @if ($result !== null)
        <div class="mt-6" wire:key="result-{{ uniqid() }}" role="status" aria-live="polite">
            @switch($result['status'])

                @case(ValidarRegistro::STATUS_REGISTERED_WITH_WAIVER)
                    {{-- ✓ Verde: registrado + waiver. Heroicon check-circle (outline, sutil). --}}
                    <div class="flex items-start gap-3 rounded-xl bg-green-50 p-5 ring-1 ring-green-600/20 dark:bg-green-400/10 dark:ring-green-400/30">
                        <svg class="mt-0.5 h-6 w-6 shrink-0 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-green-700/70 dark:text-green-300/70">
                                {{ __('admin.puerta.validar.searched_for') }}
                            </p>
                            <p class="mt-0.5 truncate text-sm font-medium text-green-700 dark:text-green-300" title="{{ $result['query'] }}">
                                {{ $result['query'] }}
                            </p>
                            <p class="mt-3 text-base font-semibold text-green-700 dark:text-green-300">
                                {{ __('admin.puerta.validar.registered_with_waiver') }}
                            </p>
                            <p class="mt-1 text-sm text-green-700/80 dark:text-green-300/80">
                                {{ __('admin.puerta.validar.waiver_date', ['date' => $result['date']]) }}
                            </p>
                            {{-- Fase 6 · waiver (§4.8): firma de una versión ANTERIOR del texto — se señala y se
                                 deja pasar; la re-firma se pide en la siguiente compra o inicio de sesión. --}}
                            @if ($result['outdated'] ?? false)
                                <p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-300">
                                    {{ __('admin.waiver.gate_outdated') }}
                                </p>
                            @endif
                        </div>
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_REGISTERED)
                    {{-- ✓ Verde (2 estados, #216): tiene cuenta. El waiver lo gestiona el sistema externo. --}}
                    <div class="flex items-start gap-3 rounded-xl bg-green-50 p-5 ring-1 ring-green-600/20 dark:bg-green-400/10 dark:ring-green-400/30">
                        <svg class="mt-0.5 h-6 w-6 shrink-0 text-green-600 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-green-700/70 dark:text-green-300/70">
                                {{ __('admin.puerta.validar.searched_for') }}
                            </p>
                            <p class="mt-0.5 truncate text-sm font-medium text-green-700 dark:text-green-300" title="{{ $result['query'] }}">
                                {{ $result['query'] }}
                            </p>
                            <p class="mt-3 text-base font-semibold text-green-700 dark:text-green-300">
                                {{ __('admin.puerta.validar.registered') }}
                            </p>
                            <p class="mt-1 text-sm text-green-700/80 dark:text-green-300/80">
                                {{ __('admin.puerta.validar.registered_sub') }}
                            </p>
                        </div>
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_REGISTERED_NO_WAIVER)
                    {{-- ! Naranja: cuenta sí, waiver no. Heroicon exclamation-circle (outline). --}}
                    <div class="flex items-start gap-3 rounded-xl bg-orange-50 p-5 ring-1 ring-orange-600/20 dark:bg-orange-400/10 dark:ring-orange-400/30">
                        <svg class="mt-0.5 h-6 w-6 shrink-0 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-orange-700/70 dark:text-orange-300/70">
                                {{ __('admin.puerta.validar.searched_for') }}
                            </p>
                            <p class="mt-0.5 truncate text-sm font-medium text-orange-700 dark:text-orange-300" title="{{ $result['query'] }}">
                                {{ $result['query'] }}
                            </p>
                            <p class="mt-3 text-base font-semibold text-orange-700 dark:text-orange-300">
                                {{ __('admin.puerta.validar.registered_no_waiver') }}
                            </p>
                            <p class="mt-1 text-sm text-orange-700/80 dark:text-orange-300/80">
                                {{ __('admin.puerta.validar.registered_no_waiver_cta') }}
                            </p>
                        </div>
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_NOT_REGISTERED)
                    {{-- × Rojo: no existe en BD. Sin CTA (la captación se hace fuera del panel). --}}
                    <div class="flex items-start gap-3 rounded-xl bg-red-50 p-5 ring-1 ring-red-600/20 dark:bg-red-400/10 dark:ring-red-400/30">
                        <svg class="mt-0.5 h-6 w-6 shrink-0 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-red-700/70 dark:text-red-300/70">
                                {{ __('admin.puerta.validar.searched_for') }}
                            </p>
                            <p class="mt-0.5 truncate text-sm font-medium text-red-700 dark:text-red-300" title="{{ $result['query'] }}">
                                {{ $result['query'] }}
                            </p>
                            <p class="mt-3 text-base font-semibold text-red-700 dark:text-red-300">
                                {{ __('admin.puerta.validar.not_registered') }}
                            </p>
                        </div>
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_CARD_REVOKED)
                    {{-- Fase 6 · A (§4.5): carné rotado o revocado → «busca por email», un camino que ya existe. --}}
                    <div class="flex items-start gap-3 rounded-xl bg-orange-50 p-5 ring-1 ring-orange-600/20 dark:bg-orange-400/10 dark:ring-orange-400/30" data-gate-card-revoked>
                        <svg class="mt-0.5 h-6 w-6 shrink-0 text-orange-600 dark:text-orange-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <p class="text-base font-semibold text-orange-700 dark:text-orange-300">{{ __('admin.puerta.validar.card_revoked') }}</p>
                            <p class="mt-1 text-sm text-orange-700/80 dark:text-orange-300/80">{{ __('admin.puerta.validar.card_revoked_cta') }}</p>
                        </div>
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_CARD_UNKNOWN)
                    <div class="rounded-xl bg-gray-100 p-4 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300" data-gate-card-unknown>
                        <p class="font-semibold">{{ __('admin.puerta.validar.card_unknown') }}</p>
                        <p class="mt-1">{{ __('admin.puerta.validar.card_unknown_cta') }}</p>
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_INVALID_INPUT)
                    <div class="rounded-xl bg-gray-100 p-4 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        {{ __('admin.puerta.validar.invalid_input') }}
                        @if (! empty($result['query']))
                            <span class="ml-1 font-mono text-gray-500 dark:text-gray-400">(«{{ $result['query'] }}»)</span>
                        @endif
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_RATE_LIMITED)
                    <div class="rounded-xl bg-gray-100 p-4 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        {{ __('admin.puerta.validar.rate_limited') }}
                    </div>
                    @break

                @case(ValidarRegistro::STATUS_LOOKUP_LIMITED)
                    {{-- Fase 6 · A (§4.6·1): el limitador de la búsqueda TECLEADA; el escaneo sigue. --}}
                    <div class="rounded-xl bg-gray-100 p-4 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300" data-gate-lookup-limited>
                        {{ __('admin.puerta.validar.lookup_limited') }}
                    </div>
                    @break
            @endswitch

            {{-- Fase 6 · subsistema A — LA FICHA (`specs/identidad-qr-puerta.md` §4.6, §4.8, §9.2 A·6/A·7).
                 Solo llega aquí con `puerta.profile`. Dos relojes en el navegador —el velo por inactividad
                 (60 s) y el cierre al TTL— que son Alpine y NO son la garantía: la ficha caduca en el
                 SERVIDOR (`ensureFresh()`). Cualquier clic o tecla reinicia los dos. --}}
            @if ($profile !== null)
                @php
                    $waiver = $profile['waiver'];
                    $minorLabel = static function (array $m): string {
                        $label = __('admin.puerta.validar.profile.minor', ['age' => $m['age']]);

                        return $m['waiver'] === null ? $label : $label.' · '.__('admin.puerta.validar.profile.minor_waiver_'.$m['waiver']);
                    };
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
                    class="relative mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    data-gate-profile
                    data-gate-via="{{ $profile['via'] }}"
                >
                    <div x-show="veiled" x-cloak class="absolute inset-0 z-10 flex cursor-pointer items-center justify-center rounded-xl bg-gray-100/95 text-sm font-medium text-gray-700 dark:bg-gray-950/95 dark:text-gray-200" data-gate-veil>
                        {{ __('admin.puerta.validar.profile.veil') }}
                    </div>

                    <div x-bind:class="{ 'select-none blur-sm': veiled }">
                        {{-- 1 · Identidad --}}
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.puerta.validar.profile.title') }}</p>
                                <h2 class="mt-0.5 text-lg font-bold text-gray-950 dark:text-white" data-gate-holder>{{ $profile['holder_name'] }}</h2>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-medium">
                                @if ($waiver['enabled'])
                                    @if ($waiver['signed'])
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-green-700 dark:bg-green-500/15 dark:text-green-300">
                                            {{ __('admin.puerta.validar.profile.waiver_signed', ['date' => \Illuminate\Support\Carbon::parse($waiver['accepted_on'])->format('d/m/Y')]) }}@if ($waiver['outdated']) · {{ __('admin.puerta.validar.profile.waiver_outdated') }}@endif
                                        </span>
                                    @else
                                        <span class="rounded-full bg-orange-100 px-2 py-1 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">{{ __('admin.puerta.validar.profile.waiver_missing') }}</span>
                                    @endif
                                @endif
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-700 dark:bg-white/10 dark:text-gray-300" data-gate-card="{{ $profile['card'] }}">
                                    {{ __('admin.puerta.validar.profile.card_'.$profile['card']) }}
                                </span>
                            </div>
                        </div>

                        {{-- 2 · HOY (en plural): los tres estados en voz alta (§4.6). --}}
                        <h3 class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.puerta.validar.profile.today') }}</h3>
                        @if ($profile['today_reservations'] === [])
                            <p class="mt-1 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10" data-gate-today-empty>
                                {{ __('admin.puerta.validar.profile.today_empty') }}
                            </p>
                        @else
                            <ul class="mt-1 space-y-2" data-gate-today>
                                @foreach ($profile['today_reservations'] as $r)
                                    @include('livewire.admin.puerta.partials.reservation', ['r' => $r])
                                @endforeach
                            </ul>
                        @endif

                        {{-- 5 · La ventana ±N, en segundo plano. --}}
                        @if ($profile['window'] !== [])
                            <h3 class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.puerta.validar.profile.window', ['days' => $profile['window_days']]) }}</h3>
                            @if ($profile['today_reservations'] === [])
                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ __('admin.puerta.validar.profile.window_note') }}</p>
                            @endif
                            <ul class="mt-1 space-y-2 opacity-80" data-gate-window>
                                @foreach ($profile['window'] as $r)
                                    @include('livewire.admin.puerta.partials.reservation', ['r' => $r])
                                @endforeach
                            </ul>
                        @endif

                        {{-- 3 · Menores a cargo: SOLO edad y estado de la exención (§4.6 fila 3). --}}
                        <h3 class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.puerta.validar.profile.minors') }}</h3>
                        @if ($profile['dependents'] === [])
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" data-gate-minors-empty>{{ __('admin.puerta.validar.profile.minors_empty') }}</p>
                        @else
                            <ul class="mt-1 flex flex-wrap gap-2" data-gate-minors>
                                @foreach ($profile['dependents'] as $m)
                                    <li class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $minorLabel($m) }}</li>
                                @endforeach
                            </ul>
                        @endif

                        {{-- 4 · La VISITA (§8.3): un acto explícito, idempotente por día. --}}
                        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                            @if ($profile['visit_registered_today'])
                                <span class="inline-flex items-center gap-1 rounded-lg bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 ring-1 ring-green-600/20 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30" data-gate-visit="registered">
                                    ✓ {{ __('admin.puerta.validar.profile.visit_registered') }}
                                </span>
                            @else
                                <button
                                    type="button"
                                    wire:click="registerVisit"
                                    class="inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:ring-offset-2 dark:bg-amber-500 dark:hover:bg-amber-400"
                                    data-gate-visit="register"
                                >
                                    {{ __('admin.puerta.validar.profile.visit_register') }}
                                </button>
                            @endif
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.puerta.validar.profile.visit_hint') }}</span>
                        </div>
                        <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">{{ __('admin.puerta.validar.profile.expires', ['minutes' => $profile['ttl_minutes']]) }}</p>
                    </div>
                </section>
            @endif

            <div class="mt-4 text-center">
                <button
                    type="button"
                    wire:click="clear"
                    class="text-sm font-medium text-gray-500 underline-offset-4 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-300"
                >
                    {{ __('admin.puerta.validar.new_search') }}
                </button>
            </div>
        </div>
    @endif
</div>
