<?php

namespace App\Filament\Widgets\Analytics;

use Illuminate\Contracts\Support\Htmlable;

/**
 * **El embudo de la fiesta como barras** (`specs/analitica-fiesta.md` §4.3, T2): las reservas que llegan a cada
 * paso —de la fiesta reservada al justificante firmado— y en el rótulo el % de las fiestas del periodo. Sin fiestas
 * no hay embudo. Su vista de tabla es «Paso a paso», plegada al pie de la pestaña.
 */
class PartiesFunnelChart extends CategoryChart
{
    protected static ?int $sort = 11;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.parties.funnel_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var list<array{step: string, reached: int, of_parties_bp: int}> $funnel */
        $funnel = $this->parties()['funnel'];

        if ($funnel === [] || $funnel[0]['reached'] === 0) {
            return null;
        }

        return [
            'labels' => array_map(
                static fn (array $row): string => __('admin.analytics.parties.step_short.'.$row['step']).' · '.self::percent($row['of_parties_bp']),
                $funnel,
            ),
            'datasets' => [[
                'label' => __('admin.analytics.parties.col.reservations'),
                'data' => array_map(static fn (array $row): int => $row['reached'], $funnel),
                'color' => MoneySeriesChart::COLORS['collected'],
            ]],
        ];
    }
}
