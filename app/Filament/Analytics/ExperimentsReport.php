<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Analytics\Reports\SqlJson;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * **La conversión por variante de cada experimento** (`docs/specs/analitica.md` §4.4, T5b).
 *
 * La EXPOSICIÓN es `experiment_exposed` en el libro (el motor lo cuenta una vez por carga y solo con variante
 * asignada): por experimento y variante, los VISITANTES distintos expuestos en el periodo, de sesiones limpias
 * (ni bot ni interna). La CONVERSIÓN es la verdad de las compras, no un hecho del cliente: un pedido cobrado por
 * la web o la app cuyo sello lleva ese mismo `visitor_id` y que se pagó DESPUÉS de la primera exposición, dentro
 * del periodo.
 *
 * ⚠️ **El sello solo lleva el visitante con la categoría «análisis» o «anuncios»** (`AttributionContext::seal()`):
 * quien no consintió compra igual, pero su compra no se puede atar a la variante que vio. La cifra que sale es la
 * conversión ENTRE QUIENES CONSINTIERON, y como el consentimiento no depende de la variante, la comparación entre
 * variantes sigue valiendo; el nivel absoluto, no. El widget lo dice.
 *
 * **Contaminados**: un visitante expuesto a dos variantes del mismo experimento (una persona con dos
 * dispositivos, o un experimento rebarajado) no cuenta en ninguna; se enseñan cuántos son, y cuántas CUENTAS
 * (`user_id`, solo en el régimen identificado) vieron dos variantes.
 *
 * **El intervalo** es Wilson al 95 %: con veinte expuestos y dos compras, un «10 %» a pelo engaña; el intervalo
 * (2,8 %–30,1 %) dice lo que de verdad se sabe. Dos variantes cuyos intervalos se solapan no se distinguen aún.
 */
final class ExperimentsReport
{
    public const CACHE_SECONDS = 300;

    /** z de la normal para el 95 % (dos colas). */
    public const Z = 1.96;

    /** @var list<string> */
    private const COLLECTED_STATUSES = [Order::STATUS_PAID, Order::STATUS_REFUNDED];

    /** @var list<string> */
    private const WEB_CHANNELS = [AttributionContext::CHANNEL_WEB, AttributionContext::CHANNEL_APP];

    /**
     * @return array{experiments: list<array{key: string, name: string, variants: list<array{variant: string, exposed: int, converted: int, rate_bp: int, low_bp: int, high_bp: int}>, contaminated_visitors: int, contaminated_users: int}>}
     */
    public static function for(Window $window): array
    {
        return Cache::remember(self::cacheKey($window), self::CACHE_SECONDS, fn (): array => (new self)->compute($window));
    }

    public static function cacheKey(Window $window): string
    {
        return 'analytics:experiments:report:v1:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo();
    }

