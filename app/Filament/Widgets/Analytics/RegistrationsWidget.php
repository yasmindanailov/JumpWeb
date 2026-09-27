<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\CustomersMetrics;

/**
 * **Las cuentas nuevas, plegado** (`specs/analitica.md` §4.5, T2b; T3a, `#759`): de las cuentas creadas en el periodo —que
 * están arriba—, cuántas verificaron el correo y cuántas han comprado alguna vez. Cómo se registran, en la tabla del
 * desglose. Las cuentas del equipo no cuentan.
 */
class RegistrationsWidget extends MetricsWidget
{
    public const KEYS = ['customers.verified', 'customers.buyers'];

    public const FOLDED = true;

    protected static ?int $sort = 8;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.customers.registrations_heading');
    }

    protected function metrics(): array
    {
        return CustomersMetrics::for($this->window(), $this->comparison());
    }
}
