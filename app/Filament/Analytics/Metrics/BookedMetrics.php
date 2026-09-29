<?php

namespace App\Filament\Analytics\Metrics;

use App\Filament\Analytics\BookedReport;
use App\Filament\Analytics\Metric;
use App\Filament\Analytics\Polarity;

/**
 * **Las cifras de la cartera** (T4 de `analitica-para-decidir.md` §4.8.quater): lo ya vendido para los próximos 7, 30 y 90 días,
 * en euros y en plazas. No es un {@see MetricSet}: no depende del periodo del filtro, y su historia no son los periodos
 * anteriores sino las FOTOS de las semanas anteriores al mismo horizonte ({@see BookedReport}). La comparación con lo vendido
 * «a estas alturas» va en la línea de detalle y sin color: no lleva prueba de cambio claro (las fotos se solapan).
 */
final class BookedMetrics
{
    /** @var list<string> */
    public const KEYS = ['booked.cents_7', 'booked.cents_30', 'booked.cents_90', 'booked.seats_7', 'booked.seats_30', 'booked.seats_90'];

    /** @return array<string, Metric> */
    public static function for(): array
    {
        return self::from(BookedReport::for());
    }

    /**
     * Las cifras, desde un informe ya calculado (una prueba le da la forma real con los números a mano).
     *
     * @param  array<string, mixed>  $report
     * @return array<string, Metric>
     */
    public static function from(array $report): array
    {
        /** @var array{kind: ?string, n: int} $baseline */
        $baseline = $report['baseline'];
        $out = [];

        foreach (BookedReport::HORIZONS as $days) {
            /** @var array{seats: int, cents: int, lines: int, baseline: array{seats: float, cents: float}|null, history: array{seats: list<int>, cents: list<int>}} $h */
            $h = $report['horizons'][$days];
            $money = static fn (?string $detail = null): Metric => Metric::money(
                "booked.cents_{$days}", __('admin.analytics.booked.cents', ['days' => $days]), $h['cents'], null, $h['lines'], null,
                Polarity::UpIsGood, __("admin.analytics.how.booked.cents_{$days}"), detail: $detail,
            );
            $seats = static fn (?string $detail = null): Metric => Metric::count(
                "booked.seats_{$days}", __('admin.analytics.booked.seats', ['days' => $days]), $h['seats'], null,
                Polarity::UpIsGood, __("admin.analytics.how.booked.seats_{$days}"), detail: $detail,
            );
            // Cada una dice en su detalle la otra medida (las plazas del dinero, el dinero de las plazas), con su formato.
            $seatsText = trans_choice('admin.analytics.booked.seats_short', $h['seats'], ['n' => $seats()->format($h['seats'])]);
            $centsText = $money()->format($h['cents']);

            $out["booked.cents_{$days}"] = $money($seatsText.' · '.self::versus($money(), $h['baseline']['cents'] ?? null, $baseline))
                ->withHistory($h['history']['cents'], 'week');
            $out["booked.seats_{$days}"] = $seats($centsText.' · '.self::versus($seats(), $h['baseline']['seats'] ?? null, $baseline))
                ->withHistory($h['history']['seats'], 'week');
        }

        // En el orden de {@see KEYS}: los euros y después las plazas.
        return array_replace(array_flip(self::KEYS), $out);
    }

    /**
     * Lo vendido a estas alturas —hace un año o de media en las semanas anteriores—, con el cambio relativo si no era cero; o
     * que aún no hay con qué comparar.
     *
     * @param  array{kind: ?string, n: int}  $baseline
     */
    private static function versus(Metric $metric, ?float $reference, array $baseline): string
    {
        if ($baseline['kind'] === null || $reference === null) {
            return __('admin.analytics.booked.no_baseline');
        }

        $value = (int) round($reference);
        $delta = $value > 0 ? ' ('.self::signed((int) round(($metric->value - $value) / $value * 100)).')' : '';

        return $baseline['kind'] === BookedReport::BASELINE_YEAR
            ? __('admin.analytics.booked.vs_year', ['value' => $metric->format($value), 'delta' => $delta])
            : trans_choice('admin.analytics.booked.vs_weeks', $baseline['n'], ['n' => $baseline['n'], 'value' => $metric->format($value), 'delta' => $delta]);
    }

    /** «+12 %», «−8 %», «0 %». */
    private static function signed(int $percent): string
    {
        return ($percent > 0 ? '+' : ($percent < 0 ? '−' : '')).abs($percent)."\u{00A0}%";
    }
}
