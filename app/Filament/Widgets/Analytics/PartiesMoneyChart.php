<?php

namespace App\Filament\Widgets\Analytics;

use Illuminate\Contracts\Support\Htmlable;

/**
 * **Lo vendido después de reservar, por complemento** (`specs/analitica-fiesta.md` §4.3, T2): una barra por
 * complemento del post-form (las :top primeras, con sus unidades en el rótulo) y, si los hubo, una más con los
 * invitados añadidos y quitados. En euros. Sin ventas no hay gráfico; su tabla es «Por complemento», plegada al pie.
 */
class PartiesMoneyChart extends CategoryChart
{
    protected static ?int $sort = 12;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.parties.money_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var array<string, mixed> $money */
        $money = $this->parties()['money'];
        /** @var list<array{addon: string, units: int, cents: int}> $byAddon */
        $byAddon = $money['by_addon'];

        $labels = [];
        $data = [];
        foreach ($byAddon as $row) {
            $labels[] = $row['addon'].' ('.$row['units'].')';
            $data[] = round($row['cents'] / 100, 2);
        }
        if ((int) $money['guests'] !== 0) {
            // Corto a propósito: el rótulo largo de la tabla se recortaba en el eje del gráfico (medido en la sonda).
            $labels[] = __('admin.analytics.parties.row.guests_chart', ['added' => $money['guests_added'], 'removed' => $money['guests_removed']]);
            $data[] = round((int) $money['guests'] / 100, 2);
        }

        if ($labels === []) {
            return null;
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => __('admin.analytics.parties.col.amount'),
                'data' => $data,
                'color' => MoneySeriesChart::COLORS['sold'],
            ]],
        ];
    }
}
