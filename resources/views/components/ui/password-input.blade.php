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
        {{-- ⚠️ Los dos ojos eran **Heroicons a trazo 1,7**, o sea otra librería y otro idioma dentro
             del mismo formulario. Desde `#258` salen del set (`eye` / `eye-off`), dibujados con la
             anatomía del artboard porque él no los tiene.
             ⚠️⚠️ **El cajón lleva su COPIA de estos dos** (`PasswordInput.vue`): son los únicos
             dibujos del sistema que no viven en `components/icons/`, y `SidebarIconParityTest` los
             recoge aparte con el nombre `pwd-eye-0/1`. Si cambias uno, cambia el otro. --}}
        <x-icons.eye x-show="!show" :width="18" :height="18" />
        <x-icons.eye-off x-show="show" x-cloak :width="18" :height="18" />
    </button>
</div>
