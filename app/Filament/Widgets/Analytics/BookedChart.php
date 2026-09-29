<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\BookedReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Lo ya vendido para cada semana que viene** (T4 de `analitica-para-decidir.md` §4.8 y §4.8.quater): esta semana (desde hoy) y
 * las 12 siguientes, en plazas: «vendido ya» frente a «a estas alturas» (la misma semana hace un año, o la media de las cuatro
 * anteriores al mismo horizonte). Es lo que dice DÓNDE falta venta: una semana muy por debajo de lo de siempre pide una
 * promoción ya. Su vista de tabla, con los euros, va plegada al pie ({@see OccupancyBreakdownWidget}). Mira hacia delante: no
 * depende del periodo del filtro.
 */
class BookedChart extends CategoryChart
{
    protected static ?int $sort = 12;

    /** Trece semanas no caben en la altura de los gráficos de media rejilla. */
    protected ?string $maxHeight = '420px';

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.analytics.booked.chart');
    }

    /** @return array{labels: list<string>, datasets: list<array{label: string, data: list<int|float>, color?: string}>}|null */
    protected function categories(): ?array
    {
        /** @var list<array{from: string, to: string, seats: int, cents: int, baseline: array{seats: float, cents: float}|null}> $weeks */
        $weeks = BookedReport::for()['weeks'];
        if (array_sum(array_column($weeks, 'seats')) === 0 && array_filter(array_column($weeks, 'baseline')) === []) {
            return null;
        }

        $datasets = [[
            'label' => __('admin.analytics.booked.chart_now'),
            'data' => array_column($weeks, 'seats'),
            'color' => self::PALETTE[0],
        ]];
        if (array_filter(array_column($weeks, 'baseline')) !== []) {
            $datasets[] = [
                'label' => __('admin.analytics.booked.chart_before'),
                'data' => array_map(static fn (array $w): float => round((float) ($w['baseline']['seats'] ?? 0), 1), $weeks),
                'color' => self::COLOR_NONE,
            ];
        }

        return ['labels' => array_map([self::class, 'weekLabel'], $weeks), 'datasets' => $datasets];
    }

    /**
     * Trece semanas de dos barras: más alto que ancho (el tope de altura sigue mandando) y con todos sus rótulos. Con la
     * proporción de siempre, 2:1, medía ~200 px y Chart.js saltaba la mitad de las semanas a 1280 px y dos tercios a 390.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $options = parent::getOptions();
        $options['aspectRatio'] = 1;
        $options['scales']['y']['ticks'] = ['autoSkip' => false];

        return $options;
    }

    /**
     * «29 sep – 5 oct»: la semana con sus dos días, en el idioma del panel.
     *
     * @param  array{from: string, to: string}  $week
     */
    public static function weekLabel(array $week): string
    {
        $day = static fn (string $date): string => CarbonImmutable::parse($date)->locale(app()->getLocale())->isoFormat(__('admin.analytics.window.date_same_year'));

        return __('admin.analytics.booked.week', ['from' => $day($week['from']), 'to' => $day($week['to'])]);
    }
}
