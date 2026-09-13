<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * **La edad del cumpleañero decide si el pack es el suyo** (`DECISIONES #588`, `[DECIDIDO owner]`).
 *
 * Un pack de cumpleaños declara su tramo de edades y pide la edad del cumpleañero (`celebrant_age`).
 * Con la edad fuera del tramo **la web no deja reservarlo** y recomienda el pack de su familia que la
 * admite; **el mostrador avisa y deja**. La regla vive en `TicketType::celebrantAgeMismatch()` y la
 * aplican la validación de línea (su caso está en `CartLineValidationTest`) y `OrderCreator`, que la
 * re-valida para que un cuerpo a mano no se la salte.
 */
class CelebrantAgeTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private TicketType $kids;

    private TicketType $jump;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $rate = RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0, 'is_active' => true]);

        $this->zone = Zone::create([
            'slug' => 'cumples', 'name' => ['es' => 'Cumpleaños'], 'is_active' => true, 'show_in_landing' => false,
            'max_per_slot' => 5, 'max_guests_per_slot' => 60,
        ]);
        $this->date = Carbon::today()->addDays(5)->toDateString();
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date, 'start_time' => '17:00:00', 'end_time' => '19:00:00',
            'capacity' => 60, 'online_capacity' => 60,
        ]);

        $this->kids = $this->pack('Pack Kids', 4, 7, 1, $rate);
        $this->jump = $this->pack('Pack Jump', 8, null, 2, $rate);
    }

    private function pack(string $name, ?int $min, ?int $max, int $position, RateType $rate): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 6, 'max_qty' => 50, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => $position,
            'guest_age_family' => 'cumple', 'guest_age_min' => $min, 'guest_age_max' => $max,
            'event_fields' => [
                ['key' => 'celebrant', 'type' => 'text', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Nombre']],
                ['key' => 'age', 'type' => TicketType::FIELD_TYPE_CELEBRANT_AGE, 'required' => true, 'stage' => 'booking', 'label' => ['es' => '¿Cuántos años cumple?']],
            ],
        ]);
        $pack->prices()->create(['rate_type_id' => $rate->id, 'amount_cents' => 1495]);

        return $pack;
    }

    /** @return list<array<string, mixed>> */
    private function cart(TicketType $pack, string $age): array
    {
        return [[
            'ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '17:00:00', 'qty' => 8,
            'event_data' => ['celebrant' => 'Mara', 'age' => $age], 'addons' => [],
        ]];
    }

    // ── La regla ──────────────────────────────────────────────────────────────────────────────

    public function test_an_age_inside_the_range_fits_and_an_age_outside_recommends_the_sibling(): void
    {
        $this->assertNull($this->kids->celebrantAgeMismatch(['age' => '7']), 'el extremo del tramo cabe');

        $fuera = $this->kids->celebrantAgeMismatch(['age' => '9']);
        $this->assertNotNull($fuera);
        $this->assertSame(['age', 9, 4, 7, $this->jump->id, 'Pack Jump'], [
            $fuera->field, $fuera->age, $fuera->min, $fuera->max, $fuera->suggestedProductId, $fuera->suggestedProductName,
        ]);
        $this->assertSame(
            __('tickets.errors.celebrant_age_between', ['min' => 4, 'max' => 7]).' '.__('tickets.errors.celebrant_age_try', ['product' => 'Pack Jump']),
            $fuera->sentence(),
        );
    }

    /** Lo que FALTA lo dice la regla de obligatorios; un hueco o una edad imposible no es «fuera de tramo». */
    public function test_an_unanswered_or_impossible_age_is_not_a_mismatch(): void
    {
        $this->assertNull($this->kids->celebrantAgeMismatch([]));
        $this->assertNull($this->kids->celebrantAgeMismatch(['age' => 'nueve']));
        $this->assertNull($this->kids->celebrantAgeMismatch(['age' => '999']));
    }

    /** Sin tramo declarado o sin el campo de edad no hay nada que comprobar. */
    public function test_a_pack_without_range_or_without_the_field_checks_nothing(): void
    {
        // Sin tramo y FUERA de la familia: dentro, un tramo abierto (0–255) pisaría al del hermano.
        $this->kids->update(['guest_age_family' => null, 'guest_age_min' => null, 'guest_age_max' => null]);
        $this->assertNull($this->kids->fresh()->celebrantAgeMismatch(['age' => '30']));

        $this->jump->update(['event_fields' => [['key' => 'age', 'type' => 'number', 'required' => true, 'stage' => 'booking', 'label' => ['es' => 'Edad']]]]);
        $this->assertNull($this->jump->fresh()->celebrantAgeMismatch(['age' => '3']), 'un `number` no es la edad del cumpleañero');
    }

    /** Un hermano inactivo o fuera de venta online no se recomienda: no se podría reservar. */
    public function test_a_sibling_that_cannot_be_sold_is_not_recommended(): void
    {
        $this->jump->update(['is_sellable' => false]);

        $this->assertNull($this->kids->fresh()->celebrantAgeMismatch(['age' => '9'])->suggestedProductId);
    }

    // ── Quién la aplica ───────────────────────────────────────────────────────────────────────

    /** La WEB no crea el pedido con la edad fuera del tramo, aunque el cuerpo salte el carrito. */
    public function test_order_creation_rejects_an_age_outside_the_range_for_an_online_buyer(): void
    {
        try {
            app(OrderCreator::class)->createPendingOrder(User::factory()->create(), $this->cart($this->kids, '9'));
            $this->fail('la web creó un pedido con la edad del cumpleañero fuera del tramo');
        } catch (ReservationException $e) {
            $this->assertSame('tickets.errors.celebrant_age_line', $e->getMessage());
        }

        $order = app(OrderCreator::class)->createPendingOrder(User::factory()->create(), $this->cart($this->kids, '6'));
        $this->assertCount(1, $order->items, 'CONTROL: con una edad del tramo el pedido nace');
    }

    /** El MOSTRADOR crea el pedido igual: el panel avisa y deja (`[DECIDIDO owner]`). */
    public function test_a_counter_sale_is_not_blocked_by_the_age(): void
    {
        $order = app(OrderCreator::class)->createPendingOrder(User::factory()->create(), $this->cart($this->kids, '9'), null, CounterSale::byOperator());

        $this->assertCount(1, $order->items);
        $this->assertSame('9', $order->items->first()->event_data['age'] ?? null);
    }
}
