<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;

/**
 * **Las cifras del marketing** (`FunnelReport`; T2c de `analitica.md` §4.5): las visitas a la web, las compras por la web
 * o la app, la conversión —una TASA: en puntos y con sus intervalos (T0b)— y lo cobrado en esas compras; y las dos de
 * «Calidad del dato», las visitas identificadas y lo que queda fuera del recuento. Las claves siguen siendo `traffic.*`
 * (la pestaña era «Conversión»): son las de su «¿Cómo se calcula?» y las del censo.
 */
final class MarketingMetrics extends MetricSet
{
    protected static function report(Window $window, Comparison $comparison): array
    {
        return FunnelReport::for($window, $comparison);
    }

    public static function from(array $report): array
    {
        /** @var array<string, int> $s */
        $s = $report['traffic'];
        /** @var array<string, int> $c */
        $c = $report['purchases'];
        /** @var array<string, int> $p */
        $p = $report['previous'];

        return self::keyed([
            Metric::count('traffic.visits', __('admin.analytics.traffic.visits'), $s['visits'], $p['visits'], Polarity::UpIsGood, self::how('traffic.visits')),
            Metric::count('traffic.purchases', __('admin.analytics.traffic.purchases'), $c['orders'], $p['orders'], Polarity::UpIsGood, self::how('traffic.purchases')),
            // T3a (`#759`): las compras y lo cobrado van plegados; la conversión los dice en su línea de detalle.
            Metric::rate(
                'traffic.conversion', __('admin.analytics.traffic.conversion'), $c['orders'], $s['visits'], $p['orders'], $p['visits'], Polarity::UpIsGood, self::how('traffic.conversion'),
                detail: trans_choice('admin.analytics.traffic.conversion_detail', $c['orders'], ['n' => number_format($c['orders'], 0, ',', '.'), 'revenue' => Money::format($c['revenue'])]),
            ),
            Metric::money('traffic.revenue', __('admin.analytics.traffic.revenue'), $c['revenue'], $p['revenue'], $c['revenue_payments'], $p['revenue_payments'], Polarity::UpIsGood, self::how('traffic.revenue'), squares: $c['revenue_sq'], previousSquares: $p['revenue_sq']),
            Metric::count('traffic.identified', __('admin.analytics.traffic.identified'), $s['identified'], null, Polarity::Neutral, self::how('traffic.identified')),
            Metric::text('traffic.excluded', __('admin.analytics.traffic.excluded'), __('admin.analytics.traffic.excluded_value', ['bots' => $s['bots'], 'internal' => $s['internal']]), self::how('traffic.excluded')),
        ]);
    }
}
