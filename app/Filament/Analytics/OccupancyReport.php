<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Services\OccupancyReader;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * **LA OCUPACIÓN: cómo de lleno está el parque, y cuándo** (`docs/specs/analitica-para-decidir.md` §4.8 y §4.8.ter, la
 * T2; `DECISIONES #758`).
 *
 * Todo sale de `Booking\Services\OccupancyReader` —la presencia en cada punto de la rejilla con la aritmética del aforo,
 * atada por paridad— y del libro de eventos (la demanda sin hueco). **Dos cifras que no se mezclan** (`#758`): la
 * ocupación de las ENTRADAS (plazas presentes frente al aforo, en las zonas que venden entradas) y las FIESTAS por franja
 * (fiestas a la vez frente al tope de su zona).
 *
 * El periodo se corta por el INSTANTE de la visita (el día y la hora de la franja, en la hora del parque): «este mes»
 * cuenta lo que ya pasó hasta ahora (T0a), y lo que aún viene es de la cartera (T4). Lo pendiente no cuenta: es lo que
 * PASÓ. Unas quince consultas por periodo y su comparación; la caché de cinco minutos las reparte entre los widgets y el
 * CSV.
 */
final class OccupancyReport
{
    public const CACHE_SECONDS = 300;

    /** Los tramos de la anticipación, en días entre el cobro y la visita. */
    public const LEAD_BUCKETS = ['same_day' => [0, 0], 'd1_2' => [1, 2], 'd3_7' => [3, 7], 'd8_30' => [8, 30], 'd31' => [31, PHP_INT_MAX]];

    /** Los tipos de visita, en su orden de lectura (la regla de `PaidVisits`). */
    public const KINDS = ['entry', 'party', 'group'];

    /** @return array<string, mixed> */
    public static function for(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $baseline = $comparison->baseline($window);

        return Cache::remember(self::cacheKey($window, $baseline), self::CACHE_SECONDS, fn (): array => (new self)->compute($window, $baseline));
    }

    /**
     * Por FECHAS, como los otros informes del cuadro. ⚠️ Hasta la T3a llevaba el corte al SEGUNDO: como toda ventana que
     * llega a hoy se corta «hasta ahora» (T0a), la clave cambiaba cada segundo y la caché no servía entre las peticiones de
     * una misma pestaña —cada widget recalculaba el informe— (medido el 28-09). Dos ventanas con las mismas fechas son la
     * misma ventana en el mismo instante; la caché de cinco minutos da, como en los demás, un corte de hasta cinco minutos
     * antes. v2 (T3a, `#759`): el informe lleva «visitors».
     */
    public static function cacheKey(Window $window, Window $baseline): string
    {
        return 'analytics:occupancy:v3:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.$baseline->dateFrom().':'.$baseline->dateTo().':'.app()->getLocale();
    }

    /**
     * @param  Window|null  $baseline  con qué se compara; sin ella, el periodo anterior
     * @return array<string, mixed>
     */
    public function compute(Window $window, ?Window $baseline = null): array
    {
        $baseline ??= $window->previous();
        $reader = app(OccupancyReader::class);
        $points = $this->within($reader->points($window->dateFrom(), $window->dateTo()), $window);
        $lines = $this->within($reader->paidLines($window->dateFrom(), $window->dateTo()), $window);
        $zones = $this->zoneNames();

        return [
            'window' => ['from' => $window->dateFrom(), 'to' => $window->dateTo(), 'days' => $window->days()],
            'entries' => $this->entries($points, $lines, $window->timezone),
            'parties' => $this->parties($points),
            'visitors' => $this->visitors($lines),
            'anticipation' => $this->anticipation($lines, $window->timezone),
            'missing' => $this->missing($window),
            'heatmap' => $this->heatmap($points),
            'by_hour' => $this->byHour($points),
            'by_zone' => $this->byZone($points, $zones),
            'by_product' => $this->byProduct($lines),
            'previous' => $this->totalsOnly($baseline, $reader),
        ];
    }

    // ─── Los puntos y las líneas del periodo ─────────────────────────────────────────────────────

