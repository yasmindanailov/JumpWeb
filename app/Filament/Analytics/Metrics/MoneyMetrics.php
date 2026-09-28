<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\MoneyReport;
use App\Filament\Analytics\Polarity;

/**
 * **Las cifras del dinero** (`MoneyReport`; T2a de `analitica.md` §4.5, anatomía de la T0b): la CAJA (cobrado, devuelto,
 * neto: lo que entró y lo que volvió), lo FACTURADO al nacer (vendido, pedidos, valor medio), la señal que queda por cobrar
 * en el parque y los clientes que compran. Cada importe se sostiene en sus OPERACIONES —cobros, devoluciones, pedidos—, que
 * son su base para el porcentaje y el color.
 */
final class MoneyMetrics extends MetricSet
{
    protected static function report(Window $window, Comparison $comparison): array
    {
        return MoneyReport::for($window, $comparison);
    }

    protected static function baselineTotals(Window $period): array
    {
        return MoneyReport::baselineTotals($period);
    }

    /** Los compradores comparados viven dentro de `customers` (T0c): también se cambian. */
    protected static function withTotals(array $report, Window $period): array
    {
        $totals = MoneyReport::baselineTotals($period);
        $report['previous'] = $totals['previous'];
        $report['customers']['previous'] = $totals['customers_previous'];

        return $report;
    }

    protected static function sources(): array
    {
        return ['*' => MeasuredSince::ORDERS];
    }

    public static function from(array $report): array
    {
        /** @var array<string, int> $t */
        $t = $report['totals'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        /** @var array{buyers: int, new: int, returning: int, returning_web: int, avg_per_customer: int, lifetime_avg: int, previous: array{buyers: int, new: int, returning: int, returning_web: int}} $c */
        $c = $report['customers'];
        $cp = $c['previous'];
        /** @var array{orders: int, pending: int, settled: int} $d */
        $d = $report['deposit'];
        $previousAvg = $p['orders'] > 0 ? intdiv($p['sold'], $p['orders']) : 0;

        return self::keyed([
            Metric::money('money.collected', __('admin.analytics.money.collected'), $t['collected'], $p['collected'], $t['payments'], $p['payments'], Polarity::UpIsGood, self::how('money.collected'), squares: $t['collected_sq'], previousSquares: $p['collected_sq']),
            Metric::money('money.refunded', __('admin.analytics.money.refunded'), $t['refunded'], $p['refunded'], $t['refunds'], $p['refunds'], Polarity::DownIsGood, self::how('money.refunded'), squares: $t['refunded_sq'], previousSquares: $p['refunded_sq']),
            // Lo neto es cobrado menos devuelto: su varianza es la de las dos sumas juntas.
            Metric::money('money.net', __('admin.analytics.money.net'), $t['net'], $p['net'], $t['payments'], $p['payments'], Polarity::UpIsGood, self::how('money.net'), squares: $t['collected_sq'] + $t['refunded_sq'], previousSquares: $p['collected_sq'] + $p['refunded_sq']),
            Metric::money('money.sold', __('admin.analytics.money.sold'), $t['sold'], $p['sold'], $t['orders'], $p['orders'], Polarity::UpIsGood, self::how('money.sold'), squares: $t['sold_sq'], previousSquares: $p['sold_sq']),
            Metric::count('money.orders', __('admin.analytics.money.orders'), $t['orders'], $p['orders'], Polarity::UpIsGood, self::how('money.orders')),
            Metric::money(
                'money.avg_order', __('admin.analytics.money.avg_order'), $t['avg_order'], $previousAvg, $t['orders'], $p['orders'], Polarity::UpIsGood, self::how('money.avg_order'),
                detail: __('admin.analytics.money.avg_collected').': '.Money::format($t['avg_collected']),
                squares: $t['sold_sq'], previousSquares: $p['sold_sq'], mean: true,
            ),
            // T3a (`#759`): la fila «Pendiente de cobrar en el parque» de «La señal», arriba. No es buena ni mala —es dinero ya
            // vendido que falta cobrar— y el informe no la trae del periodo comparado: sin cambio.
            Metric::money(
                'money.pending_in_park', __('admin.analytics.money.deposit_rows.pending'), $d['pending'], null, $d['orders'], null, Polarity::Neutral, self::how('money.pending_in_park'),
                detail: trans_choice('admin.analytics.money.pending_orders', $d['orders'], ['n' => $d['orders']]),
            ),
            Metric::money('money.adjustments', __('admin.analytics.money.adjustments'), $t['adjustments'], null, 0, null, Polarity::Neutral, self::how('money.adjustments')),
            // T0c (`#756`): los recuentos de compradores traen su periodo comparado; los valores medios, no.
            Metric::count('money.buyers', __('admin.analytics.money.buyers'), $c['buyers'], $cp['buyers'], Polarity::UpIsGood, self::how('money.buyers')),
            Metric::count('money.new', __('admin.analytics.money.new'), $c['new'], $cp['new'], Polarity::UpIsGood, self::how('money.new')),
            Metric::count('money.returning', __('admin.analytics.money.returning'), $c['returning'], $cp['returning'], Polarity::UpIsGood, self::how('money.returning')),
            Metric::count('money.returning_web', __('admin.analytics.money.returning_web'), $c['returning_web'], $cp['returning_web'], Polarity::UpIsGood, self::how('money.returning_web')),
            Metric::money('money.avg_per_customer', __('admin.analytics.money.avg_per_customer'), $c['avg_per_customer'], null, $c['buyers'], null, Polarity::UpIsGood, self::how('money.avg_per_customer')),
            Metric::money('money.lifetime_avg', __('admin.analytics.money.lifetime_avg'), $c['lifetime_avg'], null, $c['buyers'], null, Polarity::UpIsGood, self::how('money.lifetime_avg')),
        ]);
    }
}
