<?php

namespace App\Domain\Platform\Services\Surveys;

use App\Domain\Platform\Models\Setting;

/**
 * **Los ajustes de las encuestas** (`docs/specs/encuestas.md` §4.3, T3): hoy uno, el plazo entre dos correos de
 * encuesta a la misma persona. Vive en «Ajustes → Puerta» porque la encuesta nace de la visita acreditada allí.
 */
final class SurveySettings
{
    public const KEY_COOLDOWN_DAYS = 'surveys.cooldown_days';

    public const COOLDOWN_DAYS_DEFAULT = 30;

    public const COOLDOWN_DAYS_MIN = 0;

    public const COOLDOWN_DAYS_MAX = 365;

    /** Días que tienen que pasar desde el ÚLTIMO correo de encuesta a un cliente para mandarle otro (0 = ninguno). */
    public static function cooldownDays(): int
    {
        $n = filter_var(Setting::value(self::KEY_COOLDOWN_DAYS, (string) self::COOLDOWN_DAYS_DEFAULT), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::COOLDOWN_DAYS_MIN, 'max_range' => self::COOLDOWN_DAYS_MAX],
        ]);

        return $n === false ? self::COOLDOWN_DAYS_DEFAULT : (int) $n;
    }
}
