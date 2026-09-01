<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\Movement;
use App\Domain\Booking\Services\OrderBook;
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
 * Hasta la T1 «dinero devuelto sin que desapareciera producto» se DERIVABA al leer (el
 * `compensado` del modelo de dos ejes, retirado en la T3·4). Ahora cada reembolso deja, en su MISMA
 * transacción, la fila `courtesy` con la parte del importe que EXCEDE lo que se le debía al cliente
 * en el ámbito del reembolso — y «lo debido» lo dice el LIBRO (`OrderBook::owedToCustomerCents`,
 * la misma cifra que el panel le sugiere al operador). Cada fila es un movimiento del libro, y el
 * libro cierra con ella escrita: eso es lo que cada caso comprueba al final.
 *
 * Los reembolsos van en modo MANUAL (registro sin pasarela): es el mismo camino de dominio que el
 * REST, sin la llamada a Redsys, y es el que la T5 documentó como forma de constancia (§20.5).
 *
 * ▶ Desde la T4 (`DECISIONES #316`) **el motivo manda**: solo `compensation` escribe cortesía —con
 * motivo escrito, que aquí viaja en cada caso— y `value_returned` no puede exceder lo debido, así que
 * devolver «lo debido» antes de registrar la bajada se BLOQUEA en vez de convertirse en cortesía (el
 * último caso). El resto de la regla por motivo vive en `RefundIntentGovernsTest`.
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

        $result = $this->fresh($order)->executePartialRefund($item, 2000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, 'Motivo de prueba (T4 del libro)');
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get();
        $this->assertCount(1, $rows, 'una cortesía, un hecho');
        $row = $rows->first();
        $this->assertSame(-2000, (int) $row->amount_cents, 'no se le debía nada: todo lo devuelto es cortesía, en negativo');
        $this->assertSame($item->id, (int) $row->order_item_id, 'atribuida a la línea del reembolso');
        $this->assertSame((int) $result['refund']->id, (int) $row->context['refund_id']);
        $this->assertSame(PaymentRefund::INTENT_COMPENSATION, $row->reason);
        $this->assertSame($by->id, (int) $row->applied_by);

        $this->assertCourtesyIsWhatTheBookShows($order);
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
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    public function test_only_the_excess_over_what_was_owed_is_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $item = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 4000);
        $this->reduce($order, $item, by: $by, toQty: 1);   // se le deben 20,00

        $result = $this->fresh($order)->executePartialRefund($item->fresh(), 3000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, 'Motivo de prueba (T4 del libro)');
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(-1000, (int) $rows->first()->amount_cents, '30,00 devueltos − 20,00 debidos = 10,00 de cortesía');
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    /**
     * `paid_in_person` RE-CANALIZA el dinero (el cliente lo pagará en recepción): no es una
     * cortesía y no se escribe ninguna (spec §4.2) — el saldo del libro lo dice solo: el Total no
     * baja, lo pagado sí, y la diferencia queda «a pagar en el parque».
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

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION, 'Motivo de prueba (T4 del libro)');
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get()->keyBy('order_item_id');
        $this->assertCount(2, $rows, 'una fila por línea que recibió cortesía');
        $this->assertSame(-3000, (int) $rows[$a->id]->amount_cents);
        $this->assertSame(-1000, (int) $rows[$b->id]->amount_cents);
        $this->assertSame(-4000, (int) $rows->sum('amount_cents'), 'la Σ es EXACTAMENTE la cortesía del pedido');
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    public function test_a_total_refund_after_a_cancellation_credits_only_what_exceeds_the_debt(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $b = $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 4000);
        $b->markCancelled($by);   // se le deben 10,00 por la línea B

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, false, PaymentRefund::INTENT_COMPENSATION, 'Motivo de prueba (T4 del libro)');
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get()->keyBy('order_item_id');
        $this->assertCount(1, $rows, 'la línea cancelada solo recibió lo que se le debía: sin cortesía');
        $this->assertSame(-3000, (int) $rows[$a->id]->amount_cents, '40,00 devueltos − 10,00 debidos = 30,00, todos sobre la línea viva');
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    /**
     * **«También cancelar» devuelve el valor que la cancelación retira: no es una cortesía.**
     *
     * ⚠️⚠️ Defecto de la T1 cazado por la guarda del libro (T2, `OrderBookTest`): lo debido se medía
     * ANTES de aplicar la cancelación que viaja con el reembolso, así que con la línea todavía viva
     * nada se debía y los 40,00 € salían ENTEROS como cortesía sobre un pedido cancelado. Es el
     * flujo REAL del panel (`ViewOrder`, «Reembolsar» + «también cancelar», intent `value_returned`).
     * Mutación que muerde: volver a medir lo debido antes de `cancelLiveItems()`.
     */
    public function test_a_total_refund_that_also_cancels_returns_the_value_and_writes_no_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $this->attachItem($order, qty: 1, unit: 4000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executeFullRefund($by, PaymentRefund::MODE_MANUAL, true, PaymentRefund::INTENT_VALUE_RETURNED);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertTrue($result['also_cancelled']);

        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count(),
            'se devolvió exactamente lo que la cancelación dejó a deber');
        $this->assertSame(Order::STATUS_CANCELLED, $this->fresh($order)->status);
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    /** El mismo hecho, por línea: reembolsar una línea cancelándola en el mismo gesto. */
    public function test_a_partial_refund_that_also_cancels_the_line_writes_no_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(4000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $this->attachItem($order, qty: 1, unit: 1000);
        $this->attachPaidPayment($order, 4000);

        $result = $this->fresh($order)->executePartialRefund($a, 3000, $by, PaymentRefund::MODE_MANUAL, true, [], PaymentRefund::INTENT_VALUE_RETURNED);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertTrue($result['also_cancelled_item']);

        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count());
        $this->assertNotNull($a->fresh()->cancelled_at);
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    /**
     * **El ÁMBITO de un reembolso atado a línea es SU reserva, no el pedido** (spec §4.2). Con dos
     * reservas y una deuda en la OTRA, devolver sobre la sana es cortesía ENTERA: lo que se le debe
     * por B no descuenta lo que se le regala por A. Mutación que muerde: medir lo debido con el
     * libro del PEDIDO (`OrderBook::forOrder`) en el reembolso parcial — con una sola reserva las
     * dos cifras coinciden, y por eso este caso necesita dos.
     */
    public function test_a_line_refund_measures_what_is_owed_in_its_own_reservation(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(7000);
        $a = $this->attachItem($order, qty: 1, unit: 3000);
        $b = $this->attachItem($order, qty: 2, unit: 2000);
        $this->attachPaidPayment($order, 7000);
        $this->reduce($order, $b, by: $by, toQty: 1);   // por B se le deben 20,00; por A, nada

        $result = $this->fresh($order)->executePartialRefund($a, 2000, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_COMPENSATION, 'Motivo de prueba (T4 del libro)');
        $this->assertTrue($result['ok'], json_encode($result));

        $rows = OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(-2000, (int) $rows->first()->amount_cents, 'por A no se debía nada: los 20,00 son cortesía enteros');
        $this->assertSame($a->id, (int) $rows->first()->order_item_id);
        $this->assertCourtesyIsWhatTheBookShows($order);
        // Y la deuda de B sigue ahí, intacta: el libro del pedido debe 20,00 − 0 de la cortesía.
        $this->assertSame(2000, OrderBook::forReservation($this->fresh($order), $this->fresh($order)->items->firstWhere('id', $b->id))->owedToCustomerCents());
    }

    /**
     * `LB-ORDEN` (spec §6.3.7 hueco 4), cerrado por la T4: «devolver lo que se le debe» ANTES de
     * registrar la bajada ya no fabrica una cortesía que el operador no quiso — se bloquea
     * (`exceeds_owed`) porque todavía no se debe nada, y nada queda escrito. Mutación que muerde:
     * dejar pasar el reembolso (quitar el tope).
     */
    public function test_returning_what_is_owed_before_the_reduction_is_blocked_instead_of_becoming_courtesy(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder(2970);
        $item = $this->attachItem($order, qty: 3, unit: 990);
        $this->attachPaidPayment($order, 2970);

        $result = $this->fresh($order)->executePartialRefund($item, 1980, $by, PaymentRefund::MODE_MANUAL, false, [], PaymentRefund::INTENT_VALUE_RETURNED);

        $this->assertSame(['ok' => false, 'reason' => 'exceeds_owed'], $result);
        $this->assertSame(0, OrderAdjustment::where('order_id', $order->id)->where('type', OrderAdjustment::TYPE_COURTESY)->count());
        $this->assertSame(0, PaymentRefund::count(), 'ni siquiera la fila `pending`: el tope va antes de la pasarela');
        $this->assertCourtesyIsWhatTheBookShows($order);
    }

    // ─── Lo escrito al ocurrir es lo que el libro enseña ────────────────────────────────────

    private function assertCourtesyIsWhatTheBookShows(Order $order): void
    {
        $fresh = $this->fresh($order);
        $written = (int) $fresh->adjustments->where('type', OrderAdjustment::TYPE_COURTESY)->sum('amount_cents');
        $book = OrderBook::forOrder($fresh);

        $this->assertTrue($book->isConsistent, 'con la cortesía escrita, el libro tiene que cerrar');
        $inBook = array_sum(array_map(
            fn (Movement $m): int => $m->amountCents,
            array_filter($book->movements, fn (Movement $m): bool => $m->kind === Movement::KIND_COURTESY),
        ));
        $this->assertSame($written, $inBook, 'cada fila `courtesy` es un movimiento del libro, con su importe');
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
