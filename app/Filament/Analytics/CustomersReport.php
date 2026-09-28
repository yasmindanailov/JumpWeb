<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\SqlJson;
use App\Domain\Platform\Services\Analytics\Reports\SqlTime;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * **REGISTROS Y PUERTA** (`docs/specs/analitica.md` §4.5, T2b; `DECISIONES #735`): cuántos clientes se dan de
 * alta cada día, semana o mes, y cuántos pasan por la puerta.
 *
 * **Los registros** son las cuentas de CLIENTE (`users` sin rol de equipo: la misma regla que
 * `User::customers()`) por `created_at`; de cada tanda se dice cuántas verificaron el correo y cuántas han
 * comprado alguna vez; y el MÉTODO sale del libro de eventos (`user_registered.props.method`), así que antes
 * de la medición —o en un alta desde el panel— es «sin dato», no «contraseña».
 *
 * **La puerta se mide desde su RASTRO**, que existe desde agosto: cada búsqueda deja una fila en `audit_logs`
 * (`registrations.validated` el tecleo de un correo o un teléfono, `puerta.card_scanned` el escaneo de un
 * carné; `target_id` dice si se ENCONTRÓ a alguien), cada ficha abierta otra (`puerta.profile_viewed`) y cada
 * visita acreditada una fila en `customer_visits` (una por cliente y día, a fecha del parque). De ahí salen
 * las búsquedas, las encontradas y no encontradas, los CLIENTES DISTINTOS buscados, las fichas, las visitas
 * y la distribución por hora del parque. ❌ Un evento nuevo como única fuente habría nacido vacío.
 *
 * ⚠️ Solo agregados: el `payload` de la auditoría no se lee y ningún nombre sale. Diez consultas por periodo.
 */
final class CustomersReport
{
    public const CACHE_SECONDS = 300;

    public const ACTION_LOOKUP_TYPED = 'registrations.validated';

    public const ACTION_LOOKUP_SCANNED = 'puerta.card_scanned';

    public const ACTION_PROFILE_VIEWED = 'puerta.profile_viewed';

    /** Los métodos de alta que el libro conoce; lo que no conste es «sin dato». */
    public const METHODS = ['password', 'google'];

    public const METHOD_UNKNOWN = 'unknown';

    /** Una visita sin origen dicho (las del botón retirado en `#234`). */
    public const VISIT_SOURCE_UNKNOWN = 'unknown';

    /** Los tramos de «cada cuánto vuelven» (días desde la visita anterior): una semana, un mes, tres meses, más. */
    public const GAP_WEEK = 'week';

    public const GAP_MONTH = 'month';

    public const GAP_QUARTER = 'quarter';

    public const GAP_LONGER = 'longer';

    /** @var list<string> */
    private const BUYER_STATUSES = [Order::STATUS_PAID, Order::STATUS_REFUNDED];

    /** @return array<string, mixed> */
    public static function for(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $baseline = $comparison->baseline($window);

        return Cache::remember(self::cacheKey($window, $baseline), self::CACHE_SECONDS, fn (): array => (new self)->compute($window, $baseline));
    }

    public static function cacheKey(Window $window, Window $baseline): string
    {
        return 'analytics:customers:v3:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.$baseline->dateFrom().':'.$baseline->dateTo();
    }

    /** @param  Window|null  $baseline  con qué se compara; sin ella, el periodo anterior */
    public function compute(Window $window, ?Window $baseline = null): array
    {
        $baseline ??= $window->previous();
        $registrations = $this->foldBy($window, $this->registrationsByBucket($window), ['n', 'verified', 'buyers']);
        $lookupRows = $this->lookupsByBucket($window);
        $lookups = $this->foldBy($window, $lookupRows, ['n', 'typed', 'found']);
        $hours = $this->hours($window, $lookupRows);
        [$customersByKey, $customers] = $this->distinctCustomers($window);
        $visits = $this->visitsByBucket($window);

        $total = self::sumOf($registrations, 'n');
        $methods = $this->methods($window);
        $methods[self::METHOD_UNKNOWN] = max(0, $total - array_sum($methods));

        $lookupsTotal = self::sumOf($lookups, 'n');
        $typed = self::sumOf($lookups, 'typed');
        $found = self::sumOf($lookups, 'found');

        return [
            'window' => [
                'from' => $window->dateFrom(),
                'to' => $window->dateTo(),
                'days' => $window->days(),
                'granularity' => $window->granularity(),
            ],
            'registrations' => [
                'total' => $total,
                'verified' => self::sumOf($registrations, 'verified'),
                'buyers' => self::sumOf($registrations, 'buyers'),
                'by_method' => $methods,
            ],
            'gate' => [
                'lookups' => $lookupsTotal,
                'typed' => $typed,
                'scanned' => $lookupsTotal - $typed,
                'found' => $found,
                'not_found' => $lookupsTotal - $found,
                'customers' => $customers,
                'profile_views' => $this->profileViews($window),
                'visits' => array_sum($visits),
                'visitors' => $this->visitors($window),
            ],
            // T0c (`#756`): los que VUELVEN, cada cuánto, y de dónde vino cada visita.
            'returns' => $this->returns($window),
            'previous' => $this->totalsOnly($baseline),
            'series' => $this->series($window, $registrations, $lookups, $customersByKey, $visits),
            'hours' => $hours,
        ];
    }

