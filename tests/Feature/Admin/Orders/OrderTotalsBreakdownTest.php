<?php

namespace Tests\Feature\Admin\Orders;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #171 (feedback clienta) — Sub-líneas del desglose del pedido.
 *
 * Verifica:
 *  - `OrderAdjustment::breakdownLabel()`: formato compacto delta elegido por la
 *    clienta (cambio de cantidad "+N nombre", cambio de producto "Cambio a X",
 *    complementos "+N nombre"), con fallback limpio al nombre del item para
 *    ajustes legacy (context solo con claves o null).
 *  - El partial `order-totals` pinta:
 *      · "A cobrar en el parque" → un renglón por ajuste extra_due PENDIENTE.
 *      · "Devuelto" → un renglón por reembolso CON éxito ligado a un item
 *        (los del pedido completo / legacy no detallan renglón).
 */
class OrderTotalsBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $counter = 0;

    private int $paymentCounter = 0;

    // ─── breakdownLabel() ───────────────────────────────────────────────

    public function test_breakdown_label_quantity_change_shows_delta_and_name(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['changes' => ['quantity_change' => ['old' => 5, 'new' => 7]]]);

        $this->assertSame('+2 '.$this->jumpType->tr('name'), $label);
    }

    public function test_breakdown_label_product_change_shows_target_product(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['changes' => ['product_change' => ['old' => 'Pack Jump', 'new' => 'Pack Kids']]]);

        $this->assertSame(__('admin.orders.order_financial.breakdown.product_change', ['name' => 'Pack Kids']), $label);
    }

    public function test_breakdown_label_product_change_takes_priority_over_quantity(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['changes' => [
            'product_change' => ['old' => 'A', 'new' => 'Pack Kids'],
            'quantity_change' => ['old' => 1, 'new' => 2],
        ]]);

        $this->assertSame(__('admin.orders.order_financial.breakdown.product_change', ['name' => 'Pack Kids']), $label);
    }

    public function test_breakdown_label_addon_added_shows_qty_and_name(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['addon_change' => [
            'added' => [['name' => 'Calcetines', 'qty' => 3]],
            'removed' => [], 'updated' => [],
        ]]);

        $this->assertSame('+3 Calcetines', $label);
    }

    public function test_breakdown_label_addon_updated_shows_positive_delta(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['addon_change' => [
            'added' => [], 'removed' => [],
            'updated' => [['name' => 'Gorro', 'old' => 1, 'new' => 4]],
        ]]);

        $this->assertSame('+3 Gorro', $label);
    }

    public function test_breakdown_label_multiple_addons_joined(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, ['addon_change' => [
            'added' => [['name' => 'Calcetines', 'qty' => 2]],
            'removed' => [],
            'updated' => [['name' => 'Gorro', 'old' => 0, 'new' => 1]],
        ]]);

        $this->assertSame('+2 Calcetines, +1 Gorro', $label);
    }

    public function test_breakdown_label_legacy_keys_only_falls_back_to_item_name(): void
    {
        $item = $this->makeItem();

        // Formato legacy: context guardaba solo las CLAVES (lista de strings).
        $label = $this->labelFor($item, ['changes' => ['quantity_change']]);

        $this->assertSame($this->jumpType->tr('name'), $label);
    }

    public function test_breakdown_label_null_context_falls_back_to_item_name(): void
    {
        $item = $this->makeItem();

        $label = $this->labelFor($item, null);

        $this->assertSame($this->jumpType->tr('name'), $label);
    }

    // ─── Render del partial: "A cobrar en el parque" ────────────────────

    public function test_gate_breakdown_subline_renders_for_pending_adjustment(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order); // slot futuro → no finalizado
        OrderAdjustment::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE,
            'amount_cents' => 2400,
            'currency' => 'EUR',
            'reason' => 'item_edit',
            'context' => ['changes' => ['quantity_change' => ['old' => 1, 'new' => 3]]],
            'applied_by' => User::factory()->create()->id,
        ]);

        $html = $this->renderTotals($order);

        $this->assertStringContainsString('+2 '.$this->jumpType->tr('name'), $html);
        $this->assertStringContainsString('24,00', $html);
    }

    public function test_gate_breakdown_subline_hidden_when_item_finished(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);
        // Mover el item a un slot pasado → finalizado en la práctica → resuelto.
        $past = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->subDays(2)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '10:59:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
        $item->update(['slot_id' => $past->id]);
        OrderAdjustment::create([
            'order_id' => $order->id, 'order_item_id' => $item->id,
            'type' => OrderAdjustment::TYPE_EXTRA_DUE, 'amount_cents' => 2400,
            'currency' => 'EUR', 'reason' => 'item_edit',
            'context' => ['changes' => ['quantity_change' => ['old' => 1, 'new' => 3]]],
            'applied_by' => User::factory()->create()->id,
        ]);

        $html = $this->renderTotals($order);

        // Ni el bloque "A cobrar en el parque" ni su sub-línea aparecen.
        $this->assertStringNotContainsString(__('admin.orders.order_financial.pending_at_gate'), $html);
        $this->assertStringNotContainsString('+2 '.$this->jumpType->tr('name'), $html);
    }

    // ─── Render del partial: "Devuelto" ─────────────────────────────────

    public function test_refund_breakdown_subline_renders_for_per_item_refund(): void
    {
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);
        $payment = $this->attachPaidPayment($order);
        $this->attachSucceededRefund($payment, 400, $item->id);
        // El total "Devuelto" se lee del agregado legacy-safe del Order.
        $order->update(['refund_amount_cents' => 400, 'refunded_at' => now()]);

        $html = $this->renderTotals($order->fresh());

        $this->assertStringContainsString(__('admin.orders.amount_refunded'), $html);
        $this->assertStringContainsString('↳ '.$this->jumpType->tr('name'), $html);
        $this->assertStringContainsString('4,00', $html);
    }

    public function test_refund_breakdown_no_subline_for_full_order_refund(): void
    {
        $order = $this->makePaidOrder();
        $this->attachActiveItem($order);
        $payment = $this->attachPaidPayment($order);
        // Reembolso del pedido COMPLETO → order_item_id null → sin renglón.
        $this->attachSucceededRefund($payment, 2000, null);
        $order->update(['refund_amount_cents' => 2000, 'refunded_at' => now()]);

        $html = $this->renderTotals($order->fresh());

        $this->assertStringContainsString(__('admin.orders.amount_refunded'), $html);
        $this->assertStringNotContainsString('↳ ', $html);
    }

    public function test_refund_breakdown_no_subline_for_legacy_refund_without_payment_refund(): void
    {
        // Refund legacy pre-#142: solo agregado en el Order, sin fila
        // payment_refunds → total visible, sin renglón de detalle.
        $order = $this->makePaidOrder();
        $this->attachActiveItem($order);
        $this->attachPaidPayment($order);
        $order->update(['refund_amount_cents' => 1000, 'refunded_at' => now()]);

        $html = $this->renderTotals($order->fresh());

        $this->assertStringContainsString(__('admin.orders.amount_refunded'), $html);
        $this->assertStringNotContainsString('↳ ', $html);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    public function test_pendiente_devolucion_and_valor_final_render_for_owed_back(): void
    {
        // El pedido facturó online 20,00 (2 uds) pero el item está a 1 ud (10,00):
        // se deben 10,00 aún no devueltos → "Pendiente de devolución" bajo el headline
        // "Valor final del pedido" (rediseño valor-primero #199). El caption ancla el
        // bruto pagado por web (20,00) para conciliar con el banco.
        $order = $this->makePaidOrder();      // total 2000
        $this->attachPaidPayment($order);     // pagó 20,00 online — ancla de caja de «pendiente» (#225)
        $this->attachActiveItem($order);      // charged 1000 (estado reducido)

        $html = $this->renderTotals($order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType']));

        $this->assertStringContainsString(__('admin.orders.order_financial.valor_final'), $html);
        $this->assertStringContainsString(__('admin.orders.order_financial.pendiente_devolucion'), $html);
        $this->assertStringContainsString('−10,00', $html); // pendiente de devolución
        $this->assertStringContainsString('20,00', $html);  // bruto pagado por web (caption)
    }

    public function test_no_breakdown_when_products_back_the_total(): void
    {
        // total == valor de productos → sin cambios ni devoluciones → caso SIMPLE:
        // solo "Total" (sin headline "Valor final" ni "Pendiente de devolución").
        $order = $this->makePaidOrder();
        $order->forceFill(['total' => 1000])->save(); // = charged del item
        $this->attachActiveItem($order);              // charged 1000

        $html = $this->renderTotals($order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType']));

        $this->assertStringNotContainsString(__('admin.orders.order_financial.pendiente_devolucion'), $html);
        $this->assertStringNotContainsString(__('admin.orders.order_financial.valor_final'), $html);
        $this->assertStringContainsString(__('admin.orders.amount_total'), $html);
    }

    public function test_order_block_value_first_split_paid_online_plus_gate(): void
    {
        // Rediseño valor-primero (#199): Valor final = Pagado online + A cobrar en el
        // parque. Pagado 1 ud (10,00) online + subida a 2 uds con +10,00 a cobrar en
        // puerta → valor 20,00 = 10,00 online + 10,00 en el parque (reconcilia exacto).
        $order = $this->makePaidOrder();
        $order->forceFill(['total' => 1000])->save();   // pagó 1 ud online
        $item = $this->attachActiveItem($order);          // 1 ud @ 10,00
        $item->forceFill(['quantity' => 2])->save();      // ahora 2 uds → charged 20,00
        $order->applyExtraDue($item->fresh(), 1000, User::factory()->create(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        $html = $this->renderTotals($order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType', 'items.children.ticketType']));

        $this->assertStringContainsString(__('admin.orders.order_financial.valor_final'), $html);
        $this->assertStringContainsString(__('admin.orders.order_financial.pagado_online'), $html);
        $this->assertStringContainsString(__('admin.orders.order_financial.pending_at_gate'), $html);
        $this->assertStringContainsString('20,00', $html);   // valor final
        $this->assertStringContainsString('+10,00', $html);  // a cobrar en el parque
        // Lo pagado online respalda exactamente su parte → sin devolución pendiente.
        $this->assertStringNotContainsString(__('admin.orders.order_financial.pendiente_devolucion'), $html);
    }

    public function test_multi_reservation_shows_per_reservation_detail_lines(): void
    {
        // Detalle ↳ por reserva (elección de la clienta): con 2+ reservas, "Pagado
        // online" despliega un renglón por reserva. Fixture coherente: A pagó 1 ud
        // online y subió a 2 (10,00 online + 10,00 a cobrar en puerta); B pagó 1 ud.
        $order = $this->makePaidOrder();
        $order->forceFill(['total' => 2000])->save();   // 10,00 (A, 1 ud) + 10,00 (B)
        $a = $this->attachActiveItem($order);            // "Pulsera Jump" 1 ud @ 10,00
        $a->forceFill(['quantity' => 2])->save();        // sube a 2 uds → charged 20,00
        $order->applyExtraDue($a->fresh(), 1000, User::factory()->create(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);

        $kids = TicketType::create([
            'name' => ['es' => 'Pulsera Kids'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 2,
        ]);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(9)->format('Y-m-d'),
            'start_time' => '12:00:00', 'end_time' => '12:59:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $kids->id, 'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);

        $html = $this->renderTotals($order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType', 'items.children.ticketType']));

        // ↳ por reserva bajo "Pagado online" (mismas cifras que las cards).
        $this->assertStringContainsString('↳ '.$this->jumpType->tr('name'), $html);
        $this->assertStringContainsString('↳ Pulsera Kids', $html);
        // Y el detalle delta del cargo de puerta de la reserva A.
        $this->assertStringContainsString('+1 '.$this->jumpType->tr('name'), $html);
    }

    public function test_user_example_reconciles_value_first(): void
    {
        // Reproduce el ejemplo REAL de la clienta: pagó 288,00 € online; el valor final
        // es 216,00 € = 144,00 € pagado online + 72,00 € a cobrar en el parque (un +4
        // Cumpleaños Jump); y quedan 144,00 € pendientes de devolución (= 288 − 144). El
        // bloque valor-primero CUADRA: Valor final = Pagado online + A cobrar en el parque.
        $order = $this->makePaidOrder();
        $order->forceFill(['total' => 28800])->save();                       // pagó 288,00 € online
        $this->attachPaidPayment($order);                                    // pago real por web (ancla #225)
        $item = $this->attachActiveItem($order);
        $item->forceFill(['unit_price' => 1800, 'quantity' => 12])->save();  // 12 × 18 = 216,00 €
        $order->applyExtraDue($item->fresh(), 7200, User::factory()->create(), 'item_edit',
            ['changes' => ['quantity_change' => ['old' => 8, 'new' => 12]]]); // +4 → +72,00 a cobrar

        $html = $this->renderTotals($order->fresh(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType', 'items.children.ticketType']));

        $this->assertStringContainsString(__('admin.orders.order_financial.valor_final'), $html);
        $this->assertStringContainsString('216,00', $html);   // valor final
        $this->assertStringContainsString(__('admin.orders.order_financial.pagado_online'), $html);
        $this->assertStringContainsString('144,00', $html);   // pagado online (288 − 144 pendiente)
        $this->assertStringContainsString('+72,00', $html);   // a cobrar en el parque
        $this->assertStringContainsString(__('admin.orders.order_financial.pendiente_devolucion'), $html);
        $this->assertStringContainsString('−144,00', $html);  // pendiente de devolución
        $this->assertStringContainsString('288,00', $html);   // bruto pagado por web (caption)
    }

    private function renderTotals(Order $order): string
    {
        return view('filament.orders.partials.order-totals', ['record' => $order])->render();
    }

    private function labelFor(OrderItem $item, ?array $context): string
    {
        $adj = new OrderAdjustment;
        $adj->context = $context;
        $adj->setRelation('orderItem', $item);

        return $adj->breakdownLabel();
    }

    private function makeItem(): OrderItem
    {
        $order = $this->makePaidOrder();

        return $this->attachActiveItem($order);
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
            'name' => ['es' => 'Pulsera Jump'], 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makePaidOrder(): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-BD'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR',
            'paid_at' => now(),
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

    private function attachSucceededRefund(Payment $payment, int $amount, ?int $orderItemId): PaymentRefund
    {
        return PaymentRefund::create([
            'payment_id' => $payment->id,
            'order_item_id' => $orderItemId,
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
}
