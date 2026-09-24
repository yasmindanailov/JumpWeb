<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\SegmentsReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Los segmentos de clientes** (`specs/analitica.md` §4.6, T4b): cuatro listas de personas con las que el parque
 * quiere volver a hablar, con cuántas son y cuántas dieron el opt-in de comunicaciones —que son las únicas que
 * la exportación se lleva—. No depende del periodo del filtro: un segmento es un estado de HOY.
 */
class SegmentsWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $counts = SegmentsReport::counts();

        $rows = array_map(static fn (string $segment): array => [
            (string) __('admin.analytics.segments.name.'.$segment),
            (string) $counts[$segment]['size'],
            (string) $counts[$segment]['opt_in'],
        ], SegmentsReport::SEGMENTS);

        return [
            'heading' => __('admin.analytics.segments.heading'),
            'description' => __('admin.analytics.segments.note', ['days' => SegmentsReport::ONCE_DAYS, 'from' => SegmentsReport::PARTY_FROM_MONTHS, 'to' => SegmentsReport::PARTY_TO_MONTHS]),
            'tables' => [[
                'heading' => __('admin.analytics.segments.table_heading'),
                'columns' => [
                    __('admin.analytics.segments.col.segment'),
                    __('admin.analytics.segments.col.size'),
                    __('admin.analytics.segments.col.opt_in'),
                ],
                'rows' => $rows,
                'wide' => true,
            ]],
        ];
    }
}