    // ─── Los registros ───────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, stdClass> */
    private function registrationsByBucket(Window $window): Collection
    {
        $bucket = SqlTime::hourBucket('created_at');

        return $this->customers()
            ->selectRaw(
                "{$bucket} AS bucket, COUNT(*) AS n, "
                .'SUM(CASE WHEN email_verified_at IS NULL THEN 0 ELSE 1 END) AS verified, '
                .'SUM(CASE WHEN EXISTS (SELECT 1 FROM orders o WHERE o.user_id = users.id AND o.status IN (?, ?)) THEN 1 ELSE 0 END) AS buyers',
                self::BUYER_STATUSES,
            )
            ->where('created_at', '>=', $window->utcFrom())
            ->where('created_at', '<', $window->utcTo())
            ->groupByRaw($bucket)
            ->get();
    }

    /**
     * Cómo se registraron, desde el libro (`user_registered`, T1): contraseña o Google.
     *
     * @return array<string, int>
     */
    private function methods(Window $window): array
    {
        $method = SqlJson::string('props', '$.method');

        $rows = DB::table('analytics_events')
            ->selectRaw("{$method} AS method, COUNT(*) AS n")
            ->where('name', 'user_registered')
            ->where('received_at', '>=', $window->utcFrom())
            ->where('received_at', '<', $window->utcTo())
            ->groupByRaw($method)
            ->get();

        $out = array_fill_keys(self::METHODS, 0);
        foreach ($rows as $row) {
            $key = (string) $row->method;
            if (in_array($key, self::METHODS, true)) {
                $out[$key] += (int) $row->n;
            }
        }

        return $out;
    }

    // ─── La puerta ───────────────────────────────────────────────────────────────────────────────

    /** @return Collection<int, stdClass> */
    private function lookupsByBucket(Window $window): Collection
    {
        $bucket = SqlTime::hourBucket('created_at');

        return $this->lookups($window)
            ->selectRaw(
                "{$bucket} AS bucket, COUNT(*) AS n, "
                .'SUM(CASE WHEN action = ? THEN 1 ELSE 0 END) AS typed, '
                .'SUM(CASE WHEN target_id IS NULL THEN 0 ELSE 1 END) AS found',
                [self::ACTION_LOOKUP_TYPED],
            )
            ->groupByRaw($bucket)
            ->get();
    }

    /**
     * Las búsquedas por HORA DEL PARQUE (0–23): los picos de afluencia. Salen de las mismas filas por hora UTC
     * que la serie, convertidas a la zona del parque.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return list<int>
     */
    private function hours(Window $window, Collection $rows): array
    {
        $hours = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $hour = (int) SqlTime::bucketStart((string) $row->bucket)->setTimezone($window->timezone)->format('G');
            $hours[$hour] += (int) $row->n;
        }

