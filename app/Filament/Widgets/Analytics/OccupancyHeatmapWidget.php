<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * **El mapa de calor de la ocupación** (`specs/analitica-para-decidir.md` §4.8 y §4.11, la T2): día de la semana × hora
 * de inicio, con el % ESCRITO en cada celda —nunca solo el color— y su tooltip. Es la vista que dice cuándo se llena el
 * parque: qué horas subir de precio o reforzar de personal, y cuáles vaciar de franjas.
 *
 * Magnitud → UN tono (el azul validado de la T2f, `MoneySeriesChart::COLORS['collected']`) de claro a oscuro en cinco
 * pasos de 20 puntos; el texto va en tinta sobre los pasos claros y en blanco solo sobre el lleno (≈ 4,6:1 en los dos
 * modos). Una hora sin franjas ese día sale gris y con «—»: no es un 0 %.
 */
class OccupancyHeatmapWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    /** El tono de la magnitud, uno solo. */
    public const HUE = MoneySeriesChart::COLORS['collected'];

    /** Cuánto del tono lleva cada paso (0–20 %, 20–40 %…, 80–100 %). */
    public const STEPS = [10, 25, 45, 65, 100];

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.heatmap';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var array<int, array<int, array{seats: int, capacity: int}>> $heat */
        $heat = $this->occupancy()['heatmap'];
        $hours = [];
        foreach ($heat as $byHour) {
            foreach (array_keys($byHour) as $hour) {
                $hours[$hour] = true;
            }
        }
        ksort($hours);
        $hours = array_keys($hours);

        $rows = [];
        foreach (range(1, 7) as $weekday) {
            $name = Carbon::now()->startOfWeek()->addDays($weekday - 1)->locale(app()->getLocale())->isoFormat('dddd');
            $cells = [];
            foreach ($hours as $hour) {
                $cell = $heat[$weekday][$hour] ?? null;
                if ($cell === null || $cell['capacity'] === 0) {
                    $cells[] = ['empty' => true, 'label' => '—', 'title' => __('admin.analytics.occupancy.heatmap_empty', ['day' => $name, 'hour' => sprintf('%02d:00', $hour)])];

                    continue;
                }
                $bp = (int) round($cell['seats'] / $cell['capacity'] * 10000);
                $step = min(count(self::STEPS) - 1, intdiv(min($bp, 9999), 2000));
                $cells[] = [
                    'empty' => false,
                    'label' => number_format($bp / 100, 0, ',', '.').' %',
                    'mix' => self::STEPS[$step],
                    'strong' => $step === count(self::STEPS) - 1,
                    'title' => __('admin.analytics.occupancy.heatmap_cell', [
                        'day' => $name, 'hour' => sprintf('%02d:00', $hour), 'percent' => number_format($bp / 100, 1, ',', '.'),
                        'seats' => number_format($cell['seats'], 0, ',', '.'), 'capacity' => number_format($cell['capacity'], 0, ',', '.'),
                    ]),
                ];
            }
            $rows[] = ['day' => mb_convert_case($name, MB_CASE_TITLE), 'cells' => $cells];
        }

        return [
            'heading' => __('admin.analytics.occupancy.heatmap_heading'),
            'description' => __('admin.analytics.occupancy.heatmap_note'),
            'hue' => self::HUE,
            'hours' => array_map(static fn (int $h): string => sprintf('%02d', $h), $hours),
            'rows' => $hours === [] ? [] : $rows,
            'legend' => array_map(static fn (int $i): array => ['mix' => self::STEPS[$i], 'label' => ($i * 20).'–'.(($i + 1) * 20).' %'], array_keys(self::STEPS)),
        ];
    }
}
