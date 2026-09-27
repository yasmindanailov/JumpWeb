<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
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

        // La mecánica de la puerta (buscar, teclear, escanear, abrir fichas) no es buena ni mala: NEUTRA. Las visitas sí.
        return [
            $this->metric(Metric::count('customers.lookups', __('admin.analytics.customers.lookups'), $g['lookups'], $p['lookups'], Polarity::Neutral, self::how('customers.lookups'))),
            $this->metric(Metric::count('customers.typed', __('admin.analytics.customers.typed'), $g['typed'], null, Polarity::Neutral, self::how('customers.typed'))),
            $this->metric(Metric::count('customers.scanned', __('admin.analytics.customers.scanned'), $g['scanned'], null, Polarity::Neutral, self::how('customers.scanned'))),
            $this->metric(Metric::count('customers.found', __('admin.analytics.customers.found'), $g['found'], null, Polarity::Neutral, self::how('customers.found'), detail: __('admin.analytics.customers.not_found_line', ['count' => $g['not_found']]))),
            $this->metric(Metric::count('customers.customers', __('admin.analytics.customers.customers'), $g['customers'], null, Polarity::Neutral, self::how('customers.customers'))),
            $this->metric(Metric::count('customers.profile_views', __('admin.analytics.customers.profile_views'), $g['profile_views'], null, Polarity::Neutral, self::how('customers.profile_views'))),
            $this->metric(Metric::count('customers.visits', __('admin.analytics.customers.visits'), $g['visits'], $p['visits'], Polarity::UpIsGood, self::how('customers.visits'))),
            $this->metric(Metric::count('customers.visitors', __('admin.analytics.customers.visitors'), $g['visitors'], null, Polarity::UpIsGood, self::how('customers.visitors'))),
        ];
    }
}
