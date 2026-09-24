<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Money;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **Las seis cifras del dinero** (`docs/specs/analitica.md` §4.5, T2a): cobrado online, devuelto, ingresos
 * netos, vendido, pedidos cobrados y el valor medio del pedido, cada una con su «frente al periodo anterior».
 * Las tres primeras son CAJA (lo que entró y lo que volvió, I2 e I4); «vendido» es lo FACTURADO al nacer
 * (I1), y por eso puede no coincidir con lo cobrado: la señal se cobra en el parque.
 */
class MoneyOverviewWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.money.heading');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $report = $this->money();
        /** @var array<string, int> $t */
        $t = $report['totals'];
        /** @var array<string, int> $p */
        $p = $report['previous'];

        return [
            $this->moneyStat(__('admin.analytics.money.collected'), $t['collected'], $p['collected']),
            $this->moneyStat(__('admin.analytics.money.refunded'), $t['refunded'], $p['refunded'], upIsGood: false),
            $this->moneyStat(__('admin.analytics.money.net'), $t['net'], $p['net']),
            $this->moneyStat(__('admin.analytics.money.sold'), $t['sold'], $p['sold']),
            $this->countStat(__('admin.analytics.money.orders'), $t['orders'], $p['orders']),
            Stat::make(__('admin.analytics.money.avg_order'), Money::format($t['avg_order']))
                ->description(__('admin.analytics.money.avg_collected').': '.Money::format($t['avg_collected']))
                ->color('gray'),
        ];
    }
}
