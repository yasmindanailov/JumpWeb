<?php

namespace App\Domain\Platform\Services\Analytics;

use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * **Cuántos eventos rechazó la ingesta, por día y por motivo** (`docs/specs/analitica.md` §4.5, T2c).
 *
 * La T1 los dejaba en el `Log` y nada más, y el cuadro quiere enseñarlos: un `data-jw-track` mal escrito en
 * una landing o un cliente viejo mandando un evento que ya no existe se ven aquí antes que en ningún sitio.
 * Son contadores en la CACHÉ (Redis en el hosting), uno por día del parque y motivo, con ocho días de vida:
 * siete se enseñan y el octavo es el margen del cambio de día. No es un dato que merezca tabla: si la caché
 * se vacía, se pierde una semana de recuento y nada más.
 *
 * ⚠️ El día es el del PARQUE, como todo corte del cuadro.
 */
final class RejectedEvents
{
    public const DAYS = 7;

    /** Los motivos que devuelve `EventIngestor::prepare()`, en el orden en que se enseñan. */
    public const REASONS = ['unknown_event', 'server_only', 'bad_event_id', 'pii', 'malformed'];

    private const TTL_DAYS = 8;

    /** @param  list<array{index: int, reason: string}>  $rejected */
    public static function record(array $rejected): void
    {
        if ($rejected === []) {
            return;
        }

        $day = DisplayTime::today()->toDateString();

        foreach (array_count_values(array_column($rejected, 'reason')) as $reason => $count) {
            $key = self::key($day, (string) $reason);
            Cache::add($key, 0, now()->addDays(self::TTL_DAYS));
            Cache::increment($key, $count);
        }
    }

    /**
     * Los últimos siete días del parque, del más antiguo al de hoy.
     *
     * @return array{days: int, total: int, by_reason: array<string, int>, by_day: array<string, int>}
     */
    public static function lastDays(): array
    {
        $today = CarbonImmutable::instance(DisplayTime::today());
        $byReason = array_fill_keys(self::REASONS, 0);
        $byDay = [];

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $day = $today->subDays($i)->toDateString();
            $byDay[$day] = 0;

            foreach (self::REASONS as $reason) {
                $count = (int) Cache::get(self::key($day, $reason), 0);
                $byReason[$reason] += $count;
                $byDay[$day] += $count;
            }
        }

        return [
            'days' => self::DAYS,
            'total' => array_sum($byReason),
            'by_reason' => $byReason,
            'by_day' => $byDay,
        ];
    }

    private static function key(string $day, string $reason): string
    {
        return "analytics:rejected:{$day}:{$reason}";
    }
}
