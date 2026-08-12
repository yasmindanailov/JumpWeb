{{-- Acceso rápido al "Calendario" en el topbar (espejo de create-order-topbar-button). Solo
     visible para quien puede ver el calendario; no se renderiza en /admin/login (usuario nulo). --}}
@if (auth()->user()?->hasPermission('calendar.view'))
    {{-- `me-3` iguala el spacing del resto de elementos del topbar (igual que "Crear pedido"). --}}
    <x-filament::button
        tag="a"
        :href="\App\Filament\Pages\CalendarPage::getUrl()"
        icon="heroicon-o-calendar-days"
        size="sm"
        color="gray"
        class="fi-jj-calendar-btn me-3"
    >
        {{ __('admin.calendar.nav_label') }}
    </x-filament::button>
@endif
