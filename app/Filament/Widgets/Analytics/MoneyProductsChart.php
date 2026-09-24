<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\MoneyReport;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Vendido por producto, en euros** (`specs/analitica.md` §4.5, T2f): los diez primeros del periodo, como barras.
 * Su vista de tabla es «Por producto» en el desglose plegado.
 */
class MoneyProductsChart extends CategoryChart
{
    protected static ?int $sort = 3;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.money.products_chart', ['top' => MoneyReport::TOP_PRODUCTS]);
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var list<array{product: string, units: int, value: int}> $rows */
        $rows = $this->money()['by_product'];

        if ($rows === []) {
            return null;
        }

        return [
            'labels' => array_map(static fn (array $r): string => $r['product'], $rows),
            'datasets' => [[
                'label' => __('admin.analytics.money.sold'),
                'data' => array_map(static fn (array $r): float => round($r['value'] / 100, 2), $rows),
                'color' => MoneySeriesChart::COLORS['sold'],
            ]],
        ];
    }
}
