<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\PartiesReport;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Cuándo se completa el formulario** (`specs/analitica-fiesta.md` §4.3, T2): un histograma de los días que
 * faltaban para la fiesta al completarlo —después de la fiesta, el mismo día, de uno a tres, de cuatro a siete, de
 * ocho a catorce, quince o más—. Es lo que dice si el recordatorio llega tarde. Sin formularios completados no hay
 * gráfico; su tabla vive en «Tiempos», plegada al pie.
 */
class PartiesTimingChart extends CategoryChart
{
    protected static ?int $sort = 13;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.parties.timing_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var array<string, int> $histogram */
        $histogram = $this->parties()['timing']['form_days_histogram'];

        if (array_sum($histogram) === 0) {
            return null;
        }

        return [
            'labels' => array_map(static fn (string $bucket): string => __('admin.analytics.parties.days_bucket.'.$bucket), PartiesReport::DAYS_BUCKETS),
            'datasets' => [[
                'label' => __('admin.analytics.parties.col.completed'),
                'data' => array_map(static fn (string $bucket): int => (int) ($histogram[$bucket] ?? 0), PartiesReport::DAYS_BUCKETS),
                'color' => MoneySeriesChart::COLORS['refunded'],
            ]],
        ];
    }
}
