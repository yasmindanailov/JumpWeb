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
 * cuenta. **Sin valor NO se poda nada**, y eso es lo correcto como default del PRODUCTO: el plazo es
 * criterio jurídico de cada instalación, no una constante del software.
 *
 * `waiver.dependent_retention_months` — el plazo de la firma de un MENOR a cargo, contado desde su
 * 18.º cumpleaños (`DECISIONES #197`, `menores-a-cargo.md` §9.5·3). Mismo criterio.
 *
 * ▶ **`[DECIDIDO owner, 2026-09-06]` para `playjump.es`: 60 y 60** (`#441`) — cinco años desde la
 * firma, y en el menor cinco años desde que cumple 18. Se apoya en el plazo general de acciones
 * personales del art. 1964 CC; en menores el plazo no corre hasta la mayoría de edad, que es
 * exactamente por lo que esa columna se cuenta desde los 18.
 * ⚠️⚠️ **Y pasó de deuda de fondo a REQUISITO DE SALIDA con `#441`**: desde que declarar un menor
 * firma su exención, en modo `interno` **todo menor nuevo deja registro**, así que sin plazo la
 * feature conserva datos de niños **sin fecha de caducidad**. Es un paso de despliegue por
 * instalación, no un valor que el producto pueda dar por supuesto.
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

    public const KEY_DEPENDENT_RETENTION_MONTHS = 'waiver.dependent_retention_months';

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
        return self::months(self::KEY_RETENTION_MONTHS);
    }

    /** Meses de conservación de la firma de un menor a cargo DESPUÉS de cumplir 18; `null` = no se poda. */
    public static function dependentRetentionMonths(): ?int
    {
        return self::months(self::KEY_DEPENDENT_RETENTION_MONTHS);
    }

    private static function months(string $key): ?int
    {
        $n = filter_var(Setting::value($key), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::RETENTION_MIN, 'max_range' => self::RETENTION_MAX],
        ]);

        return $n === false ? null : $n;
    }
}
