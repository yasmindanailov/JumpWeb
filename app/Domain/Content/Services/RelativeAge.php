<?php

namespace App\Domain\Content\Services;

use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;

/**
 * **«hace 2 meses» a partir de una fecha, en el idioma de la página** (`#490`, `#591`).
 *
 * ⚠️ Vivía dentro de `CmsSocialProof` y sale aquí porque desde `#591` tiene un segundo consumidor: las
 * reseñas de Google se piden en UN idioma, y las otras versiones del sitio no pueden publicar la fecha
 * relativa que Google escribió en ése. Con dos copias, la misma sección acabaría contando las fechas
 * de dos maneras.
 */
final class RelativeAge
{
    /**
     * ⚠️ Una fecha FUTURA no se cuenta: `diffForHumans` diría «en 2 meses», que sobre una opinión no
     * significa nada. Se calla, que es lo que hace la ausencia de fecha.
     * ⚠️ Trabaja sobre una COPIA: el `published_at` de un modelo es un Carbon mutable, y contarlo no
     * puede cambiarle la hora.
     */
    public static function of(?CarbonInterface $fecha): ?string
    {
        if ($fecha === null) {
            return null;
        }

        if ($fecha->copy()->startOfDay()->greaterThan(DisplayTime::now()->startOfDay())) {
            return null;
        }

        return $fecha->copy()->locale(app()->getLocale())->diffForHumans(['parts' => 1]);
    }
}
