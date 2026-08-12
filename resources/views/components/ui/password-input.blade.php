@props([
    'id',
    'model',
    'autocomplete' => 'current-password',
    'required' => true,
])
{{--
    Auditoría 2026-05-26 2ª ronda (R-04) — input de contraseña con toggle mostrar/ocultar.

    Layout estándar (estilo GitHub/Stripe): el botón vive DENTRO del input, en el lateral
    derecho (absolute) con un icono SVG accesible. El input lleva padding-right reservado en
    .pwd-input input (site.css) para que el texto nunca se solape con el icono.

    Accesible:
      - aria-pressed refleja el estado mostrar/ocultar
      - aria-label traducido (account.account.password.show|hide)
      - tabindex implícito (es un <button>, queda en el orden natural de tab)
      - Si JS está deshabilitado, el input sigue funcional como type="password"

    Pásale `id` (asociado con su <label for>), `model` (clave wire:model), `autocomplete`
    (`current-password` o `new-password`) y `required` (true por defecto).
--}}
<div class="pwd-input" x-data="{ show: false }">
    <input :type="show ? 'text' : 'password'"
           type="password"
           id="{{ $id }}"
           wire:model="{{ $model }}"
           autocomplete="{{ $autocomplete }}"
           @if ($required) required @endif
           {{ $attributes->except(['id', 'model', 'autocomplete', 'required']) }}>
    <button type="button" class="pwd-input__toggle"
            @click="show = !show"
            :aria-label="show ? '{{ __('account.account.password.hide') }}' : '{{ __('account.account.password.show') }}'"
            :aria-pressed="show.toString()"
            tabindex="-1">
        {{-- Ojo (mostrar). Icono Heroicons "eye" simplificado, 18×18. --}}
        <svg x-show="!show" class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2.036 12.322a1 1 0 0 1 0-.644C3.423 7.512 7.36 4.5 12 4.5s8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644C20.577 16.488 16.64 19.5 12 19.5s-8.577-3.012-9.964-7.178Z"
                  stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" />
        </svg>
        {{-- Ojo tachado (ocultar). --}}
        <svg x-show="show" x-cloak class="pwd-input__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83M9.363 5.365A9.466 9.466 0 0 1 12 5c4.64 0 8.577 3.012 9.964 7.178a1 1 0 0 1 0 .644 9.46 9.46 0 0 1-3.07 4.385M6.61 6.61C4.547 7.97 2.999 9.984 2.036 12.178a1 1 0 0 0 0 .644C3.423 16.988 7.36 19.5 12 19.5a9.46 9.46 0 0 0 5.39-1.61"
                  stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</div>
