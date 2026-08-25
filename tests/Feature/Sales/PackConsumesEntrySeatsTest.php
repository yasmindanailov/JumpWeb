<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\PackAvailability;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **LOS DOS AFOROS NO SON INDEPENDIENTES, Y LO SON EN UNA SOLA DIRECCIÓN** (`DECISIONES #148`).
 *
 * ⚠️⚠️ Hasta el 2026-08-25 el docblock de {@see PackAvailability} afirmaba que «un cumpleaños **no
 * resta plazas de entrada** ni viceversa». **La mitad de esa frase era falsa**, y no lo destapó una
 * revisión de código: lo destapó el escenario `mixed` de `purchase:verify-oversell`, cuyo número de
 * ganadores **variaba entre ejecuciones idénticas** — dos veces 2, una vez 1.
 *
 * ▶ **La causa**: `SlotAvailability::occupancyMap()` suma los `seats` de **todos** los `order_items`
 * de la zona/día **sin filtrar por tipo**, y una línea de pack lleva `seats` como cualquier otra.
 *
 * ▶ **La asimetría, dicha en una frase**: una entrada no consume cupo de fiestas, pero **una fiesta
 * sí consume asientos de entrada**.
 *
 * ⚠️ **Este fichero NO juzga si eso está bien.** Puede ser exactamente lo correcto —los niños de un
 * cumpleaños están en el parque y ocupan sitio real— o puede ser un defecto. **Es decisión de
 * producto.** Lo que hace es **fijar el comportamiento medido** para que, el día que cambie, cambie
 * porque alguien lo decidió y no por un efecto lateral de tocar `occupancyMap`.
 */
class PackConsumesEntrySeatsTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $entry;

    private TicketType $pack;

    private string $date;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'],
            'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        // Zona con cupo de fiestas holgado: aquí no se mide el cupo, se mide la INTERFERENCIA.
        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true,
            'max_per_slot' => 5, 'max_guests_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (range(10, 14) as $hour) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 10, 'online_capacity' => 10,
            ]);
        }

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_ENTRY,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_PACK,
            'duration_min' => 60, 'prep_before_min' => 0, 'prep_after_min' => 0,
            'min_qty' => 1, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
    }

    /**
     * ⚠️⚠️ **Una fiesta SÍ resta plazas de entrada.** Medido: franja de 10 plazas, fiesta de 8
     * invitados, quedan **2**. Es lo contrario de lo que la doc afirmaba.
     */
    public function test_a_birthday_party_eats_entry_seats_from_its_slot(): void
    {
        $slot = $this->slotAt('11:00:00');
        $availability = app(SlotAvailability::class);

        $this->assertSame(10, $availability->availableFor($slot, 60), 'la franja empieza con sus 10 plazas');

        $this->party($slot, guests: 8);

        $this->assertSame(
            2, $availability->availableFor($slot->fresh('zone'), 60),
            "Una fiesta de 8 invitados ya NO resta plazas de entrada.\n".
            '▶ Si esto cambia a 10, alguien ha filtrado los packs en `SlotAvailability::occupancyMap` '.
            'y eso es una decisión de producto: los niños siguen estando en el parque.',
        );
    }

    /**
     * **Y en la otra dirección NO ocurre: una entrada no consume cupo de fiestas.**
     *
     * Es la mitad de la frase original que sí era cierta, y por eso se fija: son dos propiedades
     * distintas, y una puede cambiar sin la otra.
     */
    public function test_an_entry_does_not_eat_the_party_quota(): void
    {
        $slot = $this->slotAt('11:00:00');
        $packs = app(PackAvailability::class);

        $before = $packs->availableGuestsFor($slot, $this->pack);

        $this->entryFor($slot, seats: 6);

        $this->assertSame(
            $before, $packs->availableGuestsFor($slot->fresh('zone'), $this->pack),
            'Una entrada ha pasado a consumir cupo de fiestas: eso NO es el comportamiento medido.',
        );
    }

    /**
     * **El caso que lo hace visible para el operador, y que explica la carrera del verificador.**
     *
     * Con la franja al límite, el orden decide: si entra primero la fiesta, la entrada **ya no cabe**.
     * Es exactamente por esto que el escenario `mixed` de `purchase:verify-oversell` tiene un número
     * de ganadores variable — y por lo que su invariante mide TOPES y no ganadores.
     */
    public function test_a_party_can_leave_the_slot_without_room_for_a_single_entry(): void
    {
        $slot = $this->slotAt('12:00:00');
        $availability = app(SlotAvailability::class);

        $this->party($slot, guests: 10);   // la fiesta ocupa las 10 plazas de la franja

        $this->assertSame(
            0, $availability->availableFor($slot->fresh('zone'), 60),
            'Tras una fiesta que llena la franja, todavía se ofrecen plazas de entrada.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────────────────

    private function slotAt(string $time): Slot
    {
        return Slot::where('zone_id', $this->zone->id)->where('date', $this->date)
            ->where('start_time', $time)->firstOrFail();
    }

    private function party(Slot $slot, int $guests): OrderItem
    {
        return $this->lineFor($slot, $this->pack, $guests);
    }

    private function entryFor(Slot $slot, int $seats): OrderItem
    {
        return $this->lineFor($slot, $this->entry, $seats);
    }

    private function lineFor(Slot $slot, TicketType $type, int $qty): OrderItem
    {
        $order = Order::create([
            'user_id' => $this->user->id, 'code' => 'JJ-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PENDING, 'subtotal' => 100, 'total' => 100,
            'currency' => 'EUR', 'expires_at' => now()->addHour(),
        ]);

        return OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $qty, 'seats' => $qty, 'unit_price' => 100,
        ]);
    }
}
