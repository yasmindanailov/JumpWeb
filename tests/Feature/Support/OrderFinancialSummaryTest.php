<?php

namespace Tests\Feature\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\OrderFinancialSummary;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2e cimientos — value object OrderFinancialSummary.
 *
 * Cubre las 5 combinatorias visuales acordadas con la clienta (sesión 7.2e):
 *  0. Sin actividad (paid + nada más).
 *  1. Refund (total o parcial) sin extras.
 *  2. Extra pendiente sin refunds.
 *  3. Mixto refund + extra pendiente.
 *  4. Todo saldado (extras de items finalizados o cancelados).
 *
 * El value object es la fuente única de verdad — los blades condicionan render
 * sobre los helpers `has*()`. Sin esto, cada blade implementaría su propio
 * cálculo con riesgo de divergencia.
 */
class OrderFinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    private int $paymentCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_state_0_no_activity_returns_only_total(): void
    {
        $order = $this->makePaidOrder(2400);
        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(2400, $summary->totalOriginal);
        $this->assertSame(0, $summary->totalRefunded);
        $this->assertSame(0, $summary->extraDue);
        $this->assertSame(0, $summary->extraDueResolved);
        $this->assertSame(2400, $summary->netOnline());
        $this->assertSame(0, $summary->pendingAtGate());
        $this->assertSame(2400, $summary->totalWithChanges());
        $this->assertFalse($summary->hasActivity());
        $this->assertFalse($summary->hasRefunds());
        $this->assertFalse($summary->hasExtraDue());
        $this->assertFalse($summary->hasPendingAtGate());
        $this->assertTrue($summary->isFullyResolved());
    }

    public function test_state_1_full_refund_recorded_in_total_refunded(): void
    {
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 2400);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(2400, $summary->totalRefunded);
        $this->assertSame(0, $summary->netOnline());
        $this->assertTrue($summary->hasRefunds());
        $this->assertTrue($summary->hasActivity());
        $this->assertFalse($summary->hasExtraDue());
        $this->assertTrue($summary->isFullyResolved());
    }

    public function test_state_1b_partial_refund_aggregates_correctly(): void
    {
        $order = $this->makePaidOrder(3600);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 600);
        $this->attachSucceededRefund($payment, 600);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(1200, $summary->totalRefunded);
        $this->assertSame(2400, $summary->netOnline());
    }

    public function test_failed_and_pending_refunds_are_excluded(): void
    {
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 600);
        $this->attachFailedRefund($payment, 1200);
        $this->attachPendingRefund($payment, 300);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        // Solo el succeeded (600) cuenta. Failed y pending no devolvieron dinero
        // efectivo y deben quedar fuera de cualquier cálculo del cliente.
        $this->assertSame(600, $summary->totalRefunded);
    }

    public function test_state_2_extra_due_unresolved_appears_in_pending(): void
    {
        $order = $this->makePaidOrder(2400);
        $by = User::factory()->create();
        $item = $this->attachActiveItem($order);
        $order->applyExtraDue($item, 1200, $by, 'cambio cantidad');

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(1200, $summary->extraDue);
        $this->assertSame(0, $summary->extraDueResolved);
        $this->assertSame(1200, $summary->pendingAtGate());
        $this->assertSame(3600, $summary->totalWithChanges());
        $this->assertTrue($summary->hasExtraDue());
        $this->assertTrue($summary->hasPendingAtGate());
        $this->assertFalse($summary->isFullyResolved());
    }

    public function test_state_4_extra_due_resolved_when_item_finished(): void
    {
        $order = $this->makePaidOrder(2400);
        $by = User::factory()->create();
        $item = $this->attachItemWithPastSlot($order);
        $order->applyExtraDue($item, 1200, $by, 'cambio cantidad pre-sesión');

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        // El item con slot pasado se considera "cobrado en puerta" implícitamente
        // (decisión clienta sesión 7.2e). pendingAtGate baja a 0.
        $this->assertSame(1200, $summary->extraDue);
        $this->assertSame(1200, $summary->extraDueResolved);
        $this->assertSame(0, $summary->pendingAtGate());
        $this->assertTrue($summary->isFullyResolved());
    }

    public function test_state_4_extra_due_voided_when_item_cancelled(): void
    {
        $order = $this->makePaidOrder(2400);
        $by = User::factory()->create();
        $item = $this->attachActiveItem($order);
        $order->applyExtraDue($item, 800, $by, 'edición previa al cancelar');
        $item->markCancelled($by);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        // El extra de un item CANCELADO queda ANULADO (a diferencia de uno FINALIZADO, que
        // se asume cobrado): el cargo era por algo que se quitó antes de cobrarlo. NO cuenta
        // ni en lo pendiente NI en el total con cambios (este último era el bug: el cargo
        // fantasma inflaba el total y aparecía a la vez como "a cobrar" y "pendiente reembolso").
        $this->assertSame(0, $summary->extraDue);
        $this->assertSame(0, $summary->extraDueResolved);
        $this->assertSame(0, $summary->pendingAtGate());
        $this->assertSame(2400, $summary->totalWithChanges());  // vuelve al total original
        $this->assertFalse($summary->hasExtraDue());

        // El helper de modelo (columna "Total" de la lista de pedidos y de la pestaña del
        // usuario) NO debe divergir del detalle: también anula el extra_due del item cancelado.
        $this->assertSame(2400, $order->fresh()->loadMissing('items', 'adjustments')->totalWithChangesCents());
    }

    public function test_state_3_mixed_partial_refund_and_pending_extra_are_orthogonal(): void
    {
        $order = $this->makePaidOrder(2400);
        $by = User::factory()->create();

        // 1) Refund parcial REST de 600.
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 600);

        // 2) Extra de 1800 sobre item activo (no finalizado).
        $item = $this->attachActiveItem($order);
        $order->applyExtraDue($item, 1800, $by, 'cambio mixto');

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(2400, $summary->totalOriginal);
        $this->assertSame(600, $summary->totalRefunded);
        $this->assertSame(1800, $summary->extraDue);
        $this->assertSame(0, $summary->extraDueResolved);
        $this->assertSame(1800, $summary->netOnline());
        $this->assertSame(1800, $summary->pendingAtGate());
        $this->assertSame(3600, $summary->totalWithChanges());
        $this->assertTrue($summary->hasActivity());
        $this->assertTrue($summary->hasRefunds());
        $this->assertTrue($summary->hasExtraDue());
        $this->assertTrue($summary->hasPendingAtGate());
        $this->assertFalse($summary->isFullyResolved());
    }

    public function test_partial_extra_resolution_when_some_items_finished(): void
    {
        $order = $this->makePaidOrder(4000);
        $by = User::factory()->create();
        $past = $this->attachItemWithPastSlot($order);   // finalizado
        $future = $this->attachActiveItem($order);       // activo

        $order->applyExtraDue($past, 500, $by);   // se considera saldado
        $order->applyExtraDue($future, 700, $by); // sigue pendiente

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(1200, $summary->extraDue);
        $this->assertSame(500, $summary->extraDueResolved);
        $this->assertSame(700, $summary->pendingAtGate());
    }

    public function test_pending_at_gate_never_goes_negative(): void
    {
        // Edge defensivo: si por algún motivo extraDueResolved > extraDue
        // (no debería pasar con los flujos actuales), pendingAtGate debe
        // devolver 0 — la UI nunca muestra "Pendiente: −X €".
        $vo = new OrderFinancialSummary(
            currency: 'EUR',
            totalOriginal: 1000,
            totalRefunded: 0,
            extraDue: 100,
            extraDueResolved: 999,
        );

        $this->assertSame(0, $vo->pendingAtGate());
    }

    public function test_summary_propagates_currency_from_order(): void
    {
        // El value object refleja el currency del Order tal cual. La columna es
        // NOT NULL en BD así que el fallback `EUR` del value object es defensa
        // en profundidad para datos en memoria (Pint detectaría el caso null
        // estático si lo eliminamos del código). Verificamos al menos que un
        // currency no-EUR se propaga correctamente.
        $order = $this->makePaidOrder(1000);
        $order->currency = 'USD';
        $order->save();

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame('USD', $summary->currency);
    }

    public function test_total_final_neto_equals_products_value_with_no_activity(): void
    {
        $order = $this->makePaidOrder(1000);
        $this->attachActiveItem($order); // charged 1000

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(1000, $summary->productsValue);
        $this->assertSame(1000, $summary->totalFinalNeto());
        $this->assertSame(0, $summary->pendienteDevolucion());
        $this->assertFalse($summary->hasPendienteDevolucion());
    }

    public function test_reduction_not_refunded_surfaces_as_pendiente_devolucion(): void
    {
        // Cliente pagó online 2 uds (2000) y ahora el item está a 1 ud (1000):
        // se le deben 1000 que aún no se han devuelto (reembolso fallido/pendiente).
        $order = $this->makePaidOrder(2000);
        $this->attachPaidPayment($order); // pagó 20,00 online — ancla de caja de «pendiente» (#225)
        $this->attachActiveItem($order); // charged 1000 (estado reducido)

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(1000, $summary->productsValue);
        $this->assertSame(2000, $summary->totalWithChanges());
        $this->assertSame(1000, $summary->pendienteDevolucion());
        $this->assertSame(1000, $summary->totalFinalNeto());
        // Reconcilia: total + extraDue − devuelto − pendienteDevolucion = totalFinal.
        $this->assertSame(
            $summary->totalFinalNeto(),
            $summary->totalWithChanges() - $summary->pendienteDevolucion(),
        );
    }

    public function test_pendiente_devolucion_cleared_by_succeeded_refund(): void
    {
        $order = $this->makePaidOrder(2000);
        $this->attachActiveItem($order); // charged 1000
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 1000); // la reducción SÍ se devolvió

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(1000, $summary->totalRefunded);
        $this->assertSame(0, $summary->pendienteDevolucion()); // ya devuelto → 0
        $this->assertSame(1000, $summary->totalFinalNeto());
    }

    public function test_gate_charge_backed_by_product_has_no_pendiente_devolucion(): void
    {
        // Subida de cantidad (cobro en puerta) que SÍ tiene producto detrás:
        // no genera "pendiente de devolución" (es a cobrar, no a devolver).
        $order = $this->makePaidOrder(1000);
        $by = User::factory()->create();
        $item = $this->attachActiveItem($order);
        $item->forceFill(['quantity' => 2])->save(); // ahora charged 2000
        $order->applyExtraDue($item->fresh(), 1000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(2000, $summary->productsValue);
        $this->assertSame(2000, $summary->totalWithChanges());
        $this->assertSame(0, $summary->pendienteDevolucion());
        $this->assertSame(2000, $summary->totalFinalNeto());
    }

    public function test_legacy_partial_refund_does_not_double_count_pendiente(): void
    {
        // Reembolso parcial LEGACY (pre-#142): vive SOLO en Order.refund_amount_cents,
        // sin fila payment_refunds. Antes "Devuelto" lo mostraba (legacy-safe) pero
        // "Pendiente de devolución" usaba totalRefunded (=0) y lo DOBLE-CONTABA. Con
        // effectiveRefunded el pendiente lo descuenta → ya no se doble-cuenta (bug HIGH
        // de la revisión adversarial de la sesión).
        $order = $this->makePaidOrder(2000);          // pagó 2 uds online
        $this->attachActiveItem($order);              // reducido a 1 ud (charged 1000)
        $order->forceFill(['refunded_at' => now(), 'refund_amount_cents' => 1000])->save();

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(0, $summary->totalRefunded);          // sin fila payment_refunds
        $this->assertSame(1000, $summary->effectiveRefunded()); // pero la columna legacy sí
        $this->assertSame(1000, $summary->totalWithChanges());  // 2000 − 1000 (legacy-safe)
        $this->assertSame(0, $summary->pendienteDevolucion());  // ya devuelto → 0 (sin doble-conteo)
        $this->assertSame(1000, $summary->totalFinalNeto());
    }

    public function test_deposit_remainder_adds_to_pending_at_gate_but_not_to_total_with_changes(): void
    {
        // #225: el resto de la señal va al bucket de puerta SIN inflar el total con cambios
        // (eje VALOR intacto). Pedido valor 1000; señal cobrada 300 → resto 700 a puerta.
        $order = $this->makePaidOrder(1000);
        $item = $this->attachActiveItem($order);     // charged 1000
        $this->attachDepositRemainder($order, $item, 700);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(700, $summary->depositRemainder);
        $this->assertSame(0, $summary->extraDue);
        $this->assertSame(700, $summary->pendingAtGate());     // el resto-señal cuenta como a-cobrar
        $this->assertSame(1000, $summary->totalWithChanges()); // NO 1700 (la señal NO infla el total)
        $this->assertSame(1000, $summary->productsValue);
        $this->assertSame(0, $summary->pendienteDevolucion()); // nada que devolver (caso normal)
        $this->assertTrue($summary->hasPendingAtGate());
    }

    public function test_deposit_remainder_resolved_when_item_finished(): void
    {
        $order = $this->makePaidOrder(1000);
        $item = $this->attachItemWithPastSlot($order); // finalizado → cobrado en puerta
        $this->attachDepositRemainder($order, $item, 700);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(700, $summary->depositRemainder);
        $this->assertSame(700, $summary->depositRemainderResolved);
        $this->assertSame(0, $summary->pendingAtGate());       // ya cobrado en puerta
        $this->assertTrue($summary->isFullyResolved());
    }

    public function test_deposit_remainder_voided_when_item_cancelled(): void
    {
        // E5: cancelar la línea anula su resto-señal (mismo criterio que el extra_due).
        // (El display de "pendiente de devolución" para un depósito cancelado es coherencia
        // de cancelar/reembolsar → iter. 4; aquí solo el anulado del bucket de puerta.)
        $order = $this->makePaidOrder(1000);
        $by = User::factory()->create();
        $item = $this->attachActiveItem($order);
        $this->attachDepositRemainder($order, $item, 700);
        $item->markCancelled($by);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(0, $summary->depositRemainder);
        $this->assertSame(0, $summary->pendingAtGate());
    }

    public function test_only_deposit_order_has_activity(): void
    {
        // §1.1.E: un pedido de SOLO señal (sin extra_due ni refunds) DEBE tener desglose
        // (a cobrar en parque); si no, caería en el caso simple «solo Total» y lo ocultaría.
        $order = $this->makePaidOrder(1000);
        $item = $this->attachActiveItem($order);
        $this->attachDepositRemainder($order, $item, 700);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertFalse($summary->hasRefunds());
        $this->assertFalse($summary->hasExtraDue());
        $this->assertTrue($summary->hasActivity());            // por el resto-señal
    }

    public function test_legacy_order_without_deposit_has_zero_remainder(): void
    {
        // No-regresión: sin filas deposit_remainder, los acumuladores son 0 (defaults).
        $order = $this->makePaidOrder(2400);
        $this->attachActiveItem($order);

        $summary = OrderFinancialSummary::fromOrder($order->fresh(['payments.refunds', 'adjustments', 'items.slot']));

        $this->assertSame(0, $summary->depositRemainder);
        $this->assertSame(0, $summary->depositRemainderResolved);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function attachDepositRemainder(Order $order, OrderItem $item, int $cents): OrderAdjustment
    {
        return OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => $cents, 'currency' => 'EUR',
            'applied_by' => $order->user_id,
        ]);
    }

    private function ensureTicketTypeSetup(): void
    {
        if (isset($this->jumpType)) {
            return;
        }
        RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0,
        ]);
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
            'code' => 'JJ-FS'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachPaidPayment(Order $order): Payment
    {
        return Payment::create([
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'EUR',
            'provider' => 'redsys',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'gateway_order' => str_pad((string) (++$this->paymentCounter + 100000), 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function attachSucceededRefund(Payment $payment, int $amount): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => null,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
    }

    private function attachFailedRefund(Payment $payment, int $amount): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => null,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_FAILED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => '0190',
            'failure_reason' => PaymentRefund::FAILURE_GATEWAY_DENIED,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);
    }

    private function attachPendingRefund(Payment $payment, int $amount): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => null,
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => User::factory()->create()->id,
            'requested_at' => now(),
        ]);
    }

    private function attachActiveItem(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }

    private function attachItemWithPastSlot(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2000-01-01',
            'start_time' => '10:00:00', 'end_time' => '10:59:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }
}
