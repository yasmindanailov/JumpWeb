<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\MarketingMetrics;

/**
 * **Marketing, arriba** (T3a de `analitica-para-decidir.md` §4.13, `#759`; las cifras, T2c de `analitica.md`): las visitas
 * a la web y la conversión (la principal), que dice en su detalle las compras y lo cobrado. El coste por venta, el retorno
 * por euro y los clics en los correos llegan aquí con la T5.
 */
class TrafficWidget extends MetricsWidget
{
    public const KEYS = ['traffic.visits', 'traffic.conversion'];

    public const PRINCIPAL = 'traffic.conversion';

    protected static ?int $sort = 10;

    protected function metrics(): array
    {
        return MarketingMetrics::for($this->window(), $this->comparison());
    }
}
