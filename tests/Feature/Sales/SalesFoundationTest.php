<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\Price;
use App\Models\RateType;
use App\Models\Room;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 5.0 — Cimientos de datos de venta: tablas, columnas y relaciones.
 */
class SalesFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_tables_exist(): void
    {
        foreach ([
            'rate_types', 'prices', 'special_dates', 'slot_templates', 'slots',
            'orders', 'order_items', 'tickets', 'payments', 'opening_hours', 'rooms',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function test_ticket_types_has_sales_columns_and_drops_legacy(): void
    {
        $this->assertTrue(Schema::hasColumns('ticket_types', [
            'features', 'zone_id', 'duration_min', 'tax_rate',
            'wristband_color', 'conditions', 'is_sellable', 'seats_per_unit',
            'available_after_open_min', 'available_before_close_min',
            'prep_before_min', 'prep_after_min', 'type',
        ]));

        // El precio vive en `prices`; el catálogo ya no guarda `price_cents` ni `items`.
        $this->assertFalse(Schema::hasColumn('ticket_types', 'price_cents'));
        $this->assertFalse(Schema::hasColumn('ticket_types', 'items'));
    }

    public function test_ticket_type_defaults_to_entry_and_filters_by_type(): void
    {
        [, $ticketType] = $this->makeCatalog();

        $this->assertSame(TicketType::TYPE_ENTRY, $ticketType->fresh()->type);
        $this->assertTrue(TicketType::ofType(TicketType::TYPE_ENTRY)->whereKey($ticketType->id)->exists());
        $this->assertFalse(TicketType::ofType(TicketType::TYPE_PACK)->whereKey($ticketType->id)->exists());
    }

    public function test_room_is_translatable_and_active_by_default(): void
    {
        $room = Room::create(['name' => ['es' => 'Mesa 1', 'en' => 'Table 1'], 'capacity' => 12]);

        $this->assertSame('Mesa 1', $room->tr('name', 'es'));
        $this->assertSame(12, $room->capacity);
        $this->assertTrue($room->fresh()->is_active); // el default de BD se ve al recargar
    }

    public function test_order_aggregates_items_tickets_and_payments(): void
    {
        [$user, $ticketType, $slot] = $this->makeCatalog();

        $order = Order::create([
            'user_id' => $user->id,
            'code' => 'JJ-TEST-1',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 990,
            'tax' => 0,
            'total' => 990,
        ]);

        $order->items()->create([
            'ticket_type_id' => $ticketType->id,
            'slot_id' => $slot->id,
            'quantity' => 1,
            'unit_price' => 990,
            'seats' => 1,
        ]);

        $order->tickets()->create([
            'ticket_type_id' => $ticketType->id,
            'slot_id' => $slot->id,
            'qr_token' => Str::random(40),
        ]);

        $order->payments()->create([
            'amount' => 990,
            'status' => 'pending',
        ]);

        $this->assertCount(1, $order->items);
        $this->assertCount(1, $order->tickets);
        $this->assertCount(1, $order->payments);
        $this->assertSame(Order::class, $order->payments->first()->payable_type);
    }

    public function test_user_has_orders_and_tickets(): void
    {
        [$user, $ticketType, $slot] = $this->makeCatalog();

        $order = $user->orders()->create([
            'code' => 'JJ-TEST-2',
            'status' => Order::STATUS_PAID,
            'total' => 990,
        ]);
        $order->tickets()->create([
            'ticket_type_id' => $ticketType->id,
            'slot_id' => $slot->id,
            'qr_token' => Str::random(40),
        ]);

        $this->assertCount(1, $user->orders);
        $this->assertCount(1, $user->tickets); // hasManyThrough Order
    }

    public function test_price_is_polymorphic_and_belongs_to_a_rate_type(): void
    {
        [, $ticketType] = $this->makeCatalog();
        $rate = RateType::create(['key' => 'normal', 'label' => ['es' => 'Normal']]);

        $price = $ticketType->prices()->create([
            'rate_type_id' => $rate->id,
            'amount_cents' => 990,
        ]);

        $this->assertInstanceOf(Price::class, $ticketType->prices->first());
        $this->assertTrue($price->priceable->is($ticketType));
        $this->assertSame($rate->id, $price->rateType->id);
    }

    /**
     * @return array{0: User, 1: TicketType, 2: Slot}
     */
    private function makeCatalog(): array
    {
        $user = User::factory()->create();
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);

        $ticketType = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'],
            'zone_id' => $zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ]);

        $slot = Slot::create([
            'zone_id' => $zone->id,
            'date' => '2026-06-01',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'capacity' => 50,
            'online_capacity' => 30,
        ]);

        return [$user, $ticketType, $slot];
    }
}
