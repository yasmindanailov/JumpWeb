<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\Money;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **El desglose del dinero** (`docs/specs/analitica.md` §4.5, T2a): la serie por día o por semana —el «total
 * por mes y semana» que pidió el owner, en cifras—, y por canal, por método de cobro, por producto, la señal y
 * lo perdido, como seis tablas ya formateadas. La vista no calcula ni decide nada: recibe celdas de texto y
 * las escapa; ninguna columna analítica pasa por `->html()`.
 *
 * ⚠️ La tabla de la serie es además la «vista de tabla» que el gráfico necesita: uno de sus tres colores queda
 * por debajo de 3:1 de contraste sobre fondo claro, y la regla de la paleta exige en ese caso rótulos visibles
 * o una tabla con los mismos datos. Es esta.
 */
class MoneyBreakdownWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

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
        $report = $this->money();

        return [
            'heading' => __('admin.analytics.money.breakdown_heading'),
            'description' => __('admin.analytics.money.method_note'),
            'tables' => [
                $this->series($report['series'], (string) $report['window']['granularity']),
                $this->channels($report['by_channel']),
                $this->methods($report['by_method']),
                $this->products($report['by_product']),
                $this->deposit($report['deposit']),
                $this->lost($report['lost']),
            ],
        ];
    }

    /**
     * La serie, como tabla: una fila por día (o por semana, con su lunes) y los totales del periodo al pie.
     *
     * @param  list<array{key: string, collected: int, refunded: int, sold: int, orders: int}>  $series
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function series(array $series, string $granularity): array
    {
        $byWeek = $granularity === Window::GRANULARITY_WEEK;

        $rows = array_map(static function (array $r) use ($byWeek): array {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $r['key'], 'UTC')->format('d/m/Y');

            return [
                $byWeek ? __('admin.analytics.money.week_of', ['day' => $day]) : $day,
                (string) $r['orders'],
                Money::format($r['sold']),
                Money::format($r['collected']),
                Money::format($r['refunded']),
            ];
        }, $series);

        $rows[] = [
            __('admin.analytics.money.col.total'),
            (string) array_sum(array_column($series, 'orders')),
            Money::format(array_sum(array_column($series, 'sold'))),
            Money::format(array_sum(array_column($series, 'collected'))),
            Money::format(array_sum(array_column($series, 'refunded'))),
        ];

        return [
            'heading' => __($byWeek ? 'admin.analytics.money.by_week' : 'admin.analytics.money.by_day'),
            'columns' => [
                __($byWeek ? 'admin.analytics.money.col.week' : 'admin.analytics.money.col.day'),
                __('admin.analytics.money.col.orders'),
                __('admin.analytics.money.col.sold'),
                __('admin.analytics.money.col.collected'),
                __('admin.analytics.money.col.refunded'),
            ],
            'rows' => $rows,
            'wide' => true,
        ];
    }

    /**
     * @param  list<array{channel: string, orders: int, sold: int, collected: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function channels(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.money.by_channel'),
            'columns' => [__('admin.analytics.money.col.channel'), __('admin.analytics.money.col.orders'), __('admin.analytics.money.col.sold'), __('admin.analytics.money.col.collected')],
            'rows' => array_map(static fn (array $r): array => [
                __('admin.analytics.money.channel.'.$r['channel']),
                (string) $r['orders'],
                Money::format($r['sold']),
                Money::format($r['collected']),
            ], $rows),
        ];
    }

    /**
     * @param  list<array{method: string, payments: int, collected: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function methods(array $rows): array
    {
        $known = array_keys((array) __('admin.analytics.money.method'));

        return [
            'heading' => __('admin.analytics.money.by_method'),
            'columns' => [__('admin.analytics.money.col.method'), __('admin.analytics.money.col.payments'), __('admin.analytics.money.col.collected')],
            'rows' => array_map(static fn (array $r): array => [
                __('admin.analytics.money.method.'.(in_array($r['method'], $known, true) ? $r['method'] : 'other')),
                (string) $r['payments'],
                Money::format($r['collected']),
            ], $rows),
        ];
    }

    /**
     * @param  list<array{product: string, units: int, value: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function products(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.money.by_product'),
            'columns' => [__('admin.analytics.money.col.product'), __('admin.analytics.money.col.units'), __('admin.analytics.money.col.value')],
            'rows' => array_map(static fn (array $r): array => [
                $r['product'],
                (string) $r['units'],
                Money::format($r['value']),
            ], $rows),
        ];
    }

    /**
     * @param  array{orders: int, pending: int, settled: int}  $deposit
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function deposit(array $deposit): array
    {
        return [
            'heading' => __('admin.analytics.money.deposit'),
            'columns' => [__('admin.analytics.money.col.what'), __('admin.analytics.money.col.amount')],
            'rows' => [
                [__('admin.analytics.money.deposit_rows.orders'), (string) $deposit['orders']],
                [__('admin.analytics.money.deposit_rows.pending'), Money::format($deposit['pending'])],
                [__('admin.analytics.money.deposit_rows.settled'), Money::format($deposit['settled'])],
            ],
        ];
    }

    /**
     * @param  array{expired: array{count: int, value: int}, cancelled: array{count: int, value: int}, declined: int, incidents: int}  $lost
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function lost(array $lost): array
    {
        return [
            'heading' => __('admin.analytics.money.lost'),
            'columns' => [__('admin.analytics.money.col.what'), __('admin.analytics.money.col.count'), __('admin.analytics.money.col.value')],
            'rows' => [
                [__('admin.analytics.money.lost_rows.expired'), (string) $lost['expired']['count'], Money::format($lost['expired']['value'])],
                [__('admin.analytics.money.lost_rows.cancelled'), (string) $lost['cancelled']['count'], Money::format($lost['cancelled']['value'])],
                [__('admin.analytics.money.lost_rows.declined'), (string) $lost['declined'], '—'],
                [__('admin.analytics.money.lost_rows.incidents'), (string) $lost['incidents'], '—'],
            ],
        ];
    }
}
