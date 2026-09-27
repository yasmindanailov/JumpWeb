<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **Los registros de clientes** (`specs/analitica.md` §4.5, T2b): cuántas cuentas nuevas en el periodo —con su
 * «frente al periodo anterior» y cómo se registraron—, cuántas verificaron el correo y cuántas han comprado
 * alguna vez. Las cuentas del equipo no cuentan.
 */
class RegistrationsWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.customers.registrations_heading');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $report = $this->customers();
        /** @var array{total: int, verified: int, buyers: int} $r */
        $r = $report['registrations'];
        /** @var array<string, int> $p */
        $p = $report['previous'];

        return [
            // Cómo se registran está en la tabla «Cómo se registran» del desglose, con sus tres filas.
            $this->metric(Metric::count('customers.registrations', __('admin.analytics.customers.registrations'), $r['total'], $p['registrations'], Polarity::UpIsGood, self::how('customers.registrations'))),
            $this->metric(Metric::count('customers.verified', __('admin.analytics.customers.verified'), $r['verified'], null, Polarity::UpIsGood, self::how('customers.verified'), detail: self::share($r['verified'], $r['total']))),
            $this->metric(Metric::count('customers.buyers', __('admin.analytics.customers.buyers'), $r['buyers'], null, Polarity::UpIsGood, self::how('customers.buyers'), detail: self::share($r['buyers'], $r['total']))),
        ];
    }

    /** «7 de 12» como porcentaje del total del periodo; sin total no hay porcentaje. */
    private static function share(int $part, int $total): string
    {
        if ($total === 0) {
            return __('admin.analytics.customers.share_none');
        }

        return __('admin.analytics.customers.share', ['percent' => (int) round($part / $total * 100), 'total' => $total]);
    }
}