    /**
     * Lo que cae DENTRO de la ventana por su instante (el día y la hora de la franja en el parque): en un periodo en
     * curso, lo de más tarde de hoy aún no ha pasado.
     *
     * @template T of array{date: string, start: string}
     *
     * @param  list<T>  $rows
     * @return list<T>
     */
    private function within(array $rows, Window $window): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => $window->contains(CarbonImmutable::parse($row['date'].' '.$row['start'], $window->timezone))));
    }

    /**
     * **Los VISITANTES** (T3a, `#759`): las plazas de las visitas PAGADAS del periodo —entradas, grupos y fiestas, por el
     * instante de la visita—, las reservas que las traen y la suma de los cuadrados de cada una: las plazas llegan en lotes
     * (una fiesta de veinte) y su prueba es la de una suma ({@see Metric::units()}). No es la ocupación: aquí no hay aforo,
     * y una plaza de dos horas cuenta una vez.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{seats: int, lines: int, seats_sq: int}
     */
    private function visitors(array $lines): array
    {
        $out = ['seats' => 0, 'lines' => 0, 'seats_sq' => 0];
        foreach ($lines as $line) {
            $seats = (int) $line['seats'];
            $out['seats'] += $seats;
            $out['lines']++;
            $out['seats_sq'] += $seats * $seats;
        }

        return $out;
    }

    /**
     * Las ENTRADAS: la ocupación (plazas presentes frente al aforo), las franjas llenas y su antelación, y el ingreso por
     * plaza-hora ofrecida. Solo zonas que venden entradas y franjas abiertas.
     *
     * @param  list<array<string, mixed>>  $points
     * @param  list<array<string, mixed>>  $lines
     * @return array{seats: int, capacity: int, points: int, full: int, full_lead_days: ?int, seat_minutes: int, revenue_cents: int, lines: int}
     */
    private function entries(array $points, array $lines, string $timezone): array
    {
        $out = ['seats' => 0, 'capacity' => 0, 'points' => 0, 'full' => 0, 'full_lead_days' => null, 'seat_minutes' => 0, 'revenue_cents' => 0, 'lines' => 0];
        $entryZones = [];
        $leads = [];
        foreach ($points as $p) {
            if (! $p['entry_zone'] || $p['closed']) {
                continue;
            }
            $entryZones[$p['zone_id']] = true;
            $out['points']++;
            $out['seats'] += min($p['seats'], $p['capacity']);
            $out['capacity'] += $p['capacity'];
            $out['seat_minutes'] += $p['capacity'] * $p['minutes'];
            if ($p['online_capacity'] > 0 && $p['seats'] >= $p['online_capacity']) {
                $out['full']++;
                if ($p['last_paid_at'] !== null) {
                    // El inicio en la hora de PARED del parque; el cobro, en UTC: la resta es entre instantes.
                    $startsAt = CarbonImmutable::parse($p['date'].' '.$p['start'], $timezone);
                    $leads[] = max(0, (int) floor(CarbonImmutable::parse($p['last_paid_at'], 'UTC')->diffInDays($startsAt, false)));
                }
            }
        }
        foreach ($lines as $line) {
            if (isset($entryZones[$line['zone_id']])) {
                $out['revenue_cents'] += $line['charged_cents'];
                $out['lines']++;
            }
        }
        sort($leads);
        $out['full_lead_days'] = self::median($leads);

        return $out;
    }

    /**
     * Las FIESTAS por franja: fiestas a la vez frente al tope de su zona, en las zonas con packs. Con tope 0 («sin tope»)
     * no hay denominador: se cuenta y no se da el %.
     *
     * @param  list<array<string, mixed>>  $points
     * @return array{present: int, cap: int, points: int, capped: bool}
     */
    private function parties(array $points): array
    {
        $out = ['present' => 0, 'cap' => 0, 'points' => 0, 'capped' => false];
        foreach ($points as $p) {
            if (! $p['pack_zone'] || $p['closed']) {
                continue;
            }
            $out['points']++;
            if ($p['max_parties'] > 0) {
                $out['capped'] = true;
                $out['present'] += min($p['parties'], $p['max_parties']);
                $out['cap'] += $p['max_parties'];
            } else {
                $out['present'] += $p['parties'];
            }
        }

        return $out;
    }

    /**
     * **La anticipación**: días entre el cobro y la visita de cada línea principal (el cobro en el día del parque; uno
     * registrado DESPUÉS de la visita —un pedido del panel— cuenta como el mismo día). Mediana, reparto por tramos, por
     * tipo de visita y por día de la semana.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{n: int, median: ?int, buckets: array<string, int>, by_kind: array<string, array{n: int, median: ?int, buckets: array<string, int>}>, by_weekday: array<int, array{n: int, median: ?int}>}
     */
    private function anticipation(array $lines, string $timezone): array
    {
        $all = [];
        $byKind = array_fill_keys(self::KINDS, []);
        $byWeekday = array_fill(1, 7, []);
        foreach ($lines as $line) {
            if ($line['paid_at'] === null) {
                continue;
            }
            $paidOn = CarbonImmutable::parse($line['paid_at'], 'UTC')->setTimezone($timezone)->startOfDay();
            $visit = CarbonImmutable::parse($line['date'], $timezone);
            $days = max(0, (int) $paidOn->diffInDays($visit, false));
            $all[] = $days;
            $byKind[$line['kind']][] = $days;
            $byWeekday[(int) $visit->isoFormat('E')][] = $days;
        }

        $summary = function (array $days): array {
            sort($days);

            return ['n' => count($days), 'median' => self::median($days), 'buckets' => $this->buckets($days)];
        };

        $out = $summary($all);
        $out['by_kind'] = array_map($summary, $byKind);
        $out['by_weekday'] = array_map(static function (array $days): array {
            sort($days);

            return ['n' => count($days), 'median' => self::median($days)];
        }, $byWeekday);

        return $out;
    }

    /**
     * @param  list<int>  $days
     * @return array<string, int>
     */
    private function buckets(array $days): array
    {
        $out = array_fill_keys(array_keys(self::LEAD_BUCKETS), 0);
        foreach ($days as $d) {
            foreach (self::LEAD_BUCKETS as $key => [$min, $max]) {
                if ($d >= $min && $d <= $max) {
                    $out[$key]++;
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * **La demanda sin hueco** (`#758`): los `availability_missing` del periodo —el cajón, al cargar la oferta de un
     * producto, por cada mes sin días desde el en curso—, por producto y mes; y desde cuándo se mide.
     *
     * @return array{count: int, since: ?string, by_product_month: list<array{product: string, month: string, n: int}>}
     */
    private function missing(Window $window): array
    {
        $rows = DB::table('analytics_events')->where('name', 'availability_missing')
            ->whereBetween('received_at', [$window->utcFrom()->format('Y-m-d H:i:s'), $window->utcTo()->format('Y-m-d H:i:s')])
            ->select(['props', 'received_at'])
            ->get()
            ->filter(static fn (object $r): bool => $window->contains(CarbonImmutable::parse((string) $r->received_at, 'UTC')));
        $since = DB::table('analytics_events')->where('name', 'availability_missing')->min('received_at');

        $counts = [];
        foreach ($rows as $row) {
            $props = is_string($row->props) ? (array) json_decode($row->props, true) : [];
            $key = ((string) ($props['product'] ?? '?')).'|'.((string) ($props['month'] ?? '?'));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $ids = array_values(array_filter(array_map(static fn (string $k): int => (int) explode('|', $k)[0], array_keys($counts))));
        $names = $ids === [] ? collect() : DB::table('ticket_types')->whereIn('id', $ids)->pluck('name', 'id');
        $locale = app()->getLocale();

        $out = [];
        foreach ($counts as $key => $n) {
            [$product, $month] = explode('|', $key);
            $name = json_decode((string) ($names[(int) $product] ?? ''), true);
            $out[] = ['product' => is_array($name) ? (string) ($name[$locale] ?? $name['es'] ?? reset($name)) : '#'.$product, 'month' => $month, 'n' => $n];
        }
        usort($out, static fn (array $a, array $b): int => [$b['n'], $a['month']] <=> [$a['n'], $b['month']]);

        return [
            'count' => $rows->count(),
            'since' => $since === null ? null : CarbonImmutable::parse((string) $since, 'UTC')->setTimezone($window->timezone)->toDateString(),
            'by_product_month' => $out,
        ];
    }

    // ─── El mapa de calor y los desgloses ────────────────────────────────────────────────────────

    /**
     * Día de la semana (1 = lunes) × hora de inicio → plazas presentes y aforo, en las zonas de entradas.
     *
     * @param  list<array<string, mixed>>  $points
     * @return array<int, array<int, array{seats: int, capacity: int}>>
     */
    private function heatmap(array $points): array
    {
        $out = [];
        foreach ($points as $p) {
            if (! $p['entry_zone'] || $p['closed']) {
                continue;
            }
            $weekday = (int) CarbonImmutable::parse($p['date'])->isoFormat('E');
            $hour = (int) substr($p['start'], 0, 2);
            $out[$weekday][$hour] ??= ['seats' => 0, 'capacity' => 0];
            $out[$weekday][$hour]['seats'] += min($p['seats'], $p['capacity']);
            $out[$weekday][$hour]['capacity'] += $p['capacity'];
        }
        ksort($out);
        foreach ($out as &$hours) {
            ksort($hours);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array<int, array{seats: int, capacity: int, full: int, points: int}>
     */
    private function byHour(array $points): array
    {
        $out = [];
        foreach ($points as $p) {
            if (! $p['entry_zone'] || $p['closed']) {
                continue;
            }
            $hour = (int) substr($p['start'], 0, 2);
            $out[$hour] ??= ['seats' => 0, 'capacity' => 0, 'full' => 0, 'points' => 0];
            $out[$hour]['seats'] += min($p['seats'], $p['capacity']);
            $out[$hour]['capacity'] += $p['capacity'];
            $out[$hour]['points']++;
            if ($p['online_capacity'] > 0 && $p['seats'] >= $p['online_capacity']) {
                $out[$hour]['full']++;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @param  array<int, string>  $names
     * @return list<array{zone: string, entry: bool, pack: bool, seats: int, capacity: int, parties: int, cap: int}>
     */
    private function byZone(array $points, array $names): array
    {
        $out = [];
        foreach ($points as $p) {
            if ($p['closed']) {
                continue;
            }
            $z = $p['zone_id'];
            $out[$z] ??= ['zone' => $names[$z] ?? '#'.$z, 'entry' => $p['entry_zone'], 'pack' => $p['pack_zone'], 'seats' => 0, 'capacity' => 0, 'parties' => 0, 'cap' => 0];
            $out[$z]['seats'] += min($p['seats'], $p['capacity']);
            $out[$z]['capacity'] += $p['capacity'];
            $out[$z]['parties'] += $p['max_parties'] > 0 ? min($p['parties'], $p['max_parties']) : $p['parties'];
            $out[$z]['cap'] += $p['max_parties'];
        }
        ksort($out);

        return array_values($out);
    }

    /**
     * Las plazas vendidas y lo vendido por producto, en las visitas del periodo (líneas principales).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{product: string, seats: int, revenue_cents: int, lines: int}>
     */
    private function byProduct(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $id = $line['product_id'];
            $out[$id] ??= ['product' => $line['product'], 'seats' => 0, 'revenue_cents' => 0, 'lines' => 0];
            $out[$id]['seats'] += $line['seats'];
            $out[$id]['revenue_cents'] += $line['charged_cents'];
            $out[$id]['lines']++;
        }
        // `usort` reindexa: sale ya como lista.
        usort($out, static fn (array $a, array $b): int => [$b['seats'], $a['product']] <=> [$a['seats'], $b['product']]);

        return $out;
    }

    /**
     * Las cifras de las tarjetas para el periodo de comparación.
     *
     * @return array{seats: int, capacity: int, full: int, revenue_cents: int, seat_minutes: int, lines: int, present: int, cap: int, visitors: int, visitor_lines: int, visitors_sq: int, missing: int}
     */
    private function totalsOnly(Window $window, OccupancyReader $reader): array
    {
        $points = $this->within($reader->points($window->dateFrom(), $window->dateTo()), $window);
        $lines = $this->within($reader->paidLines($window->dateFrom(), $window->dateTo()), $window);
        $entries = $this->entries($points, $lines, $window->timezone);
        $parties = $this->parties($points);
        $visitors = $this->visitors($lines);

        return [
            'seats' => $entries['seats'], 'capacity' => $entries['capacity'], 'full' => $entries['full'],
            'revenue_cents' => $entries['revenue_cents'], 'seat_minutes' => $entries['seat_minutes'], 'lines' => $entries['lines'],
            'present' => $parties['present'], 'cap' => $parties['cap'],
            'visitors' => $visitors['seats'], 'visitor_lines' => $visitors['lines'], 'visitors_sq' => $visitors['seats_sq'],
            'missing' => (int) DB::table('analytics_events')->where('name', 'availability_missing')
                ->where('received_at', '>=', $window->utcFrom()->format('Y-m-d H:i:s'))
                ->where('received_at', '<', $window->utcTo()->format('Y-m-d H:i:s'))
                ->count(),
        ];
    }

    /** @return array<int, string> */
    private function zoneNames(): array
    {
        $locale = app()->getLocale();

        return DB::table('zones')->pluck('name', 'id')->map(static function (mixed $name) use ($locale): string {
            $decoded = json_decode((string) $name, true);

            return is_array($decoded) ? (string) ($decoded[$locale] ?? $decoded['es'] ?? reset($decoded)) : (string) $name;
        })->all();
    }

    /**
     * La mediana de una lista ORDENADA (la de abajo de las dos centrales si son pares: un día entero); `null` sin datos.
     *
     * @param  list<int>  $sorted
     */
    public static function median(array $sorted): ?int
    {
        $n = count($sorted);

        return $n === 0 ? null : $sorted[intdiv($n - 1, 2)];
    }
}
