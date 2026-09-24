<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Services\Analytics\RejectedEvents;
use App\Filament\Analytics\FunnelReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Por dónde entran, por dónde se van, con qué, en qué idioma, qué eligen y cómo contactan** (`specs/analitica.md`
 * §4.5, T2c), y los eventos que la ingesta rechazó en la última semana. Todo agregado y escapado.
 */
class PagesWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 15;

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
            'heading' => __('admin.analytics.traffic.pages_heading'),
            'description' => __('admin.analytics.traffic.pages_note'),
            'tables' => [
                $this->routes(__('admin.analytics.traffic.entries'), $report['entries'], 'visits', __('admin.analytics.traffic.col.visits')),
                $this->routes(__('admin.analytics.traffic.exits'), $report['exits'], 'count', __('admin.analytics.traffic.col.reached')),
                $this->keyed(__('admin.analytics.traffic.devices'), $report['devices'], 'admin.analytics.traffic.device.'),
                $this->keyed(__('admin.analytics.traffic.locales'), $report['locales'], null),
                $this->products($report['products']),
                $this->contact($report['contact']),
                $this->rejected($report['rejected']),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function routes(string $heading, array $rows, string $field, string $column): array
    {
        return [
            'heading' => $heading,
            'columns' => [__('admin.analytics.traffic.col.route'), $column],
            'rows' => array_map(static fn (array $r): array => [
                $r['route'] !== '' ? (string) $r['route'] : __('admin.analytics.traffic.no_route'),
                (string) $r[$field],
            ], $rows),
        ];
    }

    /**
     * @param  array<string, int>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function keyed(string $heading, array $rows, ?string $labelPrefix): array
    {
        $out = [];
        foreach ($rows as $key => $count) {
            $label = $key === '' ? __('admin.analytics.traffic.no_data') : ($labelPrefix !== null ? __($labelPrefix.$key) : $key);
            $out[] = [$label, (string) $count];
        }

        return [
            'heading' => $heading,
            'columns' => [__('admin.analytics.money.col.what'), __('admin.analytics.traffic.col.visits')],
            'rows' => $out,
        ];
    }

    /**
     * @param  list<array{product: string, count: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function products(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.traffic.products'),
            'columns' => [__('admin.analytics.money.col.product'), __('admin.analytics.traffic.col.reached')],
            'rows' => array_map(static fn (array $r): array => [$r['product'], (string) $r['count']], $rows),
        ];
    }

    /**
     * @param  array<string, int>  $contact
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function contact(array $contact): array
    {
        $rows = [];
        foreach (FunnelReport::CONTACT_EVENTS as $event) {
            $rows[] = [__('admin.analytics.traffic.contact.'.$event), (string) ($contact[$event] ?? 0)];
        }

        return [
            'heading' => __('admin.analytics.traffic.contact_heading'),
            'columns' => [__('admin.analytics.money.col.what'), __('admin.analytics.money.col.count')],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{days: int, total: int, by_reason: array<string, int>, by_day: array<string, int>, dropped_events: int}  $rejected
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function rejected(array $rejected): array
    {
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
