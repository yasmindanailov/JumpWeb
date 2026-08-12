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

    {{-- Búsqueda --}}
    <form wire:submit="search" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
        <label for="input" class="sr-only">
            {{ __('admin.puerta.validar.input_placeholder') }}
        </label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <input
                id="input"
                type="text"
                wire:model="input"
                placeholder="{{ __('admin.puerta.validar.input_placeholder') }}"
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

    {{-- Resultado: 3 estados con paletas Tailwind directas (green/orange/red)
         para no depender del shell Filament — esta página es minimal/standalone. --}}
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
            @endswitch

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
