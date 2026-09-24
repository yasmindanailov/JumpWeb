<?php

namespace App\Filament\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Enums\Comparison;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\Analytics\Reports\SqlTime;
use App\Domain\Platform\Services\Analytics\Reports\Window;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\Translated;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * **EL INFORME DEL DINERO del cuadro de mando** (`docs/specs/analitica.md` §4.5, T2a; `DECISIONES #735`).
 *
 * Todo en céntimos y de las **mismas filas que el libro de cada pedido** (`PAY-16`/`PAY-17`), nunca del
 * catálogo: lo cobrado son los `payments` con éxito (I2), lo devuelto los `payment_refunds` con éxito (I4),
 * lo vendido es `orders.total` —lo facturado al nacer, I1— y la señal son las filas `deposit_split`. Pero el
 * cuadro **no compone ningún `OrderBook`**: son agregados SQL, diecisiete consultas por periodo con
 * presupuesto medido (`MoneyReportTest`), y cinco minutos de caché por informe y periodo.
 *
 * ⚠️ **Vive en la capa de entrega y no en un módulo, a propósito**: cruza Booking (pedidos, líneas, ajustes),
 * Payments (cobros, devoluciones), Identity (clientes) y Platform (auditoría), y `ModuleBoundariesTest` solo
 * deja a la capa de entrega componer varios contextos. Un módulo que leyera las tablas de otro por su nombre
 * sería la dependencia invisible que esa guarda existe para impedir.
 *
 * ⚠️ **El tiempo**: cada corte se hace por HORA UTC en SQL ({@see SqlTime}) y por día del parque en PHP
 * ({@see Window::bucketKey()}). Lo que se corta por fecha de VISITA (`slots.date`) no se convierte.
 *
 * ⚠️ Solo agregados: aquí no entra ningún nombre de cliente. La persona es la ficha 360 (T4).
 */
final class MoneyReport
{
    public const CACHE_SECONDS = 300;

    public const TOP_PRODUCTS = 10;

    /** El canal de un pedido anterior a la medición (`attribution_channel IS NULL`): nunca «directo». */
    public const CHANNEL_BEFORE_MEASUREMENT = 'before';

    /**
     * Los estados con los que un pedido CUENTA como cobrado: un reembolso total lo deja `refunded`, y sigue
     * siendo una venta con su cobro y su devolución, cada uno en su columna.
     *
     * @var list<string>
     */
    private const COLLECTED_STATUSES = [Order::STATUS_PAID, Order::STATUS_REFUNDED];

    /** @return array<string, mixed> */
    public static function for(Window $window, Comparison $comparison = Comparison::Previous): array
    {
        $baseline = $comparison->baseline($window);

        return Cache::remember(self::cacheKey($window, $baseline), self::CACHE_SECONDS, fn (): array => (new self)->compute($window, $baseline));
    }

    /** La clave lleva la zona, las dos ventanas y el idioma: los nombres de producto salen traducidos y el día del parque depende de la zona. */
    public static function cacheKey(Window $window, Window $baseline): string
    {
        return 'analytics:money:v2:'.$window->timezone.':'.$window->dateFrom().':'.$window->dateTo().':'.$baseline->dateFrom().':'.$baseline->dateTo().':'.app()->getLocale();
    }

