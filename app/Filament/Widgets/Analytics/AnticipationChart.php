<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\OccupancyReport;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Con cuánta antelación se compra** (`specs/analitica-para-decidir.md` §4.8, la T2): cuántas líneas se cobraron el mismo
 * día, 1–2 días antes, 3–7, 8–30 y más de 30, por TIPO de visita (entrada, fiesta, grupo), en los tres colores validados en
 * su orden fijo. Es lo que dice CUÁNDO lanzar: si los sábados se compran dos días antes, la promoción sale el miércoles.
 * Su vista de tabla es «Anticipación por tipo», plegada al pie.
 */
class AnticipationChart extends CategoryChart
{
    protected static ?int $sort = 12;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.occupancy.anticipation_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var array<string, mixed> $a */
        $a = $this->occupancy()['anticipation'];
        if ((int) $a['n'] === 0) {
            return null;
        }

        $datasets = [];
        foreach (OccupancyReport::KINDS as $i => $kind) {
            /** @var array{n: int, buckets: array<string, int>} $k */
            $k = $a['by_kind'][$kind];
            if ($k['n'] === 0) {
                continue;
            }
            $datasets[] = [
                'label' => __('admin.analytics.occupancy.kind.'.$kind),
                'data' => array_values($k['buckets']),
                'color' => self::PALETTE[$i],
            ];
        }

        return [
            'labels' => array_map(static fn (string $b): string => __('admin.analytics.occupancy.bucket.'.$b), array_keys(OccupancyReport::LEAD_BUCKETS)),
            'datasets' => $datasets,
        ];
    }
}
