<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `#420` — **La rejilla de franjas puede SOLAPARSE, y eso no sobrevende.**
 *
 * Hasta el 2026-09-05 todas las plantillas del parque empezaban en punto, así que la rejilla era
 * una partición del día y ningún caso ejercitaba otra cosa. Al abrir inicios cada 30 min con
 * franjas de 60, dos franjas vecinas se PISAN (15:30–16:30 y 16:00–17:00), y la corrección pasa a
 * depender de una propiedad que hasta ahora solo estaba afirmada en un docblock de
 * {@see SlotAvailability::spanCoversDuration()} («tolera rejillas solapadas»):
 *
 * ▶ **El aforo no cuenta huecos de un contenedor: cuenta PRESENCIA.** `occupancyMap()` marca cada
 * franja cuyo `start_time` cae dentro del tramo del ocupante, así que la ocupación es la gente
 * presente en cada punto de la rejilla. Como **todo inicio de venta es un punto de la rejilla**,
 * dos tramos que se solapan comparten al menos ese punto y se ven mutuamente — sin importar si
 * comparten hora de entrada. Esa es la propiedad que se fija aquí, en los tres niveles que la
 * sostienen: la ocupación (entradas), el cupo (packs) y el guardián del checkout.
 *
 * ⚠️⚠️ **La DURACIÓN de la franja sigue siendo 60 aunque los inicios vayan cada 30**, y no es un
 * detalle de configuración: `slot.end_time` es lo que lee `OrderItem::isFinishedInPractice()`
 * para dar una reserva por terminada (y de ahí cuelgan el post-form en solo lectura, el cierre de
 * los extras y la ventana del suplemento mixto). Con franjas de 30 min, una entrada de 1 h
 * comprada a las 15:30 se declararía terminada a las 16:00. Por eso los casos de aquí construyen
 * la rejilla como la construye el panel: **inicio cada 30, duración 60**.
 *
 * ⚠️ Lo que aquí NO se prueba es la concurrencia: la serializan `ZoneDaySlotLock` (que bloquea la
 * zona/día ENTERA justamente porque la ocupación se cuenta por tramo) y los siete escenarios de
 * `purchase:verify-oversell` sobre InnoDB — `pack-prep` ya cubre dos fiestas que se pisan sin
 * compartir hora de inicio. SQLite no reproduce `FOR UPDATE` (`SUITE-04`).
 */
class OverlappingSlotGridTest extends TestCase
{
    use RefreshDatabase;

    private SlotAvailability $entries;

    private PackAvailability $packs;

    private OrderCreator $creator;

    private User $user;

    private Zone $entryZone;

    private Zone $packZone;

    private TicketType $entry60;

    private TicketType $pack120;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entries = app(SlotAvailability::class);
        $this->packs = app(PackAvailability::class);
        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();
        $this->date = Carbon::today()->addDays(3)->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->entryZone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        // Los topes van por ZONA (no por `Setting`) para no depender del memo de ajustes.
        $this->packZone = Zone::create([
            'slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 5, 'max_guests_per_slot' => 60, 'prep_blocks_cupo' => false,
        ]);

        // LA REJILLA SOLAPADA: inicios cada 30 min, franjas de 60. 15:00 → 18:30 (última 18:30-19:30).
        foreach ([$this->entryZone->id => 20, $this->packZone->id => 200] as $zoneId => $capacity) {
            for ($minutes = 0; $minutes <= 210; $minutes += 30) {
                $start = Carbon::parse('15:00')->addMinutes($minutes);
                Slot::create([
                    'zone_id' => $zoneId,
                    'date' => $this->date,
                    'start_time' => $start->format('H:i:s'),
                    'end_time' => $start->copy()->addHour()->format('H:i:s'),
                    'capacity' => $capacity,
                    'online_capacity' => $capacity,
                ]);
            }
        }

        $this->entry60 = TicketType::create([
            'name' => ['es' => 'Entrada 1 hora'], 'zone_id' => $this->entryZone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry60->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1000,
        ]);

