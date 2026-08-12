<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2e cimientos — helpers per-item del modelo Order.
 *
 * Defense in depth UI ↔ handler ↔ orquestador: la UI usa `can*Item()` para
 * mostrar/ocultar controles, el handler revalida con `*BlockedReason()` antes
 * de tocar BD, y el orquestador (`executePartialRefund`) vuelve a comprobar
 * dentro del lock. Cualquier capa que falle (regresión futura) deja las otras
 * cerrando — pero los helpers deben ser EXACTOS porque marcan el contrato.
 *
 * Cubre también `totalRefundedCents`, `itemRefundedCents`, `refundableCapacityCents`
 * que los orquestadores y la UI consultan.
 */
class OrderPerItemHelpersTest extends TestCase
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

    // ─── canEditItem / editItemBlockedReason ──────────────────────────────

    public function test_can_edit_item_true_for_paid_active_principal(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $this->assertNull($order->editItemBlockedReason($item));
        $this->assertTrue($order->canEditItem($item));
    }

    public function test_edit_blocked_when_item_not_in_order(): void
    {
        $orderA = $this->makePaidOrder(code: 'JJ-OW0001');
        $orderB = $this->makePaidOrder(code: 'JJ-OW0002');
        $itemOfB = $this->attachActiveItem($orderB);

        $this->assertSame('not_in_order', $orderA->editItemBlockedReason($itemOfB));
    }

    public function test_edit_blocked_when_order_pending(): void
    {
        $order = $this->makePendingOrder();
        $item = $this->attachActiveItem($order);

        // Item activo + Order pending → no hay bloqueo por el item específico,
        // pero la operatividad del Order lo bloquea. Última capa de check.
        $this->assertSame('order_not_operational', $order->editItemBlockedReason($item));
    }

    public function test_edit_blocked_when_item_cancelled(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);
        $item->markCancelled($by);

        $this->assertSame('item_cancelled', $order->editItemBlockedReason($item));
    }

    public function test_edit_blocked_when_item_finished(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachItemWithPastSlot($order);

        $this->assertSame('item_finished', $order->editItemBlockedReason($item));
    }

    public function test_edit_blocked_when_item_is_addon(): void
    {
        $order = $this->makePaidOrder();
        $parent = $this->attachActiveItem($order);
        $addon = $this->attachAddon($order, $parent);

        $this->assertSame('item_is_addon', $order->editItemBlockedReason($addon));
    }

    // ─── canCancelItem / cancelItemBlockedReason ──────────────────────────

    public function test_can_cancel_item_true_with_paid_payment_and_capacity(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $this->assertNull($order->cancelItemBlockedReason($item));
        $this->assertTrue($order->canCancelItem($item));
    }

    public function test_cancel_blocked_when_no_paid_payment(): void
    {
        $order = $this->makePaidOrder();
        // No attachPaidPayment.
        $item = $this->attachActiveItem($order);

        $this->assertSame('no_paid_payment', $order->cancelItemBlockedReason($item));
    }

    public function test_cancel_not_blocked_by_insufficient_refund_capacity(): void
    {
        // #172 (decisión #157 reforzada): CANCELAR ≠ REEMBOLSAR. Aunque la
        // capacidad reembolsable esté agotada (refund previo de 800€ de 1000€,
        // item de 1000€), cancelar el item NO se bloquea — cancelar solo libera
        // la plaza (soft-cancel, sin tocar Redsys); el reembolso es una acción
        // independiente que el operador decide aparte.
        $order = $this->makePaidOrder(1000);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 800);

        $item = $this->attachActiveItem($order);
        $order->refresh()->load('payments.refunds');

        $this->assertNull($order->cancelItemBlockedReason($item));
        $this->assertTrue($order->canCancelItem($item));
    }

    public function test_cancel_blocked_when_order_not_paid_inherits_from_edit(): void
    {
        $order = $this->makePendingOrder();
        $item = $this->attachActiveItem($order);

        // cancelItemBlockedReason hereda primero de editItemBlockedReason.
        $this->assertSame('order_not_operational', $order->cancelItemBlockedReason($item));
    }

    // ─── canRefundItem / refundItemBlockedReason ──────────────────────────

    public function test_can_refund_item_true_for_paid_with_capacity(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $this->assertNull($order->refundItemBlockedReason($item));
        $this->assertTrue($order->canRefundItem($item));
    }

    public function test_refund_allowed_on_finished_item_courtesy_use_case(): void
    {
        // Refund de cortesía tras servicio prestado: el item finalizó pero
        // queremos devolver dinero como compensación (algo falló durante la
        // sesión). NO se permite cancelar (servicio prestado) pero SÍ refund.
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachItemWithPastSlot($order);

        $this->assertSame('item_finished', $order->cancelItemBlockedReason($item));
        $this->assertNull($order->refundItemBlockedReason($item));
    }

    public function test_refund_blocked_when_already_fully_refunded(): void
    {
        $order = $this->makePaidOrder(1000);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 1000);   // consume toda la capacidad.

        $item = $this->attachActiveItem($order);
        $order->refresh()->load('payments.refunds');

        $this->assertSame('already_fully_refunded', $order->refundItemBlockedReason($item));
    }

    public function test_refund_allowed_on_cancelled_item(): void
    {
        // Sub-fase 7.2e.1bis (decisión #154): items cancelados SÍ permiten
        // refund posterior. El flujo operativo es "cancelar item (libera plaza)
        // + refundar después cuando el operador lo decide". Cambio sobre 7.2e.1
        // donde bloqueábamos por `item_cancelled`.
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);
        $item->markCancelled($by);

        $this->assertNull($order->refundItemBlockedReason($item));
        $this->assertTrue($order->canRefundItem($item));
    }

    public function test_refund_blocked_when_item_and_all_children_already_fully_refunded(): void
    {
        // Caso bloqueante: principal + TODOS sus children full-refunded → no
        // queda nada por devolver. `item_already_fully_refunded` aplica.
        $by = User::factory()->create();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1000);

        // Pre-refundar el item entero (1000 céntimos). Sin children.
        $this->attachSucceededRefund($payment, 1000, $item->id);
        $order->refresh()->load('payments.refunds');

        $this->assertSame('item_already_fully_refunded', $order->refundItemBlockedReason($item));
    }

    public function test_refund_allowed_when_principal_fully_refunded_but_children_remain(): void
    {
        // Bug fix 7.2e.1bis2 (feedback empírico 2026-05-30): un pack con el
        // principal refundado (180€) pero complementos VIVOS (calcetines 3€
        // + taquilla 2€ + tarta 25€ + monitor 40€ = 70€) DEBE mantener el
        // botón ↩️ disponible — el operador necesita poder refundar los
        // complementos sueltos. Antes del fix, `item_already_fully_refunded`
        // se devolvía mirando SOLO el principal, ocultando el botón.
        $by = User::factory()->create();
        $order = $this->makePaidOrder(2500);
        $payment = $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1800);
        // 4 complementos vivos.
        $this->attachAddon($order, $pack);
        $this->attachAddon($order, $pack);
        $this->attachAddon($order, $pack);
        $this->attachAddon($order, $pack);

        // Refund completo del principal (1800 céntimos) — pero NO de los addons.
        $this->attachSucceededRefund($payment, 1800, $pack->id);

        $packFresh = $pack->fresh()->load('children');
        $order->refresh()->load('payments.refunds');

        $this->assertNull($order->refundItemBlockedReason($packFresh));
        $this->assertTrue($order->canRefundItem($packFresh));
    }

    public function test_refund_blocked_when_item_is_addon(): void
    {
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $parent = $this->attachActiveItem($order);
        $addon = $this->attachAddon($order, $parent);

        $this->assertSame('item_is_addon', $order->refundItemBlockedReason($addon));
    }

    public function test_refund_blocked_when_order_expired_in_practice(): void
    {
        // Order pending con expires_at en el pasado → displayStatus()='expired'.
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-EX0001',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->subHour(),
        ]);
        $item = $this->attachActiveItem($order);

        // No es paid → primer bloqueo es order_not_paid.
        $this->assertSame('order_not_paid', $order->refundItemBlockedReason($item));
    }

    // ─── totalRefundedCents / itemRefundedCents / refundableCapacityCents ─

    public function test_total_refunded_cents_sums_only_succeeded(): void
    {
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 500);
        $this->attachSucceededRefund($payment, 300);
        $this->attachFailedRefund($payment, 999);     // ignorado
        $this->attachPendingRefund($payment, 222);    // ignorado

        $order->refresh()->load('payments.refunds');
        $this->assertSame(800, $order->totalRefundedCents());
    }

    public function test_item_refunded_cents_filters_by_order_item_id(): void
    {
        $order = $this->makePaidOrder();
        $payment = $this->attachPaidPayment($order);
        $itemA = $this->attachActiveItem($order);
        $itemB = $this->attachActiveItem($order);

        $this->attachSucceededRefund($payment, 400, $itemA->id);
        $this->attachSucceededRefund($payment, 600, $itemB->id);
        $this->attachSucceededRefund($payment, 100, null);   // total — no asignable a item.

        $order->refresh()->load('payments.refunds');
        $this->assertSame(400, $order->itemRefundedCents($itemA));
        $this->assertSame(600, $order->itemRefundedCents($itemB));
    }

    public function test_refundable_capacity_subtracts_existing_refunds(): void
    {
        $order = $this->makePaidOrder(1815);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 1000);

        $order->refresh()->load('payments.refunds');
        $this->assertSame(815, $order->refundableCapacityCents());
    }

    public function test_refundable_capacity_returns_zero_when_no_paid_payment(): void
    {
        $order = $this->makePaidOrder();
        $this->assertSame(0, $order->refundableCapacityCents());
    }

    public function test_item_original_total_multiplies_quantity_and_unit_price(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order, quantity: 3, unitPrice: 1500);

        $this->assertSame(4500, $order->itemOriginalTotal($item));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

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

    private function makePaidOrder(int $total = 1815, string $code = ''): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code !== '' ? $code : 'JJ-PI'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total, 'tax' => 0, 'total' => $total, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function makePendingOrder(): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-PND'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'expires_at' => now()->addHour(),
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

    private function attachAddon(Order $order, OrderItem $parent): OrderItem
    {
        // Addon hereda el slot del parent, parent_item_id no nulo.
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 300,
        ]);
    }
}