    /** @param  Window|null  $baseline  con qué se compara; sin ella, el periodo anterior */
    public function compute(Window $window, ?Window $baseline = null): array
    {
        $baseline ??= $window->previous();
        $collected = $this->fold($window, $this->paymentsByBucket($window));
        $refunded = $this->fold($window, $this->refundsByBucket($window));
        $sold = $this->fold($window, $this->ordersByBucket($window));

        $totals = [
            'collected' => self::sumOf($collected, 'amount'),
            'payments' => self::sumOf($collected, 'count'),
            'refunded' => self::sumOf($refunded, 'amount'),
            'sold' => self::sumOf($sold, 'amount'),
            'orders' => self::sumOf($sold, 'count'),
            'adjustments' => $this->adjustments($window),
        ];
        $totals['net'] = $totals['collected'] - $totals['refunded'];
        $totals['avg_order'] = $totals['orders'] > 0 ? intdiv($totals['sold'], $totals['orders']) : 0;
        $totals['avg_collected'] = $totals['payments'] > 0 ? intdiv($totals['collected'], $totals['payments']) : 0;

        return [
            'window' => [
                'from' => $window->dateFrom(),
                'to' => $window->dateTo(),
                'days' => $window->days(),
                'granularity' => $window->granularity(),
            ],
            'totals' => $totals,
            'previous' => $this->totalsOnly($baseline),
            'series' => $this->series($window, $collected, $refunded, $sold),
            'deposit' => $this->deposit($window),
            'by_channel' => $this->byChannel($window),
            'by_method' => $this->byMethod($window),
            'by_product' => $this->byProduct($window),
            'customers' => $this->customers($window),
            'lost' => $this->lost($window),
        ];
    }

    // ─── Las series por cubo ─────────────────────────────────────────────────────────────────────

    /** Los cobros con éxito de pedidos, por hora UTC de `paid_at`. @return Collection<int, stdClass> */
    private function paymentsByBucket(Window $window): Collection
    {
        return $this->bucketed(
            $this->paidPayments($window)->selectRaw('SUM(amount) AS amount, COUNT(*) AS n'),
            'paid_at',
        );
    }

    /** @return Collection<int, stdClass> */
    private function refundsByBucket(Window $window): Collection
    {
        return $this->bucketed(
            $this->succeededRefunds($window)->selectRaw('SUM(amount_cents) AS amount, COUNT(*) AS n'),
            'processed_at',
        );
    }

    /** @return Collection<int, stdClass> */
    private function ordersByBucket(Window $window): Collection
    {
        return $this->bucketed(
            $this->collectedOrders($window)->selectRaw('SUM(total) AS amount, COUNT(*) AS n'),
            'paid_at',
        );
    }

    /** @return Collection<int, stdClass> */
    private function bucketed(Builder $query, string $column): Collection
    {
        $bucket = SqlTime::hourBucket($column);

        return $query->addSelect(DB::raw("{$bucket} AS bucket"))->groupByRaw($bucket)->get();
    }

    /**
     * Cada cubo de hora UTC cae en su día (o semana) del PARQUE, y los cubos del mismo día se suman.
     *
     * @param  Collection<int, stdClass>  $rows
     * @return array<string, array{amount: int, count: int}>
     */
    private function fold(Window $window, Collection $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = $window->bucketKey(SqlTime::bucketStart((string) $row->bucket));
            $out[$key] ??= ['amount' => 0, 'count' => 0];
            $out[$key]['amount'] += (int) $row->amount;
            $out[$key]['count'] += (int) $row->n;
        }

