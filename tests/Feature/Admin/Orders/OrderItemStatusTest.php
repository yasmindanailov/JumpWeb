<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estado de un OrderItem: `finished` automático al pasar el slot + `active`/`finished`
 * de cara al cliente. (Antes `OrderItemPreparedTest`; las ramas de "preparado/sin
 * preparar" —markPrepared/displayStatusForStaff— se retiraron con el sistema, #202.)
 */
class OrderItemStatusTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    protected function setUp(): void
    {
        parent::setUp();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->jump1h = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function makeSlot(string $date, string $startTime = '10:00:00', string $endTime = '11:00:00'): Slot
    {
        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => $startTime, 'end_time' => $endTime,
            'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    private function makeOrder(): Order
    {
        $user = User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-T'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);
    }

    private function makeItem(Order $order, Slot $slot, ?int $parentId = null): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'parent_item_id' => $parentId,
            'ticket_type_id' => $this->jump1h->id,
            'slot_id' => $slot->id,
            'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);
    }

    // ─── isFinishedInPractice ─────────────────────────────────────────────

    public function test_item_with_future_slot_is_not_finished(): void
    {
        $slot = $this->makeSlot('2099-01-01', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertFalse($item->isFinishedInPractice());
    }

    public function test_item_with_past_slot_is_finished(): void
    {
        $slot = $this->makeSlot('2000-01-01', '10:00:00', '11:00:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertTrue($item->isFinishedInPractice());
    }

    public function test_item_with_today_slot_after_end_time_is_finished(): void
    {
        $today = now()->format('Y-m-d');
        $slot = $this->makeSlot($today, '00:00:00', '00:01:00');
        $item = $this->makeItem($this->makeOrder(), $slot);

        $this->assertTrue($item->isFinishedInPractice());
    }

    public function test_addon_inherits_finished_state_from_parent(): void
    {
        $order = $this->makeOrder();
        $parentSlot = $this->makeSlot('2000-01-01');
        $parent = $this->makeItem($order, $parentSlot);

        // Addon sin slot propio.
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);

        $this->assertTrue($parent->isFinishedInPractice());
        $this->assertTrue($addon->isFinishedInPractice(),
            'Addon (parent_item_id != null) hereda finished del parent.');
    }

    public function test_addon_inherits_active_state_from_active_parent(): void
    {
        $order = $this->makeOrder();
        $parent = $this->makeItem($order, $this->makeSlot('2099-01-01'));
        $addon = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => $parent->id,
            'ticket_type_id' => $this->jump1h->id, 'slot_id' => null,
            'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
        ]);

        $this->assertFalse($addon->isFinishedInPractice());
    }

    // ─── displayStatusForCustomer ─────────────────────────────────────────

    public function test_customer_sees_active_or_finished(): void
    {
        $active = $this->makeItem($this->makeOrder(), $this->makeSlot('2099-01-01'));
        $finished = $this->makeItem($this->makeOrder(), $this->makeSlot('2000-01-01'));

        $this->assertSame(OrderItem::STATUS_ACTIVE, $active->displayStatusForCustomer());
        $this->assertSame(OrderItem::STATUS_FINISHED, $finished->displayStatusForCustomer());
    }
}
