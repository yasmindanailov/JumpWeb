<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\CustomersReport;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;

/**
 * **Las cifras de los clientes** (`CustomersReport`; T2b de `analitica.md` §4.5 y T0c de `analitica-para-decidir.md`
 * §4.8.bis, `#756`): los registros, la mecánica de la puerta —buscar, teclear, escanear, abrir fichas: NEUTRA— y las
 * visitas acreditadas, y los que VUELVEN al parque y cada cuánto.
 */
final class CustomersMetrics extends MetricSet
{
    protected static function report(Window $window, Comparison $comparison): array
    {
        return CustomersReport::for($window, $comparison);
    }

    protected static function baselineTotals(Window $period): array
    {
        return CustomersReport::baselineTotals($period);
    }

    /** La puerta y los que vuelven, desde la primera visita acreditada; las cuentas nuevas, desde la primera cuenta. */
    protected static function sources(): array
    {
        return ['*' => MeasuredSince::GATE, 'customers.registrations' => MeasuredSince::ACCOUNTS];
    }

    public static function from(array $report): array
    {
        /** @var array{total: int, verified: int, buyers: int} $r */
        $r = $report['registrations'];
        /** @var array<string, int> $g */
        $g = $report['gate'];
        /** @var array{visitors: int, returning: int, first_time: int, repeat: int, gap_median_days: ?int, gap_buckets: array<string, int>, by_source: array<string, int>} $ret */
        $ret = $report['returns'];
        /** @var array<string, int> $p */
        $p = $report['previous'];
        $b = $ret['gap_buckets'];

        return self::keyed([
            // Cómo se registran está en la tabla «Cómo se registran» del desglose, con sus tres filas.
            Metric::count('customers.registrations', __('admin.analytics.customers.registrations'), $r['total'], $p['registrations'], Polarity::UpIsGood, self::how('customers.registrations')),
            Metric::count('customers.verified', __('admin.analytics.customers.verified'), $r['verified'], null, Polarity::UpIsGood, self::how('customers.verified'), detail: self::share($r['verified'], $r['total'])),
            Metric::count('customers.buyers', __('admin.analytics.customers.buyers'), $r['buyers'], null, Polarity::UpIsGood, self::how('customers.buyers'), detail: self::share($r['buyers'], $r['total'])),
            // La mecánica de la puerta (buscar, teclear, escanear, abrir fichas) no es buena ni mala: NEUTRA. Las visitas sí.
            Metric::count('customers.lookups', __('admin.analytics.customers.lookups'), $g['lookups'], $p['lookups'], Polarity::Neutral, self::how('customers.lookups')),
            Metric::count('customers.typed', __('admin.analytics.customers.typed'), $g['typed'], null, Polarity::Neutral, self::how('customers.typed')),
            Metric::count('customers.scanned', __('admin.analytics.customers.scanned'), $g['scanned'], null, Polarity::Neutral, self::how('customers.scanned')),
            Metric::count('customers.found', __('admin.analytics.customers.found'), $g['found'], null, Polarity::Neutral, self::how('customers.found'), detail: __('admin.analytics.customers.not_found_line', ['count' => $g['not_found']])),
            Metric::count('customers.customers', __('admin.analytics.customers.customers'), $g['customers'], null, Polarity::Neutral, self::how('customers.customers')),
            Metric::count('customers.profile_views', __('admin.analytics.customers.profile_views'), $g['profile_views'], null, Polarity::Neutral, self::how('customers.profile_views')),
            Metric::count('customers.visits', __('admin.analytics.customers.visits'), $g['visits'], $p['visits'], Polarity::UpIsGood, self::how('customers.visits'), detail: self::bySource($ret['by_source'])),
            Metric::count('customers.visitors', __('admin.analytics.customers.visitors'), $g['visitors'], null, Polarity::UpIsGood, self::how('customers.visitors')),
            // T0c (`#756`): los que vuelven al parque y cada cuánto.
            Metric::count(
                'customers.returning_visitors', __('admin.analytics.customers.returning_visitors'), $ret['returning'], $p['returning'], Polarity::UpIsGood, self::how('customers.returning_visitors'),
                detail: $ret['visitors'] > 0 ? __('admin.analytics.customers.returning_share', ['percent' => (int) round($ret['returning'] / $ret['visitors'] * 100), 'total' => $ret['visitors']]) : null,
            ),
            Metric::count('customers.first_visit', __('admin.analytics.customers.first_visit'), $ret['first_time'], $p['first_time'], Polarity::UpIsGood, self::how('customers.first_visit')),
            Metric::count('customers.repeat', __('admin.analytics.customers.repeat'), $ret['repeat'], $p['repeat'], Polarity::UpIsGood, self::how('customers.repeat')),
            Metric::text(
                'customers.return_gap',
                __('admin.analytics.customers.return_gap'),
                $ret['gap_median_days'] === null ? __('admin.analytics.customers.return_gap_none') : trans_choice('admin.analytics.customers.return_gap_value', $ret['gap_median_days'], ['days' => $ret['gap_median_days']]),
                self::how('customers.return_gap'),
                detail: $ret['gap_median_days'] === null ? null : __('admin.analytics.customers.return_gap_buckets', [
                    'week' => $b[CustomersReport::GAP_WEEK], 'month' => $b[CustomersReport::GAP_MONTH],
                    'quarter' => $b[CustomersReport::GAP_QUARTER], 'longer' => $b[CustomersReport::GAP_LONGER],
                ]),
            ),
        ]);
    }

    /** «7 de 12» como porcentaje del total del periodo; sin total no hay porcentaje. */
    private static function share(int $part, int $total): string
    {
        if ($total === 0) {
            return __('admin.analytics.customers.share_none');
        }

        return __('admin.analytics.customers.share', ['percent' => (int) round($part / $total * 100), 'total' => $total]);
    }

    /**
     * Cómo se acreditaron las visitas (`#756`): por carné o por búsqueda, y las de antes sin origen. Una búsqueda puede ser
     * una consulta sin visita; por eso se enseñan separadas.
     *
     * @param  array<string, int>  $bySource
     */
    private static function bySource(array $bySource): ?string
    {
        $total = array_sum($bySource);

        if ($total === 0) {
            return null;
        }

        $card = $bySource['card'] ?? 0;
        $lookup = $bySource['lookup'] ?? 0;
        $line = __('admin.analytics.customers.visits_by_source', ['card' => $card, 'lookup' => $lookup]);
        $other = $total - $card - $lookup;

        return $other > 0 ? __('admin.analytics.customers.visits_by_source_other', ['line' => $line, 'other' => $other]) : $line;
    }
}
