<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\MarketingMetrics;

/**
 * **Más del marketing, plegado** (T3a, `#759`; §4.1.bis): las compras por la web o la app y lo cobrado en ellas, cada una
 * con su comparación (arriba solo van en la línea de detalle de la conversión).
 */
class TrafficMoreWidget extends MetricsWidget
{
    public const KEYS = ['traffic.purchases', 'traffic.revenue'];

    public const FOLDED = true;

    protected static ?int $sort = 12;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.traffic.more_heading');
    }

    protected function metrics(): array
    {
        return MarketingMetrics::for($this->window(), $this->comparison());
    }
}
