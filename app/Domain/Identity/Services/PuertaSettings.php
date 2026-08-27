<?php

namespace App\Domain\Identity\Services;

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

    // ─── Fase 6 · subsistema A: la FICHA de puerta (`specs/identidad-qr-puerta.md` §9.2 A·5/A·6/A·9) ──

    /**
     * Búsquedas TECLEADAS (email/teléfono) por HORA y por empleado que abren la ficha completa. Es el
     * segundo limitador de §4.6·1: escanear es alto volumen y legítimo (una cola entera); teclear un
     * correo debería ser raro («me he dejado el móvil»). Un empleado que teclea cincuenta correos en
     * una hora no está atendiendo. Su rechazo se audita como acción CRÍTICA.
     */
    public const LOOKUP_RATE_LIMIT_DEFAULT = 30;

    public const LOOKUP_RATE_LIMIT_MIN = 1;

    public const LOOKUP_RATE_LIMIT_MAX = 10000;

    /** Minutos que una ficha abierta sigue valiendo EN EL SERVIDOR (§4.8: el reloj del navegador no es garantía). */
    public const PROFILE_TTL_DEFAULT = 5;

    public const PROFILE_TTL_MIN = 1;

    public const PROFILE_TTL_MAX = 60;

    /** Días a cada lado de hoy que la ficha enseña en segundo plano (§4.6·5: el que llega un día antes o después). */
    public const WINDOW_DAYS_DEFAULT = 1;

    public const WINDOW_DAYS_MIN = 0;

    public const WINDOW_DAYS_MAX = 30;

    public static function lookupRateLimitPerHour(): int
    {
        return self::intSetting('puerta.lookup_rate_limit_per_hour', self::LOOKUP_RATE_LIMIT_DEFAULT, self::LOOKUP_RATE_LIMIT_MIN, self::LOOKUP_RATE_LIMIT_MAX);
    }

    public static function profileTtlMinutes(): int
    {
        return self::intSetting('puerta.profile_ttl_minutes', self::PROFILE_TTL_DEFAULT, self::PROFILE_TTL_MIN, self::PROFILE_TTL_MAX);
    }

    public static function windowDays(): int
    {
        return self::intSetting('puerta.window_days', self::WINDOW_DAYS_DEFAULT, self::WINDOW_DAYS_MIN, self::WINDOW_DAYS_MAX);
    }

    private static function intSetting(string $key, int $default, int $min, int $max): int
    {
        $n = filter_var(Setting::value($key, (string) $default), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);

        return $n !== false ? $n : $default;
    }

    /**
     * ¿La puerta comprueba el waiver? (#216) ON (default) → 3 estados (con/sin waiver). OFF → 2
     * estados (registrado / no registrado), para cuando el waiver lo gestiona el sistema externo
     * de la clienta. Default ON = comportamiento histórico; un valor ausente/inválido NO desactiva
     * la comprobación (fallback no destructivo): solo el literal '0' la apaga.
     *
     * Fase 6 · waiver: el interruptor pasó a ser el MODO `waiver.mode` (externo · interno ·
     * desactivado, `DECISIONES #142`), y este helper delega en él. El literal '0' del ajuste
     * heredado sigue apagando la comprobación cuando no hay modo fijado.
     */
    public static function waiverCheckEnabled(): bool
    {
        return WaiverSettings::isEnabled();
    }
}
