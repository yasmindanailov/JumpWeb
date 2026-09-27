<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\OccupancyMetrics;

/**
 * **La ocupación, en seis cifras** (`specs/analitica-para-decidir.md` §4.8.ter, la T2; `#758`): la de las ENTRADAS (la
 * principal), las FIESTAS por franja —separadas, nunca sumadas—, las franjas llenas y con cuánta antelación se llenaron,
 * el ingreso por plaza-hora ofrecida, la anticipación y la demanda sin hueco. Las compone {@see OccupancyMetrics} (T3a).
 */
class OccupancyOverviewWidget extends MetricsWidget
{
    public const KEYS = ['occupancy.entries', 'occupancy.parties', 'occupancy.full', 'occupancy.revenue_per_seat_hour', 'occupancy.anticipation', 'occupancy.missing'];

    public const PRINCIPAL = 'occupancy.entries';

    protected static ?int $sort = 10;

    protected int|array|null $columns = 4;

    protected function metrics(): array
    {
        return OccupancyMetrics::for($this->window(), $this->comparison());
    }
}
