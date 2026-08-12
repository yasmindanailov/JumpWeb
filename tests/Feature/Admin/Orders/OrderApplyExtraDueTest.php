<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderAdjustment;
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
 * Sub-fase 7.2e cimientos — Order::applyExtraDue.
 *
 * Verifica el orquestador del "extra pendiente de cobrar en puerta":
 *  - Crea fila `order_adjustments` con type `extra_due`, importe, contexto,
 *    autor y currency correctos.
 *  - Audit log `orders.extra_due_applied` con payload estructurado.
 *  - Multiple llamadas acumulan (histórico inmutable, no upsert).
 *  - Bloqueos: amount ≤ 0 → InvalidArgumentException; item ajeno → DomainException.
 */
class OrderApplyExtraDueTest extends TestCase
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

    public function test_apply_extra_due_creates_adjustment_with_correct_fields(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->applyExtraDue(
            item: $item,
            amountCents: 1200,
            by: $by,
            reason: 'cantidad 3 → 5',
            context: ['quantity_change' => ['old' => 3, 'new' => 5]],
        );

        $this->assertInstanceOf(OrderAdjustment::class, $adjustment);
        $this->assertSame($order->id, (int) $adjustment->order_id);
        $this->assertSame($item->id, (int) $adjustment->order_item_id);
        $this->assertSame(OrderAdjustment::TYPE_EXTRA_DUE, $adjustment->type);
        $this->assertSame(1200, (int) $adjustment->amount_cents);
        $this->assertSame('EUR', $adjustment->currency);
        $this->assertSame('cantidad 3 → 5', $adjustment->reason);
        $this->assertSame(['quantity_change' => ['old' => 3, 'new' => 5]], $adjustment->context);
        $this->assertSame($by->id, (int) $adjustment->applied_by);
    }

    public function test_apply_extra_due_writes_audit_log(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->applyExtraDue($item, 800, $by, 'addon nuevo');

        $log = AuditLog::where('action', 'orders.extra_due_applied')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($order->code, $log->payload['order_code']);
        $this->assertSame($item->id, $log->payload['order_item_id']);
        $this->assertSame(800, $log->payload['amount_cents']);
        $this->assertSame('addon nuevo', $log->payload['reason']);
        $this->assertSame($adjustment->id, $log->payload['adjustment_id']);
    }

    public function test_multiple_extras_accumulate_as_immutable_history(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $order->applyExtraDue($item, 500, $by, 'cambio 1');
        $order->applyExtraDue($item, 700, $by, 'cambio 2');
        $order->applyExtraDue($item, 300, $by, 'cambio 3');

        $this->assertSame(3, $order->adjustments()->count());
        $this->assertSame(1500, (int) $order->adjustments()->sum('amount_cents'));
    }

    public function test_apply_extra_due_rejects_zero_amount(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $this->expectException(\InvalidArgumentException::class);
        $order->applyExtraDue($item, 0, $by);
    }

    public function test_apply_extra_due_rejects_negative_amount(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $this->expectException(\InvalidArgumentException::class);
        $order->applyExtraDue($item, -100, $by);
    }

    public function test_apply_extra_due_rejects_item_from_another_order(): void
    {
        $by = User::factory()->create();
        $orderA = $this->makePaidOrder('JJ-OWN0001');
        $orderB = $this->makePaidOrder('JJ-OWN0002');
        $itemOfB = $this->attachActiveItem($orderB);

        $this->expectException(\DomainException::class);
        // Anti-IDOR: el orquestador rechaza items que no pertenecen al Order.
        $orderA->applyExtraDue($itemOfB, 500, $by);
    }

    public function test_apply_extra_due_persists_null_context_when_empty_array(): void
    {
        $by = User::factory()->create();
        $order = $this->makePaidOrder();
        $item = $this->attachActiveItem($order);

        $adjustment = $order->applyExtraDue($item, 200, $by, 'sin contexto', []);

        // context vacío se persiste como null (la columna admite null, evita
        // serializaciones JSON "[]" inútiles que confundirían lecturas).
        $this->assertNull($adjustment->context);
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

    private function makePaidOrder(string $code = 'JJ-ED0001'): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => $code,
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
}
