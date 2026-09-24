<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Un gráfico de CATEGORÍAS del cuadro** (`docs/specs/analitica.md` §4.5, T2f; `DECISIONES #736`): barras
 * horizontales —una por producto, canal, paso del embudo o fuente— o un anillo para un reparto de tres o cuatro
 * partes (dispositivo, método de alta). Nació de la pasada de forma del 24-09: el owner vio T2a–T2d en escritorio
 * y pidió ordenar los números, mejorar los gráficos y menos tablas. Cada tabla que aquí se vuelve gráfico sigue
 * existiendo, plegada al pie de su pestaña, como su vista de tabla y como lo que lleva el CSV.
 *
 * ⚠️ Barras HORIZONTALES a propósito: los rótulos son palabras («Cumpleaños Jump 90 min», «Cesta · 12,3 %») y en
 * vertical se cortan o se giran. El eje de valores empieza en cero y no enseña decimales.
 * ⚠️ Los colores son los tres validados de {@see MoneySeriesChart::COLORS}, en orden fijo, y un gris neutro para
 * «sin dato». Sin categorías no hay ejes vacíos: sale el estado vacío de Filament con el mismo texto que las tablas.
 */
abstract class CategoryChart extends ChartWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    public const KIND_BARS = 'bars';

    public const KIND_RING = 'ring';

    /** Un gris neutro para «sin dato» (el cuarto hueco de la paleta). */
    public const COLOR_NONE = '#9ca3af';

    /** @var list<string> */
    public const PALETTE = [
        MoneySeriesChart::COLORS['collected'],
        MoneySeriesChart::COLORS['refunded'],
        MoneySeriesChart::COLORS['sold'],
        self::COLOR_NONE,
    ];

    /** Media rejilla en escritorio: estos gráficos van de dos en dos. */
    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '300px';

    protected string $kind = self::KIND_BARS;

    /**
     * Las categorías y sus valores, ya traducidos: `null` (o sin rótulos) es «sin datos». En un anillo, `colors`
     * son los de cada porción; en barras, `color` es el de la serie.
     *
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string, colors?: list<string>}>}|null
     */
    abstract protected function categories(): ?array;

    protected function getType(): string
    {
        return $this->kind === self::KIND_RING ? 'doughnut' : 'bar';
    }

    public function getEmptyStateHeading(): string|Htmlable
    {
        return __('admin.analytics.money.empty');
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $categories = $this->categories();

        if ($categories === null || $categories['labels'] === []) {
            return [];
        }

        $datasets = [];
        foreach ($categories['datasets'] as $i => $dataset) {
            $datasets[] = $this->kind === self::KIND_RING
                ? [
                    'label' => $dataset['label'],
                    'data' => $dataset['data'],
                    'backgroundColor' => $dataset['colors'] ?? self::cycle(count($dataset['data'])),
                    'borderWidth' => 0,
                ]
                : [
                    'label' => $dataset['label'],
                    'data' => $dataset['data'],
                    'backgroundColor' => $dataset['color'] ?? self::PALETTE[$i % 3],
                    'borderColor' => $dataset['color'] ?? self::PALETTE[$i % 3],
                    'borderRadius' => 4,
                    'maxBarThickness' => 22,
                ];
        }

        return ['labels' => $categories['labels'], 'datasets' => $datasets];
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        if ($this->kind === self::KIND_RING) {
            return [
                'cutout' => '62%',
                'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            ];
        }

        /** @var list<mixed> $datasets */
        $datasets = $this->getCachedData()['datasets'] ?? [];

        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => count($datasets) > 1, 'position' => 'bottom']],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }

    /**
     * Los colores de N porciones, recorriendo la paleta.
     *
     * @return list<string>
     */
    protected static function cycle(int $count): array
    {
        return array_map(static fn (int $i): string => self::PALETTE[$i % count(self::PALETTE)], range(0, max($count - 1, 0)));
    }
}
