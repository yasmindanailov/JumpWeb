<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\AudienceReport;
use App\Filament\Analytics\SegmentsReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Los segmentos de clientes** (`specs/analitica.md` §4.6, T4b): cinco grupos de personas con los que el parque quiere
 * volver a hablar, con cuántas son y cuántas dieron el opt-in de comunicaciones. No depende del periodo del filtro: un
 * segmento es un estado de HOY.
 *
 * ⚠️ **Solo recuentos** (TP·3b, `#793`: el público es ANÓNIMO): la exportación con nombres se retiró, y una cifra de 1 a 4 se
 * escribe «menos de 5», como en «Quién viene» (`RGPD-07`).
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
            self::masked((int) $counts[$segment]['size']),
            self::masked((int) $counts[$segment]['opt_in']),
        ], SegmentsReport::SEGMENTS);

        return [
            'heading' => __('admin.analytics.segments.heading'),
            'description' => __('admin.analytics.segments.note', ['days' => SegmentsReport::ONCE_DAYS, 'from' => SegmentsReport::PARTY_FROM_MONTHS, 'to' => SegmentsReport::PARTY_TO_MONTHS, 'min' => AudienceReport::MIN_CELL]),
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

    /** Un recuento de 1 a 4, dicho «menos de 5». */
    private static function masked(int $n): string
    {
        return $n > 0 && $n < AudienceReport::MIN_CELL ? __('admin.analytics.surveys.fewer_than_min', ['min' => AudienceReport::MIN_CELL]) : (string) $n;
    }
}
