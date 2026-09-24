<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **El embudo, paso a paso, y dónde se queda la gente** (`specs/analitica.md` §4.5, T2c; la definición, §4.2):
 * visitas → interés → intención → cesta → identificada → pago iniciado, cada paso con su conversión respecto al
 * anterior y a las visitas; y el abandono como el paso más alto que alcanzó cada sesión, con las que sufrieron
 * un fallo técnico. La compra no es un paso por sesión (no todas las sesiones la atan): está en las tarjetas.
 */
class FunnelWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 11;

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
        $report = $this->funnel();

        return [
            'heading' => __('admin.analytics.traffic.funnel_heading'),
            'description' => __('admin.analytics.traffic.funnel_note'),
            'tables' => [
                $this->funnelTable($report['funnel']),
                $this->abandonment($report['abandonment']),
            ],
        ];
    }

    /**
     * @param  list<array{step: string, reached: int, of_previous_bp: int, of_visits_bp: int}>  $funnel
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function funnelTable(array $funnel): array
    {
        return [
            'heading' => __('admin.analytics.traffic.funnel'),
            'columns' => [__('admin.analytics.traffic.col.step'), __('admin.analytics.traffic.col.reached'), __('admin.analytics.traffic.col.of_previous'), __('admin.analytics.traffic.col.of_visits')],
            'rows' => array_map(static fn (array $row): array => [
                __('admin.analytics.traffic.step.'.$row['step']),
                (string) $row['reached'],
                self::percent($row['of_previous_bp']),
                self::percent($row['of_visits_bp']),
            ], $funnel),
        ];
    }

    /**
     * @param  array<string, array{stuck: int, technical: int}>  $abandonment
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function abandonment(array $abandonment): array
    {
        $rows = [];
        foreach ($abandonment as $step => $data) {
            $rows[] = [__('admin.analytics.traffic.left_at.'.$step), (string) $data['stuck'], (string) $data['technical']];
        }

        return [
            'heading' => __('admin.analytics.traffic.abandonment'),
            'columns' => [__('admin.analytics.traffic.col.left_at'), __('admin.analytics.traffic.col.reached'), __('admin.analytics.traffic.col.technical')],
            'rows' => $rows,
        ];
    }
}
