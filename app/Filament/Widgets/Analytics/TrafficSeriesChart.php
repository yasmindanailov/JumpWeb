<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\BucketLabel;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Visitas y compras por día (o semana)** (`specs/analitica.md` §4.5, T2c): dos recuentos sobre un solo eje,
 * con dos de los tres colores validados en orden fijo.
 */
class TrafficSeriesChart extends ChartWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    /** @var array<string, string> */
    public const COLORS = [
        'visits' => MoneySeriesChart::COLORS['collected'],
        'purchases' => MoneySeriesChart::COLORS['refunded'],
    ];

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): string|Htmlable|null
    {
        $granularity = (string) $this->funnel()['window']['granularity'];

        return __('admin.analytics.traffic.series_heading', [
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
        $report = $this->funnel();
        /** @var list<array{key: string, visits: int, purchases: int}> $series */
        $series = $report['series'];
        $granularity = (string) $report['window']['granularity'];

        return [
            'labels' => array_map(static fn (array $row): string => BucketLabel::short($row['key'], $granularity), $series),
            'datasets' => [
                self::dataset('visits', $series),
                self::dataset('purchases', $series),
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
     * @param  list<array{key: string, visits: int, purchases: int}>  $series
     * @return array<string, mixed>
     */
    private static function dataset(string $key, array $series): array
    {
        return [
            'label' => __('admin.analytics.traffic.series.'.$key),
            'data' => array_map(static fn (array $row): int => $row[$key], $series),
            'backgroundColor' => self::COLORS[$key],
            'borderColor' => self::COLORS[$key],
            'borderRadius' => 4,
            'maxBarThickness' => 18,
        ];
    }
}
