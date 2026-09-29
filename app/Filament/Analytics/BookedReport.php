<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Services\OccupancyReader;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Analytics\Metrics\MeasuredSince;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * **La cartera: lo ya vendido para lo que viene** (T4 de `analitica-para-decidir.md` §4.8 y §4.8.quater): plazas y euros de las
 * visitas de los próximos 7, 30 y 90 días, frente a lo que había vendido A ESTAS ALTURAS. Mira hacia delante: no depende del
 * filtro de la página (§4.3). Capa de entrega, como los demás informes del cuadro.
 *
 * **Una foto** del instante *s*: lo vendido antes de *s* y no cancelado antes de *s* para las visitas de sus próximos N días
 * ({@see OccupancyReader::bookedLines()}). La de ahora es la cartera; las de antes, la referencia y la historia:
 *  - la referencia es la foto de hace un año con **−364 días** —el mismo día de la semana: una ventana de 7 días lleva los
 *    mismos sábados— y, si no vale, la MEDIA de las de hace 1, 2, 3 y 4 semanas (las que valgan: se dice cuántas);
 *  - «normal para ti» (`#790`) son las fotos de las últimas {@see HISTORY} semanas al mismo horizonte (las que valgan).
 *
 * ⚠️ **Una foto vale** si los pedidos se miden desde antes de *s* menos la antelación p95 del último año (mínimo
 * {@see MIN_MARGIN_DAYS}): un sistema recién puesto no ve lo que se vendió antes de existir, y esa foto contaría de menos.
 * ⚠️ El cambio frente a la referencia NO lleva prueba de «cambio claro»: las fotos semanales se solapan (30 días comparten 23
 * con los de la semana anterior) y la de la T0b supone periodos independientes.
 */
final class BookedReport
{
    /** Los horizontes, en días del parque desde hoy (hoy incluido). */
    public const HORIZONS = [7, 30, 90];

    /** Las semanas del gráfico: la de hoy y las 12 siguientes (~90 días). */
    public const WEEKS = 13;

    /** Cuántas fotos semanales hacen la historia (la de `MetricSet`). */
    public const HISTORY = 12;

    /** Las fotos de la media que sustituye al año anterior. */
    public const FALLBACK_WEEKS = 4;

    /** Un año, en semanas enteras: el mismo día de la semana (T0a). */
    public const YEAR_DAYS = 364;

    public const MIN_MARGIN_DAYS = 7;

    public const CACHE_SECONDS = 300;

    public const BASELINE_YEAR = 'year';

    public const BASELINE_WEEKS = 'weeks';

    /**
     * La cartera de ahora, memorizada {@see CACHE_SECONDS} (con el instante redondeado a esa caducidad).
     *
     * @return array<string, mixed>
     */
    public static function for(): array
    {
        $now = CarbonImmutable::now('UTC');
        $slot = intdiv($now->getTimestamp(), self::CACHE_SECONDS);

        return Cache::remember('analytics:booked:v1:'.DisplayTime::timezone().':'.$slot, self::CACHE_SECONDS, static fn (): array => (new self)->compute($now));
    }

