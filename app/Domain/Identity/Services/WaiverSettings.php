<?php

namespace App\Domain\Identity\Services;

use App\Domain\Platform\Models\Setting;

/**
 * Fase 6 · waiver — los ajustes del subsistema (`docs/specs/waiver-probatorio.md` §4.1, §4.6).
 * Mismo patrón defensivo que `PuertaSettings`: un valor ausente o inválido cae al comportamiento
 * histórico, nunca a uno nuevo.
 *
 * `waiver.mode` — los TRES modos (`DECISIONES #142`): `externo` (lo gestiona el sistema del parque;
 * aquí solo el sello `users.waiver_accepted_at`, como siempre), `interno` (se firma aquí; manda el
 * registro probatorio) y `desactivado` (la puerta no lo comprueba). Sin fila, se deriva del
 * interruptor heredado `puerta.waiver_check_enabled`: '0' → desactivado; lo demás → externo. Así una
 * instalación existente no cambia de conducta al desplegar.
 *
 * `waiver.retention_months` — el plazo de conservación del registro firmado, también tras borrar la
 * cuenta. ❗ `[PENDIENTE: owner]` (criterio jurídico): sin valor NO se poda nada.
 */
final class WaiverSettings
{
    public const SLUG = 'waiver';

    public const MODE_EXTERNAL = 'externo';

    public const MODE_INTERNAL = 'interno';

    public const MODE_OFF = 'desactivado';

    public const MODES = [self::MODE_EXTERNAL, self::MODE_INTERNAL, self::MODE_OFF];

    public const KEY_MODE = 'waiver.mode';

    public const KEY_RETENTION_MONTHS = 'waiver.retention_months';

    /** El interruptor de #216, hoy solo respaldo de `mode()` y espejo que escribe el panel. */
    public const LEGACY_KEY_CHECK_ENABLED = 'puerta.waiver_check_enabled';

    public const RETENTION_MIN = 1;

    public const RETENTION_MAX = 600;

    public static function mode(): string
    {
        $raw = trim((string) Setting::value(self::KEY_MODE, ''));
        if (in_array($raw, self::MODES, true)) {
            return $raw;
        }

        return (string) Setting::value(self::LEGACY_KEY_CHECK_ENABLED, '1') === '0'
            ? self::MODE_OFF
            : self::MODE_EXTERNAL;
    }

    public static function isInternal(): bool
    {
        return self::mode() === self::MODE_INTERNAL;
    }

    /** ¿La puerta comprueba el waiver? Falso solo en `desactivado`. */
    public static function isEnabled(): bool
    {
        return self::mode() !== self::MODE_OFF;
    }

    public static function retentionMonths(): ?int
    {
        $n = filter_var(Setting::value(self::KEY_RETENTION_MONTHS), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::RETENTION_MIN, 'max_range' => self::RETENTION_MAX],
        ]);

        return $n === false ? null : $n;
    }
}
