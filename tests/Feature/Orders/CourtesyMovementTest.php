<?php

namespace Tests\Feature\Orders;

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
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * **La CORTESÍA de un reembolso, escrita al ocurrir** (T1 del libro, `specs/desglose-libro.md`
 * §4.2 y §6·T1, guarda D).
 *
 * Hasta la T1 «dinero devuelto sin que desapareciera producto» se DERIVABA al leer
 * (`OrderFinancialSummary::compensado()`). Ahora cada reembolso deja, en su MISMA transacción, la
 * fila `courtesy` con la parte del importe que EXCEDE lo que se le debía al cliente en el ámbito
 * del reembolso — y el modelo de dos ejes sigue derivando el suyo hasta la T3: aquí se cruzan
 * (Σ filas `courtesy` == `compensado()`), que es la guarda puente de esta pieza.
 *
 * Los reembolsos van en modo MANUAL (registro sin pasarela): es el mismo camino de dominio que el
 * REST, sin la llamada a Redsys, y es el que la T5 documentó como forma de constancia (§20.5).
 */
class CourtesyMovementTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        Notification::fake();
    }

    public function test_a_compensation_with_nothing_owed_is_written_whole_as_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executePartialRefund($item, 2000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get();
        $this->assertCount(1, $rows, 'una cortesía, un hecho');
        $row = $rows->first();
        $this->assertSame(-2000, (int) $row->amount_cents, 'no se le debía nada: todo lo devuelto es cortesía, en negativo');
        $this->assertSame($item->id, (int) $row->order_item_id, 'atribuida a la línea del reembolso');
        $this->assertSame((int) $result['refund']->id, (int) $row->context['refund_id']);
        $this->assertSame(PaymentRefund::INTENT_COMPENSATION, $row->reason);
        $this->assertSame($by->id, (int) $row->applied_by);

        $this->assertCourtesyMatchesTheOldModel($order);
    }

    public function test_a_refund_of_exactly_what_was_owed_writes_no_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, by: $by, toQty: 1);   // se le deben 20,00

        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 2000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_VALUE_RETURNED);
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count(),
            'devolver lo debido no es una cortesía');
        $this->assertCourtesyMatchesTheOldModel($order);
    }

    public function test_only_the_excess_over_what_was_owed_is_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, by: $by, toQty: 1);   // se le deben 20,00

        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 3000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(-1000, (int) $rows->first()->amount_cents, '30,00 devueltos − 20,00 debidos = 10,00 de cortesía');
        $this->assertCourtesyMatchesTheOldModel($order);
    }

    /**
     * `paid_in_person` RE-CANALIZA el dinero (el cliente lo pagará en recepción): no es una
     * cortesía y no se escribe ninguna. ⚠️ El modelo de dos ejes lo clasificaba como
     * «compensado» (no distinguía la intención): es la divergencia DELIBERADA de la spec §4.2, y
     * por eso aquí no se cruza con `compensado()` — el saldo del libro (T2) lo dirá solo.
     */
    public function test_paid_in_person_writes_no_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executePartialRefund($item, 2000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_PAID_IN_PERSON);
        $this->assertTrue($result['ok'], json_encode($result));

        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count());
    }

    public function test_a_total_refund_spreads_the_courtesy_over_the_lines_without_losing_a_cent(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $b = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get()->keyBy('order_item_id');
        $this->assertCount(2, $rows, 'una fila por línea que recibió cortesía');
        $this->assertSame(-3000, (int) $rows[$a->id]->amount_cents);
        $this->assertSame(-1000, (int) $rows[$b->id]->amount_cents);
        $this->assertSame(-4000, (int) $rows->sum('amount_cents'), 'la Σ es EXACTAMENTE la cortesía del pedido');
        $this->assertCourtesyMatchesTheOldModel($order);
    }

    public function test_a_total_refund_after_a_cancellation_credits_only_what_exceeds_the_debt(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $b = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 4000);
        $b->markCancelled($by);   // se le deben 10,00 por la línea B

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION);
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get()->keyBy('order_item_id');
        $this->assertCount(1, $rows, 'la línea cancelada solo recibió lo que se le debía: sin cortesía');
        $this->assertSame(-3000, (int) $rows[$a->id]->amount_cents, '40,00 devueltos − 10,00 debidos = 30,00, todos sobre la línea viva');
        $this->assertCourtesyMatchesTheOldModel($order);
    }

    // ─── La guarda puente: lo escrito al ocurrir == lo derivado al leer ─────────────────────

    private function assertCourtesyMatchesTheOldModel(Order $order): void
    {
        $fresh = $this->fresh($order);
        $written = -(int) $fresh->adjustments->where('type', OrderAdjustment::TYPE_COURTESY)->sum('amount_cents');

        $this->assertSame($fresh->financialSummary()->compensado(), $written,
            'Σ filas `courtesy` tiene que ser lo que `OrderFinancialSummary::compensado()` deriva: hasta la T3 conviven y no pueden divergir');
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────────────────────

    private function reduce(Order $order, OrderItem $item, User $by, int $toQty): void
    {
        $delta = ($toQty - (int) $item->quantity) * (int) $item->unit_price;
        $order->recordEdit($item, $delta, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => (int) $item->quantity, 'new' => $toQty]]]);
        $item->forceFill(['quantity' => $toQty, 'seats' => $toQty])->save();
    }

    private function fresh(Order $order): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType', 'payments.refunds', 'adjustments'])->findOrFail($order->id);
    }

    private function ensureTicketTypeSetup(): void
    {
        if (isset($this->jumpType)) {
            return;
        }
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jumpType = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(int $total): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-CM'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachPaidPayment(Order $order, int $amount): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (200000 + $this->counter), 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function attachItem(Order $order, int $qty, int $unit): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 20, 'online_capacity' => 20,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }
}
