<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Filament\Analytics\Metric;
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
 */
abstract class MetricSet
{
    /** @return array<string, Metric> */
    public static function for(Window $window, Comparison $comparison): array
    {
        return static::from(static::report($window, $comparison));
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
