<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;

/**
 * **Las fechas EXACTAS de una ventana**, para leerlas debajo del filtro (T0 de `analitica-para-decidir.md` §4.3,
 * `#755`): «Del 1 jun. 2026 al 10 jun. 2026, hasta ahora» y, debajo de «Comparar con», «Del 1 may. 2026 al 10 may.
 * 2026, hasta la misma hora». Desde que un periodo en curso termina AHORA y se compara con el MISMO tramo, «el periodo
 * anterior» ya no dice por sí solo qué días son, y el operador tiene que poder verlo sin preguntar.
 */
final class WindowLabel
{
    /** La ventana del periodo elegido: «…, hasta ahora» si está en curso. */
    public static function period(Window $window): string
    {
        return $window->isInProgress()
            ? __('admin.analytics.window.so_far', ['range' => self::range($window)])
            : self::range($window);
    }

    /** La ventana con la que se compara: «…, hasta la misma hora» si la del periodo está en curso. */
    public static function baseline(Window $baseline): string
    {
        return $baseline->isInProgress()
            ? __('admin.analytics.window.same_stretch', ['range' => self::range($baseline)])
            : self::range($baseline);
    }

    /**
     * Un día solo, o «Del … al …» con la fecha del idioma del panel, compacta: «Del 1 al 10 jun. 2026», «Del 1 may. al
     * 10 jun. 2026», «Del 1 dic. 2025 al 10 ene. 2026». Los formatos son dato del idioma (`admin.analytics.window.date_*`).
     */
    private static function range(Window $window): string
    {
        $from = self::civil($window->dateFrom());
        $to = self::civil($window->dateTo());
        $full = self::format($to, 'date_full');

        if ($from->isSameDay($to)) {
            return $full;
        }

        $fromFormat = match (true) {
            $from->isSameMonth($to) => 'date_same_month',
            $from->isSameYear($to) => 'date_same_year',
            default => 'date_full',
        };

        return __('admin.analytics.window.range', ['from' => self::format($from, $fromFormat), 'to' => $full]);
    }

    private static function civil(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC')->locale(app()->getLocale());
    }

    private static function format(CarbonImmutable $day, string $key): string
    {
        return $day->isoFormat(__('admin.analytics.window.'.$key));
    }
}
