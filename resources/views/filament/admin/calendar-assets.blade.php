{{-- Fase 7.4 — carga del bundle del calendario (FullCalendar + componente Alpine
     `jjCalendar`). Se inyecta en `HEAD_END` de TODAS las páginas del panel del
     usuario con permiso, no solo en la del calendario: así el módulo está cargado
     y el componente registrado ANTES de navegar (Livewire SPA `wire:navigate` no
     re-ejecuta de forma fiable un módulo async recién añadido — cargarlo en el
     head, que persiste entre navegaciones, evita la carrera). El módulo solo
     INSTANCIA FullCalendar donde existe el nodo `x-ref="calendar"` (la página del
     calendario); en el resto solo registra el componente Alpine, coste mínimo.
     Gateado por `calendar.view` para no cargarlo en el login ni a quien no accede. --}}
@if (auth()->user()?->hasPermission('calendar.view'))
    @vite('resources/js/admin/calendar.js')
@endif
