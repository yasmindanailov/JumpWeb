<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Las visitas por hora del PARQUE** (`specs/analitica.md` §4.5, T2c): cuándo entra la gente en la web. Una
 * sola serie, sin leyenda; la hora es la del parque, no la UTC de la sesión.
 */
class TrafficHoursChart extends ChartWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 14;

    /** Media rejilla en escritorio (T2f): al lado va el anillo de dispositivos. */
    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.traffic.hours_heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        /** @var list<int> $hours */
        $hours = $this->funnel()['hours'];

        return [
            'labels' => array_map(static fn (int $h): string => sprintf('%02d h', $h), range(0, 23)),
            'datasets' => [[
                'label' => __('admin.analytics.traffic.visits'),
                'data' => $hours,
                'backgroundColor' => MoneySeriesChart::COLORS['collected'],
                'borderColor' => MoneySeriesChart::COLORS['collected'],
                'borderRadius' => 4,
                'maxBarThickness' => 22,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
