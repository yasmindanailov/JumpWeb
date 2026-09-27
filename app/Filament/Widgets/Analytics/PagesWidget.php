<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\FunnelReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Por dónde entran, por dónde se van, cuándo, con qué, en qué idioma, qué eligen y cómo contactan** (`specs/analitica.md`
 * §4.5, T2c). Todo agregado y escapado. Desde la T3a (`#759`) las horas y los dispositivos son TABLA y no gráfico (tres
 * gráficos por pestaña, §2) —la de las horas es nueva: su dato no estaba en ninguna tabla ni en el CSV— y los eventos
 * rechazados viven en «Calidad del dato» ({@see DataQualityWidget}).
 */
class PagesWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 15;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

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
                $this->hours($report['hours']),
                $this->keyed(__('admin.analytics.traffic.devices'), $report['devices'], 'admin.analytics.traffic.device.'),
                $this->keyed(__('admin.analytics.traffic.locales'), $report['locales'], null),
                $this->products($report['products']),
                $this->contact($report['contact']),
            ],
        ];
    }

    /**
     * Las visitas por hora del PARQUE (no la UTC de la sesión), las 24: era el gráfico de las horas.
     *
     * @param  list<int>  $hours
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function hours(array $hours): array
    {
        return [
            'heading' => __('admin.analytics.traffic.hours_heading'),
            'columns' => [__('admin.analytics.occupancy.col.hour'), __('admin.analytics.traffic.col.visits')],
            'rows' => array_map(static fn (int $h): array => [sprintf('%02d h', $h), (string) ($hours[$h] ?? 0)], range(0, 23)),
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
}
