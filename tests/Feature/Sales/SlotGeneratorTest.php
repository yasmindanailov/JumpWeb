<?php

namespace Tests\Feature\Sales;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Slot;
use App\Models\SlotTemplate;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Zone;
use App\Support\DisplayTime;
use App\Support\PaymentSettings;
use App\Support\SlotGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Fase 7.7 iter.3 — Servicio compartido {@see SlotGenerator}: generación idempotente,
 * preservación del aforo ajustado a mano (`capacity_overridden`) y PODA SEGURA (borra las
 * franjas obsoletas sin reservas; cierra —no borra— las que tienen reservas, para no destruir
 * ventas por el `cascadeOnDelete` de `order_items.slot_id`; jamás toca el pasado).
 */
class SlotGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private SlotGenerator $generator;

    private Zone $zone;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = app(SlotGenerator::class);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->user = User::factory()->create();
    }

    private function template(int $weekday, string $start, int $capacity = 50, int $online = 30): void
    {
        SlotTemplate::create([
            'zone_id' => $this->zone->id,
            'weekday' => $weekday,
            'start_time' => $start,
            'duration_min' => 60,
            'capacity' => $capacity,
            'online_capacity' => $online,
            'is_active' => true,
        ]);
    }

    private function bookSlot(Slot $slot, ?Carbon $cancelledAt = null): OrderItem
    {
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'],
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'is_sellable' => true,
            'is_active' => true,
            'seats_per_unit' => 1,
            'position' => TicketType::max('position') + 1,
        ]);

        $order = Order::create([
            'user_id' => $this->user->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);

        return $order->items()->create([
            'ticket_type_id' => $type->id,
            'slot_id' => $slot->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'seats' => 2,
            'cancelled_at' => $cancelledAt,
        ]);
    }

    public function test_preserves_manually_overridden_capacity_but_resets_the_rest(): void
    {
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00', 50, 30);
        $this->template($wednesday->dayOfWeek, '11:00:00', 50, 30);

        // Franja 10:00 ajustada a mano (online 5); franja 11:00 sin ajustar (online 5).
        $overridden = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 50, 'online_capacity' => 5, 'capacity_overridden' => true,
        ]);
        $plain = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '12:00:00',
            'capacity' => 50, 'online_capacity' => 5, 'capacity_overridden' => false,
        ]);

        $this->generator->generate($wednesday, $wednesday);

        $this->assertSame(5, $overridden->fresh()->online_capacity, 'el aforo ajustado a mano se preserva');
        $this->assertSame(30, $plain->fresh()->online_capacity, 'el aforo no ajustado se reescribe desde la plantilla');
    }

    public function test_prune_deletes_obsolete_slots_without_bookings(): void
    {
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00');

        // Franja 09:00 obsoleta (sin plantilla que la respalde), sin reservas.
        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 50, 'online_capacity' => 30,
        ]);

        $result = $this->generator->generate($wednesday, $wednesday, prune: true);

        $this->assertNull($obsolete->fresh(), 'la franja obsoleta sin reservas se borra');
        $this->assertSame(1, $result['deleted']);
        $this->assertDatabaseHas('slots', ['date' => $wednesday->toDateString(), 'start_time' => '10:00:00']);
    }

    public function test_prune_rechecks_dependents_under_lock_to_survive_a_concurrent_checkout(): void
    {
        // Auditoría Fase 1 (M3 · TOCTOU): el check de dependientes inicial es SIN lock. Si un checkout
        // crea un `order_item` sobre la franja obsoleta-aún-comprable ENTRE ese check y el borrado, el
        // `cascadeOnDelete` lo destruiría. La poda RE-comprueba dependientes bajo `lockForUpdate` por
        // franja antes de borrar: si apareció una venta, CIERRA en vez de borrar. Simulamos la venta
        // tardía inyectándola en la 2.ª recuperación de la franja (la del lock, ya pasado el check inicial).
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00');

        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 50, 'online_capacity' => 30,
        ]);

        $dispatcher = Slot::getEventDispatcher();
        $event = 'eloquent.retrieved: '.Slot::class;
        $seen = 0;
        $dispatcher->listen($event, function (Slot $s) use ($obsolete, &$seen): void {
            if ($s->id !== $obsolete->id) {
                return;
            }
            $seen++;
            if ($seen === 2) {
                // 2.ª recuperación = el lockForUpdate de la poda (tras el check inicial): llega la venta.
                $this->bookSlot($obsolete);
            }
        });

        try {
            $result = $this->generator->generate($wednesday, $wednesday, prune: true);
        } finally {
            $dispatcher->forget($event);
        }

        $fresh = $obsolete->fresh();
        $this->assertNotNull($fresh, 'NO se borra: apareció una venta tras el check inicial (TOCTOU evitado)');
        $this->assertSame(Slot::STATUS_CLOSED, $fresh->status);
        $this->assertFalse((bool) $fresh->online_sales_open);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, $result['closed']);
    }

    public function test_prune_closes_obsolete_slots_with_bookings_instead_of_deleting(): void
    {
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00');

        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 50, 'online_capacity' => 30,
        ]);
        $item = $this->bookSlot($obsolete);

        $result = $this->generator->generate($wednesday, $wednesday, prune: true);

        $fresh = $obsolete->fresh();
        $this->assertNotNull($fresh, 'una franja con reservas NUNCA se borra (cascadeOnDelete destruiría las ventas)');
        $this->assertFalse($fresh->online_sales_open, 'se cierra a venta online');
        $this->assertSame(Slot::STATUS_CLOSED, $fresh->status);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, $result['closed']);
        $this->assertNotNull($item->fresh(), 'la venta sobrevive');
    }

    public function test_prune_closes_obsolete_slots_even_when_the_booking_is_cancelled(): void
    {
        // Un order_item CANCELADO sigue siendo una fila con FK al slot: borrar el slot la
        // destruiría igual. Por seguridad, se cierra, no se borra.
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00');

        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 50, 'online_capacity' => 30,
        ]);
        $this->bookSlot($obsolete, cancelledAt: now());

        $result = $this->generator->generate($wednesday, $wednesday, prune: true);

        $this->assertNotNull($obsolete->fresh());
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, $result['closed']);
    }

    public function test_prune_closes_obsolete_slot_with_tickets_but_no_order_item(): void
    {
        // Tras reasignar la franja de un item en el panel, los tickets quedan en la franja vieja
        // sin order_item. La poda NUNCA debe borrarla (cascadeOnDelete destruiría los tickets).
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00');

        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 50, 'online_capacity' => 30,
        ]);
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => TicketType::max('position') + 1,
        ]);
        $order = Order::create(['user_id' => $this->user->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID]);
        $ticket = Ticket::create([
            'order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $obsolete->id,
            'qr_token' => Str::random(32), 'status' => Ticket::STATUS_PURCHASED,
        ]);

        $result = $this->generator->generate($wednesday, $wednesday, prune: true);

        $this->assertNotNull($obsolete->fresh(), 'una franja con tickets NUNCA se borra');
        $this->assertFalse($obsolete->fresh()->online_sales_open);
        $this->assertNotNull($ticket->fresh(), 'el ticket emitido sobrevive');
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(1, $result['closed']);
    }

    public function test_prune_keeps_and_preserves_an_overridden_scheduled_slot(): void
    {
        // El flujo real: editar el aforo de una franja (override) y luego «Regenerar franjas».
        // La franja sigue en horario (no obsoleta) → ni se poda ni se le reescribe el aforo.
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00', 50, 30);

        $overridden = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 50, 'online_capacity' => 7, 'capacity_overridden' => true,
        ]);
        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 50, 'online_capacity' => 30,
        ]);

        $result = $this->generator->generate($wednesday, $wednesday, prune: true);

        $this->assertNotNull($overridden->fresh(), 'la franja vigente sobrevive a la poda');
        $this->assertSame(7, $overridden->fresh()->online_capacity, 'el aforo ajustado a mano se preserva en la regeneración');
        $this->assertNull($obsolete->fresh(), 'la obsoleta sí se borra');
        $this->assertSame(1, $result['deleted']);
    }

    public function test_does_not_generate_a_slot_that_crosses_midnight(): void
    {
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        // Plantilla 23:30 + 60 min = 00:30 (cruza medianoche) → no se genera.
        $this->template($wednesday->dayOfWeek, '23:30:00');

        $result = $this->generator->generate($wednesday, $wednesday);

        $this->assertSame(0, Slot::where('date', $wednesday->toDateString())->count());
        $this->assertSame(0, $result['generated']);
    }

    public function test_prune_never_touches_past_dates(): void
    {
        $yesterday = Carbon::yesterday();
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);

        $pastObsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $yesterday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 50, 'online_capacity' => 30,
        ]);

        $this->generator->generate($yesterday, $wednesday, prune: true);

        $this->assertNotNull($pastObsolete->fresh(), 'el histórico no se poda');
    }

    public function test_without_prune_obsolete_slots_remain(): void
    {
        $wednesday = Carbon::now()->next(Carbon::WEDNESDAY);
        $this->template($wednesday->dayOfWeek, '10:00:00');

        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wednesday->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00',
            'capacity' => 50, 'online_capacity' => 30,
        ]);

        $result = $this->generator->generate($wednesday, $wednesday);

        $this->assertNotNull($obsolete->fresh(), 'sin --prune no se limpia nada');
        $this->assertSame(0, $result['deleted']);
        $this->assertSame(0, $result['closed']);
    }

    public function test_generate_rolling_horizon_fills_slots_up_to_the_sale_horizon(): void
    {
        // Auditoría Fase 1: sin regeneración programada las franjas se "agotaban" (solo cubrían unos
        // días del último seed). El job rodante mantiene franjas hasta el horizonte de venta.
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $this->template($weekday, '10:00:00');
        }

        $this->generator->generateRollingHorizon();

        $horizon = DisplayTime::today()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $maxDate = Slot::max('date');

        $this->assertNotNull($maxDate);
        $this->assertGreaterThanOrEqual(
            $horizon->copy()->subDays(2)->toDateString(),
            $maxDate,
            'las franjas llegan hasta (cerca de) el horizonte de venta'
        );
        $this->assertTrue(
            Slot::where('date', '>=', DisplayTime::today()->addMonths(5)->toDateString())->exists(),
            'existen franjas varios meses por delante (no solo los próximos días)'
        );
    }
}
