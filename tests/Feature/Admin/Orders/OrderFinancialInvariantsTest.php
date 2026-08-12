<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderFinancialSummary;
use App\Domain\Booking\Services\ReservationFinancials;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INVARIANTES financieros cruzados (red de seguridad para futuros refactors).
 *
 * La auditoría de organización del código marcó como hallazgo ALTO que la regla
 * "pendiente de devolución / valor neto del pedido" se calcula en TRES sitios que
 * DEBEN coincidir y hoy solo coinciden "por disciplina + tests", no por construcción:
 *   - {@see Order::itemPendingRefundCents()} (por ítem),
 *   - {@see ReservationFinancials} (por reserva = card de producto, #200),
 *   - {@see OrderFinancialSummary} (agregado del pedido).
 *
 * Este test PINEA explícitamente que las tres fuentes reconcilian, en escenarios
 * variados (sin actividad, cargo de puerta pendiente/cobrado, cancelación, reembolso
 * parcial, combo). No prueba un cómputo nuevo: ANCLA la coincidencia que un cambio
 * futuro en cualquiera de las tres implementaciones rompería en silencio.
 *
 * Invariantes verificados sobre cada pedido:
 *   1. Por reserva:  valor == pagadoOnline + aCobrarPuerta + cobradoPuerta.
 *   2. Σ cards valor          == OrderFinancialSummary::totalFinalNeto().
 *   3. Σ cards aCobrarPuerta   == OrderFinancialSummary::pendingAtGate().
 *   4. Σ cards pendienteReembolso == OrderFinancialSummary::pendienteDevolucion().
 *   5. Σ Order::itemPendingRefundCents() (todos los ítems) == pendienteDevolucion().
 */
class OrderFinancialInvariantsTest extends TestCase
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

    public function test_plain_paid_order_with_addon_reconciles(): void
    {
        $order = $this->makePaidOrder();
        $principal = $this->attachActiveItem($order, unitPrice: 1000);
        $this->attachAddon($order, $principal, unitPrice: 300);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'sin actividad (principal + complemento)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(1300, $summary->totalFinalNeto());
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_active_item_with_pending_gate_charge_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        // El producto vale 1500 hoy (subió en gestión); de eso 500 se cobra en puerta.
        $item = $this->attachActiveItem($order, unitPrice: 1500);
        $order->applyExtraDue($item, 500, $by);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'cargo de puerta PENDIENTE (ítem activo)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(500, $summary->pendingAtGate());
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_finished_item_with_collected_gate_charge_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);   // qty1 × 1000, slot pasado → finalizado
        $order->applyExtraDue($item, 400, $by);
        $this->syncTotalToOnline($order);

        $this->assertReconciles($order, 'cargo de puerta COBRADO (ítem finalizado)');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->pendingAtGate());   // finalizado → ya cobrado en puerta
        $this->assertSame(0, $summary->pendienteDevolucion());
    }

    public function test_cancelled_collected_item_surfaces_pending_refund_and_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);   // total = 1000 (lo pagado online)
        $item->markCancelled($by);          // cancelado, sin reembolsar todavía

        $this->assertReconciles($order, 'cancelación con reembolso pendiente');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(0, $summary->totalFinalNeto());        // ya no hay producto
        $this->assertSame(1000, $summary->pendienteDevolucion()); // todo lo cobrado, por devolver
    }

    public function test_partial_per_item_refund_reconciles(): void
    {
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);
        $this->syncTotalToOnline($order);
        $this->attachSucceededRefund($payment, 400, $item->id);   // reembolso parcial de cortesía

        $this->assertReconciles($order, 'reembolso parcial por ítem (producto activo)');

        $order = $this->freshOrder($order);
        $summary = $order->financialSummary();
        $this->assertSame(1000, $summary->totalFinalNeto());      // el producto sigue ahí
        $this->assertSame(0, $summary->pendienteDevolucion());    // el reembolso ya salió ("Devuelto")
        $this->assertSame(400, (int) $order->reservationFinancialsByPrincipal()[0]['rf']->devuelto);
    }

    public function test_combo_active_with_addon_plus_cancelled_principal_reconciles(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        $p1 = $this->attachActiveItem($order, unitPrice: 1000);
        $this->attachAddon($order, $p1, unitPrice: 300);
        $p2 = $this->attachActiveItem($order, unitPrice: 800);
        $this->syncTotalToOnline($order);   // total = 1000 + 300 + 800 = 2100
        $p2->markCancelled($by);            // se cancela el segundo principal (sin reembolsar)

        $this->assertReconciles($order, 'combo: activo+complemento y principal cancelado');

        $summary = $this->freshOrder($order)->financialSummary();
        $this->assertSame(1300, $summary->totalFinalNeto());       // solo P1 + su complemento
        $this->assertSame(800, $summary->pendienteDevolucion());   // P2 cancelado, por devolver
    }

    // ─── Aserción de reconciliación ────────────────────────────────────────

    private function assertReconciles(Order $order, string $label): void
    {
        $order = $this->freshOrder($order);
        $summary = $order->financialSummary();
        $cards = $order->reservationFinancialsByPrincipal();

        $sumValor = $sumPend = $sumDev = $sumPendReemb = 0;
        foreach ($cards as $card) {
            $rf = $card['rf'];
            // Invariante por reserva (documentado en ReservationFinancials).
            $this->assertSame(
                $rf->pagadoOnline + $rf->aCobrarPuerta + $rf->cobradoPuerta,
                $rf->valor,
                "$label · reserva: pagadoOnline+aCobrarPuerta+cobradoPuerta == valor",
            );
            $sumValor += $rf->valor;
            $sumPend += $rf->aCobrarPuerta;
            $sumDev += $rf->devuelto;
            $sumPendReemb += $rf->pendienteReembolso;
        }

        $this->assertSame($summary->totalFinalNeto(), $sumValor, "$label · Σ valor cards == Total final");
        $this->assertSame($summary->pendingAtGate(), $sumPend, "$label · Σ aCobrarPuerta cards == pendingAtGate");
        $this->assertSame($summary->pendienteDevolucion(), $sumPendReemb, "$label · Σ pendienteReembolso cards == pendienteDevolucion");

        // Hallazgo ALTO del audit: la TERCERA fuente (por ítem, directa) también coincide.
        $directPending = (int) $order->items->sum(fn (OrderItem $i) => $order->itemPendingRefundCents($i));
        $this->assertSame($summary->pendienteDevolucion(), $directPending, "$label · Σ itemPendingRefundCents == pendienteDevolucion");
    }

    // ─── Fixtures (patrón de OrderPerItemHelpersTest) ──────────────────────

    private function freshOrder(Order $order): Order
    {
        return Order::with(['items.children', 'items.slot', 'items.ticketType', 'payments.refunds', 'adjustments'])
            ->findOrFail($order->id);
    }

    /** Ajusta `Order.total` a lo realmente cobrado online (Σ itemCollectedCents): invariante de alta. */
    private function syncTotalToOnline(Order $order): void
    {
        $order->refresh()->load(['items', 'adjustments']);
        $online = (int) $order->items->sum(fn (OrderItem $i) => $order->itemCollectedCents($i));
        $order->update(['subtotal' => $online, 'total' => $online]);
        // #225: «pendiente de devolución» se ancla al dinero REALMENTE cobrado por web (no a
        // `Order.total` como proxy). El pago se crea ANTES de añadir items en estos fixtures, así
        // que aquí lo sincronizamos a lo cobrado online tras montarlos — como en un pedido real.
        $order->payments()->where('status', Payment::STATUS_PAID)->update(['amount' => $online]);
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

    private function makePaidOrder(int $total = 1000): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-IV'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
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

    private function attachSucceededRefund(Payment $payment, int $amount, ?int $itemId = null): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $itemId,
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

    private function attachActiveItem(Order $order, int $quantity = 1, int $unitPrice = 1000): OrderItem
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
            'quantity' => $quantity, 'seats' => $quantity, 'unit_price' => $unitPrice,
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

    private function attachAddon(Order $order, OrderItem $parent, int $unitPrice = 300): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => $unitPrice,
        ]);
    }
}
