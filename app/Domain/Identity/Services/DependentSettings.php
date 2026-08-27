<?php

namespace App\Domain\Identity\Services;

use App\Domain\Platform\Models\Setting;

/**
 * Fase 6 · menores a cargo — el ajuste del subsistema (`docs/specs/menores-a-cargo.md` §4.5).
 * Mismo patrón defensivo que `WaiverSettings` y `PuertaSettings`: un valor ausente o inválido cae
 * al valor por defecto, nunca a «sin tope».
 *
 * `dependents.max_per_account` — cuántas personas a cargo puede declarar una cuenta. **Tope de
 * SERVIDOR** (`[DECIDIDO owner]`, `DECISIONES #142`; doctrina `PAY-12`): «cuantos quiera» no puede
 * ser literal en una superficie de escritura barata que crea PII de terceros. Lo aplica
 * `DependentRegistry::add()` bajo el lock de la fila del titular.
 */
final class DependentSettings
{
    public const KEY_MAX_PER_ACCOUNT = 'dependents.max_per_account';

    public const GROUP = 'dependents';

    public const DEFAULT_MAX_PER_ACCOUNT = 20;

    public const MAX_PER_ACCOUNT_MIN = 1;

    public const MAX_PER_ACCOUNT_MAX = 100;

    public static function maxPerAccount(): int
    {
        $n = filter_var(Setting::value(self::KEY_MAX_PER_ACCOUNT), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => self::MAX_PER_ACCOUNT_MIN, 'max_range' => self::MAX_PER_ACCOUNT_MAX],
        ]);

        return $n === false ? self::DEFAULT_MAX_PER_ACCOUNT : $n;
    }
}
