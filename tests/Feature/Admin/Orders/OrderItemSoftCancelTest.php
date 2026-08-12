<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sub-fase 7.2e cimientos — soft-cancel de OrderItem.
 *
 * Verifica:
 *  - `markCancelled` setea `cancelled_at` + `cancelled_by` (idempotente).
 *  - `isCancelled()` refleja la columna.
 *  - Scope `active()` excluye items con `cancelled_at`.
 *  - Items cancelados se EXCLUYEN de `Order::displayOperativeStatus` — se pueden
 *    cancelar items sin que el cómputo operativo del Order quede incoherente.
 */
class OrderItemSoftCancelTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jumpType;

    private int $slotCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_mark_cancelled_sets_timestamp_and_by(): void
    {
        $by = User::factory()->create();
        $item = $this->makeActiveItem();

        $this->assertNull($item->cancelled_at);
        $this->assertNull($item->cancelled_by);

        $item->markCancelled($by);

        $item->refresh();
        $this->assertNotNull($item->cancelled_at);
        $this->assertSame($by->id, $item->cancelled_by);
        $this->assertTrue($item->isCancelled());
    }

    public function test_mark_cancelled_is_idempotent_preserves_original_timestamp(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $item = $this->makeActiveItem();

        $item->markCancelled($alice);
        $item->refresh();
        $originalAt = $item->cancelled_at->copy();

        // Segundo intento con OTRO user — NO debe sobrescribir.
        $item->markCancelled($bob);
        $item->refresh();

        $this->assertEquals($originalAt->toDateTimeString(), $item->cancelled_at->toDateTimeString());
        $this->assertSame($alice->id, $item->cancelled_by);
    }

    public function test_active_scope_excludes_cancelled_items(): void
    {
        $by = User::factory()->create();
        $itemA = $this->makeActiveItem();
        $itemB = $this->makeActiveItem();
        $itemC = $this->makeActiveItem();

        $itemB->markCancelled($by);

        $ids = OrderItem::active()->pluck('id')->all();
        $this->assertContains($itemA->id, $ids);
        $this->assertNotContains($itemB->id, $ids);
        $this->assertContains($itemC->id, $ids);
    }

    public function test_cancelled_item_is_excluded_from_operative_status(): void
    {
        $by = User::factory()->create();
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-SC0002',
            'status' => Order::STATUS_PAID,
            'subtotal' => 2000, 'tax' => 0, 'total' => 2000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        $a = $this->attachActiveItem($order);
        $b = $this->attachActiveItem($order);

        // Dos items activos (sin cancelar) → active.
        $this->assertSame(Order::OPERATIVE_STATUS_ACTIVE, $order->displayOperativeStatus());

        // Cancelar uno → sigue habiendo un item vivo y activo → active.
        $b->markCancelled($by);
        $this->assertSame(Order::OPERATIVE_STATUS_ACTIVE, $order->displayOperativeStatus());

        // Cancelar también el restante → no quedan items vivos → fallback active
        // (los cancelados se EXCLUYEN del cómputo; sin esto daría finished espurio).
        $a->markCancelled($by);
        $this->assertSame(Order::OPERATIVE_STATUS_ACTIVE, $order->displayOperativeStatus());
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

    private function makeFutureSlot(): Slot
    {
        $this->ensureTicketTypeSetup();
        $h = str_pad((string) ($this->slotCounter++ % 23), 2, '0', STR_PAD_LEFT);

        return Slot::create([
            'zone_id' => $this->zone->id,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    private function makeActiveItem(): OrderItem
    {
        $this->ensureTicketTypeSetup();
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-SCITEM'.($this->slotCounter + 100),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        return $this->attachActiveItem($order);
    }

    private function attachActiveItem(Order $order): OrderItem
    {
        $this->ensureTicketTypeSetup();

        return OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null,
            'ticket_type_id' => $this->jumpType->id,
            'slot_id' => $this->makeFutureSlot()->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }
}
