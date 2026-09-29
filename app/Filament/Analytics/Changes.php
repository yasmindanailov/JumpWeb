<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Metrics\BookedMetrics;
use App\Filament\Analytics\Metrics\CustomersMetrics;
use App\Filament\Analytics\Metrics\MarketingMetrics;
use App\Filament\Analytics\Metrics\MoneyMetrics;
use App\Filament\Analytics\Metrics\OccupancyMetrics;
use App\Filament\Analytics\Metrics\PartiesMetrics;
use App\Filament\Analytics\Metrics\SurveysMetrics;

/**
 * **Lo que ha cambiado** (T3c·1 de `analitica-para-decidir.md` §4.5 y §4.13; `DECISIONES #791`): de TODAS las cifras del
 * cuadro —también las plegadas: su valor es sacar lo que no está arriba—, las que salen de su rango normal (`#790`) con una
 * escala que merezca decirse. Reglas fijas, sin IA.
 *
 *  - Entra una cifra con veredicto alta o baja —que ya exige 8 periodos de historia y, en tasas y medias, 20 casos— y cuya
 *    escala llega a {@see Metric::MIN_BASE}: `max(base, base comparada)`. Medido el 28-09: sin ella entraban números
 *    diminutos («siempre había sido 1» → 0). La base de un recuento es él mismo; la del dinero, sus operaciones; y una bajada
 *    tiene su escala en el periodo de antes.
 *  - Primero el DINERO, por los euros que quedan fuera de su banda; después lo demás, por lo lejos que queda, relativo a
 *    su banda. No se convierte a euros lo que no es dinero: se inventaría.
 *  - Como mucho {@see MAX}; las que no caben se cuentan («y N más»): nunca un tope callado.
 */
final class Changes
{
    public const MAX = 5;

    /**
     * Lo que ha cambiado en una ventana, de los seis catálogos y la cartera (T4) (con su historia; cacheados).
     *
     * @return array{items: list<array{metric: Metric, verdict: array{state: string, tone: string, low: ?int, high: ?int, n: int}}>, more: int, judged: int}
     */
    public static function for(Window $window, Comparison $comparison): array
    {
        return self::select(
            MoneyMetrics::for($window, $comparison)
            + OccupancyMetrics::for($window, $comparison)
            + CustomersMetrics::for($window, $comparison)
            + MarketingMetrics::for($window, $comparison)
            + PartiesMetrics::for($window, $comparison)
            + SurveysMetrics::for($window, $comparison)
            // La cartera (T4): no depende de la ventana, y su historia son las fotos de las semanas anteriores.
            + BookedMetrics::for(),
        );
    }

    /**
     * La selección y el orden, sin tocar la base de datos.
     *
     * @param  array<string, Metric>  $metrics
     * @return array{items: list<array{metric: Metric, verdict: array{state: string, tone: string, low: ?int, high: ?int, n: int}}>, more: int, judged: int}
     */
    public static function select(array $metrics): array
    {
        $out = [];
        $judged = 0;

        foreach ($metrics as $metric) {
            $verdict = $metric->verdict();
            if ($verdict === null || ! in_array($verdict['state'], [Metric::VERDICT_NORMAL, Metric::VERDICT_HIGH, Metric::VERDICT_LOW], true)) {
                continue;
            }
            $judged++;

            if ($verdict['state'] === Metric::VERDICT_NORMAL || max($metric->base ?? 0, $metric->previousBase ?? 0) < Metric::MIN_BASE) {
                continue;
            }

            $out[] = ['metric' => $metric, 'verdict' => $verdict];
        }

        usort($out, static fn (array $a, array $b): int => self::rank($b) <=> self::rank($a));

        return ['items' => array_slice($out, 0, self::MAX), 'more' => max(0, count($out) - self::MAX), 'judged' => $judged];
    }

    /** «meses», «semanas», «domingos»…: la unidad de la historia de una ventana, para «aún sin historia». */
    public static function unitOf(Window $window): string
    {
        return $window->unit === Window::UNIT_DAY
            ? __('admin.analytics.verdict.weekday.'.$window->from->isoWeekday())
            : __('admin.analytics.verdict.unit.'.$window->unit);
    }

    /**
     * Lo que pesa una cifra fuera de su banda: el dinero va delante (sus euros fuera de la banda), lo demás detrás (lo lejos
     * que queda, relativo a la banda). Un par ordenable.
     *
     * @param  array{metric: Metric, verdict: array{state: string, tone: string, low: ?int, high: ?int, n: int}}  $item
     * @return array{0: int, 1: float}
     */
    private static function rank(array $item): array
    {
        $metric = $item['metric'];
        $low = (int) $item['verdict']['low'];
        $high = (int) $item['verdict']['high'];
        $edge = $item['verdict']['state'] === Metric::VERDICT_HIGH ? $high : $low;
        $outside = abs($metric->value - $edge);

        return $metric->unit === Metric::UNIT_MONEY
            ? [1, (float) $outside]
            : [0, $outside / max(abs($edge), $high - $low, 1)];
    }
}
