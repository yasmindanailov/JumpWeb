<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Las fuentes y las campañas** (`specs/analitica.md` §4.5, T2c): por primer toque —visitas, pedidos, vendido y
 * cobrado— y por último toque (la campaña que cerró la compra). Lo que teclea un anunciante en una UTM llega
 * aquí como texto y se escapa al pintar: `=1+1` es una campaña que se llama así.
 */
class SourcesWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 12;

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
            'heading' => __('admin.analytics.traffic.sources_heading'),
            'description' => __('admin.analytics.traffic.sources_note'),
            'tables' => [
                $this->firstTouch($report['sources']),
                $this->lastTouch($report['last_touch']),
            ],
        ];
    }

    /**
     * @param  list<array{source: string, medium: string, campaign: ?string, visits: int, orders: int, sold: int, collected: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function firstTouch(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.traffic.first_touch'),
            'columns' => [__('admin.analytics.traffic.col.source'), __('admin.analytics.traffic.col.medium'), __('admin.analytics.traffic.col.campaign'), __('admin.analytics.traffic.col.visits'), __('admin.analytics.money.col.orders'), __('admin.analytics.money.col.sold'), __('admin.analytics.money.col.collected')],
            'rows' => array_map(fn (array $r): array => [
                self::label($r['source']),
                self::label($r['medium']),
                $r['campaign'] ?? '—',
                (string) $r['visits'],
                (string) $r['orders'],
                Money::format($r['sold']),
                Money::format($r['collected']),
            ], $rows),
            'wide' => true,
        ];
    }

    /**
     * @param  list<array{source: string, medium: string, campaign: ?string, orders: int, sold: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function lastTouch(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.traffic.last_touch'),
            'columns' => [__('admin.analytics.traffic.col.source'), __('admin.analytics.traffic.col.medium'), __('admin.analytics.traffic.col.campaign'), __('admin.analytics.money.col.orders'), __('admin.analytics.money.col.sold')],
            'rows' => array_map(fn (array $r): array => [
                self::label($r['source']),
                self::label($r['medium']),
                $r['campaign'] ?? '—',
                (string) $r['orders'],
                Money::format($r['sold']),
            ], $rows),
            'wide' => true,
        ];
    }

    /** «direct», «none» y «unknown» son palabras del contrato: se traducen; lo demás es lo que escribió el anunciante. */
    private static function label(string $value): string
    {
        return match ($value) {
            'direct' => __('admin.analytics.traffic.source.direct'),
            'none' => __('admin.analytics.traffic.source.none'),
            FunnelReport::SOURCE_UNKNOWN => __('admin.analytics.traffic.source.unknown'),
            default => $value,
        };
    }
}
