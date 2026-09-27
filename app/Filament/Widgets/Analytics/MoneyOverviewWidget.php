<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
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

    /**
     * Cada cifra con su anatomía (T0b, `#755`): el dinero se sostiene en sus OPERACIONES —cobros para lo cobrado y lo
     * neto, devoluciones para lo devuelto, pedidos para lo vendido— y ésas son su base para el porcentaje y el color.
     *
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $report = $this->money();
        /** @var array<string, int> $t */
        $t = $report['totals'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        $previousAvg = $p['orders'] > 0 ? intdiv($p['sold'], $p['orders']) : 0;

        // Lo neto es cobrado menos devuelto: su varianza es la de las dos sumas juntas.
        return [
            $this->metric(Metric::money('money.collected', __('admin.analytics.money.collected'), $t['collected'], $p['collected'], $t['payments'], $p['payments'], Polarity::UpIsGood, self::how('money.collected'), squares: $t['collected_sq'], previousSquares: $p['collected_sq'])),
            $this->metric(Metric::money('money.refunded', __('admin.analytics.money.refunded'), $t['refunded'], $p['refunded'], $t['refunds'], $p['refunds'], Polarity::DownIsGood, self::how('money.refunded'), squares: $t['refunded_sq'], previousSquares: $p['refunded_sq'])),
            $this->metric(Metric::money('money.net', __('admin.analytics.money.net'), $t['net'], $p['net'], $t['payments'], $p['payments'], Polarity::UpIsGood, self::how('money.net'), squares: $t['collected_sq'] + $t['refunded_sq'], previousSquares: $p['collected_sq'] + $p['refunded_sq'])),
            $this->metric(Metric::money('money.sold', __('admin.analytics.money.sold'), $t['sold'], $p['sold'], $t['orders'], $p['orders'], Polarity::UpIsGood, self::how('money.sold'), squares: $t['sold_sq'], previousSquares: $p['sold_sq'])),
            $this->metric(Metric::count('money.orders', __('admin.analytics.money.orders'), $t['orders'], $p['orders'], Polarity::UpIsGood, self::how('money.orders'))),
            $this->metric(Metric::money(
                'money.avg_order', __('admin.analytics.money.avg_order'), $t['avg_order'], $previousAvg, $t['orders'], $p['orders'], Polarity::UpIsGood, self::how('money.avg_order'),
                detail: __('admin.analytics.money.avg_collected').': '.Money::format($t['avg_collected']),
                squares: $t['sold_sq'], previousSquares: $p['sold_sq'], mean: true,
            )),
        ];
    }
}