    /**
     * @return array{
     *   margin_days: int,
     *   baseline: array{kind: ?string, n: int},
     *   horizons: array<int, array{seats: int, cents: int, lines: int, baseline: array{seats: float, cents: float}|null, history: array{seats: list<int>, cents: list<int>}}>,
     *   weeks: list<array{from: string, to: string, seats: int, cents: int, baseline: array{seats: float, cents: float}|null}>
     * }
     */
    public function compute(CarbonImmutable $now): array
    {
        $tz = DisplayTime::timezone();
        $now = $now->utc();
        $today = $now->setTimezone($tz)->startOfDay();
        $reader = app(OccupancyReader::class);

        $leads = $reader->leadDays($now->subDays(365)->format('Y-m-d H:i:s'));
        $margin = max(self::MIN_MARGIN_DAYS, self::percentile($leads, 0.95));
        $since = MeasuredSince::of(MeasuredSince::ORDERS);
        $valid = static fn (CarbonImmutable $s): bool => $since !== null && $since->lessThanOrEqualTo($s->subDays($margin));

        $weeksStart = $today->startOfWeek(CarbonImmutable::MONDAY);
        $lastDate = max($today->addDays(max(self::HORIZONS) - 1), $weeksStart->addDays(self::WEEKS * 7 - 1));

        // Las fotos: la de hace un año y las semanales (las que valen).
        $yearAgo = $now->subDays(self::YEAR_DAYS);
        $yearValid = $valid($yearAgo);
        $weekly = [];
        for ($k = 1; $k <= self::HISTORY; $k++) {
            $s = $now->subDays(7 * $k);
            if ($valid($s)) {
                $weekly[$k] = $s;
            }
        }

        // Dos lecturas: lo de estas semanas (ahora y las fotos semanales) y lo de hace un año, solo si vale.
        $recent = $reader->bookedLines($today->subDays(7 * self::HISTORY)->toDateString(), $lastDate->toDateString());
        $year = $yearValid ? $reader->bookedLines($yearAgo->setTimezone($tz)->toDateString(), $lastDate->subDays(self::YEAR_DAYS)->toDateString()) : [];

        $fallback = array_filter($weekly, static fn (int $k): bool => $k <= self::FALLBACK_WEEKS, ARRAY_FILTER_USE_KEY);
        $kind = $yearValid ? self::BASELINE_YEAR : ($fallback !== [] ? self::BASELINE_WEEKS : null);

        // La referencia de un tramo de días (desde la fecha de la foto): la foto de hace un año, o la media de las semanales.
        $baseline = function (int $fromOffset, int $toOffset) use ($kind, $year, $yearAgo, $recent, $fallback, $tz): ?array {
            if ($kind === self::BASELINE_YEAR) {
                $r = self::snapshot($year, $yearAgo, $tz, $fromOffset, $toOffset);

                return ['seats' => (float) $r['seats'], 'cents' => (float) $r['cents']];
            }
            if ($kind === self::BASELINE_WEEKS) {
                $sum = ['seats' => 0, 'cents' => 0];
                foreach ($fallback as $s) {
                    $r = self::snapshot($recent, $s, $tz, $fromOffset, $toOffset);
                    $sum['seats'] += $r['seats'];
                    $sum['cents'] += $r['cents'];
                }

                return ['seats' => $sum['seats'] / count($fallback), 'cents' => $sum['cents'] / count($fallback)];
            }

            return null;
        };

        $horizons = [];
        foreach (self::HORIZONS as $days) {
            $current = self::snapshot($recent, $now, $tz, 0, $days - 1);
            $history = ['seats' => [], 'cents' => []];
            foreach ($weekly as $s) {
                $r = self::snapshot($recent, $s, $tz, 0, $days - 1);
                $history['seats'][] = $r['seats'];
                $history['cents'][] = $r['cents'];
            }
            $horizons[$days] = $current + ['baseline' => $baseline(0, $days - 1), 'history' => $history];
        }

        // Por semana del calendario: la de hoy (desde hoy) y las siguientes; cada una frente a la misma semana a estas alturas.
        $weeks = [];
        for ($j = 0; $j < self::WEEKS; $j++) {
            // Días de calendario en la zona del parque: con el cambio de hora en medio también salen enteros (medido el 29-09).
            $from = max(0, (int) $today->diffInDays($weeksStart->addDays(7 * $j), false));
            $to = (int) $today->diffInDays($weeksStart->addDays(7 * $j + 6), false);
            $current = self::snapshot($recent, $now, $tz, $from, $to);
            $weeks[] = [
                'from' => $today->addDays($from)->toDateString(),
                'to' => $today->addDays($to)->toDateString(),
                'seats' => $current['seats'],
                'cents' => $current['cents'],
                'baseline' => $baseline($from, $to),
            ];
        }

        return [
            'margin_days' => $margin,
            'baseline' => ['kind' => $kind, 'n' => $kind === self::BASELINE_WEEKS ? count($fallback) : ($kind === null ? 0 : 1)],
            'horizons' => $horizons,
            'weeks' => $weeks,
        ];
    }

    /**
     * La foto del instante `$s`: lo vendido antes de `$s` y no cancelado antes de `$s` para las visitas de los días
     * `[$fromOffset, $toOffset]` contados desde el día de `$s` (en días del parque).
     *
     * @param  list<array{date: string, principal: bool, seats: int, cents: int, paid_at: string, cancelled_at: ?string}>  $lines
     * @return array{seats: int, cents: int, lines: int}
     */
    public static function snapshot(array $lines, CarbonImmutable $s, string $tz, int $fromOffset, int $toOffset): array
    {
        $day = $s->setTimezone($tz)->startOfDay();
        $from = $day->addDays($fromOffset)->toDateString();
        $to = $day->addDays($toOffset)->toDateString();
        $at = $s->utc()->format('Y-m-d H:i:s');

        $out = ['seats' => 0, 'cents' => 0, 'lines' => 0];
        foreach ($lines as $line) {
            if ($line['date'] < $from || $line['date'] > $to || $line['paid_at'] > $at || ($line['cancelled_at'] !== null && $line['cancelled_at'] <= $at)) {
                continue;
            }
            $out['seats'] += $line['seats'];
            $out['cents'] += $line['cents'];
            $out['lines'] += $line['principal'] ? 1 : 0;
        }

        return $out;
    }

    /**
     * El percentil `$q` de una lista (el valor en esa posición, sin interpolar); 0 sin datos.
     *
     * @param  list<int>  $values
     */
    private static function percentile(array $values, float $q): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);

        return $values[(int) ceil($q * count($values)) - 1];
    }
}
