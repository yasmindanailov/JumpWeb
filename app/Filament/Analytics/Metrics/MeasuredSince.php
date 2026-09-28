<?php

namespace App\Filament\Analytics\Metrics;

use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * **Desde cuándo mide cada fuente del cuadro** (T3b de `analitica-para-decidir.md` §4.13, `#790`): un periodo entra en la
 * historia de una cifra solo si empieza cuando su fuente YA medía. Un cero de antes de medir no es un dato: con las visitas a
 * la web, que empiezan con la v2.0.0, los meses de antes dirían «cero visitas» y ninguna cifra podría quedar «por debajo de
 * lo normal».
 *
 * El primer registro de cada fuente, en UTC; `null` si aún no hay ninguno. Una hora en caché: el primer registro no cambia.
 */
final class MeasuredSince
{
    /** Los pedidos cobrados: el dinero, la ocupación, las fiestas. */
    public const ORDERS = 'orders';

    /**
     * Las visitas a la web (`analytics_sessions`): el marketing. ⚠️ Su clave no es el nombre de la tabla de credenciales
     * (`AccessRevocationTest` no deja nombrarla fuera de su punto único, y no la distingue de esta).
     */
    public const WEB_VISITS = 'web_visits';

    /** Las visitas acreditadas en la puerta: los que vienen y vuelven. */
    public const GATE = 'gate';

    /** Las cuentas de cliente: los registros. */
    public const ACCOUNTS = 'accounts';

    /** Las encuestas: la satisfacción. */
    public const SURVEYS = 'surveys';

    /** La demanda sin hueco: se emite desde la T2 (`#758`). */
    public const MISSING = 'missing';

    public const SOURCES = [self::ORDERS, self::WEB_VISITS, self::GATE, self::ACCOUNTS, self::SURVEYS, self::MISSING];

    private const CACHE_SECONDS = 3600;

    public static function of(string $source): ?CarbonImmutable
    {
        $first = Cache::remember('analytics:measured-since:v1:'.$source, self::CACHE_SECONDS, static fn (): ?string => self::first($source));

        return $first === null ? null : CarbonImmutable::parse($first, 'UTC');
    }

    /** El primer registro de la fuente, como instante UTC escrito. */
    private static function first(string $source): ?string
    {
        $value = match ($source) {
            self::ORDERS => DB::table('orders')->whereNotNull('paid_at')->min('paid_at'),
            self::WEB_VISITS => DB::table('analytics_sessions')->min('started_at'),
            // Un día civil del parque: su primer instante, en UTC.
            self::GATE => ($day = DB::table('customer_visits')->min('visited_on')) === null
                ? null
                : CarbonImmutable::parse(substr((string) $day, 0, 10), DisplayTime::timezone())->startOfDay()->utc()->format('Y-m-d H:i:s'),
            self::ACCOUNTS => DB::table('users')->min('created_at'),
            self::SURVEYS => DB::table('surveys')->min('created_at'),
            self::MISSING => DB::table('analytics_events')->where('name', 'availability_missing')->min('received_at'),
            default => throw new InvalidArgumentException("«{$source}» no es una fuente del cuadro"),
        };

        return $value === null ? null : (string) $value;
    }
}
