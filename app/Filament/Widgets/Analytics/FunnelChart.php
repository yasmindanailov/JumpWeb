<?php

namespace App\Filament\Widgets\Analytics;

use Illuminate\Contracts\Support\Htmlable;

/**
 * **El embudo como barras** (`specs/analitica.md` §4.5, T2f): las sesiones que alcanzan cada paso, de las visitas
 * al pago iniciado, y en el rótulo de cada barra el % de las visitas («Cesta · 12,3 %»), que es la cifra que se
 * busca al mirarlo. Sin visitas no hay embudo. Su vista de tabla es «Paso a paso», plegada al pie de la pestaña.
 */
class FunnelChart extends CategoryChart
{
    protected static ?int $sort = 11;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.traffic.funnel_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var list<array{step: string, reached: int, of_previous_bp: int, of_visits_bp: int}> $funnel */
        $funnel = $this->funnel()['funnel'];

        if ($funnel === [] || $funnel[0]['reached'] === 0) {
            return null;
        }

        return [
            'labels' => array_map(
                static fn (array $row): string => __('admin.analytics.traffic.step_short.'.$row['step']).' · '.self::percent($row['of_visits_bp']),
                $funnel,
            ),
            'datasets' => [[
                'label' => __('admin.analytics.traffic.col.reached'),
                'data' => array_map(static fn (array $row): int => $row['reached'], $funnel),
                'color' => MoneySeriesChart::COLORS['collected'],
            ]],
        ];
    }
}
