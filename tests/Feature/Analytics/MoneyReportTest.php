<?php

namespace Tests\Feature\Analytics;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Enums\ReportPeriod;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Analytics\MoneyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **El informe del dinero, contra hechos sembrados** (`specs/analitica.md` §4.5 y §6, T2a; `#735`).
 *
 * El parque está en `Europe/Madrid` y el reloj fijado en el miércoles 2026-06-10 a las 09:00 UTC (11:00 en el
 * parque). El fixture es UN mes de junio con todo lo que el informe distingue: un cobro a las 00:30 de Madrid
 * del 1 de junio (22:30 UTC del 31 de mayo, el caso de la medianoche), otro a las 00:30 del 1 de julio (que
 * NO es junio), una señal con la visita ya pasada y otra por venir, una devolución, un pedido del panel en
 * efectivo, una línea cancelada, una línea de crédito, una gestión posterior, un pedido de mayo (el periodo
 * anterior), un caducado, un cancelado, un cobro rechazado y una incidencia.
 */
class MoneyReportTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump;

    private TicketType $pack;

    private User $ana;

    private User $bea;

    private User $carl;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'Europe/Madrid', 'group' => 'general']);
        Setting::flushMemo();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00')); // miércoles, 11:00 en Madrid

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jump = $this->type('Jump 1h', TicketType::TYPE_ENTRY);
        $this->pack = $this->type('Pack cumple', TicketType::TYPE_ENTRY);
        $this->ana = User::factory()->create();
        $this->bea = User::factory()->create();
        $this->carl = User::factory()->create();
    }

    // ─── El fixture de junio ────────────────────────────────────────────────────────────────────

    private function seedJune(): void
    {
        // Mayo (el periodo anterior): Ana compró una vez.
        $o0 = $this->order($this->ana, 2000, '2026-05-20 10:00:00', 'web');
        $this->payment($o0, 2000, '2026-05-20 10:00:00');
        $this->line($o0, $this->jump, 2, 1000, '2026-05-25');

        // El caso de la MEDIANOCHE: cobrado a las 22:30 UTC del 31 de mayo = 00:30 del 1 de junio en Madrid.
        // Anterior a la medición (sin canal).
        $o7 = $this->order($this->ana, 4000, '2026-05-31 22:30:00', null);
        $this->payment($o7, 4000, '2026-05-31 22:30:00');
        $this->line($o7, $this->jump, 4, 1000, '2026-06-15');

        // Ana repite en junio; una línea cancelada que no cuenta y una gestión posterior de +5 €.
        $o1 = $this->order($this->ana, 3000, '2026-06-09 12:00:00', 'web');
        $this->payment($o1, 3000, '2026-06-09 12:00:00');
        $live = $this->line($o1, $this->jump, 3, 1000, '2026-06-20');
        $this->line($o1, $this->jump, 1, 1000, '2026-06-20', ['cancelled_at' => Carbon::parse('2026-06-09 13:00:00')]);
        OrderAdjustment::create(['order_id' => $o1->id, 'order_item_id' => $live->id, 'type' => OrderAdjustment::TYPE_EDIT, 'amount_cents' => 500, 'currency' => 'EUR', 'applied_by' => $this->ana->id, 'created_at' => '2026-06-09 12:30:00', 'updated_at' => '2026-06-09 12:30:00']);

        // Bea, nueva: un pack con SEÑAL cuya visita ya pasó (liquidada en el parque), una línea de crédito y
        // una devolución de 5 € al día siguiente.
        $o2 = $this->order($this->bea, 5000, '2026-06-05 10:00:00', 'web');
        $p2 = $this->payment($o2, 2000, '2026-06-05 10:00:00');
        $packLine = $this->line($o2, $this->pack, 1, 5000, '2026-06-08');
        $this->depositSplit($packLine, 3000);
        $this->line($o2, $this->pack, 1, 300, '2026-06-08', ['is_credit' => true]);
        $this->refund($p2, 500, '2026-06-06 09:00:00');

        // Carl, nuevo, por el PANEL y en efectivo, con señal pendiente (la visita es el 30).
        $o3 = $this->order($this->carl, 1500, '2026-06-07 16:00:00', 'panel');
        $this->payment($o3, 800, '2026-06-07 16:00:00', 'cash');
        $this->depositSplit($this->line($o3, $this->jump, 1, 1500, '2026-06-30'), 700);

        // Las 00:30 de Madrid del 1 de JULIO: fuera de junio.
        $o8 = $this->order($this->ana, 700, '2026-06-30 22:30:00', 'web');
        $this->payment($o8, 700, '2026-06-30 22:30:00');
        $this->line($o8, $this->jump, 1, 700, '2026-07-05');

        // Lo perdido: un caducado, un cancelado, un cobro rechazado por el banco y una incidencia.
        $this->order($this->bea, 900, null, 'web', Order::STATUS_EXPIRED);
        $this->order($this->carl, 1200, null, 'web', Order::STATUS_CANCELLED);
        $pending = $this->order($this->bea, 1000, null, 'web', Order::STATUS_PENDING);
        $this->payment($pending, 1000, null, 'redsys', Payment::STATUS_FAILED);
        AuditLogger::logSystem(AuditLog::ACTION_OVERBOOKED_CAPTURE, null, ['order' => 'JW-X']);
    }

    // ─── Los totales y las series ───────────────────────────────────────────────────────────────

    public function test_june_totals_come_from_the_same_rows_as_the_ledger(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame(['from' => '2026-06-01', 'to' => '2026-06-30', 'days' => 30, 'granularity' => 'day'], $r['window']);

        // Cobrado = los cobros con éxito (I2); devuelto = los reembolsos con éxito (I4); vendido = lo facturado (I1).
        $this->assertSame(9800, $r['totals']['collected'], 'O7 4000 + O1 3000 + O2 2000 (señal) + O3 800 (efectivo)');
        $this->assertSame(4, $r['totals']['payments']);
        $this->assertSame(500, $r['totals']['refunded']);
        $this->assertSame(9300, $r['totals']['net']);
        $this->assertSame(13500, $r['totals']['sold'], 'O7 4000 + O1 3000 + O2 5000 + O3 1500; O8 es julio y O0 es mayo');
        $this->assertSame(4, $r['totals']['orders']);
        $this->assertSame(3375, $r['totals']['avg_order']);
        $this->assertSame(2450, $r['totals']['avg_collected']);
        $this->assertSame(500, $r['totals']['adjustments']);
    }

    public function test_the_previous_period_is_may(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame(['collected' => 2000, 'payments' => 1, 'refunded' => 0, 'net' => 2000, 'sold' => 2000, 'orders' => 1], $r['previous']);
    }

    /** El cobro de las 22:30 UTC del 31 de mayo cae en el DÍA 1 de junio del parque, y el del 30 a las 22:30 UTC ya es julio. */
    public function test_the_series_places_a_late_evening_payment_in_the_park_day(): void
    {
        $this->seedJune();

        $series = collect((new MoneyReport)->compute(ReportPeriod::ThisMonth->window())['series'])->keyBy('key');

        $this->assertCount(30, $series);
        $this->assertSame(['key' => '2026-06-01', 'collected' => 4000, 'refunded' => 0, 'sold' => 4000, 'orders' => 1], $series['2026-06-01']);
        $this->assertSame(['key' => '2026-06-05', 'collected' => 2000, 'refunded' => 0, 'sold' => 5000, 'orders' => 1], $series['2026-06-05']);
        $this->assertSame(['key' => '2026-06-06', 'collected' => 0, 'refunded' => 500, 'sold' => 0, 'orders' => 0], $series['2026-06-06']);
        $this->assertSame(['key' => '2026-06-07', 'collected' => 800, 'refunded' => 0, 'sold' => 1500, 'orders' => 1], $series['2026-06-07']);
        $this->assertSame(['key' => '2026-06-09', 'collected' => 3000, 'refunded' => 0, 'sold' => 3000, 'orders' => 1], $series['2026-06-09']);
        $this->assertSame(0, $series['2026-06-30']['collected'], 'las 00:30 del 1 de julio no son el 30 de junio');
        $this->assertSame(0, $series['2026-06-02']['sold']);

        // El control: en UTC ese cobro es del 31 de mayo y junio pierde 4.000.
        Setting::updateOrCreate(['key' => 'display_timezone'], ['value' => 'UTC', 'group' => 'general']);
        Setting::flushMemo();
        $utc = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());
        $this->assertSame(9800 + 700 - 4000, $utc['totals']['collected'], 'en UTC entra O8 (30 de junio a las 22:30) y sale O7 (31 de mayo a las 22:30)');
        $this->assertSame(2000 + 4000, $utc['previous']['collected'], 'y O7 pasa a mayo');
    }

    public function test_last_month_only_sees_may(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::LastMonth->window());

        $this->assertSame(2000, $r['totals']['sold']);
        $this->assertSame(1, $r['totals']['orders']);
        $this->assertSame(2000, $r['totals']['collected']);
        $this->assertSame(0, $r['totals']['refunded']);
        $this->assertSame(0, $r['previous']['sold'], 'abril está vacío');
    }

    // ─── La señal, los desgloses y los clientes ─────────────────────────────────────────────────

    public function test_the_deposit_splits_between_pending_and_settled_by_visit_date(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame(['orders' => 2, 'pending' => 700, 'settled' => 3000], $r['deposit']);
    }

    public function test_the_breakdown_by_channel_method_and_product(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([
            ['channel' => 'web', 'orders' => 2, 'sold' => 8000, 'collected' => 5000],
            ['channel' => MoneyReport::CHANNEL_BEFORE_MEASUREMENT, 'orders' => 1, 'sold' => 4000, 'collected' => 4000],
            ['channel' => 'panel', 'orders' => 1, 'sold' => 1500, 'collected' => 800],
        ], $r['by_channel']);

        $this->assertSame([
            ['method' => 'redsys', 'payments' => 3, 'collected' => 9000],
            ['method' => 'cash', 'payments' => 1, 'collected' => 800],
        ], $r['by_method']);

        // La línea cancelada no cuenta; la de crédito RESTA y no suma unidades; O8 es de julio.
        $this->assertSame([
            ['product' => 'Jump 1h', 'units' => 8, 'value' => 8500],
            ['product' => 'Pack cumple', 'units' => 1, 'value' => 4700],
        ], $r['by_product']);
    }

    public function test_customers_new_versus_returning_and_the_two_averages(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([
            'buyers' => 3,
            'new' => 2,          // Bea y Carl: su primer pedido cobrado es de junio
            'returning' => 1,    // Ana ya compró en mayo
            'avg_per_customer' => 4500,   // 13.500 entre tres
            'lifetime_avg' => 5400,       // Ana 9.700 + Bea 5.000 + Carl 1.500, entre tres
        ], $r['customers']);
    }

    public function test_lost_orders_declined_payments_and_incidents(): void
    {
        $this->seedJune();

        $r = (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());

        $this->assertSame([
            'expired' => ['count' => 1, 'value' => 900],
            'cancelled' => ['count' => 1, 'value' => 1200],
            'declined' => 1,
            'incidents' => 1,
        ], $r['lost']);
    }

    public function test_an_empty_period_is_all_zeros_and_no_rows(): void
    {
        $r = (new MoneyReport)->compute(ReportPeriod::Yesterday->window());

        $this->assertSame(0, $r['totals']['collected']);
        $this->assertSame(0, $r['totals']['avg_order']);
        $this->assertSame([], $r['by_product']);
        $this->assertSame(['buyers' => 0, 'new' => 0, 'returning' => 0, 'avg_per_customer' => 0, 'lifetime_avg' => 0], $r['customers']);
        $this->assertCount(1, $r['series']);
    }

    // ─── El presupuesto y la caché ──────────────────────────────────────────────────────────────

    /** Diecisiete consultas por periodo, sean cuatro pedidos o cuatro mil: ninguna crece con las filas. */
    public function test_the_query_budget_does_not_grow_with_the_rows(): void
    {
        $this->seedJune();

        // El memo de `Setting` (la zona del parque) se calienta antes de contar: su única consulta no es del
        // informe y salía en la primera pasada y no en la segunda, que es justo lo que este caso no mide.
        ReportPeriod::ThisMonth->window();

        DB::flushQueryLog();
        DB::enableQueryLog();
        (new MoneyReport)->compute(ReportPeriod::ThisMonth->window());
        $withRows = count(DB::getQueryLog());

        DB::flushQueryLog();
        (new MoneyReport)->compute(ReportPeriod::Yesterday->window());
        $empty = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, $withRows);
        $this->assertSame($empty, $withRows, 'el número de consultas no depende de las filas');
    }

    public function test_the_report_is_cached_for_five_minutes_per_period(): void
    {
        $this->seedJune();
        Cache::flush();

        $window = ReportPeriod::ThisMonth->window();
        $first = MoneyReport::for($window);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $second = MoneyReport::for($window);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $second);
        $this->assertSame(0, $queries, 'la segunda lectura sale de la caché');
        $this->assertTrue(Cache::has(MoneyReport::cacheKey($window, $window->previous())));
    }

    /** «Frente al mismo periodo del año pasado»: junio de 2026 contra junio de 2025, que aquí está vacío. */
    public function test_the_comparison_can_be_the_same_period_a_year_ago(): void
    {
        $this->seedJune();
        $window = ReportPeriod::ThisMonth->window();

        $previous = (new MoneyReport)->compute($window, $window->previous());
        $yearAgo = (new MoneyReport)->compute($window, $window->yearAgo());

        $this->assertSame(2000, $previous['previous']['sold'], 'mayo');
        $this->assertSame(0, $yearAgo['previous']['sold'], 'junio de 2025, vacío');
        $this->assertSame($previous['totals'], $yearAgo['totals'], 'el periodo es el mismo; solo cambia con qué se compara');
        $this->assertNotSame(MoneyReport::cacheKey($window, $window->previous()), MoneyReport::cacheKey($window, $window->yearAgo()));
    }

    // ─── Los ayudantes del fixture ──────────────────────────────────────────────────────────────

    private function type(string $name, string $type): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => $type, 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => ++$this->counter,
        ]);
    }

    private function order(User $customer, int $total, ?string $paidAtUtc, ?string $channel, string $status = Order::STATUS_PAID): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'code' => 'JW-MR'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT),
            'status' => $status, 'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => $paidAtUtc,
        ]);

        // El observador sella el canal al nacer (`system`, sin petición): aquí se fija el que el caso pide,
        // sin volver a sellar — la misma forma que la migración de historia deja los pedidos viejos.
        $order->forceFill(['attribution_channel' => $channel])->saveQuietly();

        return $order;
    }

    private function payment(Order $order, int $amount, ?string $paidAtUtc, string $provider = 'redsys', string $status = Payment::STATUS_PAID): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $amount, 'currency' => 'EUR', 'provider' => $provider, 'status' => $status,
            'paid_at' => $paidAtUtc,
            'gateway_order' => str_pad((string) (700000 + ++$this->counter), 10, '0', STR_PAD_LEFT),
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function line(Order $order, TicketType $type, int $qty, int $unit, string $slotDate, array $extra = []): OrderItem
    {
        $h = str_pad((string) (++$this->counter % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $slotDate, 'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        return OrderItem::create(array_merge([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ], $extra));
    }

    private function depositSplit(OrderItem $line, int $cents): void
    {
        OrderAdjustment::create([
            'order_id' => $line->order_id, 'order_item_id' => $line->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_SPLIT, 'amount_cents' => $cents, 'currency' => 'EUR',
            'applied_by' => $this->ana->id,
        ]);
    }

    private function refund(Payment $payment, int $cents, string $processedAtUtc): void
    {
        PaymentRefund::create([
            'payment_id' => $payment->id, 'amount_cents' => $cents, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED, 'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order, 'requested_by' => $this->ana->id,
            'requested_at' => $processedAtUtc, 'processed_at' => $processedAtUtc,
        ]);
    }
}
