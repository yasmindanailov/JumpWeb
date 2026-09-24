<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\BucketLabel;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Cuentas nuevas, clientes buscados en la puerta y visitas acreditadas por día (o semana)**
 * (`specs/analitica.md` §4.5, T2b): tres recuentos sobre un solo eje, con los mismos tres colores validados
 * del gráfico del dinero en orden fijo, y la tabla por día del desglose como vista de tabla.
 */
class CustomersSeriesChart extends ChartWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    /** @var array<string, string> serie → color (los huecos 1–3 de la paleta validada, como en el dinero) */
    public const COLORS = [
        'registrations' => MoneySeriesChart::COLORS['collected'],
        'customers' => MoneySeriesChart::COLORS['refunded'],
        'visits' => MoneySeriesChart::COLORS['sold'],
    ];

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): string|Htmlable|null
    {
        $granularity = (string) $this->customers()['window']['granularity'];

        return __('admin.analytics.customers.series_heading', [
            'granularity' => __('admin.analytics.money.granularity.'.$granularity),
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $report = $this->customers();
        /** @var list<array{key: string, registrations: int, verified: int, lookups: int, found: int, customers: int, visits: int}> $series */
        $series = $report['series'];
        $granularity = (string) $report['window']['granularity'];

        return [
            'labels' => array_map(static fn (array $row): string => BucketLabel::short($row['key'], $granularity), $series),
            'datasets' => [
                self::dataset('registrations', $series),
                self::dataset('customers', $series),
                self::dataset('visits', $series),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    /**
     * @param  list<array{key: string, registrations: int, verified: int, lookups: int, found: int, customers: int, visits: int}>  $series
     * @return array<string, mixed>
     */
    private static function dataset(string $key, array $series): array
    {
        return [
            'label' => __('admin.analytics.customers.series.'.$key),
            'data' => array_map(static fn (array $row): int => $row[$key], $series),
            'backgroundColor' => self::COLORS[$key],
            'borderColor' => self::COLORS[$key],
            'borderRadius' => 4,
            'maxBarThickness' => 18,
        ];
    }
}
