<?php

namespace App\Filament\Widgets\Analytics;

use Illuminate\Contracts\Support\Htmlable;

/**
 * **Vendido y cobrado por canal, en euros** (`specs/analitica.md` §4.5, T2f): web, app, panel, sistema y lo
 * anterior a la medición, con las dos series en sus colores de siempre (vendido en verde-agua, cobrado en azul).
 * Su vista de tabla es «Por canal» en el desglose plegado.
 */
class MoneyChannelsChart extends CategoryChart
{
    protected static ?int $sort = 3;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.money.channels_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var list<array{channel: string, orders: int, sold: int, collected: int}> $rows */
        $rows = $this->money()['by_channel'];

        if ($rows === []) {
            return null;
        }

        return [
            'labels' => array_map(static fn (array $r): string => __('admin.analytics.money.channel.'.$r['channel']), $rows),
            'datasets' => [
                [
                    'label' => __('admin.analytics.money.sold'),
                    'data' => array_map(static fn (array $r): float => round($r['sold'] / 100, 2), $rows),
                    'color' => MoneySeriesChart::COLORS['sold'],
                ],
                [
                    'label' => __('admin.analytics.money.collected'),
                    'data' => array_map(static fn (array $r): float => round($r['collected'] / 100, 2), $rows),
                    'color' => MoneySeriesChart::COLORS['collected'],
                ],
            ],
        ];
    }
}
