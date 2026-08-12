<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\Redsys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sub-fase 7.2e.1bis (decisión #154) — orquestador
 * `Order::executePartialRefundBatch`.
 *
 * Modela "refundar múltiples items en una sola operación del operador" (modal
 * de checkboxes). Cada item es una transacción independiente; si uno falla
 * por REST, abortamos los siguientes (no desperdiciar más llamadas REST si
 * el banco está caído). Pre-validación bloquea el batch entero ante
 * inconsistencias del input.
 */
class OrderExecutePartialRefundBatchTest extends TestCase
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

    public function test_batch_default_refunds_without_cancelling_items(): void
    {
        // Sub-fase 7.2e.1bis4 (decisión #157): refund y cancel son ahora
        // dimensiones independientes. El default del batch es
        // `alsoCancelItems=false` — refundar dinero NO cancela los items.
        Http::fake();  // Manual mode — no REST.
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1500);
        $a = $this->attachAddon($order, $pack, unitPrice: 500);
        $b = $this->attachAddon($order, $pack, unitPrice: 400);

        $result = $order->executePartialRefundBatch(
            itemIds: [$pack->id, $a->id, $b->id],
            by: $by,
            mode: PaymentRefund::MODE_MANUAL,
        );

        $this->assertCount(3, $result['succeeded']);

        // Importes refundados (3 succeeded), pero items NO cancelados.
        $this->assertFalse($pack->fresh()->isCancelled());
        $this->assertFalse($a->fresh()->isCancelled());
        $this->assertFalse($b->fresh()->isCancelled());

        // Order agregados (refund_amount_cents cuenta lo devuelto).
        $order->refresh();
        $this->assertSame(2400, (int) $order->refund_amount_cents);
    }

    public function test_batch_with_also_cancel_items_true_cancels_each_in_atomic_txn(): void
    {
        // Path opt-in: clientes programáticos que necesitan cancel atómico
        // con el refund pasan `alsoCancelItems=true`. Cada item refundado
        // queda soft-cancelled en la MISMA 2ª txn que el refund — atomicidad
        // a nivel item (no del batch entero).
        Http::fake();
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1500);
        $a = $this->attachAddon($order, $pack, unitPrice: 500);

        $result = $order->executePartialRefundBatch(
            itemIds: [$pack->id, $a->id],
            by: $by,
            mode: PaymentRefund::MODE_MANUAL,
            alsoCancelItems: true,
        );

        $this->assertCount(2, $result['succeeded']);
        $this->assertTrue($pack->fresh()->isCancelled());
        $this->assertTrue($a->fresh()->isCancelled());
    }

    public function test_batch_aborts_after_first_failure(): void
    {
        // Configurar 3 items: pack + 2 addons. Primera REST call OK, segunda
        // denegada → tercera NO se intenta.
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $pack = $this->attachActiveItem($order, unitPrice: 1500);
        $a = $this->attachAddon($order, $pack, unitPrice: 500);
        $b = $this->attachAddon($order, $pack, unitPrice: 400);

        Http::fake([
            Redsys::REST_URL_TEST => Http::sequence()
                ->push($this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ), 200)
                ->push($this->fakeRedsysRefundResponse(
                    '0190',  // denegado
                    $payment->gateway_order,
                ), 200),
        ]);

        $result = $order->executePartialRefundBatch(
            itemIds: [$pack->id, $a->id, $b->id],
            by: $by,
            mode: PaymentRefund::MODE_REST,
            alsoCancelItems: true,  // explícito — el test simula path cancel+refund
        );

        // 1 OK, 1 failed, 1 aborted (no se intentó).
        $this->assertCount(1, $result['succeeded']);
        $this->assertCount(1, $result['failed']);
        $this->assertCount(1, $result['aborted']);
        $this->assertSame($b->id, $result['aborted'][0]);

        // Pack quedó cancelled (alsoCancel=true + refund OK), addonA NO
        // (refund falló, sin cancel), addonB NO (no se intentó).
        $this->assertTrue($pack->fresh()->isCancelled());
        $this->assertFalse($a->fresh()->isCancelled());
        $this->assertFalse($b->fresh()->isCancelled());

        // Order tiene refund parcial (solo el del pack).
        $order->refresh();
        $this->assertSame(1500, (int) $order->refund_amount_cents);

        // Solo 2 HTTP calls (no la 3ª — abortada).
        Http::assertSentCount(2);
    }

    public function test_batch_aborts_entirely_if_item_not_in_order(): void
    {
        // Pre-validación: si CUALQUIER item del input no pertenece al Order,
        // abortamos sin tocar nada (todo-o-nada en la pre-fase).
        Http::fake();
        $by = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);
        $otherOrder = $this->makePaidOrder(code: 'JJ-EVL2');
        $otherItem = $this->attachActiveItem($otherOrder);

        $result = $order->executePartialRefundBatch(
            itemIds: [$item->id, $otherItem->id],
            by: $by,
            mode: PaymentRefund::MODE_MANUAL,
        );

        $this->assertCount(0, $result['succeeded']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame('not_in_order', $result['failed'][0]['reason']);

        // El item legítimo NO se procesó.
        $this->assertFalse($item->fresh()->isCancelled());
        $this->assertSame(0, PaymentRefund::count());
    }

    public function test_batch_empty_input_returns_no_action(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);

        $result = $order->executePartialRefundBatch(
            itemIds: [],
            by: $by,
            mode: PaymentRefund::MODE_REST,
        );

        $this->assertCount(0, $result['succeeded']);
        $this->assertCount(0, $result['failed']);
        $this->assertCount(0, $result['aborted']);
    }

    public function test_batch_skips_items_already_fully_refunded_with_aborted(): void
    {
        // Item con refund total previo → en el bucle, su remainder es 0 →
        // entra en la rama "item_already_fully_refunded" → aborta el resto.
        Http::fake();
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $a = $this->attachActiveItem($order, unitPrice: 1200);
        $b = $this->attachActiveItem($order, unitPrice: 1000);

        // Pre-refund completo de A.
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $a->id,
            'amount_cents' => 1200,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_SUCCEEDED,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'gateway_response_code' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
            'requested_by' => $by->id,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $result = $order->fresh()->executePartialRefundBatch(
            itemIds: [$a->id, $b->id],
            by: $by,
            mode: PaymentRefund::MODE_MANUAL,
        );

        // A falla con item_already_fully_refunded, B abortado.
        $this->assertCount(0, $result['succeeded']);
        $this->assertCount(1, $result['failed']);
        $this->assertSame('item_already_fully_refunded', $result['failed'][0]['reason']);
        $this->assertSame([$b->id], $result['aborted']);
        $this->assertFalse($b->fresh()->isCancelled());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
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

    private function makePaidOrder(int $total = 1815, string $code = ''): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code !== '' ? $code : 'JJ-BR'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
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

    private function attachAddon(Order $order, OrderItem $parent, int $unitPrice = 200): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => $unitPrice,
        ]);
    }

    private function fakeRedsysRefundResponse(string $dsResponse, string $gatewayOrder): array
    {
        $redsys = app(Redsys::class);
        $cfg = $redsys->config();
        $params = $redsys->createMerchantParameters([
            'Ds_Order' => $gatewayOrder,
            'Ds_Response' => $dsResponse,
            'Ds_MerchantCode' => $cfg['merchant_code'],
            'Ds_Terminal' => $cfg['terminal'],
        ]);
        $sig = $redsys->createMerchantSignature($cfg['secret_key'], $params, $gatewayOrder);

        return [
            'Ds_SignatureVersion' => Redsys::SIGNATURE_VERSION,
            'Ds_MerchantParameters' => $params,
            'Ds_Signature' => $sig,
        ];
    }
}
