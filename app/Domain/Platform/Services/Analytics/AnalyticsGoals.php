<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Models\AnalyticsGoal;
use App\Domain\Platform\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * **Los objetivos del mes: el único que los lee y el único que los escribe** (T3c·2 de
 * `docs/specs/analitica-para-decidir.md` §4.13, `DECISIONES #759`).
 *
 * Qué cifras admiten objetivo y en qué unidad lo decide el cuadro (`Filament\Analytics\Goal::KEYS`); aquí se guardan
 * números por clave y mes, sin saber qué significan. Cada guardado deja UN rastro (`analytics.goals_updated`) con el
 * antes y el después de las claves que cambiaron —claves y números, ningún dato personal— y olvida la caché del mes: la
 * tarjeta dice el objetivo nuevo en la petición siguiente.
 */
final class AnalyticsGoals
{
    /** La forma de una clave del catálogo (`money.net`): lo demás no es una cifra. */
    public const KEY_RE = '/^[a-z_]+\.[a-z_]+$/';

    private const CACHE_SECONDS = 3600;

    /**
     * Los objetivos de un mes, por clave (en la unidad de cada cifra).
     *
     * @return array<string, int>
     */
    public static function forMonth(CarbonInterface $month): array
    {
        $first = self::firstDay($month);

        return Cache::remember(self::cacheKey($first), self::CACHE_SECONDS, static fn (): array => AnalyticsGoal::query()
            ->whereDate('month', $first->toDateString())
            ->orderBy('metric_key')
            ->pluck('target', 'metric_key')
            ->map(static fn (mixed $target): int => (int) $target)
            ->all());
    }

    /**
     * Guarda los objetivos de un mes: un número lo pone o lo cambia; `null`, lo quita. Las claves que no vienen no se tocan.
     *
     * @param  array<string, ?int>  $targets
     * @return bool si cambió algo (sin cambios, ni se escribe ni se deja rastro)
     */
    public static function save(CarbonInterface $month, array $targets, ?int $setBy): bool
    {
        $first = self::firstDay($month);

        foreach ($targets as $key => $target) {
            if (preg_match(self::KEY_RE, $key) !== 1) {
                throw new InvalidArgumentException("«{$key}» no es la clave de una cifra.");
            }
            if ($target !== null && $target <= 0) {
                throw new InvalidArgumentException("El objetivo de «{$key}» tiene que ser mayor que cero.");
            }
        }

        $changed = DB::transaction(static function () use ($first, $targets, $setBy): array {
            $before = AnalyticsGoal::query()
                ->whereDate('month', $first->toDateString())
                ->whereIn('metric_key', array_keys($targets))
                ->lockForUpdate()
                ->get()
                ->keyBy('metric_key');

            $changes = ['before' => [], 'after' => []];

            foreach ($targets as $key => $target) {
                $row = $before->get($key);
                $old = $row?->target;

                if ($old === $target) {
                    continue;
                }

                if ($target === null) {
                    $row?->delete();
                } elseif ($row !== null) {
                    $row->update(['target' => $target, 'set_by' => $setBy]);
                } else {
                    AnalyticsGoal::query()->create(['metric_key' => $key, 'month' => $first->toDateString(), 'target' => $target, 'set_by' => $setBy]);
                }

                $changes['before'][$key] = $old;
                $changes['after'][$key] = $target;
            }

            return $changes;
        });

        Cache::forget(self::cacheKey($first));

        if ($changed['after'] === []) {
            return false;
        }

        AuditLogger::log('analytics.goals_updated', null, ['month' => $first->format('Y-m')] + $changed);

        return true;
    }

    private static function firstDay(CarbonInterface $month): CarbonImmutable
    {
        return CarbonImmutable::parse($month->format('Y-m-01'));
    }

    private static function cacheKey(CarbonImmutable $first): string
    {
        return 'analytics:goals:v1:'.$first->format('Y-m');
    }
}
