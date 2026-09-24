<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CustomersReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Registros y puerta, al detalle** (`specs/analitica.md` §4.5, T2b): la tabla por día (o semana) con las
 * cuentas nuevas, las verificadas, las búsquedas, las encontradas, los clientes distintos y las visitas, más
 * cómo se registran. Es la vista de tabla del gráfico de al lado.
 */
class CustomersBreakdownWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /**
     * Las mismas tablas, para el CSV (T2d): el periodo viene de fuera, no del filtro de la página.
     *
     * @return list<array{heading: string, columns: list<string>, rows: list<list<string>>}>
     */
    public function tablesFor(ReportPeriod $period): array
    {
        $this->pageFilters = ['period' => $period->value];

        return $this->getViewData()['tables'];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = $this->customers();

        return [
            'heading' => __('admin.analytics.customers.breakdown_heading'),
            'description' => __('admin.analytics.customers.breakdown_note'),
            'tables' => [
                $this->series($report['series'], (string) $report['window']['granularity']),
                $this->methods($report['registrations']['by_method']),
            ],
        ];
    }

    /**
     * @param  list<array{key: string, registrations: int, verified: int, lookups: int, found: int, customers: int, visits: int}>  $series
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function series(array $series, string $granularity): array
    {
        $byWeek = $granularity === Window::GRANULARITY_WEEK;

        $rows = array_map(static function (array $r) use ($byWeek): array {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $r['key'], 'UTC')->format('d/m/Y');

            return [
                $byWeek ? __('admin.analytics.money.week_of', ['day' => $day]) : $day,
                (string) $r['registrations'],
                (string) $r['verified'],
                (string) $r['lookups'],
                (string) $r['found'],
                (string) $r['customers'],
                (string) $r['visits'],
            ];
        }, $series);

        $sum = static fn (string $field): string => (string) array_sum(array_column($series, $field));
        $rows[] = [
            __('admin.analytics.money.col.total'),
            $sum('registrations'), $sum('verified'), $sum('lookups'), $sum('found'), '—', $sum('visits'),
        ];

        return [
            'heading' => __($byWeek ? 'admin.analytics.money.by_week' : 'admin.analytics.money.by_day'),
            'columns' => [
                __($byWeek ? 'admin.analytics.money.col.week' : 'admin.analytics.money.col.day'),
                __('admin.analytics.customers.col.registrations'),
                __('admin.analytics.customers.col.verified'),
                __('admin.analytics.customers.col.lookups'),
                __('admin.analytics.customers.col.found'),
                __('admin.analytics.customers.col.customers'),
                __('admin.analytics.customers.col.visits'),
            ],
            'rows' => $rows,
            'wide' => true,
        ];
    }

    /**
     * @param  array<string, int>  $methods
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function methods(array $methods): array
    {
        $rows = [];
        foreach ([...CustomersReport::METHODS, CustomersReport::METHOD_UNKNOWN] as $method) {
            $rows[] = [__('admin.analytics.customers.method.'.$method), (string) ($methods[$method] ?? 0)];
        }

        return [
            'heading' => __('admin.analytics.customers.by_method'),
            'columns' => [__('admin.analytics.customers.col.method'), __('admin.analytics.customers.col.count')],
            'rows' => $rows,
        ];
    }
}
