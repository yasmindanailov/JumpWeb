<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\AudienceReport;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * **Quién viene, plegado** (TP·2 de `specs/analitica-para-decidir.md` §4.14, `#792`): la edad de quien reserva, sus hijos y
 * quién los declara, con quién viene, y de las fiestas la edad de quien cumple y la de sus invitados. Tablas, como el
 * desglose: lo que se reparte no es una cifra suelta, y el CSV de «Clientes» las lleva (`tablesFor()`).
 *
 * ⚠️ Cada título dice de cuántos hay dato («12 de 63 con dato»; con menos de cinco, «menos de 5», `RGPD-07`), y las celdas
 * pequeñas ya llegan fundidas del informe: aquí solo se ponen nombres.
 */
class AudienceWidget extends Widget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.analytics.tables';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $r = AudienceReport::for($this->window());
        $people = __('admin.analytics.audience.col.people');

        return [
            'heading' => __('admin.analytics.audience.heading'),
            'description' => __('admin.analytics.audience.note', ['min' => AudienceReport::MIN_CELL]),
            'tables' => [
                $this->ranged('holders', $r['holders'], $people, 'years'),
                $this->ranged('kids_count', $r['kids_count'], $people, 'kids'),
                $this->ranged('kids_ages', $r['kids_ages'], __('admin.analytics.audience.col.kids'), 'years'),
                $this->declaredBy($r['declared_by']),
                $this->company($r['company']),
                $this->ranged('honorees', $r['honorees'], __('admin.analytics.audience.col.parties'), 'years'),
                $this->ranged('guests', $r['guests'], __('admin.analytics.audience.col.guests'), 'years'),
            ],
        ];
    }

    /**
     * @param  array{of: int, with_data: int, rows: list<array{from: int, to: ?int, count: int}>}  $t
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function ranged(string $key, array $t, string $unit, string $labels): array
    {
        $rows = array_map(fn (array $r): array => [$this->rangeLabel($r['from'], $r['to'], $labels), (string) $r['count'], self::share($r['count'], $t['with_data'])], $t['rows']);

        return $this->table($key, $t, $unit, $rows);
    }

    /**
     * @param  array{of: int, with_data: int, rows: list<array{key: string, count: int}>, unknown: int}  $t
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function declaredBy(array $t): array
    {
        $rows = array_map(static fn (array $r): array => [__('admin.analytics.audience.relationship.'.$r['key']), (string) $r['count'], self::share($r['count'], $t['with_data'])], $t['rows']);

        return $this->table('declared_by', $t, __('admin.analytics.audience.col.people'), $rows);
    }

    /**
     * Por RESERVA: con menores, y el resto sin dato. Una celda de 1 a 4 se dice «menos de 5» en vez de su número.
     *
     * @param  array{of: int, with_data: int}  $t
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function company(array $t): array
    {
        $rows = [];
        if ($t['of'] >= AudienceReport::MIN_CELL) {
            $noData = $t['of'] - $t['with_data'];
            $rows = [
                [__('admin.analytics.audience.company.'.AudienceReport::WITH_MINORS), self::masked($t['with_data']), self::share($t['with_data'], $t['of'], masked: true)],
                [__('admin.analytics.audience.company.'.AudienceReport::NO_DATA), self::masked($noData), self::share($noData, $t['of'], masked: true)],
            ];
        }

        // Sin cobertura aparte: las dos filas (con menores · sin dato) ya suman todas las reservas.
        return [
            'heading' => __('admin.analytics.audience.tables.company'),
            'columns' => [__('admin.analytics.audience.col.group'), __('admin.analytics.audience.col.reservations'), '%'],
            'rows' => $rows,
        ];
    }

    /**
     * Una tabla con su título FIJO (el censo lo busca en el CSV) y, de primera fila, cuántos tienen dato: «Con dato · 12 de
     * 63». Sin reparto (menos de cinco con dato), una fila más que lo dice.
     *
     * @param  array{of: int, with_data: int}  $t
     * @param  list<list<string>>  $rows
     * @return array{heading: string, columns: list<string>, rows: list<list<string>>}
     */
    private function table(string $key, array $t, string $unit, array $rows): array
    {
        $coverage = match (true) {
            $t['with_data'] === 0 => __('admin.analytics.audience.coverage_none', ['of' => $t['of']]),
            $t['with_data'] < AudienceReport::MIN_CELL => __('admin.analytics.audience.coverage_few', ['min' => AudienceReport::MIN_CELL, 'of' => $t['of']]),
            default => __('admin.analytics.audience.coverage', ['with' => $t['with_data'], 'of' => $t['of']]),
        };

        if ($rows === [] && $t['with_data'] > 0) {
            $rows = [[__('admin.analytics.audience.too_few', ['min' => AudienceReport::MIN_CELL]), '—', '—']];
        }

        return [
            'heading' => __('admin.analytics.audience.tables.'.$key),
            'columns' => [__('admin.analytics.audience.col.group'), $unit, '%'],
            'rows' => [[__('admin.analytics.audience.with_data'), $coverage, ''], ...$rows],
        ];
    }

    /** «18–24 años», «55 años o más», «7 años»; o «1 hijo», «2 hijos», «3 o más». */
    private function rangeLabel(int $from, ?int $to, string $labels): string
    {
        if ($labels === 'kids') {
            return $to === null ? __('admin.analytics.audience.kids_open', ['from' => $from]) : trans_choice('admin.analytics.audience.kids', $from, ['count' => $from]);
        }

        return match (true) {
            $to === null => __('admin.analytics.audience.years_open', ['from' => $from]),
            $to === $from => trans_choice('admin.analytics.audience.years_one', $from, ['count' => $from]),
            default => __('admin.analytics.audience.years_range', ['from' => $from, 'to' => $to]),
        };
    }

    /** Un recuento de 1 a 4, dicho «menos de 5». */
    private static function masked(int $n): string
    {
        return $n > 0 && $n < AudienceReport::MIN_CELL ? __('admin.analytics.surveys.fewer_than_min', ['min' => AudienceReport::MIN_CELL]) : (string) $n;
    }

    /** El porcentaje de la fila sobre los que tienen dato; enmascarado cuando lo está su recuento. */
    private static function share(int $n, int $of, bool $masked = false): string
    {
        if ($of === 0 || ($masked && $n > 0 && $n < AudienceReport::MIN_CELL)) {
            return '—';
        }

        return number_format($n / $of * 100, 0, ',', '.').' %';
    }
}
