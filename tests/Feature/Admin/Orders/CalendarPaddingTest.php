<?php

namespace Tests\Feature\Admin\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #172 — Relleno del calendario del modal Gestionar.
 *
 * Los días de RELLENO del mes vecino (in_month=false) que completan la primera
 * y última semana de la rejilla deben ser INERTES: ni seleccionables (sin
 * wire:click) ni con punto de disponibilidad, aunque su fecha caiga en una
 * franja abierta. Antes parecían vacíos pero mostraban punto verde y eran un
 * <button> clicable (los gateamos a `in_month` en el blade).
 */
class CalendarPaddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_out_of_month_padding_cells_are_inert(): void
    {
        [$order, $item] = $this->makeOrderItem();

        $cell = fn (string $date, int $day, bool $inMonth, bool $selectable, ?string $sat): array => [
            'date' => $date, 'day' => $day, 'in_month' => $inMonth,
            'selectable' => $selectable, 'is_current' => false, 'is_selected' => false,
            'is_past' => false, 'is_beyond_horizon' => false, 'saturation' => $sat,
        ];

        // Una semana: relleno fuera de mes (selectable + saturado) + un día DEL
        // mes (selectable + saturado) + relleno de días no seleccionables.
        $week = [
            $cell('2026-05-31', 31, false, true, 'high'),  // fuera de mes → debe ser inerte
            $cell('2026-06-01', 1, true, true, 'high'),    // del mes → clicable + punto
            $cell('2026-06-02', 2, true, false, null),
            $cell('2026-06-03', 3, true, false, null),
            $cell('2026-06-04', 4, true, false, null),
            $cell('2026-06-05', 5, true, false, null),
            $cell('2026-06-06', 6, true, false, null),
        ];

        $html = view('filament.orders.partials.manage-item-calendar', [
            'item' => $item,
            'record' => $order,
            'editable' => true,
            'blockedReason' => null,
            'matrix' => [$week],
            'monthLabel' => 'Junio 2026',
            'selectedDate' => null,
            'selectedTime' => null,
            'times' => [],
        ])->render();

        // El día DEL mes es clicable → su fecha aparece en la llamada wire.
        $this->assertStringContainsString('2026-06-01', $html);
        // El día de RELLENO fuera de mes NO es clicable → su fecha NO aparece
        // (ni en wire:click ni en aria-label) aunque sea selectable.
        $this->assertStringNotContainsString('2026-05-31', $html);
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function makeOrderItem(): array
    {
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $type = TicketType::create([
            'name' => ['es' => 'Jump 1h'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 5,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-CAL01',
            'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'total' => 1000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'parent_item_id' => null, 'ticket_type_id' => $type->id,
            'slot_id' => $slot->id, 'quantity' => 1, 'seats' => 1, 'unit_price' => 1000,
        ]);

        return [$order, $item];
    }
}
