<?php

namespace App\Support;

use App\Domain\Platform\Models\Setting;

/**
 * Helpers defensivos para los settings de la operativa de puerta (Fase 7.1).
 *
 * Mismo patrón que `PaymentSettings` (#113 M2) y `DisplayTime::timezone()` (#111):
 * el panel admin permite escribir cualquier valor en `settings`; un valor inválido
 * (no numérico, fuera de rango) podría reventar la puerta en horario de operación.
 * Estos helpers leen + validan tipo/rango + fallback no destructivo al default.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §3.7.1a y `docs/DECISIONES.md` #126.
 */
class PuertaSettings
{
    /**
     * Búsquedas de validación de registro permitidas por minuto y por usuario staff.
     *
     * Default 100/min: el parque en pico (hora valle de cumpleaños/grupos) tiene alto
     * throughput de validaciones — un empleado puede chequear 20–40 pulseras seguidas.
     * El rate limit existe sobre todo como freno anti-enumeración masiva si una sesión
     * staff es comprometida, no como cuota operativa estricta. Configurable desde panel.
     */
    public const VALIDATE_RATE_LIMIT_DEFAULT = 100;

    public const VALIDATE_RATE_LIMIT_MIN = 1;

    public const VALIDATE_RATE_LIMIT_MAX = 10000;

    public static function validateRateLimit(): int
    {
        $raw = Setting::value(
            'puerta.validate_rate_limit_per_minute',
            (string) self::VALIDATE_RATE_LIMIT_DEFAULT,
        );

        $n = filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => self::VALIDATE_RATE_LIMIT_MIN,
                'max_range' => self::VALIDATE_RATE_LIMIT_MAX,
            ],
        ]);

        return $n !== false ? $n : self::VALIDATE_RATE_LIMIT_DEFAULT;
    }

    /**
     * ¿La puerta comprueba el waiver? (#216) ON (default) → 3 estados (con/sin waiver). OFF → 2
     * estados (registrado / no registrado), para cuando el waiver lo gestiona el sistema externo
     * de la clienta. Default ON = comportamiento histórico; un valor ausente/inválido NO desactiva
     * la comprobación (fallback no destructivo): solo el literal '0' la apaga.
     */
    public static function waiverCheckEnabled(): bool
    {
        return (string) Setting::value('puerta.waiver_check_enabled', '1') !== '0';
    }
}
