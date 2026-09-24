<?php

namespace App\Filament\Analytics;

use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;

/**
 * **El rótulo de un cubo de la serie** —un día, una semana o un mes— para los gráficos (corto) y las tablas
 * (largo), en el idioma del panel. Un solo sitio: los tres gráficos de serie y las dos tablas por día lo usan.
 */
final class BucketLabel
{
    public static function short(string $key, string $granularity): string
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $key, 'UTC');

        return match ($granularity) {
            Window::GRANULARITY_MONTH => ucfirst($day->locale(app()->getLocale())->isoFormat('MMM YY')),
            Window::GRANULARITY_WEEK => __('admin.analytics.money.week_of', ['day' => $day->format('d/m')]),
            default => $day->format('d/m'),
        };
    }

    public static function long(string $key, string $granularity): string
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $key, 'UTC');

        return match ($granularity) {
            Window::GRANULARITY_MONTH => ucfirst($day->locale(app()->getLocale())->isoFormat('MMMM YYYY')),
            Window::GRANULARITY_WEEK => __('admin.analytics.money.week_of', ['day' => $day->format('d/m/Y')]),
            default => $day->format('d/m/Y'),
        };
    }

    /** El rótulo de la columna de la tabla por cubo («Día», «Semana», «Mes») y el de su cabecera («Por día»…). */
    public static function column(string $granularity): string
    {
        return __('admin.analytics.money.col.'.$granularity);
    }

    public static function heading(string $granularity): string
    {
        return __('admin.analytics.money.by_'.$granularity);
    }
}
