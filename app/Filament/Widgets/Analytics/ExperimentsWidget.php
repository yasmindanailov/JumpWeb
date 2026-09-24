<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\ExperimentsReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Los experimentos en «Conversión»** (`specs/analitica.md` §4.4, T5b): por cada experimento con exposiciones en el
 * periodo, una tabla con sus variantes —visitantes expuestos, cuántos compraron después y la conversión con su
 * intervalo de Wilson al 95 %— y, en el título, los contaminados (visitantes y cuentas que vieron dos variantes).
 * Depende del periodo del filtro, como el embudo. La configuración (alta y cierre) vive en «Ajustes → Experimentos».
 */
class ExperimentsWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $report = ExperimentsReport::for($this->window());
        $columns = [
            __('admin.analytics.experiments.col.variant'),
            __('admin.analytics.experiments.col.exposed'),
            __('admin.analytics.experiments.col.converted'),
            __('admin.analytics.experiments.col.rate'),
        ];

        $tables = [];
        foreach ($report['experiments'] as $experiment) {
            $tables[] = [
                'heading' => __('admin.analytics.experiments.table_heading', [
                    'name' => $experiment['name'],
                    'key' => $experiment['key'],
                    'visitors' => $experiment['contaminated_visitors'],
                    'users' => $experiment['contaminated_users'],
                ]),
                'columns' => $columns,
                'rows' => array_map(static fn (array $v): array => [
                    $v['variant'],
                    (string) $v['exposed'],
                    (string) $v['converted'],
                    self::percent($v['rate_bp']).' ('.self::percent($v['low_bp']).'–'.self::percent($v['high_bp']).')',
                ], $experiment['variants']),
                'wide' => true,
            ];
        }

        if ($tables === []) {
            $tables[] = ['heading' => __('admin.analytics.experiments.none'), 'columns' => $columns, 'rows' => [], 'wide' => true];
        }

        return [
            'heading' => __('admin.analytics.experiments.heading'),
            'description' => __('admin.analytics.experiments.note'),
            'tables' => $tables,
        ];
    }
}