    /** @return array{experiments: list<array<string, mixed>>} */
    public function compute(Window $window): array
    {
        $exposures = $this->exposures($window);

        if ($exposures->isEmpty()) {
            return ['experiments' => []];
        }

        $paidAt = $this->firstPaymentByVisitor($window);
        $contaminatedUsers = $this->contaminatedUsers($window);
        $names = Experiment::query()->whereIn('key', $exposures->pluck('k')->unique()->all())->pluck('name', 'key');

        $experiments = [];

        foreach ($exposures->groupBy('k') as $key => $rows) {
            // Por visitante: qué variantes vio y cuándo vio la primera vez cada una.
            $byVisitor = [];
            foreach ($rows as $row) {
                $byVisitor[(string) $row->visitor_id][(string) $row->v] = CarbonImmutable::parse((string) $row->first_at, 'UTC');
            }

            $variants = [];
            $contaminated = 0;
            foreach ($byVisitor as $visitor => $seen) {
                if (count($seen) > 1) {
                    $contaminated++;

                    continue;
                }
                $variant = (string) array_key_first($seen);
                $variants[$variant] ??= ['exposed' => 0, 'converted' => 0];
                $variants[$variant]['exposed']++;
                $paid = $paidAt[$visitor] ?? null;
                if ($paid !== null && $paid->gte($seen[$variant])) {
                    $variants[$variant]['converted']++;
                }
            }

            ksort($variants);
            $list = [];
            foreach ($variants as $variant => $counts) {
                $list[] = ['variant' => (string) $variant] + $counts + self::wilson($counts['converted'], $counts['exposed']);
            }

            $experiments[] = [
                'key' => (string) $key,
                'name' => (string) ($names[$key] ?? $key),
                'variants' => $list,
                'contaminated_visitors' => $contaminated,
                'contaminated_users' => (int) ($contaminatedUsers[$key] ?? 0),
            ];
        }

        usort($experiments, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        return ['experiments' => $experiments];
    }

    /**
     * La tasa y su intervalo de Wilson al 95 %, en puntos básicos (10000 = 100 %). Sin expuestos, todo a cero.
     *
     * @return array{rate_bp: int, low_bp: int, high_bp: int}
     */
    public static function wilson(int $converted, int $exposed): array
    {
        if ($exposed <= 0) {
            return ['rate_bp' => 0, 'low_bp' => 0, 'high_bp' => 0];
        }

        $n = $exposed;
        $p = min(1.0, max(0.0, $converted / $n));
        $z2 = self::Z * self::Z;
        $denominator = 1 + $z2 / $n;
        $centre = ($p + $z2 / (2 * $n)) / $denominator;
        $half = (self::Z * sqrt($p * (1 - $p) / $n + $z2 / (4 * $n * $n))) / $denominator;

        return [
            'rate_bp' => (int) round($p * 10000),
            'low_bp' => (int) round(max(0.0, $centre - $half) * 10000),
            'high_bp' => (int) round(min(1.0, $centre + $half) * 10000),
        ];
    }

    /**
     * Las exposiciones del periodo, de sesiones limpias: por experimento, variante y visitante, la primera vez
     * (por `received_at`, la verdad temporal del servidor).
     *
     * @return Collection<int, object{k: string, v: string, visitor_id: string, first_at: string}>
     */
    private function exposures(Window $window): Collection
    {
        $key = SqlJson::string('e.props', '$.key');
        $variant = SqlJson::string('e.props', '$.variant');

        /** @var Collection<int, object{k: string, v: string, visitor_id: string, first_at: string}> $rows */
        $rows = DB::table('analytics_events as e')
            ->join('analytics_sessions as s', 's.id', '=', 'e.session_id')
            ->where('e.name', 'experiment_exposed')
            ->where('e.received_at', '>=', $window->utcFrom())
            ->where('e.received_at', '<', $window->utcTo())
            ->where('s.is_bot', false)
            ->where('s.is_internal', false)
            ->whereNotNull('e.visitor_id')
            ->whereRaw("{$key} IS NOT NULL")
            ->whereRaw("{$variant} IS NOT NULL")
            ->selectRaw("{$key} AS k, {$variant} AS v, e.visitor_id, MIN(e.received_at) AS first_at")
            ->groupByRaw("{$key}, {$variant}, e.visitor_id")
            ->get();

        return $rows;
    }

    /**
     * Las CUENTAS que vieron dos variantes del mismo experimento en el periodo (`user_id` solo viaja en el régimen
     * identificado), por clave.
     *
     * @return array<string, int>
     */
    private function contaminatedUsers(Window $window): array
    {
        $key = SqlJson::string('e.props', '$.key');
        $variant = SqlJson::string('e.props', '$.variant');

        $rows = DB::table('analytics_events as e')
            ->where('e.name', 'experiment_exposed')
            ->where('e.received_at', '>=', $window->utcFrom())
            ->where('e.received_at', '<', $window->utcTo())
            ->whereNotNull('e.user_id')
            ->whereRaw("{$key} IS NOT NULL")
            ->selectRaw("{$key} AS k, e.user_id, COUNT(DISTINCT {$variant}) AS nv")
            ->groupByRaw("{$key}, e.user_id")
            ->havingRaw("COUNT(DISTINCT {$variant}) >= 2")
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->k] = ($out[(string) $row->k] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * El primer cobro de cada visitante en el periodo, desde los pedidos cobrados por la web o la app cuyo sello
     * lleva `visitor_id`.
     *
     * @return array<string, CarbonImmutable>
     */
    private function firstPaymentByVisitor(Window $window): array
    {
        $visitor = SqlJson::string('attribution', '$.visitor_id');

        $rows = DB::table('orders')
            ->whereIn('status', self::COLLECTED_STATUSES)
            ->whereIn('attribution_channel', self::WEB_CHANNELS)
            ->where('paid_at', '>=', $window->utcFrom())
            ->where('paid_at', '<', $window->utcTo())
            ->whereRaw("{$visitor} IS NOT NULL")
            ->selectRaw("{$visitor} AS visitor_id, MIN(paid_at) AS paid_at")
            ->groupByRaw($visitor)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->visitor_id] = CarbonImmutable::parse((string) $row->paid_at, 'UTC');
        }

        return $out;
    }
}
