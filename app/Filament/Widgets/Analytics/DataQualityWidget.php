<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\RejectedEvents;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use Filament\Schemas\Components\View;

/**
 * **«Calidad del dato», plegado y al final de Marketing** (T3a de `analitica-para-decidir.md` §4.1 y §4.13, `#759`): cuánto
 * fiarse de las cifras de arriba —las visitas atadas a una cuenta, lo que queda fuera del recuento (robots y el equipo), los
 * eventos que la ingesta rechazó— y qué quieren decir «Sin dato (antes de medir)» y «Automático» en las tablas por canal. Es
 * el ÚNICO sitio del cuadro donde vive la jerga técnica (§4.11; `AnalyticsJargonTest` lo vigila).
 */
class DataQualityWidget extends MetricsWidget
{
    public const KEYS = ['traffic.identified', 'traffic.excluded'];

    public const FOLDED = true;

    protected static ?int $sort = 16;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.traffic.quality_heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.traffic.quality_note');
    }

    protected function metrics(): array
    {
        return MarketingMetrics::for($this->window(), $this->comparison());
    }

    protected function afterTiles(): array
    {
        return [View::make('filament.widgets.analytics.table')->viewData(['table' => $this->rejected()])->columnSpanFull()];
    }

    /**
     * La tabla de los eventos rechazados, también para el CSV de su informe.
     *
     * @return list<array{heading: string, columns: list<string>, rows: list<list<string>>}>
     */
    public function tablesFor(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $this->forcedWindow = $window;
        $this->forcedComparison = $comparison;

        return [$this->rejected()];
    }

    /** @return array{heading: string, columns: list<string>, rows: list<list<string>>} */
    private function rejected(): array
    {
        /** @var array{days: int, total: int, by_reason: array<string, int>, by_day: array<string, int>, dropped_events: int} $rejected */
        $rejected = FunnelReport::for($this->window(), $this->comparison())['rejected'];
        $rows = [];
        foreach (RejectedEvents::REASONS as $reason) {
            $rows[] = [__('admin.analytics.traffic.rejected_reason.'.$reason), (string) ($rejected['by_reason'][$reason] ?? 0)];
        }
        $rows[] = [__('admin.analytics.traffic.rejected_total'), (string) $rejected['total']];
        $rows[] = [__('admin.analytics.traffic.dropped_events'), (string) $rejected['dropped_events']];

        return [
            'heading' => __('admin.analytics.traffic.rejected_heading', ['days' => $rejected['days']]),
            'columns' => [__('admin.analytics.money.col.what'), __('admin.analytics.money.col.count')],
            'rows' => $rows,
        ];
    }
}
