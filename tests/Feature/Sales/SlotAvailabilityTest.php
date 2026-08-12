<?php

namespace Tests\Feature\Sales;

use App\Domain\Identity\Models\User;
use App\Models\Order;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use App\Support\SlotAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 5.0/5.3 — Aforo por ocupación a lo largo de la visita (#60): una entrada ocupa
 * una plaza en cada franja que abarca su duración; las plazas libres son el mínimo del tramo.
 */
class SlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private SlotAvailability $availability;

    private User $user;

    private Zone $zone;

    /** @var array<string, Slot> franjas 10:00, 11:00, 12:00 */
    private array $slots = [];

    private TicketType $h1;   // 60 min

    private TicketType $h3;   // 180 min

    private TicketType $unlimited; // null

    protected function setUp(): void
    {
        parent::setUp();

        $this->availability = new SlotAvailability;
        $this->user = User::factory()->create();
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            $this->slots[$start] = Slot::create([
                'zone_id' => $this->zone->id,
                'date' => '2026-06-10',
                'start_time' => $start,
                'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10,
                'online_capacity' => 10,
            ]);
        }

        $this->h1 = $this->ticketType(60);
        $this->h3 = $this->ticketType(180);
        $this->unlimited = $this->ticketType(null);
    }

    public function test_full_capacity_when_no_orders(): void
    {
        $this->assertSame(10, $this->availability->availableFor($this->slots['10:00:00'], 60));
    }

    public function test_a_one_hour_order_only_occupies_its_slot(): void
    {
        $this->order($this->slots['10:00:00'], $this->h1, 3);

        $this->assertSame(7, $this->availability->availableFor($this->slots['10:00:00'], 60));
        $this->assertSame(10, $this->availability->availableFor($this->slots['11:00:00'], 60));
    }

    public function test_a_three_hour_order_occupies_all_spanned_slots(): void
    {
        $this->order($this->slots['10:00:00'], $this->h3, 4); // ocupa 10, 11 y 12

        $this->assertSame(6, $this->availability->availableFor($this->slots['10:00:00'], 60));
        $this->assertSame(6, $this->availability->availableFor($this->slots['11:00:00'], 60));
        $this->assertSame(6, $this->availability->availableFor($this->slots['12:00:00'], 60));
    }

    public function test_long_entry_is_not_offered_when_it_would_run_past_the_grid(): void
    {
        // BUG: una entrada que NO cabe entera antes del fin de la rejilla (≈ cierre) no debe ofrecerse
        // (vendía tiempo fuera de horario). Rejilla 10:00–13:00 (franjas 10/11/12).
        // 2 h a las 12:00 → terminaría a las 14:00 (1 h fuera) → 0.
        $this->assertSame(0, $this->availability->availableFor($this->slots['12:00:00'], 120));
        // 3 h a las 11:00 → terminaría a las 14:00 → 0.
        $this->assertSame(0, $this->availability->availableFor($this->slots['11:00:00'], 180));
        // Las que SÍ caben siguen disponibles:
        $this->assertSame(10, $this->availability->availableFor($this->slots['12:00:00'], 60));  // 1 h cabe (última franja)
        $this->assertSame(10, $this->availability->availableFor($this->slots['11:00:00'], 120)); // 2 h cabe (11→13)
    }

    public function test_long_entry_is_not_offered_when_a_grid_slot_is_missing(): void
    {
        // Hueco interior: sin la franja de las 11:00, una 3 h a las 10:00 (necesita 10/11/12 contiguas)
        // no cabe → 0; pero una 1 h a las 10:00 (solo necesita su franja) sí.
        $this->slots['11:00:00']->delete();

        $this->assertSame(0, $this->availability->availableFor($this->slots['10:00:00'], 180));
        $this->assertSame(10, $this->availability->availableFor($this->slots['10:00:00'], 60));
    }

    public function test_buying_a_long_entry_is_limited_by_the_busiest_spanned_slot(): void
    {
        $this->order($this->slots['11:00:00'], $this->h1, 2); // solo 11:00

        // Comprar una de 3h que entra a las 10:00 abarca 10/11/12; la más ocupada es 11 (8 libres).
        $this->assertSame(8, $this->availability->availableFor($this->slots['10:00:00'], 180));
    }

    public function test_unlimited_entry_occupies_until_closing(): void
    {
        $this->order($this->slots['11:00:00'], $this->unlimited, 5); // ocupa 11 y 12 (hasta el cierre)

        $this->assertSame(10, $this->availability->availableFor($this->slots['10:00:00'], 60));
        $this->assertSame(5, $this->availability->availableFor($this->slots['11:00:00'], 60));
        $this->assertSame(5, $this->availability->availableFor($this->slots['12:00:00'], 60));
    }

    public function test_paid_and_active_pending_count_but_expired_pending_does_not(): void
    {
        $this->order($this->slots['10:00:00'], $this->h1, 2, Order::STATUS_PAID);
        $this->order($this->slots['10:00:00'], $this->h1, 3, Order::STATUS_PENDING, now()->addMinutes(15));
        $this->order($this->slots['10:00:00'], $this->h1, 4, Order::STATUS_PENDING, now()->subMinute()); // caducado → no cuenta

        $this->assertSame(5, $this->availability->availableFor($this->slots['10:00:00'], 60)); // 10 - (2+3)
    }

    public function test_soft_cancelled_items_release_their_seats(): void
    {
        // Sub-fase 7.2e.2 (decisión #159): items con `cancelled_at !== null`
        // se EXCLUYEN del cómputo de ocupación. Antes del fix, un item
        // cancelado seguía contando aforo, inflando la ocupación aparente.
        //
        // Setup: 3 items en la misma franja:
        //  - 4 plazas (activo, paid) → cuenta.
        //  - 3 plazas (cancelado, paid) → NO debe contar tras el fix.
        //  - 2 plazas (activo, paid) → cuenta.
        // Total ocupado: 4+2=6. Aforo libre: 10−6=4.
        $this->order($this->slots['10:00:00'], $this->h1, 4);
        $this->orderAndCancelLastItem($this->slots['10:00:00'], $this->h1, 3);
        $this->order($this->slots['10:00:00'], $this->h1, 2);

        $this->assertSame(4, $this->availability->availableFor($this->slots['10:00:00'], 60));
    }

    public function test_soft_cancelled_items_in_spanned_slots_release_long_entry(): void
    {
        // Regresión del fix sobre entradas con duración: un item LARGO cancelado
        // (h3 = 180 min) libera plazas en TODAS las franjas que abarcaba (10/11/12),
        // permitiendo que entradas nuevas con la misma duración tengan aforo completo.
        $this->orderAndCancelLastItem($this->slots['10:00:00'], $this->h3, 5); // ocupaba 10/11/12

        $this->assertSame(10, $this->availability->availableFor($this->slots['10:00:00'], 180));
        $this->assertSame(10, $this->availability->availableFor($this->slots['11:00:00'], 60));
    }

    public function test_no_seats_when_online_sales_closed(): void
    {
        $this->slots['10:00:00']->update(['online_sales_open' => false]);

        $this->assertSame(0, $this->availability->availableFor($this->slots['10:00:00'], 60));
    }

    public function test_cart_provisional_occupants_reduce_availability(): void
    {
        // 3 plazas ya elegidas en la cesta para 10:00 (aún sin pedido) → restan aforo solo ahí.
        $cart = [['entry_start' => '10:00:00', 'duration_min' => 60, 'seats' => 3]];

        $this->assertSame(7, $this->availability->availableFor($this->slots['10:00:00'], 60, $cart));
        $this->assertSame(10, $this->availability->availableFor($this->slots['11:00:00'], 60, $cart));
    }

    public function test_cart_occupants_combine_with_orders(): void
    {
        $this->order($this->slots['10:00:00'], $this->h1, 4); // 4 pagadas
        $cart = [['entry_start' => '10:00:00', 'duration_min' => 60, 'seats' => 3]]; // 3 en la cesta

        $this->assertSame(3, $this->availability->availableFor($this->slots['10:00:00'], 60, $cart)); // 10-4-3
    }

    public function test_a_long_cart_line_occupies_all_spanned_slots(): void
    {
        // Una línea de 3h en la cesta que entra a las 10:00 ocupa 10, 11 y 12.
        $cart = [['entry_start' => '10:00:00', 'duration_min' => 180, 'seats' => 4]];

        $this->assertSame(6, $this->availability->availableFor($this->slots['10:00:00'], 60, $cart));
        $this->assertSame(6, $this->availability->availableFor($this->slots['11:00:00'], 60, $cart));
        $this->assertSame(6, $this->availability->availableFor($this->slots['12:00:00'], 60, $cart));
    }

    private function ticketType(?int $durationMin): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada '.($durationMin ?? 'ilimitada')],
            'zone_id' => $this->zone->id,
            'duration_min' => $durationMin,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => TicketType::max('position') + 1,
        ]);
    }

    private function order(Slot $entry, TicketType $type, int $seats, string $status = Order::STATUS_PAID, ?Carbon $expires = null): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => $status,
            'expires_at' => $expires,
        ]);

        $order->items()->create([
            'ticket_type_id' => $type->id,
            'slot_id' => $entry->id,
            'quantity' => $seats,
            'unit_price' => 1000,
            'seats' => $seats,
        ]);
    }

    private function orderAndCancelLastItem(Slot $entry, TicketType $type, int $seats): void
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);

        $item = $order->items()->create([
            'ticket_type_id' => $type->id,
            'slot_id' => $entry->id,
            'quantity' => $seats,
            'unit_price' => 1000,
            'seats' => $seats,
        ]);

        $item->update([
            'cancelled_at' => now(),
            'cancelled_by' => $this->user->id,
        ]);
    }
}
