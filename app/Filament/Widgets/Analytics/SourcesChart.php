<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\FunnelReport;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Visitas por fuente, primer toque** (`specs/analitica.md` §4.5, T2f): las filas de fuentes del informe —que van
 * por fuente, medio y campaña— sumadas por FUENTE, las ocho primeras, como barras. Lo que tecleó el anunciante se
 * pinta como texto. Su vista de tabla es «Por primer toque», plegada al pie de la pestaña.
 */
class SourcesChart extends CategoryChart
{
    public const TOP = 8;

    protected static ?int $sort = 11;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.traffic.sources_chart', ['top' => self::TOP]);
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var list<array{source: string, medium: string, campaign: ?string, visits: int, orders: int, sold: int, collected: int}> $rows */
        $rows = $this->funnel()['sources'];

        $bySource = [];
        foreach ($rows as $row) {
            $bySource[$row['source']] = ($bySource[$row['source']] ?? 0) + $row['visits'];
        }
        arsort($bySource);
        $bySource = array_slice($bySource, 0, self::TOP, preserve_keys: true);

        if ($bySource === [] || array_sum($bySource) === 0) {
            return null;
        }

        return [
            'labels' => array_map(static fn (string $source): string => match ($source) {
                'direct' => __('admin.analytics.traffic.source.direct'),
                FunnelReport::SOURCE_UNKNOWN => __('admin.analytics.traffic.source.unknown'),
                default => $source,
            }, array_keys($bySource)),
            'datasets' => [[
                'label' => __('admin.analytics.traffic.col.visits'),
                'data' => array_values($bySource),
                'color' => MoneySeriesChart::COLORS['collected'],
            ]],
        ];
    }
}
