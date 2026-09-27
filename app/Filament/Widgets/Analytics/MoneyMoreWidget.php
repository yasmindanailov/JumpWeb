<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metrics\MoneyMetrics;

/**
 * **Más del dinero, plegado** (T3a, `#759`; §4.1.bis: se resume, no se quita): lo cobrado —todos los cobros con éxito, de
 * la pasarela y del mostrador— y las gestiones posteriores sobre pedidos ya cobrados.
 */
class MoneyMoreWidget extends MetricsWidget
{
    public const KEYS = ['money.collected', 'money.adjustments'];

    public const FOLDED = true;

    protected static ?int $sort = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.money.more_heading');
    }

    protected function metrics(): array
    {
        return MoneyMetrics::for($this->window(), $this->comparison());
    }
}
