<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Booking\Services\SlotAvailability;
use App\Domain\Booking\Services\TicketIssuer;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La HORA EXTRA (`specs/hora-extra.md`, `#410`): un complemento que OCUPA la franja siguiente al
 * tramo de su padre — «4 personas de 10 a 12 y UNA de 12 a 13»: menos plazas más tarde, un OCUPANTE
 * nuevo, no una duración distinta.
 *
 * Lo que se fija aquí es la MECÁNICA de venta (§6 de la spec): la hija nace con franja y plazas y el
 * aforo la cuenta (§4.3) · un complemento neutro queda IDÉNTICO a siempre (§6·2, el control) · sin
 * franja siguiente no hay hora extra (§6·3) · los HERMANOS de la misma línea se cuentan entre sí
 * (§6·6, el borde que sobrevende SIN carrera) · no se quedan más de los que entran (§6·7, la SUMA
 * que `effectiveQuantity` no puede ver) · una fila torcida por la puerta de atrás ni se ofrece ni se
 * vende — y JAMÁS ocupa «hasta el cierre» (§6·8, el cinturón del guard) · la oferta cuenta lo mismo
 * que el cobro (§6·9) · y un ticket es una ADMISIÓN (§6·11).
 *
 * La CARRERA (dos compradores disputándose la última plaza de la franja de la hija) no se puede
 * probar en SQLite: vive en `purchase:verify-oversell --scenario=extra-hour`, visto FALLAR sin la
 * validación (5 asientos escritos en una franja de 1) y PASAR con ella (1 de 8), sobre InnoDB real.
 */
class ExtraHourAddonTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private SlotAvailability $availability;

    private User $user;

    private Zone $zone;

    private string $date;

    private int $normalRateId;

    private TicketType $entry;

    private TicketType $extraHour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = app(OrderCreator::class);
        $this->availability = app(SlotAvailability::class);
        $this->user = User::factory()->create();

        $this->normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->entry = $this->makeEntry('Entrada 1h', durationMin: 60);
        $this->extraHour = $this->makeOccupyingAddon('Hora extra', durationMin: 60, priceCents: 500);
        $this->attach($this->entry, $this->extraHour);
    }

    // ─── El caso normal (§4.8 · caso 1) ─────────────────────────────────────────────────────────

    public function test_the_child_is_born_occupying_the_next_slot(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $this->extraHour->id, 'qty' => 1],
        ])]);
        $order->load('items');

        $parent = $order->items->firstWhere('parent_item_id', null);
        $child = $order->items->firstWhere('parent_item_id', $parent->id);

        $this->assertNotNull($child, 'la hija tiene que existir, colgada del padre como cualquier complemento');
        $this->assertSame($this->slotAt('11:00:00')->id, (int) $child->slot_id, 'su franja es la SIGUIENTE al tramo del padre');
        $this->assertSame(1, (int) $child->seats, 'sus plazas son las entradas que se quedan');
        $this->assertSame(1, (int) $child->quantity);

        // El cobro: 4 entradas + 1 hora extra a 5 €. La cantidad SON personas, el precio escala solo.
        $this->assertSame(4 * 1000 + 500, (int) $order->total);

        // Y el aforo la CUENTA sin tocar `occupancyMap`: el padre (60 min desde las 10:00) marca
        // SOLO su franja; a las 11:00 ocupa únicamente la hija, y las 12:00 quedan intactas.
        $this->assertSame(50 - 4, $this->availability->availableFor($this->slotAt('10:00:00'), 60));
        $this->assertSame(50 - 1, $this->availability->availableFor($this->slotAt('11:00:00'), 60));
        $this->assertSame(50, $this->availability->availableFor($this->slotAt('12:00:00'), 60));
    }

    public function test_cancelling_the_child_frees_its_seat(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $this->extraHour->id, 'qty' => 2],
        ])]);
        $child = $order->items()->whereNotNull('parent_item_id')->first();

        $this->assertSame(50 - 2, $this->availability->availableFor($this->slotAt('11:00:00'), 60));

        // El mecanismo EXISTENTE libera (`occupancyMap` excluye `cancelled_at`): nada que construir,
        // pero sí que fijar — si alguien filtrara las hijas de otra forma, esto se pondría rojo.
        $child->markCancelled($this->user);

        $this->assertSame(50, $this->availability->availableFor($this->slotAt('11:00:00'), 60));
    }

    // ─── El CONTROL: nada viejo se mueve (§6·2) ─────────────────────────────────────────────────

    public function test_a_neutral_addon_is_identical_to_before(): void
    {
        $socks = TicketType::create([
            'name' => ['es' => 'Calcetines'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 9,
        ]);
        $socks->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 300]);
        $this->attach($this->entry, $socks);

        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $socks->id, 'qty' => 2],
        ])]);
        $child = $order->items()->whereNotNull('parent_item_id')->first();

        $this->assertNull($child->slot_id, 'un complemento neutro sigue sin franja');
        $this->assertSame(0, (int) $child->seats, 'y sin plazas');
        $this->assertSame(50, $this->availability->availableFor($this->slotAt('11:00:00'), 60), 'y el aforo de la franja siguiente queda IDÉNTICO');
    }

    // ─── Sin franja siguiente no hay hora extra (§6·3 + borde 7) ────────────────────────────────

    public function test_the_last_slot_of_the_day_rejects_the_extra_hour(): void
    {
        try {
            $this->creator->createPendingOrder($this->user, [$this->line(2, '12:00:00', [
                ['ticket_type_id' => $this->extraHour->id, 'qty' => 1],
            ])]);
            $this->fail('a las 12:00 no hay franja siguiente: la hora extra tenía que rechazarse');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.addon_occupancy_line', $e->getMessage());
            $this->assertSame('Hora extra', $e->context['addon'] ?? null, 'el error NOMBRA al complemento culpable');
        }

        // El control: la misma compra SIN la hora extra entra sin problema.
        $order = $this->creator->createPendingOrder($this->user, [$this->line(2, '12:00:00')]);
        $this->assertNotNull($order->id);
    }

    public function test_a_parent_without_duration_has_no_next_slot(): void
    {
        // Borde 7: 2 de las 10 entradas reales son «ilimitadas hasta el cierre» — no tienen tramo
        // que termine, así que «la siguiente» no existe. La guarda de §4.1 protege la duración del
        // COMPLEMENTO; ésta es la del PADRE.
        $allDay = $this->makeEntry('Entrada día completo', durationMin: null);
        $this->attach($allDay, $this->extraHour);

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('tickets.errors.addon_occupancy_line');

        $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $allDay->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
            'addons' => [['ticket_type_id' => $this->extraHour->id, 'qty' => 1]],
        ]]);
    }

    public function test_a_full_next_slot_rejects_the_extra_hour(): void
    {
        // 11:00 se queda con UNA plaza libre ocupándola con una compra real (49 de 50).
        $this->creator->createPendingOrder($this->user, [$this->line(49, '11:00:00')]);

        // Una hora extra de 2 personas no cabe (queda 1)…
        try {
            $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
                ['ticket_type_id' => $this->extraHour->id, 'qty' => 2],
            ])]);
            $this->fail('con 1 plaza libre a las 11:00, una hora extra de 2 tenía que rechazarse');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.addon_occupancy_line', $e->getMessage());
        }

        // …y la de 1 sí — el rechazo es por AFORO, no por existencia de la franja.
        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $this->extraHour->id, 'qty' => 1],
        ])]);
        $this->assertNotNull($order->id);
        $this->assertSame(0, $this->availability->availableFor($this->slotAt('11:00:00'), 60));
    }

    // ─── Los HERMANOS de la misma línea (§6·6: sobrevende SIN carrera) ──────────────────────────

    public function test_siblings_of_the_same_line_count_each_other(): void
    {
        $twoHours = $this->makeOccupyingAddon('2 horas extra', durationMin: 120, priceCents: 900);
        $this->attach($this->entry, $twoHours);

        // A las 11:00 queda UNA plaza (49 vendidas). Dos hermanos que ocupan —«1 hora» y «2 horas»,
        // los dos empezando a las 11:00— piden una plaza cada uno: solo existe una.
        $this->creator->createPendingOrder($this->user, [$this->line(49, '11:00:00')]);

        try {
            $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
                ['ticket_type_id' => $this->extraHour->id, 'qty' => 1],
                ['ticket_type_id' => $twoHours->id, 'qty' => 1],
            ])]);
            $this->fail('dos hermanos sobre la última plaza: antes de `CartOccupants`, cada uno se validaba contra un mapa sin el otro y la plaza se vendía DOS veces en una sola petición, con el lock puesto');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.addon_occupancy_line', $e->getMessage());
        }

        // El control de la premisa: UNO solo de los dos sí cabe.
        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $this->extraHour->id, 'qty' => 1],
        ])]);
        $this->assertNotNull($order->id);
    }

    // ─── No se quedan más de los que entran (§6·7: la SUMA) ─────────────────────────────────────

    public function test_the_sum_of_staying_cannot_exceed_the_entering(): void
    {
        $twoHours = $this->makeOccupyingAddon('2 horas extra', durationMin: 120, priceCents: 900);
        $this->attach($this->entry, $twoHours);

        // «1 hora extra» ×3 y «2 horas extra» ×3 sobre un padre de 4: cada uno pasa por separado
        // (3 ≤ 4) y se quedan 6 de 4 — la mitad del tope que `effectiveQuantity()` no puede ver,
        // porque resuelve UN complemento cada vez (§4.4·5, el hueco 1 de la segunda revisión).
        try {
            $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
                ['ticket_type_id' => $this->extraHour->id, 'qty' => 3],
                ['ticket_type_id' => $twoHours->id, 'qty' => 3],
            ])]);
            $this->fail('6 se quedan de 4: la SUMA tenía que rechazarse aunque cada complemento pase por separado');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.addon_over_line', $e->getMessage());
            $this->assertSame(6, $e->context['staying'] ?? null);
            $this->assertSame(4, $e->context['entering'] ?? null);
        }

        // Un solo complemento por encima del padre cae por la MISMA regla (5 > 4)…
        try {
            $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
                ['ticket_type_id' => $this->extraHour->id, 'qty' => 5],
            ])]);
            $this->fail('5 se quedan de 4');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.addon_over_line', $e->getMessage());
        }

        // …y el borde exacto (4 de 4: todos se quedan) es legítimo y entra.
        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $this->extraHour->id, 'qty' => 4],
        ])]);
        $this->assertSame(4, (int) $order->items()->whereNotNull('parent_item_id')->first()->seats);
    }

    // ─── El CINTURÓN del guard (§6·8): la puerta de atrás no ocupa «hasta el cierre» ────────────

    public function test_a_backdoor_insane_occupant_is_not_offered_nor_sold(): void
    {
        // El CONTROL de la aserción de oferta: con la configuración SANA, el complemento SÍ se
        // lista — sin esto, el «no se ofrece» de abajo pasaría igual con el viewModel roto.
        $this->assertContains($this->extraHour->id, $this->offeredSingleIds(), 'control: sano, se ofrece');

        // `Query\Builder::update()` esquiva los eventos del modelo (el límite documentado de #299):
        // así entra una fila «ocupa y no dice cuánto», que el guard de `saving` jamás dejaría.
        TicketType::whereKey($this->extraHour->id)->update(['duration_min' => null]);

        // Ni se OFRECE (el modelo de vista no la lista)…
        $this->assertNotContains($this->extraHour->id, $this->offeredSingleIds(), 'un ocupante sin duración no se ofrece');

        // …ni se VENDE (y JAMÁS degrada a complemento neutro: eso sería vender sin ocupar).
        try {
            $this->creator->createPendingOrder($this->user, [$this->line(2, '10:00:00', [
                ['ticket_type_id' => $this->extraHour->id, 'qty' => 1],
            ])]);
            $this->fail('un ocupante declarado con configuración rota no puede venderse');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.unavailable', $e->getMessage());
        }

        // Y el peor mundo NO existe: nada quedó escrito ocupando «hasta el cierre».
        $this->assertSame(50, $this->availability->availableFor($this->slotAt('11:00:00'), 60));
        $this->assertSame(50, $this->availability->availableFor($this->slotAt('12:00:00'), 60));
    }

    // ─── La oferta y el cobro dicen lo MISMO (§6·9) ─────────────────────────────────────────────

    public function test_the_offer_counts_the_extra_hour_of_the_cart(): void
    {
        // La cesta lleva una línea con hora extra; la OFERTA de la franja siguiente tiene que
        // descontarla, o se ofrece lo que el checkout rechaza (`AFORO-02` — el defecto exacto del
        // borde 6: la derivación de la oferta era una copia ciega a las hijas).
        $cart = [$this->line(4, '10:00:00', [['ticket_type_id' => $this->extraHour->id, 'qty' => 2]])];

        $offer = app(AvailabilityOffer::class);
        $times = collect($offer->times($this->entry->id, $this->date, $cart));
        $elevenOclock = $times->firstWhere('time', '11:00:00');

        $this->assertSame(50 - 2, $elevenOclock->available, 'la oferta descuenta a la hija de la cesta');
        $this->assertSame(50 - 2, $offer->maxQuantity($this->entry->id, $this->date, '11:00:00', $cart));
    }

    // ─── Un ticket es una ADMISIÓN (§6·11) ──────────────────────────────────────────────────────

    public function test_tickets_are_issued_only_for_admissions(): void
    {
        $order = $this->creator->createPendingOrder($this->user, [$this->line(4, '10:00:00', [
            ['ticket_type_id' => $this->extraHour->id, 'qty' => 2],
        ])]);

        app(TicketIssuer::class)->issue($order);

        // 4 admisiones → 4 tickets. La hija estrenó franja y plazas, pero es la MISMA persona
        // quedándose: cero tickets suyos (era no-op mientras ninguna hija tenía franja — desde la
        // hora extra deja de serlo, y por eso se fija con caso y no se deja emergente, `#410`).
        $this->assertSame(4, Ticket::where('order_id', $order->id)->count());
        $this->assertSame(0, Ticket::where('order_id', $order->id)->where('slot_id', $this->slotAt('11:00:00')->id)->count());
    }

    // ─── Fixture ────────────────────────────────────────────────────────────────────────────────

    private function makeEntry(string $name, ?int $durationMin): TicketType
    {
        $type = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => $durationMin, 'is_sellable' => true, 'is_active' => true,
            'seats_per_unit' => 1, 'position' => (int) TicketType::max('position') + 1,
        ]);
        $type->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 1000]);

        return $type;
    }

    private function makeOccupyingAddon(string $name, int $durationMin, int $priceCents): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
            'duration_min' => $durationMin, 'occupies_after_parent' => true,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => (int) TicketType::max('position') + 1,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        return $addon;
    }

    private function attach(TicketType $product, TicketType $addon): void
    {
        $product->addons()->attach($addon->id, [
            'position' => (int) $addon->position, 'is_included' => false, 'included_quantity' => 1,
            'is_mandatory' => false, 'quantity_mode' => 'fixed', 'allow_extra' => true,
            'choice_group' => null, 'max_qty' => null, 'requires_addon_id' => null,
        ]);
        $product->unsetRelation('addons');
    }

    /**
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $addons
     * @return array<string, mixed>
     */
    private function line(int $qty, string $time, array $addons = []): array
    {
        return [
            'ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => $time,
            'qty' => $qty, 'addons' => $addons,
        ];
    }

    private function slotAt(string $time): Slot
    {
        return Slot::where('zone_id', $this->zone->id)->where('date', $this->date)
            ->where('start_time', $time)->firstOrFail();
    }

    /** Ids de complementos SUELTOS que el modelo de vista ofrece para la entrada del fixture. */
    private function offeredSingleIds(): array
    {
        $view = app(AddonResolver::class)->viewModel(
            $this->entry->fresh()->load('addons')->addons, [], [], 4, false, Carbon::parse($this->date),
        );

        return array_map(fn (array $row): int => (int) $row['id'], $view['singles']);
    }
}
