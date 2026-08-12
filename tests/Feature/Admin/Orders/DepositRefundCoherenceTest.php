<?php

namespace Tests\Feature\Admin\Orders;

use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #225 iter. 4 — coherencia de cancelar/reembolsar (D7 + D9) a nivel modelo.
 *
 *  - D7: el reembolso REST solo es ejecutable en pagos Redsys (con `gateway_order`); un pago en
 *    CAJA (sin gateway) NO es Redsys-reembolsable → solo cabe el «reembolso manual» (record-only).
 *  - D7 (techo): el techo de reembolso por línea se ensancha al online ORIGINAL → el sobre-cobro
 *    de una bajada (D8) es reembolsable aparte.
 *  - D9: un reembolso COMPLETO no bloquea el CANCELAR individual (cancelar ≠ reembolsar).
 */
class DepositRefundCoherenceTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    public function test_redsys_refundable_only_when_payment_has_gateway_order(): void
    {
        $redsys = $this->makePaidOrder(3000);
        $this->attachPayment($redsys, 3000, 'redsys', gateway: '1234567890');
        $this->assertTrue($redsys->fresh(['payments'])->isRedsysRefundable());

        $cash = $this->makePaidOrder(3000);
        $this->attachPayment($cash, 3000, 'cash', gateway: null);
        $this->assertFalse($cash->fresh(['payments'])->isRedsysRefundable());
    }

    public function test_cash_order_manual_refund_records_without_redsys(): void
    {
        // D7: un pedido en caja se reembolsa SOLO en modo manual (record-only) — sin tocar Redsys,
        // que abortaría por falta de gateway_order. executeFullRefund(manual) cierra la cuenta.
        $order = $this->makePaidOrder(3000);
        $this->attachItem($order, qty: 1, unit: 3000);
        $this->attachPayment($order, 3000, 'cash', gateway: null);

        $result = $order->fresh(['payments.refunds', 'items'])->executeFullRefund(
            by: User::factory()->create(),
            mode: PaymentRefund::MODE_MANUAL,
            alsoCancel: false,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(3000, (int) $order->fresh()->refund_amount_cents);
    }

    public function test_cancel_item_still_available_after_full_refund(): void
    {
        // D9: reembolso completo (sin cancelar) → el Order sigue `paid`; cancelar productos SIGUE
        // disponible (cancelar ≠ reembolsar). Solo refundItem queda bloqueado (nada que devolver).
        $order = $this->makePaidOrder(3000);
        $item = $this->attachItem($order, qty: 1, unit: 3000);
        $this->attachPayment($order, 3000, 'redsys', gateway: '1234567890');

        // Modo manual (record-only) para no depender de Redsys; lo que probamos es que el FULL
        // refund no impide cancelar después (cancelar ≠ reembolsar), no el canal del reembolso.
        $order->fresh(['payments.refunds', 'items'])->executeFullRefund(
            by: User::factory()->create(),
            mode: PaymentRefund::MODE_MANUAL,
            alsoCancel: false,
        );

        $order = $order->fresh(['payments.refunds', 'items.slot', 'adjustments']);
        $this->assertSame(Order::STATUS_PAID, $order->status);            // sigue pagado
        $this->assertTrue($order->canCancelItem($item->fresh()));         // cancelar SÍ disponible
        $this->assertFalse($order->canRefundItem($item->fresh()));        // refundItem NO (ya devuelto)
    }

    public function test_refund_cap_widens_to_original_online_for_reduced_item(): void
    {
        // D7 (techo): tras una bajada, el techo de reembolso por línea = lo cobrado online ORIGINAL
        // − devuelto (no el valor actual) → el sobre-cobro es reembolsable aparte.
        $by = User::factory()->create();
        $order = $this->makePaidOrder(3600);
        $item = $this->attachItem($order, qty: 1, unit: 1200); // estado tras bajar 3 → 1 (charged 12)
        $this->attachPayment($order, 3600, 'redsys', gateway: '1234567890');
        // Marcador de la bajada 3 → 1 (porta el quantity_change para reconstruir el original).
        $order->recordReductionMarker($item, $by, ['changes' => ['quantity_change' => ['old' => 3, 'new' => 1]]]);

        $order = $order->fresh(['payments.refunds', 'items.ticketType', 'adjustments']);

        // online original = 3 × 12 = 36; techo = 36 − 0 = 36 (no 12). El operador puede reembolsar
        // el sobre-cobro (24) con «Reembolsar».
        $this->assertSame(3600, $order->itemRefundableRemainderCents($item->fresh()));
        $this->assertSame(2400, $order->itemPendingRefundCents($item->fresh())); // 36 original − 12 actual
    }

    public function test_per_guest_addon_child_of_deposit_pack_has_no_phantom_pending_refund(): void
    {
        // P1 (auditoría Fase 1): un COMPLEMENTO per-invitado de un pack CON señal se cobra ÍNTEGRO en
        // PUERTA (Opción A → online 0; el child nace con un `deposit_remainder` por su importe completo).
        // Tras un cambio de cantidad (rescale M4, que le pone el `quantity_change`), su «online original»
        // debe ser 0 — NO su valor pleno (lo daría `depositCents` del addon, deposit=none). Si no, aparece
        // un «pendiente de devolución» FANTASMA y el techo de reembolso se infla contra una línea sin cobro
        // online (drena la señal). Era el bug #225-F1 NO extendido a los children.
        $by = User::factory()->create();
        $order = $this->makePaidOrder(3000);

        $packItem = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $this->entry->id,
            'slot_id' => $this->makeSlot()->id, 'quantity' => 2, 'seats' => 2, 'unit_price' => 1500,
        ]);
        // Complemento per-invitado: 2 uds (1 por invitado) × 10 € = 20 €, TODO a puerta (Opción A).
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $packItem->id, 'ticket_type_id' => $this->entry->id,
            'slot_id' => null, 'quantity' => 2, 'free_quantity' => 0, 'seats' => 0, 'unit_price' => 1000,
        ]);
        $order->adjustments()->create([
            'order_item_id' => $child->id, 'type' => OrderAdjustment::TYPE_DEPOSIT_REMAINDER,
            'amount_cents' => 2000, 'currency' => 'EUR', 'reason' => 'deposit_remainder', 'applied_by' => $by->id,
        ]);

        // Rescale por bajar 2 → 1 invitado: credita el deposit_remainder del child y porta el quantity_change.
        $order = $order->fresh(['adjustments', 'items.ticketType']);
        $child = $order->items->firstWhere('id', $child->id);
        $order->applyDepositRemainderCredit($child, 1000, $by, 'addon_per_guest_rescale_reduction',
            ['changes' => ['quantity_change' => ['old' => 2, 'new' => 1]]]);
        $child->forceFill(['quantity' => 1])->save();

        $order = $order->fresh(['adjustments', 'items.ticketType', 'payments.refunds']);
        $child = $order->items->firstWhere('id', $child->id);

        $this->assertSame(0, $order->itemOriginalOnlineCents($child), 'online original del child de pack con señal = 0');
        $this->assertSame(0, $order->itemPendingRefundCents($child), 'sin «pendiente de devolución» fantasma');
        $this->assertSame(0, $order->itemRefundableRemainderCents($child), 'techo de reembolso = 0 (nada cobrado online)');
    }

    public function test_addon_child_without_deposit_keeps_full_online_original(): void
    {
        // No-regresión: un complemento de pago de un pack SIN señal SÍ se cobró online → su «online
        // original» tras un cambio de cantidad sigue siendo su valor pleno (el fix P1 NO lo toca: el
        // child no tiene fila `deposit_remainder`).
        $by = User::factory()->create();
        $order = $this->makePaidOrder(3000);
        $packItem = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $this->entry->id,
            'slot_id' => $this->makeSlot()->id, 'quantity' => 2, 'seats' => 2, 'unit_price' => 1500,
        ]);
        $child = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $packItem->id, 'ticket_type_id' => $this->entry->id,
            'slot_id' => null, 'quantity' => 1, 'free_quantity' => 0, 'seats' => 0, 'unit_price' => 1000,
        ]);
        $order = $order->fresh(['adjustments', 'items.ticketType']);
        $child = $order->items->firstWhere('id', $child->id);
        $order->applyExtraDue($child, 1000, $by, 'addon_per_guest_rescale',
            ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $child->forceFill(['quantity' => 2])->save();

        $order = $order->fresh(['adjustments', 'items.ticketType']);
        $child = $order->items->firstWhere('id', $child->id);

        // online original = 1 × 10 € (addon deposit=none → `depositCents` = valor pleno). Sin cambios.
        $this->assertSame(1000, $order->itemOriginalOnlineCents($child));
    }

    private function makeSlot(): Slot
    {
        $h = str_pad((string) (++$this->counter % 23), 2, '0', STR_PAD_LEFT);

        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00", 'capacity' => 50, 'online_capacity' => 50,
        ]);
    }

    private function makePaidOrder(int $total): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-RC'.str_pad((string) ++$this->counter, 4, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PAID, 'subtotal' => $total, 'tax' => 0, 'total' => $total,
            'currency' => 'EUR', 'paid_at' => now(),
        ]);
    }

    private function attachItem(Order $order, int $qty, int $unit): OrderItem
    {
        $h = str_pad((string) ($this->counter % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00", 'capacity' => 50, 'online_capacity' => 50,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $this->entry->id,
            'slot_id' => $slot->id, 'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }

    private function attachPayment(Order $order, int $amount, string $provider, ?string $gateway): Payment
    {
        return Payment::create([
            'payable_type' => Order::class, 'payable_id' => $order->id,
            'provider' => $provider, 'amount' => $amount, 'currency' => 'EUR',
            'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => $gateway,
        ]);
    }
}
