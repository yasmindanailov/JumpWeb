<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Robustez del desglose — Order::applyGateCredit + Order::pendingAtGateLines.
 *
 * Un CRÉDITO de puerta es una fila `extra_due` NEGATIVA que netea el cargo de
 * una subida cuando una edición baja la cantidad/importe. Verifica:
 *  - el crédito se persiste negado, con audit `orders.gate_credit_applied`;
 *  - los derivados (`itemExtraDueCents`, `pendingAtGate`) suman con signo →
 *    subir y bajar la misma cantidad deja neto 0 (sin cargos fantasma);
 *  - `pendingAtGateLines` agrupa por item, oculta los de neto 0, reconstruye la
 *    cantidad neta en la etiqueta y su Σ cuadra con `pendingAtGate()`.
 */
class OrderGateCreditTest extends TestCase
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
    }

    public function test_apply_gate_credit_persists_negative_adjustment_and_audit(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, qty: 3, unit: 1000);

        $adjustment = $order->applyGateCredit($item, 2000, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 5, 'new' => 3]]]);

        $this->assertSame(OrderAdjustment::TYPE_EXTRA_DUE, $adjustment->type);
        $this->assertSame(-2000, (int) $adjustment->amount_cents); // se persiste NEGADO
        $this->assertSame($item->id, (int) $adjustment->order_item_id);
        $this->assertSame($by->id, (int) $adjustment->applied_by);

        $log = AuditLog::where('action', 'orders.gate_credit_applied')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame(-2000, $log->payload['amount_cents']);
        $this->assertSame($adjustment->id, $log->payload['adjustment_id']);
    }

    public function test_apply_gate_credit_rejects_non_positive_input(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order);

        $this->expectException(\InvalidArgumentException::class);
        $order->applyGateCredit($item, 0, $by);
    }

    public function test_apply_gate_credit_rejects_item_from_another_order(): void
    {
        $by = User::factory()->create();
        $orderA = $this->makePaidOrder('JJ-GC0001');
        $orderB = $this->makePaidOrder('JJ-GC0002');
        $itemB = $this->attachItem($orderB);

        $this->expectException(\DomainException::class);
        $orderA->applyGateCredit($itemB, 100, $by);
    }

    public function test_extra_due_and_pending_at_gate_net_with_credits(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, qty: 3, unit: 1000);

        $order->applyExtraDue($item, 2000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 3, 'new' => 5]]]);
        $order->applyGateCredit($item, 500, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 5, 'new' => 4]]]);

        $order->refresh()->load('adjustments', 'items');

        // 2000 − 500 = 1500 neto.
        $this->assertSame(1500, $order->itemExtraDueCents($item));
        $this->assertSame(1500, $order->financialSummary()->pendingAtGate());
    }

    public function test_up_then_down_same_quantity_nets_to_zero_no_phantom_line(): void
    {
        // Reproduce el núcleo del bug JJ-WIMWJW a nivel modelo: subir y bajar la
        // misma cantidad debe dejar neto 0 y NO pintar ninguna línea.
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, qty: 1, unit: 2290); // como el Jump · Ilimitada

        $order->applyExtraDue($item, 2290, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $order->applyGateCredit($item, 2290, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 1]]]);

        $order->refresh()->load('adjustments', 'items.ticketType');

        $this->assertSame(0, $order->itemExtraDueCents($item));
        $this->assertSame(0, $order->financialSummary()->pendingAtGate());
        $this->assertSame([], $order->pendingAtGateLines()); // sin renglón fantasma
    }

    public function test_pending_at_gate_lines_group_by_item_and_reconstruct_net_quantity(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachItem($order, qty: 5, unit: 1800); // como el Cumpleaños Jump

        // Dos subidas (+2 y +2) y una bajada parcial (−1) → neto +3 unidades.
        $order->applyExtraDue($item, 3600, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 2, 'new' => 4]]]);
        $order->applyExtraDue($item, 3600, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 4, 'new' => 6]]]);
        $order->applyGateCredit($item, 1800, $by, 'item_edit_reduction', ['changes' => ['quantity_change' => ['old' => 6, 'new' => 5]]]);

        $order->refresh()->load('adjustments', 'items.ticketType');

        $lines = $order->pendingAtGateLines();
        $this->assertCount(1, $lines);
        $this->assertSame(5400, $lines[0]['amount']);                 // 3 × 1800 neto
        $this->assertSame('+3 Jump 1h', $lines[0]['label']);          // cantidad NETA, no la suma de subidas
        $sum = array_sum(array_column($lines, 'amount'));
        $this->assertSame($order->financialSummary()->pendingAtGate(), $sum); // desglose cuadra con el total
    }

    public function test_pending_at_gate_lines_exclude_cancelled_and_finished_items(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();

        $cancelled = $this->attachItem($order, qty: 2, unit: 1000);
        $order->applyExtraDue($cancelled, 1000, $by, 'item_edit', ['changes' => ['quantity_change' => ['old' => 1, 'new' => 2]]]);
        $cancelled->markCancelled($by);

        $order->refresh()->load('adjustments', 'items.ticketType', 'items.slot');

        $this->assertSame([], $order->pendingAtGateLines());            // cancelado → fuera
        $this->assertSame(0, $order->financialSummary()->pendingAtGate());
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

    private function makePaidOrder(string $code = 'JJ-GC0009'): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code,
            'status' => Order::STATUS_PAID,
            'subtotal' => 5000, 'tax' => 0, 'total' => 5000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function attachItem(Order $order, int $qty = 1, int $unit = 1000): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->counter++ % 23), 2, '0', STR_PAD_LEFT);
        $slot = Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => $unit,
        ]);
    }
}
