<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Widgets\Analytics\Concerns\ReadsMoneyReport;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Cobrado, devuelto y vendido por día (o por semana)** (`specs/analitica.md` §4.5, T2a): tres series en
 * EUROS sobre UN solo eje, barras finas con hueco y esquinas suaves en el extremo, leyenda siempre (son tres)
 * y el tooltip por columna de Chart.js, que Filament trae.
 *
 * ⚠️ Un solo eje: las tres cifras son la misma magnitud. Dos ejes para dos escalas es el error número uno de
 * un gráfico, y aquí no hace falta.
 * ⚠️ Los colores son los tres primeros huecos de una paleta categórica VALIDADA para daltonismo (ΔE ≥ 8 entre
 * vecinos, medido con el validador el 24-09: 9,2 en deuteranopía): azul, naranja y verde-agua, en orden FIJO —la
 * identidad sigue a la serie, nunca a su posición—. El verde-agua queda a 2,74:1 sobre fondo claro y la regla
 * de la paleta pide entonces una VISTA DE TABLA con los mismos datos: es la tabla «Por día» del desglose. Se
 * midieron y descartaron el verde (ΔE 3,2 contra el naranja en protanopía: indistinguibles) y el violeta (2,04:1
 * en oscuro). En modo oscuro Filament no cambia los colores de serie; el naranja roza el borde de luminosidad
 * (0,671 frente a 0,67). Lo que se ve lo juzga el owner en vivo.
 */
class MoneySeriesChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use ReadsMoneyReport;

    /** @var array<string, string> serie → color (paleta categórica validada, huecos 1–3, modo claro) */
    public const COLORS = [
        'collected' => '#2a78d6',
        'refunded' => '#eb6834',
        'sold' => '#1baf7a',
    ];

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): string|Htmlable|null
    {
        $granularity = (string) $this->report()['window']['granularity'];

        return __('admin.analytics.money.series_heading', [
            'granularity' => __('admin.analytics.money.granularity.'.$granularity),
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $report = $this->report();
        /** @var list<array{key: string, collected: int, refunded: int, sold: int, orders: int}> $series */
        $series = $report['series'];
        $granularity = (string) $report['window']['granularity'];

        return [
            'labels' => array_map(static fn (array $row): string => self::label($row['key'], $granularity), $series),
            'datasets' => [
                self::dataset('collected', $series),
                self::dataset('refunded', $series),
                self::dataset('sold', $series),
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }

    /**
     * @param  list<array{key: string, collected: int, refunded: int, sold: int, orders: int}>  $series
     * @return array<string, mixed>
     */
    private static function dataset(string $key, array $series): array
    {
        return [
            'label' => __('admin.analytics.money.'.$key),
            'data' => array_map(static fn (array $row): float => round($row[$key] / 100, 2), $series),
            'backgroundColor' => self::COLORS[$key],
            'borderColor' => self::COLORS[$key],
            'borderRadius' => 4,
            'maxBarThickness' => 18,
        ];
    }

    /** `01/06` por día; «Sem. del 01/06» por semana (su lunes). */
    private static function label(string $key, string $granularity): string
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $key, 'UTC')->format('d/m');

        return $granularity === Window::GRANULARITY_WEEK
            ? __('admin.analytics.money.week_of', ['day' => $day])
            : $day;
    }
}
