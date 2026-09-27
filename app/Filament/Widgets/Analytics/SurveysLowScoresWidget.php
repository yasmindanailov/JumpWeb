<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\SurveysReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **«Notas bajas y si volvieron»** (`specs/encuestas.md` §4.7, T5; `[DECIDIDO owner]` `DECISIONES #754`): sustituye a
 * «Por atender», que enseñaba quién puntuó mal para llamarle («para llamarle, no», el owner). Ahora, sin nadie: de las
 * respuestas del periodo con una escala, cuántas fueron nota baja (≤ 2) y cuántas del resto, y de cada grupo cuántas
 * ya han VUELTO al parque en los 90 días siguientes y cuántas aún pueden volver.
 *
 * ⚠️⚠️ **Los mínimos** (`SurveysReport::MIN_CELL`): el reparto entre los dos grupos, solo con cinco o más respuestas en
 * total; «han vuelto» y «aún pueden volver» de un grupo, solo con cinco o más en ESE grupo (sin las que «no se sabe»:
 * anonimizadas o de antes de medir, que tampoco entran en la tasa). Con un grupo de uno, su «volvió» y la lista de
 * quién volvió (la 360) dirían quién puntuó mal.
 */
class SurveysLowScoresWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var array{low: array{n: int, returned: int, pending: int, unknown: int}, rest: array{n: int, returned: int, pending: int, unknown: int}} $groups */
        $groups = $this->surveys()['low_scores'];
        $total = $groups['low']['n'] + $groups['rest']['n'];
        $fewer = __('admin.analytics.surveys.fewer_than_min', ['min' => SurveysReport::MIN_CELL]);

        $rows = [];
        if ($total > 0 && ! SurveysReport::enough($total)) {
            $rows[] = [__('admin.analytics.surveys.low.all'), $fewer, '—', '—'];
        } elseif ($total > 0) {
            foreach (['low', 'rest'] as $group) {
                $g = $groups[$group];
                // La tasa, sobre las que se saben o aún pueden volver: una anonimizada o de antes de medir no es un «no».
                $base = $g['n'] - $g['unknown'];
                $known = SurveysReport::enough($base);
                $rows[] = [
                    __('admin.analytics.surveys.low.'.$group),
                    (string) $g['n'],
                    $known ? $g['returned'].' · '.self::percent((int) round($g['returned'] / $base * 10000)) : $fewer,
                    $known ? (string) $g['pending'] : '—',
                ];
            }
        }

        return [
            'heading' => __('admin.analytics.surveys.low.heading'),
            'description' => __('admin.analytics.surveys.low.note', ['min' => SurveysReport::MIN_CELL]),
            'tables' => [[
                'heading' => __('admin.analytics.surveys.low.table'),
                'columns' => [
                    __('admin.analytics.surveys.low.col.group'),
                    __('admin.analytics.surveys.low.col.answers'),
                    __('admin.analytics.surveys.low.col.returned'),
                    __('admin.analytics.surveys.low.col.pending'),
                ],
                'rows' => $rows,
                'wide' => true,
            ]],
        ];
    }
}
