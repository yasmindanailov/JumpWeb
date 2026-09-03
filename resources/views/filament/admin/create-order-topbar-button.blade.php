{{-- Acceso rápido a "Crear pedido manual" en el topbar (Fase 7.3, #120). Solo visible para
     quien tiene el permiso; no se renderiza en /admin/login (usuario nulo).

     `#461` — pasa a **acción primaria** (`[DECIDIDO owner]`: «el botón "crear pedido" más grande»).
     ⚠️ El tamaño NO se escribe aquí: lo pone la clase `.fi-jj-action`, que es la ESCALA de acción
     primaria del panel (`theme.css`). Es lo que impide que el siguiente botón importante se ponga a
     ojo y acabemos con cuatro tamaños para la misma jerarquía — que es exactamente lo que el owner
     pidió evitar («tampoco que haya incoherencia entre tamaños de los botones»). --}}
@if (auth()->user()?->hasPermission('orders.create_manual'))
    {{-- `me-3` iguala el spacing del resto de elementos del topbar. --}}
    <x-filament::button
        tag="a"
        :href="\App\Filament\Pages\CreateManualOrderPage::getUrl()"
        icon="heroicon-o-plus-circle"
        size="lg"
        class="fi-jj-create-order-btn fi-jj-action me-3"
    >
        {{ __('admin.orders.create_manual.nav_label') }}
    </x-filament::button>
@endif
