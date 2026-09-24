<?php

namespace App\Filament\Widgets\Analytics;

use Illuminate\Contracts\Support\Htmlable;

/**
 * **Visitas por dispositivo** (`specs/analitica.md` §4.5, T2f): móvil, tableta, ordenador y sin dato, como anillo.
 * Su vista de tabla es «Dispositivo», plegada al pie de la pestaña.
 */
class DevicesChart extends CategoryChart
{
    /** @var list<string> en este orden, con su color fijo; lo que no esté aquí es «sin dato» */
    public const DEVICES = ['mobile', 'tablet', 'desktop'];

    protected static ?int $sort = 14;

    protected string $kind = self::KIND_RING;

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.traffic.devices_chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, colors?: list<string>}>}|null */
    protected function categories(): ?array
    {
        /** @var array<string, int> $devices */
        $devices = $this->funnel()['devices'];

        $labels = [];
        $data = [];
        $colors = [];
        foreach (self::DEVICES as $i => $device) {
            if (($devices[$device] ?? 0) === 0) {
                continue;
            }
            $labels[] = __('admin.analytics.traffic.device.'.$device);
            $data[] = $devices[$device];
            $colors[] = self::PALETTE[$i];
        }

        $other = array_sum(array_filter($devices, static fn (string $k): bool => ! in_array($k, self::DEVICES, true), ARRAY_FILTER_USE_KEY));
        if ($other > 0) {
            $labels[] = __('admin.analytics.traffic.no_data');
            $data[] = $other;
            $colors[] = self::COLOR_NONE;
        }

        if ($data === []) {
            return null;
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => __('admin.analytics.traffic.col.visits'),
                'data' => $data,
                'colors' => $colors,
            ]],
        ];
    }
}
