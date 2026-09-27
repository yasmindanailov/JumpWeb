<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;
use App\Filament\Widgets\Analytics\Concerns\AnalyticsWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * **La ocupación, en seis cifras** (`specs/analitica-para-decidir.md` §4.8.ter, la T2; `#758`): la de las ENTRADAS (la
 * principal), las FIESTAS por franja —separadas, nunca sumadas—, las franjas llenas y con cuánta antelación se llenaron,
 * el ingreso por plaza-hora ofrecida, la anticipación y la demanda sin hueco. Cada una con su anatomía (T0b).
 */
class OccupancyOverviewWidget extends StatsOverviewWidget
{
    use AnalyticsWidget;
    use InteractsWithPageFilters;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    protected function getHeading(): ?string
    {
        return __('admin.analytics.occupancy.heading');
    }

    protected function getDescription(): ?string
    {
        return __('admin.analytics.occupancy.note');
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $r = $this->occupancy();
        /** @var array<string, mixed> $e */
        $e = $r['entries'];
        /** @var array<string, mixed> $f */
        $f = $r['parties'];
        /** @var array<string, int> $p */
        $p = $r['previous'];
        /** @var array<string, mixed> $a */
        $a = $r['anticipation'];
        /** @var array<string, mixed> $m */
        $m = $r['missing'];

        return [
            $this->metric(Metric::rate(
                'occupancy.entries', __('admin.analytics.occupancy.entries'), (int) $e['seats'], (int) $e['capacity'], $p['seats'], $p['capacity'], Polarity::UpIsGood, self::how('occupancy.entries'),
                detail: __('admin.analytics.occupancy.entries_hint', ['seats' => number_format((int) $e['seats'], 0, ',', '.'), 'capacity' => number_format((int) $e['capacity'], 0, ',', '.')]),
            )),
            $this->metric($this->partiesMetric($f, $p)),
            $this->metric(Metric::count(
                'occupancy.full', __('admin.analytics.occupancy.full'), (int) $e['full'], $p['full'], Polarity::Neutral, self::how('occupancy.full'),
                detail: $e['full_lead_days'] === null
                    ? __('admin.analytics.occupancy.full_hint', ['points' => (int) $e['points']])
                    : __('admin.analytics.occupancy.full_hint_lead', ['points' => (int) $e['points'], 'days' => self::days((int) $e['full_lead_days'])]),
            )),
            $this->metric(Metric::money(
                'occupancy.revenue_per_seat_hour', __('admin.analytics.occupancy.revenue_per_seat_hour'),
                self::perSeatHour((int) $e['revenue_cents'], (int) $e['seat_minutes']), $p['seat_minutes'] > 0 ? self::perSeatHour($p['revenue_cents'], $p['seat_minutes']) : null,
                (int) $e['lines'], $p['lines'], Polarity::UpIsGood, self::how('occupancy.revenue_per_seat_hour'),
                detail: __('admin.analytics.occupancy.revenue_hint', ['hours' => number_format(intdiv((int) $e['seat_minutes'], 60), 0, ',', '.')]),
            )),
            $this->metric(Metric::text(
                'occupancy.anticipation', __('admin.analytics.occupancy.anticipation'),
                $a['median'] === null ? __('admin.analytics.parties.none') : self::days((int) $a['median']),
                self::how('occupancy.anticipation'),
                detail: $this->anticipationByKind($a),
            )),
            $this->metric(Metric::count(
                'occupancy.missing', __('admin.analytics.occupancy.missing'), (int) $m['count'], $p['missing'], Polarity::DownIsGood, self::how('occupancy.missing'),
                detail: $m['since'] === null
                    ? __('admin.analytics.occupancy.missing_not_yet')
                    : __('admin.analytics.occupancy.missing_since', ['date' => Carbon::parse((string) $m['since'])->format('d/m/Y')]),
            )),
        ];
    }

    /**
     * Las fiestas por franja: con tope, una tasa (fiestas a la vez frente al tope); sin tope (0), no hay denominador y se
     * dice; sin zona de fiestas, tampoco se inventa.
     *
     * @param  array<string, mixed>  $f
     * @param  array<string, int>  $p
     */
    private function partiesMetric(array $f, array $p): Metric
    {
        if ($f['capped']) {
            return Metric::rate(
                'occupancy.parties', __('admin.analytics.occupancy.parties'), (int) $f['present'], (int) $f['cap'], $p['present'], $p['cap'], Polarity::UpIsGood, self::how('occupancy.parties'),
                detail: __('admin.analytics.occupancy.parties_hint', ['present' => (int) $f['present'], 'cap' => (int) $f['cap']]),
            );
        }

        return Metric::text(
            'occupancy.parties', __('admin.analytics.occupancy.parties'),
            (int) $f['points'] === 0 ? __('admin.analytics.parties.none') : (string) $f['present'],
            self::how('occupancy.parties'),
            detail: (int) $f['points'] === 0 ? __('admin.analytics.occupancy.parties_no_zone') : __('admin.analytics.occupancy.parties_no_cap'),
        );
    }

    /** @param  array<string, mixed>  $a */
    private function anticipationByKind(array $a): string
    {
        $parts = [];
        foreach (['entry', 'party', 'group'] as $kind) {
            /** @var array{n: int, median: ?int} $k */
            $k = $a['by_kind'][$kind];
            if ($k['n'] > 0 && $k['median'] !== null) {
                $parts[] = __('admin.analytics.occupancy.kind.'.$kind).' '.self::days($k['median']);
            }
        }

        return $parts === [] ? __('admin.analytics.occupancy.anticipation_none') : implode(' · ', $parts);
    }

    /** Céntimos por plaza-hora: lo vendido entre las plazas × horas ofrecidas. */
    private static function perSeatHour(int $cents, int $seatMinutes): int
    {
        return $seatMinutes > 0 ? (int) round($cents * 60 / $seatMinutes) : 0;
    }

    /** «el mismo día» · «1 día» · «12 días». */
    public static function days(int $days): string
    {
        return $days === 0 ? __('admin.analytics.occupancy.same_day') : trans_choice('admin.analytics.occupancy.days', $days, ['n' => $days]);
    }
}