        $this->pack120 = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'zone_id' => $this->packZone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 120, 'prep_before_min' => 0, 'prep_after_min' => 0,
            'min_qty' => 8, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $this->pack120->prices()->create([
            'rate_type_id' => RateType::where('key', 'normal')->value('id'), 'amount_cents' => 1500,
        ]);
    }

    // ─── Entradas: la ocupación es presencia, no un hueco de contenedor ───────────────

    public function test_an_entry_starting_on_the_half_hour_occupies_both_slots_it_overlaps(): void
    {
        // Una entrada de 1 h a las 15:30 está dentro entre 15:30 y 16:30, así que pisa la franja
        // de las 15:30 Y la de las 16:00 — que empieza mientras ella sigue dentro.
        $this->entryOrder('15:30:00', $this->entry60, 6);

        $this->assertSame(14, $this->entries->availableFor($this->slot($this->entryZone, '15:30:00'), 60));
        $this->assertSame(14, $this->entries->availableFor($this->slot($this->entryZone, '16:00:00'), 60));

        // ⚠️⚠️ **Y las 15:00 bajan a 14 TAMBIÉN, aunque a esa hora no haya nadie dentro.** Es la
        // consecuencia que una rejilla en punto no puede enseñar y que hay que entender antes de
        // tocar nada aquí: las plazas de una franja no son «su» aforo, son el MÍNIMO del rato que
        // dura la visita — quien entre a las 15:00 seguirá dentro a las 15:30, y ahí solo caben 14
        // más. Afirmar 20 sería vender 26 personas simultáneas en una zona de 20.
        $this->assertSame(14, $this->entries->availableFor($this->slot($this->entryZone, '15:00:00'), 60));

        // Fuera del tramo, intacto: a las 16:30 los seis ya han salido.
        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '16:30:00'), 60));
    }

    public function test_a_partial_sale_can_be_topped_up_on_the_half_hour_without_oversell(): void
    {
        // El motivo comercial de la rejilla fina: 12 de 20 a las 15:00 dejan 8 plazas que la
        // rejilla en punto no podía vender hasta las 16:00. Aquí se venden a las 15:30 — y ni una más.
        $this->entryOrder('15:00:00', $this->entry60, 12);

        $this->assertSame(8, $this->entries->availableFor($this->slot($this->entryZone, '15:30:00'), 60));
        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '16:00:00'), 60));

        $this->entryOrder('15:30:00', $this->entry60, 8);

        $this->assertSame(0, $this->entries->availableFor($this->slot($this->entryZone, '15:30:00'), 60));
        // Los 12 de las 15:00 ya salieron; los 8 de las 15:30 siguen dentro hasta las 16:30.
        $this->assertSame(12, $this->entries->availableFor($this->slot($this->entryZone, '16:00:00'), 60));
        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '16:30:00'), 60));
    }

    public function test_a_later_sale_does_not_eat_into_an_earlier_slot(): void
    {
        // La dirección del tiempo, y en una rejilla solapada es donde peor se ve: las franjas
        // vecinas comparten ocupantes DE VERDAD, así que una ocupación que se derrame hacia atrás
        // se confunde con el solape legítimo y no la delata ninguna de las otras aserciones.
        // Quien entra a las 17:00 no está dentro a las 15:00.
        $this->entryOrder('17:00:00', $this->entry60, 6);

        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '15:00:00'), 60));
        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '15:30:00'), 60));
        // Y sí resta desde las 16:30, que es la primera entrada que sigue dentro a las 17:00.
        $this->assertSame(14, $this->entries->availableFor($this->slot($this->entryZone, '16:30:00'), 60));
        $this->assertSame(14, $this->entries->availableFor($this->slot($this->entryZone, '17:00:00'), 60));
    }

    public function test_a_long_entry_is_covered_by_the_overlapping_grid(): void
    {
        // La cobertura del tramo avanza al fin MÁS LEJANO, así que una rejilla solapada cubre una
        // visita larga igual que una contigua (15:30 + 2 h = 17:30, cubierto por 15:30→16:30 …).
        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '15:30:00'), 120));
        // Y el techo del horario sigue mandando: la rejilla acaba a las 19:30, así que 2 h a las
        // 18:30 caben justas y a las 18:30 + 2 h 30 ya no.
        $this->assertSame(20, $this->entries->availableFor($this->slot($this->entryZone, '18:30:00'), 60));
        $this->assertSame(0, $this->entries->availableFor($this->slot($this->entryZone, '18:30:00'), 150));
    }

    // ─── Packs: el cupo ve las fiestas que se pisan sin compartir hora de entrada ─────

    public function test_a_party_blocks_the_half_hour_starts_it_overlaps_and_frees_the_one_after(): void
    {
        // Tres fiestas de 20 a las 15:00 agotan el cupo de 60 niños entre las 15:00 y las 17:00.
        foreach ([20, 20, 20] as $guests) {
            $this->partyOrder('15:00:00', $guests);
        }

        foreach (['15:00:00', '15:30:00', '16:00:00', '16:30:00'] as $blocked) {
            $this->assertSame(
                0,
                $this->packs->availableGuestsFor($this->slot($this->packZone, $blocked), $this->pack120),
                "La franja {$blocked} está dentro del tramo de las fiestas de las 15:00 y no puede admitir otra.",
            );
        }

        // A las 17:00 las tres han terminado (el tramo es semiabierto): el cupo vuelve entero.
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot($this->packZone, '17:00:00'), $this->pack120));
        $this->assertSame(60, $this->packs->freeGuestSlots($this->slot($this->packZone, '17:00:00'), $this->pack120));
    }

    public function test_a_party_that_starts_on_the_half_hour_consumes_the_quota_of_the_starts_around_it(): void
    {
        // El caso espejo, y el que la rejilla en punto no podía expresar: la fiesta entra a las
        // 15:30, así que resta cupo TAMBIÉN a la de las 15:00 (que seguiría dentro a las 15:30).
        $this->partyOrder('15:30:00', 30);

        $this->assertSame(30, $this->packs->freeGuestSlots($this->slot($this->packZone, '15:00:00'), $this->pack120));
        $this->assertSame(30, $this->packs->freeGuestSlots($this->slot($this->packZone, '15:30:00'), $this->pack120));
        $this->assertSame(30, $this->packs->freeGuestSlots($this->slot($this->packZone, '17:00:00'), $this->pack120));
        // A las 17:30 ya no se solapan: la fiesta de las 15:30 terminó a las 17:30.
        $this->assertSame(60, $this->packs->freeGuestSlots($this->slot($this->packZone, '17:30:00'), $this->pack120));
    }

    public function test_a_later_party_does_not_eat_into_an_earlier_start(): void
    {
        // El gemelo del anterior en el pool de packs: una fiesta de las 18:00 no puede restar cupo
        // a las 15:00, por muy solapada que esté la rejilla entre medias.
        $this->partyOrder('18:00:00', 20);

        $this->assertSame(60, $this->packs->freeGuestSlots($this->slot($this->packZone, '15:00:00'), $this->pack120));
        $this->assertSame(60, $this->packs->freeGuestSlots($this->slot($this->packZone, '15:30:00'), $this->pack120));
        // Desde las 16:30 el tramo de dos horas ya alcanza a las 18:00 y el cupo baja.
        $this->assertSame(40, $this->packs->freeGuestSlots($this->slot($this->packZone, '16:30:00'), $this->pack120));
    }

    public function test_the_party_count_is_capped_across_overlapping_starts(): void
    {
        // El OTRO tope, y se mide en SIMULTÁNEAS aunque entren por franjas distintas: cinco fiestas
        // a la vez es el máximo de la zona (`max_per_slot`). Con 40 niños en juego, el cupo de 60
        // ni se acerca — lo que corta aquí es el número de fiestas, no las plazas.
        $this->partyOrder('15:00:00', 8);
        $this->partyOrder('15:00:00', 8);
        foreach (['15:30:00', '16:00:00', '16:30:00'] as $start) {
            $this->partyOrder($start, 8);
        }

        // A las 16:30 conviven las cinco (las dos de las 15:00 duran hasta las 17:00) → cerrado.
        $this->assertSame(0, $this->packs->availableGuestsFor($this->slot($this->packZone, '16:30:00'), $this->pack120));

        // CONTROL: a las 17:00 las dos primeras ya han terminado y quedan tres → vuelve a caber una,
        // así que el 0 de arriba es el tope de fiestas y no una franja rota.
        $this->assertSame(20, $this->packs->availableGuestsFor($this->slot($this->packZone, '17:00:00'), $this->pack120));
    }

    // ─── El guardián: no basta con que la oferta lo esconda ───────────────────────────

    public function test_the_checkout_rejects_a_party_that_overlaps_a_full_quota(): void
    {
        foreach ([20, 20, 20] as $guests) {
            $this->partyOrder('15:00:00', $guests);
        }

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->pack120->id, 'date' => $this->date, 'time' => '15:30:00', 'qty' => 20,
        ]]);
    }

    public function test_the_checkout_sells_the_same_half_hour_start_when_nothing_overlaps_it(): void
    {
        // CONTROL del caso anterior: sin el solape, esa misma franja `:30` se vende de verdad —
        // el rechazo de arriba es por aforo, no porque una franja `:30` sea invendible.
        $order = $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->pack120->id, 'date' => $this->date, 'time' => '15:30:00', 'qty' => 20,
        ]]);

        $item = $order->items()->firstOrFail();
        $this->assertSame('15:30:00', (string) $item->slot->start_time);
        $this->assertSame(20, (int) $item->seats);
    }

    public function test_the_checkout_rejects_an_entry_that_overlaps_a_full_slot(): void
    {
        $this->entryOrder('15:00:00', $this->entry60, 20); // la zona entera, 15:00–16:00

        $this->expectException(ReservationException::class);
        $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->entry60->id, 'date' => $this->date, 'time' => '15:30:00', 'qty' => 1,
        ]]);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────────────

    private function slot(Zone $zone, string $time): Slot
    {
        return Slot::with('zone')
            ->where('zone_id', $zone->id)->where('date', $this->date)->where('start_time', $time)
            ->firstOrFail();
    }

    private function entryOrder(string $time, TicketType $type, int $seats): void
    {
        $this->storedOrder($this->slot($this->entryZone, $time), $type, $seats, 1000);
    }

    private function partyOrder(string $time, int $guests): void
    {
        $this->storedOrder($this->slot($this->packZone, $time), $this->pack120, $guests, 1500);
    }

    private function storedOrder(Slot $entry, TicketType $type, int $seats, int $unitPrice): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)),
            'status' => Order::STATUS_PAID,
        ]);

        $order->items()->create([
            'ticket_type_id' => $type->id,
            'slot_id' => $entry->id,
            'quantity' => $seats,
            'unit_price' => $unitPrice,
            'seats' => $seats,
        ]);
    }
}