        return $out;
    }

    /**
     * @param  array<string, array{amount: int, count: int}>  $collected
     * @param  array<string, array{amount: int, count: int}>  $refunded
     * @param  array<string, array{amount: int, count: int}>  $sold
     * @return list<array{key: string, collected: int, refunded: int, sold: int, orders: int}>
     */
    private function series(Window $window, array $collected, array $refunded, array $sold): array
    {
        $series = [];
        foreach ($window->bucketKeys() as $key) {
            $series[] = [
                'key' => $key,
                'collected' => $collected[$key]['amount'] ?? 0,
                'refunded' => $refunded[$key]['amount'] ?? 0,
                'sold' => $sold[$key]['amount'] ?? 0,
                'orders' => $sold[$key]['count'] ?? 0,
            ];
        }

        return $series;
    }

    /** @param  array<string, array{amount: int, count: int}>  $folded */
    private static function sumOf(array $folded, string $field): int
    {
        return array_sum(array_map(static fn (array $b): int => $b[$field], $folded));
    }

    // ─── Los totales, sin cubos (el periodo anterior) ────────────────────────────────────────────

    /** @return array<string, int> */
    private function totalsOnly(Window $window): array
    {
        $payments = $this->paidPayments($window)->selectRaw('COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS n')->first();
        $refunds = $this->succeededRefunds($window)->selectRaw('COALESCE(SUM(amount_cents), 0) AS amount')->first();
        $orders = $this->collectedOrders($window)->selectRaw('COALESCE(SUM(total), 0) AS amount, COUNT(*) AS n')->first();

        $collected = (int) ($payments->amount ?? 0);
        $refunded = (int) ($refunds->amount ?? 0);

        return [
            'collected' => $collected,
            'payments' => (int) ($payments->n ?? 0),
            'refunded' => $refunded,
            'net' => $collected - $refunded,
            'sold' => (int) ($orders->amount ?? 0),
            'orders' => (int) ($orders->n ?? 0),
        ];
    }

    /** Σ de las gestiones posteriores (`edit` + `mixed`, con signo) escritas en el periodo. */
    private function adjustments(Window $window): int
    {
        return (int) $this->between(DB::table('order_adjustments'), 'created_at', $window)
            ->whereIn('type', OrderAdjustment::VALUE_DELTA_TYPES)
            ->sum('amount_cents');
    }

    // ─── La señal ────────────────────────────────────────────────────────────────────────────────

    /**
     * De lo vendido en el periodo, el reparto de señal de las líneas VIVAS: lo que queda por cobrar en el parque
     * (visita futura) y lo que el libro da por liquidado allí (visita pasada: la inferencia D9, rotulada así).
     * La fecha de visita de un complemento es la de su línea principal.
     *
     * @return array{orders: int, pending: int, settled: int}
     */
    private function deposit(Window $window): array
    {
        $today = DisplayTime::today()->toDateString();

        $row = DB::table('order_adjustments as a')
            ->join('order_items as i', function (JoinClause $join): void {
                $join->on('i.id', '=', 'a.order_item_id')->whereNull('i.cancelled_at');
            })
            ->join('orders as o', 'o.id', '=', 'a.order_id')
            ->leftJoin('slots as s', 's.id', '=', 'i.slot_id')
            ->leftJoin('order_items as p', 'p.id', '=', 'i.parent_item_id')
            ->leftJoin('slots as ps', 'ps.id', '=', 'p.slot_id')
            ->where('a.type', OrderAdjustment::TYPE_DEPOSIT_SPLIT)
            ->where('a.amount_cents', '>', 0)
            ->whereIn('o.status', self::COLLECTED_STATUSES)
            ->where('o.paid_at', '>=', $window->utcFrom())
            ->where('o.paid_at', '<', $window->utcTo())
            ->selectRaw(
                'COUNT(DISTINCT o.id) AS orders, '
                .'COALESCE(SUM(CASE WHEN COALESCE(s.date, ps.date) < ? THEN a.amount_cents ELSE 0 END), 0) AS settled, '
                .'COALESCE(SUM(CASE WHEN COALESCE(s.date, ps.date) < ? THEN 0 ELSE a.amount_cents END), 0) AS pending',
                [$today, $today],
            )
            ->first();

        return [
            'orders' => (int) ($row->orders ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'settled' => (int) ($row->settled ?? 0),
        ];
    }

    // ─── Los desgloses ───────────────────────────────────────────────────────────────────────────

    /**
     * Por canal del sello (`attribution_channel`): pedidos y vendido desde `orders`, cobrado desde `payments`.
     * `NULL` es «anterior a la medición», nunca «directo» (spec §4.1).
     *
     * @return list<array{channel: string, orders: int, sold: int, collected: int}>
     */
    private function byChannel(Window $window): array
    {
        $orders = $this->collectedOrders($window)
            ->selectRaw('attribution_channel AS channel, COUNT(*) AS n, COALESCE(SUM(total), 0) AS sold')
            ->groupBy('attribution_channel')
            ->get();

        $payments = $this->paidPayments($window, 'p')
            ->join('orders as o', 'o.id', '=', 'p.payable_id')
            ->selectRaw('o.attribution_channel AS channel, COALESCE(SUM(p.amount), 0) AS collected')
            ->groupBy('o.attribution_channel')
            ->get();

        $rows = [];
        foreach ($orders as $row) {
            $channel = $row->channel === null ? self::CHANNEL_BEFORE_MEASUREMENT : (string) $row->channel;
            $rows[$channel] = ['channel' => $channel, 'orders' => (int) $row->n, 'sold' => (int) $row->sold, 'collected' => 0];
        }
        foreach ($payments as $row) {
            $channel = $row->channel === null ? self::CHANNEL_BEFORE_MEASUREMENT : (string) $row->channel;
            $rows[$channel] ??= ['channel' => $channel, 'orders' => 0, 'sold' => 0, 'collected' => 0];
            $rows[$channel]['collected'] = (int) $row->collected;
        }

        usort($rows, static fn (array $a, array $b): int => $b['sold'] <=> $a['sold']);

        return $rows;
    }

    /**
     * Por método de cobro: el `provider` del pago (`redsys` la pasarela; `cash` y `datafono` el mostrador).
     *
     * @return list<array{method: string, payments: int, collected: int}>
     */
    private function byMethod(Window $window): array
    {
        return $this->paidPayments($window)
            ->selectRaw('provider AS method, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS collected')
            ->groupBy('provider')
            ->orderByDesc('collected')
            ->get()
            ->map(static fn (object $row): array => [
                'method' => (string) $row->method,
                'payments' => (int) $row->n,
                'collected' => (int) $row->collected,
            ])
            ->values()
            ->all();
    }

    /**
     * Por producto: unidades y valor de las líneas VIVAS de los pedidos cobrados en el periodo, con la misma
     * aritmética que `OrderItem::chargedSubtotalCents()` —unidades de pago = `quantity − free_quantity`, y una
     * línea de crédito RESTA y no cuenta unidades—. Los diez primeros por valor.
     *
     * @return list<array{product: string, units: int, value: int}>
     */
    private function byProduct(Window $window): array
    {
        $paidUnits = 'CASE WHEN i.quantity > i.free_quantity THEN i.quantity - i.free_quantity ELSE 0 END';

        return DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('ticket_types as t', 't.id', '=', 'i.ticket_type_id')
            ->whereNull('i.cancelled_at')
            ->whereIn('o.status', self::COLLECTED_STATUSES)
            ->where('o.paid_at', '>=', $window->utcFrom())
            ->where('o.paid_at', '<', $window->utcTo())
            ->selectRaw(
                't.name AS name, '
                ."COALESCE(SUM(CASE WHEN i.is_credit THEN 0 ELSE {$paidUnits} END), 0) AS units, "
                ."COALESCE(SUM(({$paidUnits}) * i.unit_price * CASE WHEN i.is_credit THEN -1 ELSE 1 END), 0) AS value",
            )
            ->groupBy('i.ticket_type_id', 't.name')
            ->orderByDesc('value')
            ->limit(self::TOP_PRODUCTS)
            ->get()
            ->map(static fn (object $row): array => [
                'product' => self::productName((string) $row->name),
                'units' => (int) $row->units,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }

    /** El nombre del producto en el idioma del panel; el JSON traducible sale tal cual de la tabla. */
    private static function productName(string $json): string
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? (string) Translated::pick($decoded, app()->getLocale()) : $json;
    }

    // ─── Los clientes que compran ────────────────────────────────────────────────────────────────

    /**
     * Compradores distintos del periodo; NUEVOS los que hicieron su primer pedido cobrado dentro de él; el
     * valor medio por cliente en el periodo; y el valor de vida medio de TODOS los clientes con compra.
     *
     * @return array{buyers: int, new: int, returning: int, avg_per_customer: int, lifetime_avg: int}
     */
    private function customers(Window $window): array
    {
        $from = $window->utcFrom()->format('Y-m-d H:i:s');
        $to = $window->utcTo()->format('Y-m-d H:i:s');

        $buyers = $this->collectedOrders($window)->whereNotNull('user_id')->select('user_id');

        $rows = DB::table('orders')
            ->selectRaw('user_id, MIN(paid_at) AS first_paid, SUM(CASE WHEN paid_at >= ? AND paid_at < ? THEN total ELSE 0 END) AS period_sold', [$from, $to])
            ->whereIn('status', self::COLLECTED_STATUSES)
            ->whereIn('user_id', $buyers)
            ->groupBy('user_id')
            ->get();

        $count = $rows->count();
        $new = $rows->filter(fn (object $row): bool => $window->contains(CarbonImmutable::parse((string) $row->first_paid, 'UTC')))->count();
        $periodSold = (int) $rows->sum(static fn (object $row): int => (int) $row->period_sold);

        $lifetime = DB::table('orders')
            ->whereIn('status', self::COLLECTED_STATUSES)
            ->whereNotNull('user_id')
            ->selectRaw('COUNT(DISTINCT user_id) AS customers, COALESCE(SUM(total), 0) AS sold')
            ->first();
        $lifetimeCustomers = (int) ($lifetime->customers ?? 0);

        return [
            'buyers' => $count,
            'new' => $new,
            'returning' => $count - $new,
            'avg_per_customer' => $count > 0 ? intdiv($periodSold, $count) : 0,
            'lifetime_avg' => $lifetimeCustomers > 0 ? intdiv((int) $lifetime->sold, $lifetimeCustomers) : 0,
        ];
    }

    // ─── Lo perdido ──────────────────────────────────────────────────────────────────────────────

    /**
     * Pedidos caducados y cancelados (por fecha de creación, que es la que tienen), cobros rechazados por el
     * banco y las incidencias de cobro de `PAY-05`.
     *
     * @return array{expired: array{count: int, value: int}, cancelled: array{count: int, value: int}, declined: int, incidents: int}
     */
    private function lost(Window $window): array
    {
        $byStatus = $this->between(DB::table('orders'), 'created_at', $window)
            ->whereIn('status', [Order::STATUS_EXPIRED, Order::STATUS_CANCELLED])
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(total), 0) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $declined = $this->between(DB::table('payments'), 'created_at', $window)
            ->where('payable_type', self::orderMorph())
            ->where('status', Payment::STATUS_FAILED)
            ->count();

        $incidents = $this->between(DB::table('audit_logs'), 'created_at', $window)
            ->whereIn('action', [AuditLog::ACTION_DUPLICATE_CAPTURE, AuditLog::ACTION_OVERBOOKED_CAPTURE])
            ->count();

        $of = static fn (string $status): array => [
            'count' => (int) ($byStatus[$status]->n ?? 0),
            'value' => (int) ($byStatus[$status]->total ?? 0),
        ];

        return [
            'expired' => $of(Order::STATUS_EXPIRED),
            'cancelled' => $of(Order::STATUS_CANCELLED),
            'declined' => $declined,
            'incidents' => $incidents,
        ];
    }

    // ─── Las consultas base ──────────────────────────────────────────────────────────────────────

    /** Los cobros con éxito de PEDIDOS, cobrados dentro de la ventana. */
    private function paidPayments(Window $window, ?string $alias = null): Builder
    {
        $table = $alias === null ? 'payments' : "payments as {$alias}";
        $prefix = $alias === null ? '' : "{$alias}.";

        return $this->between(DB::table($table), "{$prefix}paid_at", $window)
            ->where("{$prefix}payable_type", self::orderMorph())
            ->where("{$prefix}status", Payment::STATUS_PAID);
    }

    /** Las devoluciones con éxito procesadas dentro de la ventana. */
    private function succeededRefunds(Window $window): Builder
    {
        return $this->between(DB::table('payment_refunds'), 'processed_at', $window)
            ->where('status', PaymentRefund::STATUS_SUCCEEDED);
    }

    /** Los pedidos COBRADOS dentro de la ventana (por `paid_at`). */
    private function collectedOrders(Window $window): Builder
    {
        return $this->between(DB::table('orders'), 'paid_at', $window)
            ->whereIn('status', self::COLLECTED_STATUSES);
    }

    /** `[from, to)` sobre un instante en UTC. */
    private function between(Builder $query, string $column, Window $window): Builder
    {
        return $query
            ->where($column, '>=', $window->utcFrom())
            ->where($column, '<', $window->utcTo());
    }

    private static function orderMorph(): string
    {
        return (new Order)->getMorphClass();
    }
}
