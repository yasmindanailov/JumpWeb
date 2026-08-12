<?php

namespace Tests\Feature\Admin\Orders;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\RateType;
use App\Models\Role;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Support\Redsys;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sub-fase 7.2e cimientos — orquestador Order::executePartialRefund.
 *
 * Simétrico a `executeFullRefund` (#142) — clona la estructura 2-txn + lock +
 * mutex + REST fuera de lock + audit categorizado + email post-éxito. La
 * cobertura sigue el patrón ya validado de OrderAdminActionsTest (#142 REST
 * tests) pero parametrizado por importe + item + alsoCancelItem.
 *
 * Tests:
 *  - REST happy path → succeeded + Order.refunded_at + refund_amount_cents
 *    agregado + audit `orders.item_refunded` + envío al endpoint Redsys con el
 *    importe parcial. El email NO lo envía el orquestador (responsabilidad del
 *    caller, mismo patrón que `executeFullRefund`).
 *  - REST gateway denied → failed `gateway_denied` + audit `*_failed`.
 *  - REST transport error → failed `transport_error_check_portal`.
 *  - REST HTTP 5xx → tratado como transport_error.
 *  - Manual mode → succeeded sin REST.
 *  - alsoCancelItem=true → item.cancelled_at seteado.
 *  - alsoCancelItem=false → item activo tras éxito.
 *  - Inflight: segundo intento concurrente bloqueado per-item.
 *  - amount > capacidad → bloqueado pre-REST.
 *  - amount ≤ 0 → invalid_amount.
 *  - mode inválido → invalid_mode.
 *  - Item de otro Order → not_in_order.
 *  - Item addon → item_is_addon.
 *  - Order sin paid payment → no_paid_payment.
 *  - 2 refunds parciales sucesivos agregan correctamente en refund_amount_cents.
 *  - Order.refunded_at se PRESERVA en refunds sucesivos (no se reescribe).
 */
class OrderExecutePartialRefundTest extends TestCase
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

    public function test_rest_happy_path_records_succeeded(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        $result = $order->fresh()->executePartialRefund(
            item: $item,
            amountCents: 1200,
            by: $by,
            mode: PaymentRefund::MODE_REST,
            alsoCancelItem: false,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(PaymentRefund::REDSYS_REFUND_SUCCESS_CODE, $result['gateway_response_code']);

        // POST a Redsys con el importe parcial (1200), no el total del Order.
        Http::assertSent(function ($request) use ($payment): bool {
            if ($request->url() !== Redsys::REST_URL_TEST) {
                return false;
            }
            $body = $request->data();
            $decoded = app(Redsys::class)->decodeMerchantParameters($body['Ds_MerchantParameters']);

            return ($decoded['DS_MERCHANT_ORDER'] ?? null) === $payment->gateway_order
                && ($decoded['DS_MERCHANT_TRANSACTIONTYPE'] ?? null) === '3'
                && ($decoded['DS_MERCHANT_AMOUNT'] ?? null) === '1200';
        });

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(1200, (int) $refund->amount_cents);
        $this->assertSame($item->id, (int) $refund->order_item_id);

        // Order agregados.
        $order->refresh();
        $this->assertNotNull($order->refunded_at);
        $this->assertSame(1200, (int) $order->refund_amount_cents);
        // status sigue paid: refund parcial NO transiciona el Order (solo el item lo haría con alsoCancelItem).
        $this->assertSame(Order::STATUS_PAID, $order->status);

        // Audit log.
        $log = AuditLog::where('action', 'orders.item_refunded')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($item->id, $log->payload['order_item_id']);
        $this->assertSame(1200, $log->payload['amount_cents']);
        $this->assertFalse($log->payload['also_cancel_item_requested']);
    }

    public function test_also_cancel_item_marks_item_cancelled_in_same_transaction(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        $result = $order->fresh()->executePartialRefund(
            $item,
            1200,
            $by,
            PaymentRefund::MODE_REST,
            alsoCancelItem: true,
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['also_cancelled_item']);

        $item->refresh();
        $this->assertNotNull($item->cancelled_at);
        $this->assertSame($by->id, (int) $item->cancelled_by);
    }

    public function test_rest_gateway_denied_records_failed(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse('0190', $payment->gateway_order),
                200,
            ),
        ]);

        $result = $order->fresh()->executePartialRefund($item, 1200, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('gateway_failed', $result['reason']);

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame(PaymentRefund::FAILURE_GATEWAY_DENIED, $refund->failure_reason);
        $this->assertSame('0190', $refund->gateway_response_code);

        // Order intacto.
        $order->refresh();
        $this->assertNull($order->refunded_at);

        // Audit del fallo.
        $log = AuditLog::where('action', 'orders.item_refund_failed')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame(PaymentRefund::FAILURE_GATEWAY_DENIED, $log->payload['failure_reason']);
    }

    public function test_rest_bare_error_code_surfaces_the_redsys_code(): void
    {
        // Recomendación A (verificado contra el sandbox real): una respuesta de ERROR "bare" de
        // Redsys `{"errorCode":"SIS0054"}` (HTTP 200, SIN Ds_MerchantParameters ni firma) ahora se
        // surfacea como denegación con el CÓDIGO REAL (SIS0054 = no existe la operación a devolver),
        // no como un genérico «missing Ds_MerchantParameters». Clasificarla como fallo es seguro
        // (fail-closed: nunca damos por buena una devolución sin firma; el operador revisa el código).
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(['errorCode' => 'SIS0054'], 200),
        ]);

        $result = $order->fresh()->executePartialRefund($item, 1200, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('gateway_failed', $result['reason']);

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_FAILED, $refund->status);
        $this->assertSame(PaymentRefund::FAILURE_GATEWAY_DENIED, $refund->failure_reason);
        $this->assertSame('SIS0054', $refund->gateway_response_code, 'el código real de Redsys se surfacea, no un genérico');
    }

    public function test_rest_transport_error_records_failed_with_transport_reason(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => fn () => throw new ConnectionException('Connection timed out'),
        ]);

        $result = $order->fresh()->executePartialRefund($item, 1200, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('gateway_failed', $result['reason']);

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::FAILURE_TRANSPORT, $refund->failure_reason);
        $this->assertNull($refund->gateway_response_code);
    }

    public function test_rest_http_5xx_treated_as_transport_error(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response('<html>Gateway 502</html>', 502),
        ]);

        $result = $order->fresh()->executePartialRefund($item, 1200, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::FAILURE_TRANSPORT, $refund->failure_reason);
    }

    public function test_manual_mode_records_succeeded_without_rest_call(): void
    {
        Http::fake();  // sin nada que matchear — si llamamos a Redsys, falla.
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        $result = $order->fresh()->executePartialRefund($item, 1200, $by, PaymentRefund::MODE_MANUAL, false);

        $this->assertTrue($result['ok']);
        Http::assertNothingSent();

        $refund = PaymentRefund::where('order_item_id', $item->id)->latest()->first();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame(PaymentRefund::MODE_MANUAL, $refund->mode);
        $this->assertSame(PaymentRefund::MANUAL_RESPONSE_MARKER, $refund->gateway_response_code);
    }

    public function test_multiple_partial_refunds_aggregate_in_order_refund_amount_cents(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $itemA = $this->attachActiveItem($order, unitPrice: 1000);
        $itemB = $this->attachActiveItem($order, unitPrice: 800);

        Http::fake([
            Redsys::REST_URL_TEST => Http::sequence()
                ->push($this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ), 200)
                ->push($this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ), 200),
        ]);

        $first = $order->fresh()->executePartialRefund($itemA, 1000, $by, PaymentRefund::MODE_REST, false);
        $this->assertTrue($first['ok']);
        $order->refresh();
        $firstRefundedAt = $order->refunded_at->copy();

        // Forzar gap temporal para distinguir si refunded_at se reescribiría. Usamos
        // `travel()` (reloj de prueba) en vez de `sleep(1)`: determinista y sin coste real.
        $this->travel(1)->seconds();

        $second = $order->fresh()->executePartialRefund($itemB, 800, $by, PaymentRefund::MODE_REST, false);
        $this->assertTrue($second['ok']);

        $order->refresh();
        // Agregado de ambos refunds.
        $this->assertSame(1800, (int) $order->refund_amount_cents);
        // refunded_at PRESERVADO de la primera (no reescrito al segundo refund).
        $this->assertEquals($firstRefundedAt->toDateTimeString(), $order->refunded_at->toDateTimeString());
    }

    public function test_inflight_pending_refund_per_item_blocks_second_attempt(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        // Simular un PaymentRefund pending pre-existente sobre el MISMO item.
        // Materializa el escenario "primer click creó la pending row pero la REST
        // call sigue en vuelo y el operador hizo doble-click".
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $item->id,
            'amount_cents' => 1200,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => $by->id,
            'requested_at' => now(),
        ]);

        $result = $order->fresh()->executePartialRefund($item, 1200, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('inflight_refund', $result['reason']);

        // No se creó una segunda fila — sigue habiendo solo la pending.
        $this->assertSame(1, PaymentRefund::where('order_item_id', $item->id)->count());
    }

    public function test_inflight_on_one_item_does_not_block_refund_on_another_item(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $itemA = $this->attachActiveItem($order, unitPrice: 1000);
        $itemB = $this->attachActiveItem($order, unitPrice: 800);

        // Pending sobre itemA.
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $itemA->id,
            'amount_cents' => 1000,
            'currency' => 'EUR',
            'status' => PaymentRefund::STATUS_PENDING,
            'mode' => PaymentRefund::MODE_REST,
            'gateway_order' => $payment->gateway_order,
            'requested_by' => $by->id,
            'requested_at' => now(),
        ]);

        Http::fake([
            Redsys::REST_URL_TEST => Http::response(
                $this->fakeRedsysRefundResponse(
                    PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
                    $payment->gateway_order,
                ),
                200,
            ),
        ]);

        // Refund sobre itemB debe pasar (mutex es per-item, no per-Order).
        $result = $order->fresh()->executePartialRefund($itemB, 800, $by, PaymentRefund::MODE_REST, false);

        $this->assertTrue($result['ok']);
    }

    public function test_amount_exceeding_capacity_blocked_pre_rest(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder(1000);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 500);

        Http::fake();  // si llamamos a Redsys, falla.

        // Pedir refund de 9999 cuando solo hay 1000 cobrados.
        $result = $order->fresh()->executePartialRefund($item, 9999, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('exceeds_refundable_capacity', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_zero_amount_rejected(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $result = $order->executePartialRefund($item, 0, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_amount', $result['reason']);
    }

    public function test_invalid_mode_rejected(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order);

        $result = $order->executePartialRefund($item, 500, $by, 'invented_mode', false);

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_mode', $result['reason']);
    }

    public function test_item_from_another_order_rejected(): void
    {
        $by = $this->admin();
        $orderA = $this->makePaidOrder(code: 'JJ-OW0001');
        $orderB = $this->makePaidOrder(code: 'JJ-OW0002');
        $this->attachPaidPayment($orderA);
        $itemOfB = $this->attachActiveItem($orderB);

        $result = $orderA->fresh()->executePartialRefund($itemOfB, 500, $by, PaymentRefund::MODE_MANUAL, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('not_in_order', $result['reason']);
    }

    public function test_addon_item_accepted_by_orchestrator(): void
    {
        // Sub-fase 7.2e.1bis (decisión #154): el orquestador `executePartialRefund`
        // acepta addons como input — el bloqueo "no refundes addons
        // directamente desde la UI" vive en `refundItemBlockedReason()`, no en
        // el orquestador. Esto permite que `executePartialRefundBatch` itere
        // sobre pack + complementos sin requerir 2 métodos distintos.
        $by = $this->admin();
        $order = $this->makePaidOrder();
        $this->attachPaidPayment($order);
        $parent = $this->attachActiveItem($order);
        $addon = $this->attachAddon($order, $parent);

        $result = $order->fresh()->executePartialRefund($addon, 200, $by, PaymentRefund::MODE_MANUAL, false);

        $this->assertTrue($result['ok']);
        $this->assertSame(PaymentRefund::MANUAL_RESPONSE_MARKER, $result['gateway_response_code']);
    }

    public function test_order_without_paid_payment_returns_no_paid_payment(): void
    {
        $by = $this->admin();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);
        // Sin attachPaidPayment.

        $result = $order->executePartialRefund($item, 500, $by, PaymentRefund::MODE_REST, false);

        $this->assertFalse($result['ok']);
        $this->assertSame('no_paid_payment', $result['reason']);
    }

    public function test_pending_total_refund_reserves_capacity_blocking_a_concurrent_partial(): void
    {
        // Auditoría Fase 1 (M5): un reembolso TOTAL pendiente (REST en vuelo, order_item_id NULL)
        // reserva toda la capacidad. Un parcial concurrente NO debe poder devolver encima
        // (sobre-reembolso). Antes del fix la capacidad solo restaba succeeded → el parcial pasaba.
        $by = $this->admin();
        $order = $this->makePaidOrder(2000);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 2000);

        // Total pendiente en vuelo (simula un full refund cuya REST aún no volvió).
        PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => null,
            'amount_cents' => 2000, 'currency' => 'EUR', 'status' => PaymentRefund::STATUS_PENDING,
            'mode' => PaymentRefund::MODE_REST, 'gateway_order' => $payment->gateway_order,
            'requested_by' => $by->id, 'requested_at' => now(),
        ]);

        $this->assertSame(0, $order->fresh()->refundableCapacityCents(), 'el total pendiente reserva toda la capacidad');

        $result = $order->fresh()->executePartialRefund(
            item: $item, amountCents: 500, by: $by, mode: PaymentRefund::MODE_MANUAL, alsoCancelItem: false,
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('exceeds_refundable_capacity', $result['reason']);
    }

    public function test_partial_refund_blocked_when_amount_exceeds_the_items_own_remainder(): void
    {
        // Auditoría Fase 1 (L1): la capacidad AGREGADA no basta. Un item barato no puede reembolsarse
        // por más de SU remanente aunque el pedido tenga capacidad de OTROS items. El pedido cobró 3000
        // (capacidad amplia) pero el item vale 500; pedir 1500 sobre ese item se bloquea per-item.
        $by = $this->admin();
        $order = $this->makePaidOrder(3000);
        $this->attachPaidPayment($order);
        $cheap = $this->attachActiveItem($order, quantity: 1, unitPrice: 500);
        $this->attachActiveItem($order, quantity: 1, unitPrice: 2500); // otro item: aporta capacidad agregada

        $blocked = $order->fresh()->executePartialRefund(
            item: $cheap, amountCents: 1500, by: $by, mode: PaymentRefund::MODE_MANUAL, alsoCancelItem: false,
        );
        $this->assertFalse($blocked['ok']);
        $this->assertSame('exceeds_item_refundable', $blocked['reason']);

        // El importe correcto (≤ remanente del item) sí pasa.
        $ok = $order->fresh()->executePartialRefund(
            item: $cheap, amountCents: 500, by: $by, mode: PaymentRefund::MODE_MANUAL, alsoCancelItem: false,
        );
        $this->assertTrue($ok['ok']);
    }

    public function test_rest_refund_with_unsigned_response_is_rejected_not_accepted(): void
    {
        // Auditoría Fase 1 (endurecimiento del camino del dinero): una respuesta REST de devolución
        // SIN firma NO se acepta a ciegas (antes el `!== ''` la daba por buena al faltar Ds_Signature).
        // Se trata como malformed → el refund FALLA y queda registrado para verificar en el portal.
        $by = $this->admin();
        $order = $this->makePaidOrder(2400);
        $payment = $this->attachPaidPayment($order);
        $item = $this->attachActiveItem($order, unitPrice: 1200);

        // Respuesta con Ds_MerchantParameters (Ds_Response=0900, "éxito") pero SIN Ds_Signature.
        $redsys = app(Redsys::class);
        $params = $redsys->createMerchantParameters([
            'Ds_Order' => $payment->gateway_order,
            'Ds_Response' => PaymentRefund::REDSYS_REFUND_SUCCESS_CODE,
        ]);
        Http::fake([Redsys::REST_URL_TEST => Http::response(['Ds_MerchantParameters' => $params], 200)]);

        $result = $order->fresh()->executePartialRefund(
            item: $item, amountCents: 1200, by: $by, mode: PaymentRefund::MODE_REST, alsoCancelItem: false,
        );

        $this->assertFalse($result['ok'], 'una respuesta sin firma NO cuenta como reembolso exitoso');
        $this->assertSame(
            PaymentRefund::STATUS_FAILED,
            PaymentRefund::where('order_item_id', $item->id)->latest('id')->first()->status,
        );
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
            'code' => $code !== '' ? $code : 'JJ-PR'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
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

    private function attachAddon(Order $order, OrderItem $parent): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $parent->slot_id,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
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