        return $hours;
    }

    /**
     * Los CLIENTES DISTINTOS buscados: por cubo y en todo el periodo. Un cliente buscado dos veces el mismo
     * día cuenta una; buscado en dos días, una en cada uno y una en el total.
     *
     * @return array{0: array<string, int>, 1: int}
     */
    private function distinctCustomers(Window $window): array
    {
        $bucket = SqlTime::hourBucket('created_at');

        $rows = $this->lookups($window)
            ->selectRaw("{$bucket} AS bucket, target_id")
            ->whereNotNull('target_id')
            ->groupByRaw("{$bucket}, target_id")
            ->get();

        $byKey = [];
        $all = [];
        foreach ($rows as $row) {
            $key = $window->bucketKey(SqlTime::bucketStart((string) $row->bucket));
            $byKey[$key][(int) $row->target_id] = true;
            $all[(int) $row->target_id] = true;
        }

        return [array_map('count', $byKey), count($all)];
    }

    private function profileViews(Window $window): int
    {
        return $this->between(DB::table('audit_logs'), 'created_at', $window)
            ->where('action', self::ACTION_PROFILE_VIEWED)
            ->count();
    }

    /**
     * Las visitas acreditadas por día del parque: `visited_on` ya es la fecha civil del parque, sin zona.
     *
     * @return array<string, int>
     */
    private function visitsByBucket(Window $window): array
    {
        $rows = DB::table('customer_visits')
            ->selectRaw('visited_on, COUNT(*) AS n')
            ->whereBetween('visited_on', [$window->dateFrom(), $window->dateTo()])
            ->groupBy('visited_on')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $key = $window->bucketKey(CarbonImmutable::parse((string) $row->visited_on, $window->timezone));
            $out[$key] = ($out[$key] ?? 0) + (int) $row->n;
        }

        return $out;
    }

    private function visitors(Window $window): int
    {
        return (int) DB::table('customer_visits')
            ->whereBetween('visited_on', [$window->dateFrom(), $window->dateTo()])
            ->distinct()
            ->count('user_id');
    }

    /**
     * **Los que VUELVEN al parque** (T0c de `analitica-para-decidir.md` §4.8.bis, `#756`; el owner, 27-09: «los clientes
     * que vuelven no los veo… cuántas veces vuelven cada X tiempo»). UNA consulta: cada visita acreditada del periodo con
     * la ANTERIOR de ese cliente (`LAG` sobre toda su historia, en una tabla derivada, y el periodo se filtra DESPUÉS: si
     * se filtrara antes, la primera visita del periodo no vería la de antes). De ahí salen:
     *  - `returning`: clientes cuya primera visita del periodo tenía una anterior (ya habían venido);
     *  - `first_time`: los que no; `repeat`: los que vinieron dos o más días dentro del periodo;
     *  - `gaps`: los días entre cada visita del periodo y la anterior del mismo cliente —su mediana es «cada cuánto
     *    vuelven»— y su reparto (una semana, un mes, tres meses, más);
     *  - `by_source`: las visitas por su origen (`card`, `lookup`; `null` las del botón retirado, `#234`).
     *
     * ⚠️ La historia de visitas tiene un HUECO (`DEUDA.md`): del 28-08 al 25-09 no se acreditó ninguna; quien vino solo en
     * ese hueco cuenta como «primera vez». Lo dice su «¿Cómo se calcula?».
     *
     * @return array{visitors: int, returning: int, first_time: int, repeat: int, gap_median_days: ?int, gap_buckets: array<string, int>, by_source: array<string, int>}
     */
    private function returns(Window $window): array
    {
        $ranked = DB::table('customer_visits')
            ->select(['user_id', 'visited_on', 'source'])
            ->selectRaw('LAG(visited_on) OVER (PARTITION BY user_id ORDER BY visited_on) AS previous_on')
            ->where('visited_on', '<=', $window->dateTo());

        $rows = DB::query()->fromSub($ranked, 'v')
            ->whereBetween('visited_on', [$window->dateFrom(), $window->dateTo()])
            ->orderBy('user_id')
            ->orderBy('visited_on')
            ->get();

        $byUser = [];
        $gaps = [];
        $bySource = [];
        foreach ($rows as $row) {
            $user = (int) $row->user_id;
            $byUser[$user] ??= ['visits' => 0, 'came_before' => $row->previous_on !== null];
            $byUser[$user]['visits']++;
            $source = (string) ($row->source ?? self::VISIT_SOURCE_UNKNOWN);
            $bySource[$source] = ($bySource[$source] ?? 0) + 1;

            if ($row->previous_on !== null) {
                $gaps[] = (int) CarbonImmutable::parse((string) $row->previous_on)->diffInDays(CarbonImmutable::parse((string) $row->visited_on));
            }
        }

        $returning = count(array_filter($byUser, static fn (array $u): bool => $u['came_before']));
        sort($gaps);

        return [
            'visitors' => count($byUser),
            'returning' => $returning,
            'first_time' => count($byUser) - $returning,
            'repeat' => count(array_filter($byUser, static fn (array $u): bool => $u['visits'] >= 2)),
            'gap_median_days' => self::median($gaps),
            'gap_buckets' => [
                self::GAP_WEEK => count(array_filter($gaps, static fn (int $d): bool => $d <= 7)),
                self::GAP_MONTH => count(array_filter($gaps, static fn (int $d): bool => $d > 7 && $d <= 30)),
                self::GAP_QUARTER => count(array_filter($gaps, static fn (int $d): bool => $d > 30 && $d <= 90)),
                self::GAP_LONGER => count(array_filter($gaps, static fn (int $d): bool => $d > 90)),
            ],
            'by_source' => $bySource,
        ];
    }

    /**
     * La mediana de una lista ORDENADA de días (la de abajo de las dos centrales si son pares: un día entero); `null` sin datos.
     *
     * @param  list<int>  $sorted
     */
    private static function median(array $sorted): ?int
    {
        $n = count($sorted);

        return $n === 0 ? null : $sorted[intdiv($n - 1, 2)];
    }

    // ─── El periodo anterior y la serie ─────────────────────────────────────────────────────────

    /**
     * Los totales de una ventana tal como este informe los usa para su periodo comparado: la historia de la T3b (`#790`).
     *
     * @return array{previous: array<string, int>}
     */
    public static function baselineTotals(Window $window): array
    {
        return ['previous' => (new self)->totalsOnly($window)];
    }

    /** @return array<string, int> */
    private function totalsOnly(Window $window): array
    {
        $registrations = $this->customers()
            ->where('created_at', '>=', $window->utcFrom())
            ->where('created_at', '<', $window->utcTo())
            ->count();

        $lookups = $this->lookups($window)
            ->selectRaw('COUNT(*) AS n, SUM(CASE WHEN target_id IS NULL THEN 0 ELSE 1 END) AS found')
            ->first();

        $visits = DB::table('customer_visits')
            ->whereBetween('visited_on', [$window->dateFrom(), $window->dateTo()])
            ->count();
        $returns = $this->returns($window);

        return [
            'registrations' => $registrations,
            'lookups' => (int) ($lookups->n ?? 0),
            'found' => (int) ($lookups->found ?? 0),
            'visits' => $visits,
            'returning' => $returns['returning'],
            'first_time' => $returns['first_time'],
            'repeat' => $returns['repeat'],
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $registrations
     * @param  array<string, array<string, int>>  $lookups
     * @param  array<string, int>  $customers
     * @param  array<string, int>  $visits
     * @return list<array{key: string, registrations: int, verified: int, lookups: int, found: int, customers: int, visits: int}>
     */
    private function series(Window $window, array $registrations, array $lookups, array $customers, array $visits): array
    {
        $series = [];
        foreach ($window->bucketKeys() as $key) {
            $series[] = [
                'key' => $key,
                'registrations' => $registrations[$key]['n'] ?? 0,
                'verified' => $registrations[$key]['verified'] ?? 0,
                'lookups' => $lookups[$key]['n'] ?? 0,
                'found' => $lookups[$key]['found'] ?? 0,
                'customers' => $customers[$key] ?? 0,
                'visits' => $visits[$key] ?? 0,
            ];
        }

        return $series;
    }

    // ─── Las consultas base y los pliegues ──────────────────────────────────────────────────────

    /** Las cuentas de CLIENTE: sin rol de equipo, la regla de `User::customers()` escrita en SQL. */
    private function customers(): Builder
    {
        return DB::table('users')->whereNotExists(function (Builder $query): void {
            $query->select(DB::raw(1))
                ->from('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->whereColumn('role_user.user_id', 'users.id')
                ->whereIn('roles.name', User::PANEL_ROLES);
        });
    }

    /** Las búsquedas de la puerta (tecleadas y escaneadas) dentro de la ventana. */
    private function lookups(Window $window): Builder
    {
        return $this->between(DB::table('audit_logs'), 'created_at', $window)
            ->whereIn('action', [self::ACTION_LOOKUP_TYPED, self::ACTION_LOOKUP_SCANNED]);
    }

    private function between(Builder $query, string $column, Window $window): Builder
    {
        return $query
            ->where($column, '>=', $window->utcFrom())
            ->where($column, '<', $window->utcTo());
    }

    /**
     * Cada cubo de hora UTC cae en su día (o semana) del PARQUE, y los campos se suman por cubo.
     *
     * @param  Collection<int, stdClass>  $rows
     * @param  list<string>  $fields
     * @return array<string, array<string, int>>
     */
    private function foldBy(Window $window, Collection $rows, array $fields): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = $window->bucketKey(SqlTime::bucketStart((string) $row->bucket));
            foreach ($fields as $field) {
                $out[$key][$field] = ($out[$key][$field] ?? 0) + (int) $row->{$field};
            }
        }

        return $out;
    }

    /** @param  array<string, array<string, int>>  $folded */
    private static function sumOf(array $folded, string $field): int
    {
        return array_sum(array_map(static fn (array $b): int => $b[$field] ?? 0, $folded));
    }
}
