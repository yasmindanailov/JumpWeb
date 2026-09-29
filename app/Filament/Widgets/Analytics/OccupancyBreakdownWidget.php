<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Platform\Services\Money;
use App\Filament\Analytics\BookedReport;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\OccupancyReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * **La ocupación, al detalle** (`specs/analitica-para-decidir.md` §4.8.ter, la T2): plegado al pie de la pestaña y entero
 * en el CSV — por hora de inicio (con sus franjas llenas), por zona (las plazas y, en las de fiestas, las fiestas frente
 * al tope), por producto (plazas y lo vendido), la anticipación por tipo y por día de la semana, la demanda sin hueco
 * por producto y mes y, desde la T4, lo ya vendido por semana que viene. Es la vista de tabla del mapa de calor, del gráfico
 * de la anticipación y del de la cartera.
 */
class OccupancyBreakdownWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 14;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $r = $this->occupancy();

        return [
            'heading' => __('admin.analytics.occupancy.breakdown_heading'),
            // La nota del informe iba bajo el título de la pestaña hasta la T3a (`#759`): la cabecera es ya su pregunta.
            'description' => __('admin.analytics.occupancy.note').' '.__('admin.analytics.occupancy.breakdown_note'),
            'tables' => [
                $this->heatmap($r['heatmap']),
                $this->byHour($r['by_hour']),
                $this->byZone($r['by_zone']),
                $this->byProduct($r['by_product']),
                $this->anticipationByKind($r['anticipation']),
                $this->anticipationByWeekday($r['anticipation']),
                $this->missing($r['missing']),
                $this->booked(),
            ],
        ];
    }

    /**
     * Lo ya vendido por semana que viene (T4, §4.8.quater): la vista de tabla de {@see BookedChart}, con los euros. No depende
     * del periodo del filtro. «A estas alturas» puede ser una media (la de las cuatro semanas anteriores): lleva un decimal.
     *
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function booked(): array
    {
        /** @var list<array{from: string, to: string, seats: int, cents: int, baseline: array{seats: float, cents: float}|null}> $weeks */
        $weeks = BookedReport::for()['weeks'];
        $seats = static fn (float $n): string => floor($n) === $n ? number_format($n, 0, ',', '.') : number_format($n, 1, ',', '.');

        return [
            'heading' => __('admin.analytics.booked.by_week'),
            'columns' => [
                __('admin.analytics.booked.col.week'), __('admin.analytics.booked.col.seats_now'), __('admin.analytics.booked.col.seats_before'),
                __('admin.analytics.booked.col.cents_now'), __('admin.analytics.booked.col.cents_before'),
            ],
            'rows' => array_map(static fn (array $w): array => [
                BookedChart::weekLabel($w),
                $seats((float) $w['seats']),
                $w['baseline'] === null ? '—' : $seats(round($w['baseline']['seats'], 1)),
                Money::format($w['cents']),
                $w['baseline'] === null ? '—' : Money::format((int) round($w['baseline']['cents'])),
            ], $weeks),
        ];
    }

    /**
     * El mapa de calor como TABLA (día × hora, la ocupación en cada celda): su vista de tabla y lo que lleva el CSV.
     *
     * @param  array<int, array<int, array{seats: int, capacity: int}>>  $heat
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function heatmap(array $heat): array
    {
        $hours = [];
        foreach ($heat as $byHour) {
            $hours += array_fill_keys(array_keys($byHour), true);
        }
        ksort($hours);
        $hours = array_keys($hours);

        $rows = [];
        foreach ($heat as $weekday => $byHour) {
            $name = Carbon::now()->startOfWeek()->addDays($weekday - 1)->locale(app()->getLocale())->isoFormat('dddd');
            $rows[] = [mb_convert_case($name, MB_CASE_TITLE), ...array_map(static fn (int $h): string => isset($byHour[$h]) && $byHour[$h]['capacity'] > 0
                ? self::percent((int) round($byHour[$h]['seats'] / $byHour[$h]['capacity'] * 10000))
                : '—', $hours)];
        }

        return [
            'heading' => __('admin.analytics.occupancy.by_day_hour'),
            'columns' => [__('admin.analytics.occupancy.col.weekday'), ...array_map(static fn (int $h): string => sprintf('%02d:00', $h), $hours)],
            'rows' => $rows,
            'wide' => true,
        ];
    }

    /**
     * @param  array<int, array{seats: int, capacity: int, full: int, points: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function byHour(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.occupancy.by_hour'),
            'columns' => [__('admin.analytics.occupancy.col.hour'), __('admin.analytics.occupancy.col.seats'), __('admin.analytics.occupancy.col.capacity'), __('admin.analytics.occupancy.col.occupancy'), __('admin.analytics.occupancy.col.full')],
            'rows' => array_map(fn (int $hour, array $h): array => [
                sprintf('%02d:00', $hour),
                self::n($h['seats']),
                self::n($h['capacity']),
                self::percent($h['capacity'] > 0 ? (int) round($h['seats'] / $h['capacity'] * 10000) : 0),
                $h['full'].' / '.$h['points'],
            ], array_keys($rows), $rows),
            'wide' => false,
        ];
    }

    /**
     * @param  list<array{zone: string, entry: bool, pack: bool, seats: int, capacity: int, parties: int, cap: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function byZone(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.occupancy.by_zone'),
            'columns' => [__('admin.analytics.occupancy.col.zone'), __('admin.analytics.occupancy.col.occupancy'), __('admin.analytics.occupancy.col.parties')],
            'rows' => array_map(static fn (array $z): array => [
                $z['zone'],
                $z['entry'] ? self::percent($z['capacity'] > 0 ? (int) round($z['seats'] / $z['capacity'] * 10000) : 0).' · '.self::n($z['seats']).' / '.self::n($z['capacity']) : '—',
                $z['pack'] ? ($z['cap'] > 0 ? self::percent((int) round($z['parties'] / $z['cap'] * 10000)).' · '.$z['parties'].' / '.$z['cap'] : (string) $z['parties']) : '—',
            ], $rows),
            'wide' => false,
        ];
    }

    /**
     * @param  list<array{product: string, seats: int, revenue_cents: int, lines: int}>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function byProduct(array $rows): array
    {
        return [
            'heading' => __('admin.analytics.occupancy.by_product'),
            'columns' => [__('admin.analytics.occupancy.col.product'), __('admin.analytics.occupancy.col.seats'), __('admin.analytics.occupancy.col.lines'), __('admin.analytics.occupancy.col.sold')],
            'rows' => array_map(static fn (array $p): array => [$p['product'], self::n($p['seats']), self::n($p['lines']), Money::format($p['revenue_cents'])], $rows),
            'wide' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function anticipationByKind(array $a): array
    {
        $rows = [];
        foreach (OccupancyReport::KINDS as $kind) {
            /** @var array{n: int, median: ?int, buckets: array<string, int>} $k */
            $k = $a['by_kind'][$kind];
            $rows[] = [
                __('admin.analytics.occupancy.kind.'.$kind),
                self::n($k['n']),
                $k['median'] === null ? '—' : OccupancyMetrics::days($k['median']),
                ...array_map(static fn (int $n): string => self::n($n), array_values($k['buckets'])),
            ];
        }

        return [
            'heading' => __('admin.analytics.occupancy.anticipation_by_kind'),
            'columns' => [
                __('admin.analytics.occupancy.col.kind'), __('admin.analytics.occupancy.col.lines'), __('admin.analytics.occupancy.col.median'),
                ...array_map(static fn (string $b): string => __('admin.analytics.occupancy.bucket.'.$b), array_keys(OccupancyReport::LEAD_BUCKETS)),
            ],
            'rows' => $rows,
            'wide' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function anticipationByWeekday(array $a): array
    {
        $rows = [];
        /** @var array<int, array{n: int, median: ?int}> $byWeekday */
        $byWeekday = $a['by_weekday'];
        foreach ($byWeekday as $weekday => $w) {
            $name = Carbon::now()->startOfWeek()->addDays($weekday - 1)->locale(app()->getLocale())->isoFormat('dddd');
            $rows[] = [mb_convert_case($name, MB_CASE_TITLE), self::n($w['n']), $w['median'] === null ? '—' : OccupancyMetrics::days($w['median'])];
        }

        return [
            'heading' => __('admin.analytics.occupancy.anticipation_by_weekday'),
            'columns' => [__('admin.analytics.occupancy.col.weekday'), __('admin.analytics.occupancy.col.lines'), __('admin.analytics.occupancy.col.median')],
            'rows' => $rows,
            'wide' => false,
        ];
    }

    /**
     * @param  array{count: int, since: ?string, by_product_month: list<array{product: string, month: string, n: int}>}  $m
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>, wide: bool}
     */
    private function missing(array $m): array
    {
        return [
            'heading' => __('admin.analytics.occupancy.missing_heading'),
            'columns' => [__('admin.analytics.occupancy.col.product'), __('admin.analytics.occupancy.col.month'), __('admin.analytics.occupancy.col.times')],
            'rows' => array_map(static fn (array $row): array => [
                $row['product'],
                preg_match('/^\d{4}-\d{2}$/', $row['month']) === 1 ? Carbon::parse($row['month'].'-01')->locale(app()->getLocale())->isoFormat('MMMM YYYY') : $row['month'],
                self::n($row['n']),
            ], $m['by_product_month']),
            'wide' => false,
        ];
    }

    private static function n(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
