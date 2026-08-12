<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estado operativo del pedido (#127, podado al retirar "preparado/no preparado", #202):
 * `displayOperativeStatus()` ahora deriva SOLO de si la franja de cada item ya pasó
 * (active / in_progress / finished — sin el antiguo estado `ready`), y
 * `isOperationalForItemActions()` (antes `canToggleItems`) es la guarda raíz de editar/
 * cancelar item. Sustituye al antiguo `OrderPreparedSummaryTest` (que probaba además
 * `preparedSummary` y el estado `ready`, ambos eliminados).
 */
class OrderOperativeStatusTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $jump1h;

    private int $slotCounter = 0;

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

    private function makeSlot(string $date): Slot
    {
        $h = str_pad((string) ($this->slotCounter++ % 23), 2, '0', STR_PAD_LEFT);

        return Slot::create([
            'zone_id' => $this->zone->id, 'date' => $date,
            'start_time' => "{$h}:00:00", 'end_time' => "{$h}:59:00",
            'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    /**
     * @param  array<int, array{date:string, with_addon?:bool}>  $itemsConfig
     */
    private function makeOrderWithItems(array $itemsConfig): Order
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-S'.bin2hex(random_bytes(2)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR',
            'paid_at' => now(),
        ]);

        foreach ($itemsConfig as $config) {
            $slot = $this->makeSlot($config['date']);
            $item = OrderItem::create([
                'order_id' => $order->id, 'parent_item_id' => null,
                'ticket_type_id' => $this->jump1h->id, 'slot_id' => $slot->id,
                'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
            ]);
            // Cada item puede tener un addon para verificar que NO entra en el cómputo.
            if (! empty($config['with_addon'])) {
                OrderItem::create([
                    'order_id' => $order->id, 'parent_item_id' => $item->id,
                    'ticket_type_id' => $this->jump1h->id, 'slot_id' => null,
                    'quantity' => 1, 'seats' => 0, 'unit_price' => 200,
                ]);
            }
        }

        return $order;
    }

    // ─── displayOperativeStatus ───────────────────────────────────────────

    public function test_operative_status_finished_when_all_items_finished(): void
    {
        $order = $this->makeOrderWithItems([
            ['date' => '2000-01-01'],
            ['date' => '2000-01-02'],
        ]);
        $this->assertSame(Order::OPERATIVE_STATUS_FINISHED, $order->displayOperativeStatus());
    }

    public function test_operative_status_in_progress_when_mixed(): void
    {
        $order = $this->makeOrderWithItems([
            ['date' => '2000-01-01'],  // finalizado
            ['date' => '2099-01-01'],  // futuro
        ]);
        $this->assertSame(Order::OPERATIVE_STATUS_IN_PROGRESS, $order->displayOperativeStatus());
    }

    public function test_operative_status_active_when_none_finished(): void
    {
        $order = $this->makeOrderWithItems([
            ['date' => '2099-01-01', 'with_addon' => true],
            ['date' => '2099-01-02'],
        ]);
        $this->assertSame(Order::OPERATIVE_STATUS_ACTIVE, $order->displayOperativeStatus());
    }

    public function test_operative_status_active_when_no_principal_items(): void
    {
        $order = $this->makeOrderWithItems([]);
        $this->assertSame(Order::OPERATIVE_STATUS_ACTIVE, $order->displayOperativeStatus());
    }

    // ─── isOperationalForItemActions (antes canToggleItems) ───────────────

    public function test_operational_for_paid_with_active_items(): void
    {
        $order = $this->makeOrderWithItems([['date' => '2099-01-01']]);
        $this->assertTrue($order->isOperationalForItemActions());
    }

    public function test_not_operational_when_all_finished(): void
    {
        $order = $this->makeOrderWithItems([
            ['date' => '2000-01-01'],
            ['date' => '2000-01-02'],
        ]);
        $this->assertFalse($order->isOperationalForItemActions(),
            'Si todos los items están finalizados, no hay acciones por-item operables.');
    }

    public function test_operational_when_some_finished_some_active(): void
    {
        $order = $this->makeOrderWithItems([
            ['date' => '2000-01-01'],
            ['date' => '2099-01-01'],
        ]);
        $this->assertTrue($order->isOperationalForItemActions(),
            'Order in_progress permite acciones sobre los items que aún no han pasado.');
    }

    public function test_not_operational_for_non_paid_orders(): void
    {
        $cancelled = $this->makeOrderWithItems([['date' => '2099-01-01']]);
        $cancelled->update(['status' => Order::STATUS_CANCELLED]);
        $this->assertFalse($cancelled->fresh()->isOperationalForItemActions());

        $refunded = $this->makeOrderWithItems([['date' => '2099-01-01']]);
        $refunded->update(['status' => Order::STATUS_REFUNDED]);
        $this->assertFalse($refunded->fresh()->isOperationalForItemActions());

        $pending = $this->makeOrderWithItems([['date' => '2099-01-01']]);
        $pending->update(['status' => Order::STATUS_PENDING]);
        $this->assertFalse($pending->fresh()->isOperationalForItemActions());
    }

    public function test_not_operational_when_pending_is_expired_in_practice(): void
    {
        $order = $this->makeOrderWithItems([['date' => '2099-01-01']]);
        $order->update([
            'status' => Order::STATUS_PENDING,
            'expires_at' => now()->subHour(),  // displayStatus() → expired
        ]);

        $this->assertSame(Order::STATUS_EXPIRED, $order->fresh()->displayStatus());
        $this->assertFalse($order->fresh()->isOperationalForItemActions());
    }
}
