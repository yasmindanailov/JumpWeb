<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\CustomersReport;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **Vuelven al parque** (T0c de `specs/analitica-para-decidir.md` §4.8.bis, `#756`): de los clientes que vinieron, cuántos
 * ya habían venido, cuántos vienen por primera vez, cuántos vinieron varios días y cada cuánto vuelven. Lo pidió el owner el 27-09: «los clientes que vuelven no los veo… cuántas veces vuelven cada X tiempo».
 * Todo sale de `CustomersReport::returns()`: una consulta sobre las visitas acreditadas.
 */
class ReturnsWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.customers.returns_heading');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $report = $this->customers();
        /** @var array{visitors: int, returning: int, first_time: int, repeat: int, gap_median_days: ?int, gap_buckets: array<string, int>, by_source: array<string, int>} $r */
        $r = $report['returns'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        $b = $r['gap_buckets'];

        // Cómo se acreditó cada visita (carné o búsqueda) va como detalle de «Visitas acreditadas» (`GateWidget`): es un
        // desglose de las visitas, no una cifra, y en la tarjeta grande partía el valor en dos líneas (sonda, 27-09).
        return [
            $this->metric(Metric::count(
                'customers.returning_visitors', __('admin.analytics.customers.returning_visitors'), $r['returning'], $p['returning'], Polarity::UpIsGood, self::how('customers.returning_visitors'),
                detail: $r['visitors'] > 0 ? __('admin.analytics.customers.returning_share', ['percent' => (int) round($r['returning'] / $r['visitors'] * 100), 'total' => $r['visitors']]) : null,
            )),
            $this->metric(Metric::count('customers.first_visit', __('admin.analytics.customers.first_visit'), $r['first_time'], $p['first_time'], Polarity::UpIsGood, self::how('customers.first_visit'))),
            $this->metric(Metric::count('customers.repeat', __('admin.analytics.customers.repeat'), $r['repeat'], $p['repeat'], Polarity::UpIsGood, self::how('customers.repeat'))),
            $this->metric(Metric::text(
                'customers.return_gap',
                __('admin.analytics.customers.return_gap'),
                $r['gap_median_days'] === null ? __('admin.analytics.customers.return_gap_none') : trans_choice('admin.analytics.customers.return_gap_value', $r['gap_median_days'], ['days' => $r['gap_median_days']]),
                self::how('customers.return_gap'),
                detail: $r['gap_median_days'] === null ? null : __('admin.analytics.customers.return_gap_buckets', [
                    'week' => $b[CustomersReport::GAP_WEEK], 'month' => $b[CustomersReport::GAP_MONTH],
                    'quarter' => $b[CustomersReport::GAP_QUARTER], 'longer' => $b[CustomersReport::GAP_LONGER],
                ]),
            )),
        ];
    }
}
