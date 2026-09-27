<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
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
    use AnalyticsWidget;
    use InteractsWithPageFilters;

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
        $report = $this->money();
        /** @var array{buyers: int, new: int, returning: int, returning_web: int, avg_per_customer: int, lifetime_avg: int, previous: array{buyers: int, new: int, returning: int, returning_web: int}} $c */
        $c = $report['customers'];
        /** @var array<string, int> $t */
        $t = $report['totals'];

        // T0c (`#756`): los recuentos de compradores traen ya su periodo comparado; «Repiten por la web» es nueva (el owner:
        // «cuántos clientes compran de nuevo por la web»). Los valores medios siguen sin él, como antes.
        $p = $c['previous'];

        return [
            $this->metric(Metric::count('money.buyers', __('admin.analytics.money.buyers'), $c['buyers'], $p['buyers'], Polarity::UpIsGood, self::how('money.buyers'))),
            $this->metric(Metric::count('money.new', __('admin.analytics.money.new'), $c['new'], $p['new'], Polarity::UpIsGood, self::how('money.new'))),
            $this->metric(Metric::count('money.returning', __('admin.analytics.money.returning'), $c['returning'], $p['returning'], Polarity::UpIsGood, self::how('money.returning'))),
            $this->metric(Metric::count('money.returning_web', __('admin.analytics.money.returning_web'), $c['returning_web'], $p['returning_web'], Polarity::UpIsGood, self::how('money.returning_web'))),
            $this->metric(Metric::money('money.avg_per_customer', __('admin.analytics.money.avg_per_customer'), $c['avg_per_customer'], null, $c['buyers'], null, Polarity::UpIsGood, self::how('money.avg_per_customer'))),
            $this->metric(Metric::money('money.lifetime_avg', __('admin.analytics.money.lifetime_avg'), $c['lifetime_avg'], null, $c['buyers'], null, Polarity::UpIsGood, self::how('money.lifetime_avg'))),
            $this->metric(Metric::money('money.adjustments', __('admin.analytics.money.adjustments'), $t['adjustments'], null, 0, null, Polarity::Neutral, self::how('money.adjustments'))),
        ];
    }
}
