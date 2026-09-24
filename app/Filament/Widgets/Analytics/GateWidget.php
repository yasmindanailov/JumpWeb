<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * **La puerta** (`specs/analitica.md` §4.5, T2b): las búsquedas de la pantalla del QR —tecleadas o escaneadas,
 * encontradas o no—, los clientes distintos a los que se buscó, las fichas abiertas y las visitas acreditadas.
 * Es la medida de «cuántos clientes ha habido en el día» que pidió el owner, desde el rastro que la puerta
 * deja desde agosto.
 */
class GateWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.customers.gate_heading');
    }

    /**
     * Ocho tarjetas en cuatro columnas: las dos con variación llevan su texto, su icono y su color JUNTOS
     * (una descripción prestada sobre el color de la variación pintaba en rojo un dato neutro, 24-09).
     *
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $report = $this->customers();
        /** @var array<string, int> $g */
        $g = $report['gate'];
        /** @var array<string, int> $p */
        $p = $report['previous'];

        return [
            $this->countStat(__('admin.analytics.customers.lookups'), $g['lookups'], $p['lookups']),
            Stat::make(__('admin.analytics.customers.typed'), (string) $g['typed'])
                ->description(__('admin.analytics.customers.typed_hint'))
                ->color('gray'),
            Stat::make(__('admin.analytics.customers.scanned'), (string) $g['scanned'])
                ->description(__('admin.analytics.customers.scanned_hint'))
                ->color('gray'),
            Stat::make(__('admin.analytics.customers.found'), (string) $g['found'])
                ->description(__('admin.analytics.customers.not_found_line', ['count' => $g['not_found']]))
                ->color('gray'),
            Stat::make(__('admin.analytics.customers.customers'), (string) $g['customers'])
                ->description(__('admin.analytics.customers.customers_hint'))
                ->color('primary'),
            Stat::make(__('admin.analytics.customers.profile_views'), (string) $g['profile_views'])->color('gray'),
            $this->countStat(__('admin.analytics.customers.visits'), $g['visits'], $p['visits']),
            Stat::make(__('admin.analytics.customers.visitors'), (string) $g['visitors'])
                ->description(__('admin.analytics.customers.visitors_hint'))
                ->color('gray'),
        ];
    }
}
