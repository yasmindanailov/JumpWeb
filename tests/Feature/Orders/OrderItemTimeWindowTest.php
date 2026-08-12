<?php

namespace Tests\Feature\Orders;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `OrderItem::displayTimeWindow()` — fuente única de la ventana horaria a mostrar.
 *
 * La rejilla de aforo es SIEMPRE de 60 min, así que `slot->end_time` no refleja la
 * duración real del producto. La ventana mostrada debe ser entrada → entrada +
 * `duration_min` (y solo la entrada para productos ilimitados).
 */
class OrderItemTimeWindowTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Slot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
        // Slot único de entrada a las 10:00 (rejilla de 60 min): la ventana mostrada
        // depende de la DURACIÓN del producto, no de este end_time.
        $this->slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => '2026-06-10',
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 10,
        ]);
    }

    private function itemWithDuration(?int $durationMin): OrderItem
    {
        $type = TicketType::create([
            'name' => ['es' => 'Producto'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => $durationMin,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID,
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $this->slot->id,
            'quantity' => 1, 'unit_price' => 1000, 'seats' => 1,
        ]);
    }

    public function test_timed_product_shows_entry_to_entry_plus_duration(): void
    {
        $this->assertSame('10:00–11:00', $this->itemWithDuration(60)->displayTimeWindow());
        $this->assertSame('10:00–12:00', $this->itemWithDuration(120)->displayTimeWindow());
        $this->assertSame('10:00–13:00', $this->itemWithDuration(180)->displayTimeWindow());
    }

    public function test_unlimited_product_shows_entry_with_no_limit_marker(): void
    {
        app()->setLocale('es');

        $this->assertSame('10:00 – sin límite', $this->itemWithDuration(null)->displayTimeWindow());
    }

    public function test_item_without_slot_returns_null(): void
    {
        $item = $this->itemWithDuration(60);
        $item->slot_id = null;
        $item->save();

        $this->assertNull($item->fresh()->displayTimeWindow());
    }
}
