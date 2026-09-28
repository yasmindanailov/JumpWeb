<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\PartiesReport;
use App\Filament\Analytics\Polarity;

/**
 * **Las cifras de la fiesta** (`PartiesReport`; T2 de `analitica-fiesta.md` §4.3): las fiestas del periodo, lo vendido
 * después de reservar y lo cobrado en el parque, las reservas con extras y su media, los formularios completados y cuántos
 * dentro del plazo, las respuestas «sí» y los justificantes firmados. El dinero se sostiene en sus FIESTAS (y la media de
 * extras, en las que los compraron); el plazo es una TASA de los completados, sin periodo comparado.
 */
final class PartiesMetrics extends MetricSet
{
    protected static function report(Window $window, Comparison $comparison): array
    {
        return PartiesReport::for($window, $comparison);
    }

    protected static function baselineTotals(Window $period): array
    {
        return PartiesReport::baselineTotals($period);
    }

    protected static function sources(): array
    {
        return ['*' => MeasuredSince::ORDERS];
    }

    public static function from(array $report): array
    {
        /** @var array<string, mixed> $m */
        $m = $report['money'];
        /** @var array<string, int> $f */
        $f = $report['forms'];
        /** @var array<string, int> $i */
        $i = $report['invitations'];
        /** @var array<string, int> $a */
        $a = $report['authorizations'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        $parties = (int) $report['parties'];

        return self::keyed([
            Metric::count('parties.parties', __('admin.analytics.parties.parties'), $parties, $p['parties'], Polarity::UpIsGood, self::how('parties.parties')),
            Metric::money('parties.sold_after', __('admin.analytics.parties.sold_after'), (int) $m['sold_after_booking'], $p['sold_after_booking'], $parties, $p['parties'], Polarity::UpIsGood, self::how('parties.sold_after'), squares: (int) $m['sold_after_sq'], previousSquares: $p['sold_after_sq']),
            Metric::money('parties.collected_in_park', __('admin.analytics.parties.collected_in_park'), (int) $m['collected_in_park'], $p['collected_in_park'], $parties, $p['parties'], Polarity::UpIsGood, self::how('parties.collected_in_park'), squares: (int) $m['collected_in_park_sq'], previousSquares: $p['collected_in_park_sq']),
            Metric::count('parties.with_extras', __('admin.analytics.parties.with_extras'), (int) $m['with_extras'], $p['with_extras'], Polarity::UpIsGood, self::how('parties.with_extras'), display: self::countOf((int) $m['with_extras'], $parties)),
            Metric::money('parties.avg_extras', __('admin.analytics.parties.avg_extras'), (int) $m['avg_extras'], $p['avg_extras'], (int) $m['with_extras'], $p['with_extras'], Polarity::UpIsGood, self::how('parties.avg_extras'), squares: (int) $m['extras_sq'], previousSquares: $p['extras_sq'], mean: true),
            Metric::count('parties.completed', __('admin.analytics.parties.completed'), $f['completed'], $p['completed'], Polarity::UpIsGood, self::how('parties.completed'), display: self::countOf($f['completed'], $parties)),
            Metric::rate('parties.on_time', __('admin.analytics.parties.on_time'), $f['on_time'], $f['completed'], null, null, Polarity::UpIsGood, self::how('parties.on_time', ['hours' => $f['cutoff_hours']])),
            Metric::count('parties.replies_yes', __('admin.analytics.parties.replies_yes'), $i['replies_yes'], $p['replies_yes'], Polarity::UpIsGood, self::how('parties.replies_yes')),
            Metric::count('parties.signatures', __('admin.analytics.parties.signatures'), $a['signatures'], $p['signatures'], Polarity::UpIsGood, self::how('parties.signatures')),
        ]);
    }

    /** «12 · 80,0 %»: el recuento y lo que supone de las fiestas del periodo. */
    private static function countOf(int $count, int $parties): string
    {
        return $count.' · '.self::percent($parties > 0 ? (int) round($count / $parties * 10000) : 0);
    }
}
