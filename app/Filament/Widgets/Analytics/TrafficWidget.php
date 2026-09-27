<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
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

        // La conversión es una TASA (compras entre visitas): se compara en puntos y con sus intervalos (T0b, `#755`).
        return [
            $this->metric(Metric::count('traffic.visits', __('admin.analytics.traffic.visits'), $s['visits'], $p['visits'], Polarity::UpIsGood, self::how('traffic.visits'))),
            $this->metric(Metric::count('traffic.purchases', __('admin.analytics.traffic.purchases'), $c['orders'], $p['orders'], Polarity::UpIsGood, self::how('traffic.purchases'))),
            $this->metric(Metric::rate('traffic.conversion', __('admin.analytics.traffic.conversion'), $c['orders'], $s['visits'], $p['orders'], $p['visits'], Polarity::UpIsGood, self::how('traffic.conversion'))),
            $this->metric(Metric::money('traffic.revenue', __('admin.analytics.traffic.revenue'), $c['revenue'], $p['revenue'], $c['revenue_payments'], $p['revenue_payments'], Polarity::UpIsGood, self::how('traffic.revenue'), squares: $c['revenue_sq'], previousSquares: $p['revenue_sq'])),
            $this->metric(Metric::count('traffic.identified', __('admin.analytics.traffic.identified'), $s['identified'], null, Polarity::Neutral, self::how('traffic.identified'))),
            $this->metric(Metric::text('traffic.excluded', __('admin.analytics.traffic.excluded'), __('admin.analytics.traffic.excluded_value', ['bots' => $s['bots'], 'internal' => $s['internal']]), self::how('traffic.excluded'))),
        ];
    }
}
