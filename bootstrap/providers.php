<?php

use App\Domain\Booking\BookingServiceProvider;
use App\Domain\Payments\PaymentsServiceProvider;
use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    // Superficie `/api/v1` (Fase 3 · paso 0): limitadores nombrados y, según avance la fase, sus
    // bindings propios. Separado de `AppServiceProvider` a propósito — ver su docblock.
    ApiServiceProvider::class,
    // Módulos de dominio (Fase 2 — `docs/specs/modulos-dominio.md`). Cada uno publica los
    // bindings de SUS contratos; en el paso 1 apuntan a las implementaciones legacy de
    // `app/Support`. Se añadirán Platform, Content e Identity cuando tengan contrato propio.
    BookingServiceProvider::class,
    PaymentsServiceProvider::class,
];
