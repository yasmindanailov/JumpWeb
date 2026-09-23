<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Money;
use App\Filament\Widgets\Analytics\Concerns\ReadsMoneyReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **Los clientes que compran** (`docs/specs/analitica.md` §4.5, T2a): compradores distintos, nuevos frente a
 * recurrentes, el valor medio por cliente en el periodo y el valor de vida medio de todos los que han
 * comprado alguna vez. Solo agregados: ningún nombre (la persona es la ficha 360, T4).
 */
class MoneyCustomersWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ReadsMoneyReport;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.money.customers_heading');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        /** @var array<string, int> $c */
        $c = $this->report()['customers'];
        /** @var array<string, int> $t */
        $t = $this->report()['totals'];

        return [
            Stat::make(__('admin.analytics.money.buyers'), (string) $c['buyers'])->color('primary'),
            Stat::make(__('admin.analytics.money.new'), (string) $c['new'])
                ->description(__('admin.analytics.money.new_hint'))
                ->color('success'),
            Stat::make(__('admin.analytics.money.returning'), (string) $c['returning'])->color('gray'),
            Stat::make(__('admin.analytics.money.avg_per_customer'), Money::format($c['avg_per_customer']))->color('gray'),
            Stat::make(__('admin.analytics.money.lifetime_avg'), Money::format($c['lifetime_avg']))
                ->description(__('admin.analytics.money.lifetime_avg_hint'))
                ->color('gray'),
            Stat::make(__('admin.analytics.money.adjustments'), Money::format($t['adjustments']))
                ->description(__('admin.analytics.money.adjustments_hint'))
                ->color('gray'),
        ];
    }
}
