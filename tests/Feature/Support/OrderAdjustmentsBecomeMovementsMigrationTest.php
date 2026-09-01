<?php

namespace Tests\Feature\Support;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\AuditLogger;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * **La migración de HECHOS del libro** (`2026_09_01_000100_order_adjustments_become_movements`,
 * `specs/desglose-libro.md` §4.8 y §6·T1 guarda E).
 *
 * Siembra filas con la FORMA VIEJA (`extra_due` / `deposit_remainder`: cascadas de créditos,
 * marcadores de 0 €, bajadas a medias, ediciones anteriores a `#150` que solo dejaron rastro) y
 * comprueba que la migración las convierte en hechos con su delta entero, que recupera lo que
 * faltaba, y que es IDEMPOTENTE: correrla dos veces deja exactamente lo mismo que una.
 *
 * Los importes son los del corpus local que motivó el diseño (`T4-PRB01`, `T5-PRB01`,
 * `R-DWFRDP`, `R-MOTEHE`), para que lo que aquí cierra sea lo mismo que allí se midió.
 */
class OrderAdjustmentsBecomeMovementsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_01_000100_order_adjustments_become_movements.php';

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_birth_remainder_becomes_a_deposit_split(): void
    {
        [$order, $item] = $this->paidOrder(qty: 4, unit: 1500, total: 6000);
        $this->oldRow($order, $item, 'deposit_remainder', 5000, at: '2026-08-31 18:02:26', reason: 'deposit_remainder');

        $this->migrate();

        $row = OrderAdjustment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(OrderAdjustment::TYPE_DEPOSIT_SPLIT, $row->type);
        $this->assertSame('deposit_split', $row->reason);
        $this->assertSame(5000, (int) $row->amount_cents);
    }

    public function test_a_mixed_party_twin_keeps_its_own_type(): void
    {
        [$order, $item] = $this->paidOrder(qty: 8, unit: 1500, total: 12000);
        $child = OrderItem::create(['order_id' => $order->id, 'parent_item_id' => $item->id, 'ticket_type_id' => $this->jumpType->id,
            'slot_id' => null, 'quantity' => 2, 'unit_price' => 400, 'seats' => 0]);
        $this->oldRow($order, $child, 'extra_due', 800, at: '2026-08-31 12:23:53', reason: 'mixed_party_surcharge',
            context: ['mixed_party' => ['guests' => 2, 'unit_cents' => 400, 'target_name' => 'Jump', 'target_type_id' => 9]]);

        $this->migrate();

        $this->assertSame(OrderAdjustment::TYPE_MIXED, OrderAdjustment::where('order_item_id', $child->id)->firstOrFail()->type);
    }

    /** `T4-PRB01`: una bajada 4 → 3 escrita en cascada (crédito contra el resto de la señal). */
    public function test_a_cascade_reduction_collapses_into_one_edit_row_with_the_whole_delta(): void
    {
        [$order, $item] = $this->paidOrder(qty: 3, unit: 1500, total: 6000);
        $this->oldRow($order, $item, 'deposit_remainder', 5000, at: '2026-08-31 18:02:26', reason: 'deposit_remainder');
        $ctx = ['changes' => ['quantity_change' => ['old' => 4, 'new' => 3]]];
        $this->oldRow($order, $item, 'extra_due', -500, at: '2026-08-31 21:41:58', reason: 'item_edit_reduction', context: $ctx);
        $this->oldRow($order, $item, 'deposit_remainder', -1000, at: '2026-08-31 21:41:58', reason: 'item_edit_reduction', context: $ctx);

        $this->migrate();

        $edits = OrderAdjustment::where('order_item_id', $item->id)->where('type', OrderAdjustment::TYPE_EDIT)->get();
        $this->assertCount(1, $edits, 'la cascada de dos filas es UN hecho');
        $this->assertSame(-1500, (int) $edits->first()->amount_cents, '(3 − 4) × 15,00');
        $this->assertSame('item_edit_reduction', $edits->first()->reason);
        $this->assertSame(5000, (int) OrderAdjustment::where('order_item_id', $item->id)->where('type', OrderAdjustment::TYPE_DEPOSIT_SPLIT)->sum('amount_cents'));

        $fresh = $this->fresh($order);
        $this->assertSame(6000, $fresh->birthValueCents());
        $this->assertSame(3500, $fresh->itemDepositRemainderCents($fresh->items->firstWhere('id', $item->id)), 'la lectura absorbe la bajada contra el resto de la señal: 50,00 − 15,00');
    }

    /** `T5-PRB01`: una bajada 100 % online que solo dejó un marcador de 0 €. */
    public function test_a_zero_marker_becomes_the_whole_delta(): void
    {
        [$order, $item] = $this->paidOrder(qty: 2, unit: 1500, total: 6000);
        $this->oldRow($order, $item, 'extra_due', 0, at: '2026-08-31 20:36:29', reason: 'reduction_marker',
            context: ['changes' => ['quantity_change' => ['old' => 4, 'new' => 2]]]);

        $this->migrate();

        $row = OrderAdjustment::where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(OrderAdjustment::TYPE_EDIT, $row->type);
        $this->assertSame(-3000, (int) $row->amount_cents, '(2 − 4) × 15,00');
        $this->assertSame('item_edit_reduction', $row->reason, 'el marcador deja de ser marcador');
        $this->assertSame(6000, $this->fresh($order)->birthValueCents());
    }

    /** Una bajada cubierta A MEDIAS: la cascada guardó solo la parte que la señal absorbió. */
    public function test_a_partially_covered_reduction_recovers_the_uncovered_rest(): void
    {
        [$order, $item] = $this->paidOrder(qty: 1, unit: 1500, total: 6000);
        $this->paidPayment($order, 4000);   // 60,00 de valor, 20,00 de reparto en puerta → 40,00 online
        $this->oldRow($order, $item, 'deposit_remainder', 2000, at: '2026-08-31 18:02:26', reason: 'deposit_remainder');
        $this->oldRow($order, $item, 'deposit_remainder', -2000, at: '2026-08-31 21:41:58', reason: 'item_edit_reduction',
            context: ['changes' => ['quantity_change' => ['old' => 4, 'new' => 1]]]);

        $this->migrate();

        $edit = OrderAdjustment::where('order_item_id', $item->id)->where('type', OrderAdjustment::TYPE_EDIT)->firstOrFail();
        $this->assertSame(-4500, (int) $edit->amount_cents, '(1 − 4) × 15,00: el delta entero, no los 20,00 cubiertos');
        $fresh = $this->fresh($order);
        $this->assertSame(6000, $fresh->birthValueCents());
        $this->assertSame(2500, $fresh->financialSummary()->pendienteDevolucion(), 'los 25,00 que la puerta no cubrió afloran');
    }

    /** Una CADENA (bajar el precio, luego subirlo): el precio vigente en cada gestión se recorre hacia atrás. */
    public function test_a_chain_of_edits_reconstructs_each_delta_with_the_price_of_its_moment(): void
    {
        [$order, $item] = $this->paidOrder(qty: 2, unit: 2000, total: 4000);
        $this->oldRow($order, $item, 'extra_due', 0, at: '2026-06-01 09:00:00', reason: 'reduction_marker',
            context: ['changes' => ['slot_change' => ['old' => 'sáb', 'new' => 'lun'], 'unit_price_change' => ['old' => 2000, 'new' => 1200]]]);
        $this->oldRow($order, $item, 'extra_due', 1600, at: '2026-06-01 09:05:00', reason: 'item_edit',
            context: ['changes' => ['slot_change' => ['old' => 'lun', 'new' => 'sáb'], 'unit_price_change' => ['old' => 1200, 'new' => 2000]]]);

        $this->migrate();

        $rows = OrderAdjustment::where('order_item_id', $item->id)->orderBy('created_at')->get();
        $this->assertSame([-1600, 1600], $rows->map(fn ($r) => (int) $r->amount_cents)->all());
        $this->assertSame(4000, $this->fresh($order)->birthValueCents());
    }

    /** Una edición anterior a `#150` (solo rastro de auditoría) recupera su fila desde `price_diff_cents`. */
    public function test_an_edit_that_only_left_an_audit_trail_recovers_its_row(): void
    {
        Carbon::setTestNow('2026-08-25 18:28:40');
        [$order, $item] = $this->paidOrder(qty: 1, unit: 3000, total: 4000);
        AuditLogger::log('orders.item_edited', $order, [
            'order_code' => $order->code, 'order_item_id' => $item->id,
            'from_quantity' => 1, 'to_quantity' => 1, 'from_unit_price' => 4000, 'to_unit_price' => 3000,
            'price_diff_cents' => -1000, 'changes' => ['slot_change', 'unit_price_change'],
        ]);
        Carbon::setTestNow();

        $this->migrate();

        $row = OrderAdjustment::where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(OrderAdjustment::TYPE_EDIT, $row->type);
        $this->assertSame(-1000, (int) $row->amount_cents);
        $this->assertSame(['old' => 4000, 'new' => 3000], $row->context['changes']['unit_price_change']);
        $this->assertSame('2026-08-25 18:28:40', $row->created_at->format('Y-m-d H:i:s'), 'fechada cuando ocurrió');
        $this->assertSame(4000, $this->fresh($order)->birthValueCents());
    }

    /** `R-MOTEHE`: la compensación que el modelo viejo derivaba pasa a fila `courtesy`. */
    public function test_a_historical_compensation_becomes_a_courtesy_row(): void
    {
        [$order, $item] = $this->paidOrder(qty: 1, unit: 3000, total: 4000);
        $payment = $this->paidPayment($order, 4000);
        $this->oldRow($order, $item, 'extra_due', 0, at: '2026-08-25 18:28:45', reason: 'reduction_marker',
            context: ['changes' => ['unit_price_change' => ['old' => 4000, 'new' => 3000]]]);
        $refund = PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $item->id, 'amount_cents' => 3000, 'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED, 'mode' => PaymentRefund::MODE_REST, 'intent' => PaymentRefund::INTENT_COMPENSATION,
            'gateway_order' => $payment->gateway_order, 'gateway_response_code' => '0900',
            'requested_by' => $order->user_id, 'requested_at' => '2026-08-25 18:35:04', 'processed_at' => '2026-08-25 18:35:04',
        ]);
        $order->forceFill(['refunded_at' => '2026-08-25 18:35:04', 'refund_amount_cents' => 3000])->save();

        $this->migrate();

        $courtesy = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get();
        $this->assertCount(1, $courtesy);
        $this->assertSame(-2000, (int) $courtesy->first()->amount_cents, '30,00 devueltos − 10,00 que se debían (40 → 30)');
        $this->assertSame($item->id, (int) $courtesy->first()->order_item_id);
        $this->assertSame((int) $refund->id, (int) $courtesy->first()->context['refund_id']);
        $this->assertSame('2026-08-25 18:35:04', $courtesy->first()->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(2000, $this->fresh($order)->financialSummary()->compensado(), 'coincide con lo que el modelo viejo deriva');
    }

    public function test_running_it_twice_changes_nothing_and_leaves_no_old_types(): void
    {
        [$order, $item] = $this->paidOrder(qty: 3, unit: 1500, total: 6000);
        $this->oldRow($order, $item, 'deposit_remainder', 5000, at: '2026-08-31 18:02:26', reason: 'deposit_remainder');
        $ctx = ['changes' => ['quantity_change' => ['old' => 4, 'new' => 3]]];
        $this->oldRow($order, $item, 'extra_due', -500, at: '2026-08-31 21:41:58', reason: 'item_edit_reduction', context: $ctx);
        $this->oldRow($order, $item, 'deposit_remainder', -1000, at: '2026-08-31 21:41:58', reason: 'item_edit_reduction', context: $ctx);

        $this->migrate();
        $first = DB::table('order_adjustments')->orderBy('id')->get()->map(fn ($r) => [$r->id, $r->type, (int) $r->amount_cents, $r->reason])->all();
        $this->migrate();
        $second = DB::table('order_adjustments')->orderBy('id')->get()->map(fn ($r) => [$r->id, $r->type, (int) $r->amount_cents, $r->reason])->all();

        $this->assertSame($first, $second, 'idempotente: la segunda pasada no toca nada');
        $this->assertSame(0, DB::table('order_adjustments')->whereIn('type', ['extra_due', 'deposit_remainder'])->count());
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────────────────────

    private function migrate(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->up();
    }

    private function fresh(Order $order): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType', 'payments.refunds', 'adjustments'])->findOrFail($order->id);
    }

    /** Una fila con la forma ANTERIOR a la T1, tal como la escribía la cascada. */
    private function oldRow(Order $order, OrderItem $item, string $type, int $cents, string $at, ?string $reason = null, ?array $context = null): void
    {
        DB::table('order_adjustments')->insert([
            'order_id' => $order->id, 'order_item_id' => $item->id, 'type' => $type, 'amount_cents' => $cents,
            'currency' => 'EUR', 'reason' => $reason, 'context' => $context === null ? null : json_encode($context),
            'applied_by' => $order->user_id, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** @return array{0: Order, 1: OrderItem} */
    private function paidOrder(int $qty, int $unit, int $total): array
    {
        if (! isset($this->jumpType)) {
            RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
            $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
            $this->jumpType = TicketType::create([
                'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id,
                'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
            ]);
        }
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-MG'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => '2026-08-31 18:02:26',
        ]);
        $h = str_pad((string) ($this->counter % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(14)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00", 'capacity' => 20, 'online_capacity' => 20,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id, 'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);

        return [$order, $item];
    }

    private function paidPayment(Order $order, int $amount): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id,
            'amount' => $amount, 'currency' => 'EUR', 'provider' => 'redsys', 'status' => Payment::STATUS_PAID,
            'paid_at' => '2026-08-31 18:02:26', 'gateway_order' => str_pad((string) (300000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
    }
}
