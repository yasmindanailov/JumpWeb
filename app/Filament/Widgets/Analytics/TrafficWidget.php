<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **La conversión, en seis cifras** (`specs/analitica.md` §4.5, T2c): visitas, compras por la web o la app,
 * conversión, ingresos de esas compras, sesiones identificadas, y los bots e internos que quedan fuera.
 */
class TrafficWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.traffic.heading');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $report = $this->funnel();
        /** @var array<string, int> $s */
        $s = $report['traffic'];
        /** @var array<string, int> $c */
        $c = $report['purchases'];
        /** @var array<string, int> $p */
        $p = $report['previous'];

        return [
            $this->countStat(__('admin.analytics.traffic.visits'), $s['visits'], $p['visits']),
            $this->countStat(__('admin.analytics.traffic.purchases'), $c['orders'], $p['orders']),
            $this->countStat(__('admin.analytics.traffic.conversion'), $c['conversion_bp'], $p['conversion_bp'])
                ->value(self::percent($c['conversion_bp'])),
            $this->moneyStat(__('admin.analytics.traffic.revenue'), $c['revenue'], $p['revenue']),
            Stat::make(__('admin.analytics.traffic.identified'), (string) $s['identified'])
                ->description(__('admin.analytics.traffic.identified_hint'))
                ->color('gray'),
            Stat::make(__('admin.analytics.traffic.excluded'), __('admin.analytics.traffic.excluded_value', ['bots' => $s['bots'], 'internal' => $s['internal']]))
                ->description(__('admin.analytics.traffic.excluded_hint'))
                ->color('gray'),
        ];
    }
}
