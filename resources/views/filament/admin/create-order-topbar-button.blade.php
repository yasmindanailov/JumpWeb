{{-- Acceso rápido a "Crear pedido manual" en el topbar (Fase 7.3, #120). Solo visible para
     quien tiene el permiso; no se renderiza en /admin/login (usuario nulo). --}}
@if (auth()->user()?->hasPermission('orders.create_manual'))
    {{-- `me-3` iguala el spacing del resto de elementos del topbar (mismo que el selector de idioma). --}}
    <x-filament::button
        tag="a"
        :href="\App\Filament\Pages\CreateManualOrderPage::getUrl()"
        icon="heroicon-o-plus-circle"
        size="sm"
        class="fi-jj-create-order-btn me-3"
    >
        {{ __('admin.orders.create_manual.nav_label') }}
    </x-filament::button>
@endif
