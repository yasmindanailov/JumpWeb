<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;

/**
 * **Quién viene y quién vuelve, arriba** (T3a de `analitica-para-decidir.md` §4.13, `#759`): los visitantes (las plazas
 * de las visitas pagadas: la principal), los compradores nuevos y los recurrentes (el owner, 27-09: «clientes recurrentes
 * sí, eso me sirve»), los que ya habían venido al parque (T0c), el valor de vida y las cuentas nuevas. Tres informes,
 * elegidos por clave: la cifra es la misma que en su catálogo, y «Resumen» la reusa.
 *
 * «Vuelven a los 90 días» (§4.1) es de las cohortes (T6); hasta entonces, «Ya habían venido».
 */
class CustomersOverviewWidget extends MetricsWidget
{
    public const KEYS = ['occupancy.visitors', 'money.new', 'money.returning', 'customers.returning_visitors', 'money.lifetime_avg', 'customers.registrations'];

    public const PRINCIPAL = 'occupancy.visitors';

    protected static ?int $sort = 5;

    protected int|array|null $columns = 4;

    protected function metrics(): array
    {
        return OccupancyMetrics::for($this->window(), $this->comparison())
            + MoneyMetrics::for($this->window(), $this->comparison())
            + CustomersMetrics::for($this->window(), $this->comparison());
    }
}
