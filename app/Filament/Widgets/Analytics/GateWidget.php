<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\CustomersMetrics;

/**
 * **La puerta, plegado** (`specs/analitica.md` §4.5, T2b; T3a, `#759`): las búsquedas de la pantalla del QR —tecleadas o
 * escaneadas, encontradas o no—, los clientes distintos buscados, las fichas abiertas y las visitas acreditadas. Es su
 * MECÁNICA: contesta «¿cuánta gente vino?» a medias, y por eso va a un clic y no arriba (§1, medido el 27-09).
 */
class GateWidget extends MetricsWidget
{
    public const KEYS = [
        'customers.lookups', 'customers.typed', 'customers.scanned', 'customers.found',
        'customers.customers', 'customers.profile_views', 'customers.visits', 'customers.visitors',
    ];

    public const FOLDED = true;

    protected static ?int $sort = 9;

    protected int|array|null $columns = 4;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.customers.gate_heading');
    }

    protected function metrics(): array
    {
        return CustomersMetrics::for($this->window(), $this->comparison());
    }
}
