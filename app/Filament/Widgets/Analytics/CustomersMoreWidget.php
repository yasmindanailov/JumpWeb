<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;

/**
 * **Más de los clientes, plegado** (T3a, `#759`; §4.1.bis): los compradores del periodo y lo que gasta cada uno, los que
 * repiten por la web, y de las visitas acreditadas los que vienen por primera vez, los que vinieron varios días y cada
 * cuánto vuelven (T0c, `#756`).
 */
class CustomersMoreWidget extends MetricsWidget
{
    public const KEYS = ['money.buyers', 'money.avg_per_customer', 'money.returning_web', 'customers.first_visit', 'customers.repeat', 'customers.return_gap'];

    public const FOLDED = true;

    protected static ?int $sort = 7;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.customers.more_heading');
    }

    protected function metrics(): array
    {
        return MoneyMetrics::for($this->window(), $this->comparison()) + CustomersMetrics::for($this->window(), $this->comparison());
    }
}
