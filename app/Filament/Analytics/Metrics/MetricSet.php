<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Metric;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * **Las cifras de un informe, compuestas UNA vez** (T3a de `specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`).
 *
 * Cada clase de este espacio lee SU informe —cacheado cinco minutos— y devuelve sus {@see Metric} por clave. Las pestañas,
 * «Resumen» y, desde la T3c y la T3d, «lo que ha cambiado» y el texto para IA ELIGEN claves de aquí: una cifra no se
 * define dos veces, y la de «Resumen» ES la de su pestaña (la misma clave, la misma definición).
 *
 * La composición va en `from()`, que recibe el array del informe: así un test le da la forma REAL de un informe con los
 * valores que quiera, sin montar la base de datos entera para cada cifra.
 *
 * **La historia** (T3b, `#790`): los {@see HISTORY} periodos anteriores encadenando `previous()` —la misma unidad y el mismo
 * tramo; en días y semanas, el mismo día de la semana—, cada uno por los TOTALES que su informe ya calcula para compararse, y
 * leídos con `from()`: el valor «comparado» de cada cifra ES su valor en ese periodo, con la misma fórmula que la tarjeta.
 * Un periodo cuenta si empieza cuando su fuente ya medía ({@see MeasuredSince}).
 */
abstract class MetricSet
{
    /** Cuántos periodos comparables atrás mira la historia (§4.4). */
    public const HISTORY = 12;

    /** @return array<string, Metric> */
    public static function for(Window $window, Comparison $comparison): array
    {
        $report = static::report($window, $comparison);

        return static::judged(static::from($report), $report, $window);
    }

    /**
     * Las cifras con su historia: la que no tiene comparación, sin ella.
     *
     * @param  array<string, Metric>  $metrics
     * @param  array<string, mixed>  $report
     * @return array<string, Metric>
     */
    public static function judged(array $metrics, array $report, Window $window): array
    {
        $history = static::history($report, $window);
        $unit = self::historyUnit($window);

        foreach ($metrics as $key => $metric) {
            if (array_key_exists($key, $history)) {
                $metrics[$key] = $metric->withHistory($history[$key], $unit);
            }
        }

        return $metrics;
    }

    /**
     * El valor de cada cifra en los periodos comparables anteriores que cuentan, del más reciente al más antiguo. Solo las
     * cifras con comparación; una cuyo informe no la mide en ningún periodo, con la lista vacía («aún sin historia»).
     *
     * ⚠️ Caché por las FECHAS y el corte redondeado a su caducidad: cinco minutos en día y semana (una hora de más en un día
     * sesgaría la banda) y una hora en lo demás (en un mes, una hora es ~0,1 %).
     *
     * @param  array<string, mixed>  $report
     * @return array<string, list<int>>
     */
    public static function history(array $report, Window $window): array
    {
        $ttl = in_array($window->unit, [Window::UNIT_DAY, Window::UNIT_WEEK], true) ? 300 : 3600;
        $cut = $window->isInProgress() ? (string) intdiv($window->to->getTimestamp(), $ttl) : 'whole';
        $key = 'analytics:history:v1:'.static::class.':'.$window->timezone.':'.$window->unit.':'.$window->dateFrom().':'.$window->dateTo().':'.$cut;

        return Cache::remember($key, $ttl, static function () use ($report, $window): array {
            $values = [];
            $period = $window;

            for ($k = 0; $k < self::HISTORY; $k++) {
                $period = $period->previous();
                foreach (static::from(static::withTotals($report, $period)) as $key => $metric) {
                    if ($metric->previous === null) {
                        continue;
                    }
                    $values[$key] ??= [];
                    // Una media o una tasa SIN CASOS en ese periodo no vale cero: no existe (visto el 28-09 en el navegador:
                    // «Valor medio del pedido» de las semanas sin pedidos entraba como 0 € y estrechaba la banda).
                    $undefined = ($metric->unit === Metric::UNIT_RATE || $metric->isMean) && ($metric->previousBase ?? 0) === 0;
                    if (static::measured($key, $period) && ! $undefined) {
                        $values[$key][] = $metric->previous;
                    }
                }
            }

            return $values;
        });
    }

    /**
     * El informe con los totales de OTRO periodo donde van los del comparado: con él, `from()` da el valor de cada cifra en
     * ese periodo (lo lee como «comparado»).
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    protected static function withTotals(array $report, Window $period): array
    {
        return array_replace($report, static::baselineTotals($period));
    }

    /**
     * Los totales de un periodo tal como su informe los usa para compararse (`XReport::baselineTotals()`).
     *
     * @return array<string, mixed>
     */
    abstract protected static function baselineTotals(Window $period): array;

    /**
     * De qué fuente sale cada cifra, para saber desde cuándo se mide: `*` para todas y las excepciones por clave.
     *
     * @return array<string, string>
     */
    abstract protected static function sources(): array;

    /** ¿Empieza el periodo cuando la fuente de la cifra ya medía? */
    protected static function measured(string $key, Window $period): bool
    {
        $sources = static::sources();
        $since = MeasuredSince::of($sources[$key] ?? $sources['*']);

        return $since !== null && $period->from->greaterThanOrEqualTo($since);
    }

    /** Cómo se dice la unidad de la historia en la frase: `month`… o `day:3` (los miércoles). */
    private static function historyUnit(Window $window): string
    {
        return $window->unit === Window::UNIT_DAY ? 'day:'.$window->from->isoWeekday() : $window->unit;
    }

    /**
     * El informe del que salen, cacheado.
     *
     * @return array<string, mixed>
     */
    abstract protected static function report(Window $window, Comparison $comparison): array;

    /**
     * Las cifras, desde un informe ya calculado.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, Metric>
     */
    abstract public static function from(array $report): array;

    /**
     * Por clave; una clave repetida es un error de programación, no un dato.
     *
     * @param  list<Metric>  $metrics
     * @return array<string, Metric>
     */
    protected static function keyed(array $metrics): array
    {
        $out = [];
        foreach ($metrics as $metric) {
            if (isset($out[$metric->key])) {
                throw new LogicException("La cifra «{$metric->key}» está dos veces en ".static::class);
            }
            $out[$metric->key] = $metric;
        }

        return $out;
    }

    /**
     * «¿Cómo se calcula?» de una cifra, por su clave.
     *
     * @param  array<string, int|string>  $replace
     */
    protected static function how(string $key, array $replace = []): string
    {
        return __('admin.analytics.how.'.$key, $replace);
    }

    /** Puntos básicos → «12,3 %». */
    protected static function percent(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 1, ',', '.')."\u{00A0}%";
    }
}
