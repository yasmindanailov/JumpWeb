<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\EmailsReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * **CUÁNDO abren y pulsan, plegado** (`specs/correos-salientes.md` §4.14, `#796`, la C4): dos mapas día × hora del parque —los
 * CLICS de una persona, el dato fiable, y las APERTURAS que cuentan, aproximadas—, solo de los correos que al cliente le
 * LLEGAN (`EmailTiming::RECEIVED`). Es lo que dice a qué hora mandar los de marketing.
 *
 * En conjunto y sin persona (`RGPD-07`): una celda de 1 a 4 se escribe «—» (con su tooltip «menos de 5»), y el color va
 * sobre la celda más llena del mapa, en cinco pasos de UN tono (el de la ocupación, `OccupancyHeatmapWidget::HUE`). Con sus
 * SUMAS —«Todo el día» por día y «Toda la semana» por hora—, que son las que contestan con poco volumen. El CSV de
 * «Marketing» lleva los dos como tablas día × hora (`tablesFor()`), con las sumas y los mismos mínimos.
 */
class EmailsHeatmapWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 19;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.email-heatmaps';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $r = EmailsReport::for($this->window());
        $hours = self::hours([$r['clicks_heat'], $r['opens_heat']]);

        $maps = [];
        $tables = [];
        foreach (['clicks' => $r['clicks_heat'], 'opens' => $r['opens_heat']] as $key => $heat) {
            $map = $this->map($key, $heat, $hours);
            $maps[] = $map;
            $tables[] = [
                'heading' => __('admin.analytics.emails.when.table_'.$key),
                'wide' => true,
                'columns' => [
                    __('admin.analytics.emails.when.col_day'),
                    ...array_map(static fn (int $h): string => sprintf('%02d:00', $h), $hours),
                    __('admin.analytics.emails.when.all_day'),
                ],
                'rows' => array_map(static fn (array $row): array => [$row['day'], ...array_map(static fn (array $c): string => $c['label'], $row['cells'])], $map['rows']),
            ];
        }

        return [
            'heading' => __('admin.analytics.emails.when.heading'),
            'description' => __('admin.analytics.emails.when.note', ['min' => EmailsReport::MIN_CELL]),
            'hue' => OccupancyHeatmapWidget::HUE,
            'maps' => $maps,
            'tables' => $tables,
        ];
    }

    /**
     * @param  array<int, array<int, int>>  $heat
     * @param  list<int>  $hours
     * @return array{key: string, heading: string, note: string, hours: list<string>, rows: list<array{day: string, cells: list<array<string, mixed>>}>, legend: list<array{mix: int, label: string}>}
     */
    private function map(string $key, array $heat, array $hours): array
    {
        $max = 0;
        $byDay = array_fill(1, 7, 0);
        $byHour = array_fill_keys($hours, 0);
        foreach ($heat as $weekday => $counts) {
            foreach ($counts as $hour => $n) {
                $max = max($max, $n);
                $byDay[$weekday] += $n;
                $byHour[$hour] += $n;
            }
        }
        $total = array_sum($byDay);
        $allDay = (string) __('admin.analytics.emails.when.all_day');
        $allWeek = (string) __('admin.analytics.emails.when.all_week');

        // ⚠️ Las SUMAS contestan la pregunta del owner («¿a qué hora?», «¿qué día?») antes que cada casilla: con poco volumen
        // casi todas se quedan por debajo de cinco y solo las sumas llegan. Cada una se colorea en SU escala (la fila de la
        // semana contra la hora más llena; la columna del día contra el día más lleno) y con los mismos mínimos.
        $rows = [];
        if ($total >= EmailsReport::MIN_CELL) {
            foreach (range(1, 7) as $weekday) {
                $name = Carbon::now()->startOfWeek()->addDays($weekday - 1)->locale(app()->getLocale())->isoFormat('dddd');
                $cells = [];
                foreach ($hours as $hour) {
                    $cells[] = $this->cell($heat[$weekday][$hour] ?? 0, $max, $name, sprintf('%02d:00', $hour));
                }
                $cells[] = $this->cell($byDay[$weekday], max($byDay), $name, $allDay);
                $rows[] = ['day' => mb_convert_case($name, MB_CASE_TITLE), 'cells' => $cells];
            }

            $week = array_map(fn (int $hour): array => $this->cell($byHour[$hour], max($byHour), $allWeek, sprintf('%02d:00', $hour)), $hours);
            $week[] = $this->cell($total, $total, $allWeek, $allDay);
            $rows[] = ['day' => $allWeek, 'cells' => $week];
        }

        $steps = OccupancyHeatmapWidget::STEPS;

        return [
            'key' => $key,
            'heading' => __('admin.analytics.emails.when.'.$key),
            'note' => __('admin.analytics.emails.when.'.$key.'_note'),
            'hours' => [...array_map(static fn (int $h): string => sprintf('%02d', $h), $hours), $allDay],
            'rows' => $rows,
            'legend' => array_map(static fn (int $i): array => ['mix' => $steps[$i], 'label' => match ($i) {
                0 => (string) __('admin.analytics.emails.when.less'),
                count($steps) - 1 => (string) __('admin.analytics.emails.when.more'),
                default => '',
            }], array_keys($steps)),
        ];
    }

    /** @return array<string, mixed> */
    private function cell(int $n, int $max, string $day, string $hour): array
    {
        if ($n === 0) {
            return ['empty' => true, 'label' => '', 'title' => __('admin.analytics.emails.when.cell_none', ['day' => $day, 'hour' => $hour])];
        }
        if ($n < EmailsReport::MIN_CELL) {
            return ['empty' => true, 'label' => '—', 'title' => __('admin.analytics.emails.when.cell_few', ['day' => $day, 'hour' => $hour, 'min' => EmailsReport::MIN_CELL])];
        }

        $steps = OccupancyHeatmapWidget::STEPS;
        $step = min(count($steps) - 1, intdiv($n * count($steps) - 1, $max));

        return [
            'empty' => false,
            'label' => (string) $n,
            'mix' => $steps[$step],
            'strong' => $step === count($steps) - 1,
            'title' => __('admin.analytics.emails.when.cell', ['day' => $day, 'hour' => $hour, 'count' => $n]),
        ];
    }

    /**
     * Las horas que se enseñan: de la primera a la última con algo en cualquiera de los dos mapas, seguidas, para que los dos
     * se lean con las mismas columnas y un hueco se vea como hueco.
     *
     * @param  list<array<int, array<int, int>>>  $heats
     * @return list<int>
     */
    private static function hours(array $heats): array
    {
        $seen = [];
        foreach ($heats as $heat) {
            foreach ($heat as $byHour) {
                foreach (array_keys($byHour) as $hour) {
                    $seen[] = $hour;
                }
            }
        }

        return $seen === [] ? [] : range(min($seen), max($seen));
    }
}
