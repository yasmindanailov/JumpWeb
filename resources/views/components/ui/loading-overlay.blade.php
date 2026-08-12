@props(['label' => null, 'fixed' => false, 'size' => 'lg'])

{{--
    Velo de carga con spinner + etiqueta. Ver docs/UI-SPINNER.md.
    - Por defecto cubre SOLO el panel contenedor (que debe ser position:relative).
    - fixed=true → a pantalla completa (saltos que bloquean todo, p. ej. ir a pagar).
    Se controla con Livewire en el propio elemento:
        <x-ui.loading-overlay wire:loading.delay wire:target="selectDate, selectTime" />
--}}
<div {{ $attributes->class(['jj-loading' => ! $fixed, 'jj-spinner-overlay' => $fixed]) }}>
    <span class="jj-spinner-with-label">
        <x-ui.spinner :size="$size" :label="$label" />
        <span class="jj-spinner-label" aria-hidden="true">{{ $label ?? __('ui.loading') }}</span>
    </span>
</div>
