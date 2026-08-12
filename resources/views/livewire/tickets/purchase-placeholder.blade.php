{{-- Placeholder del sidebar de compra mientras carga (Livewire lazy). Ver docs/UI-SPINNER.md. --}}
<div class="purchase-loading">
    <span class="jj-spinner-with-label">
        <x-ui.spinner size="lg" :label="__('ui.loading')" />
        <span class="jj-spinner-label" aria-hidden="true">{{ __('ui.loading') }}</span>
    </span>
</div>
