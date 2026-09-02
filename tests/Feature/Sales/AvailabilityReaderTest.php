<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 3 · paso 4b — la DISPONIBILIDAD ofrecida (`Booking\Contracts\AvailabilityOffer`).
 *
 * Dos cosas se prueban aquí y no en el endpoint, porque son de dominio:
 *
 * 1. **La cesta descuenta cupo.** Es lo que obliga a que la disponibilidad la lleve (`AFORO-02`):
 *    sin ella, una segunda línea sobre la misma franja vería libres las plazas que la primera ya
 *    retiene, y el checkout la rechazaría después. Antes esa derivación —de líneas de cesta a
 *    ocupantes— vivía dentro de `Livewire\Tickets\Purchase`, o sea en una clase de interfaz.
 *
 * 2. **`available` y `maxQuantity` no son el mismo número en un pack**, y confundirlos vende de
 *    más. `SlotOffer` calculaba los dos desde siempre y solo publicaba el primero.
 */
class AvailabilityReaderTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityOffer $offer;

    private Zone $zone;

    private string $date;

    private int $normalRateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offer = app(AvailabilityOffer::class);

        $this->normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        // Cupo de invitados por franja MUY por encima del máximo del pack: es la configuración que
        // separa «plazas que quedan» de «invitados que admite esta fiesta».
        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
            'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);

        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 10, 'online_capacity' => 10,
            ]);
        }
    }

    // ── Días ──────────────────────────────────────────────────────────────────────────────────

    public function test_the_offered_days_carry_the_price_and_the_rate_of_each_day(): void
    {
        $entry = $this->entry(990);

        $days = $this->offer->dates($entry->id);

        $this->assertCount(1, $days);
        $this->assertSame($this->date, $days[0]->date);
        $this->assertSame(990, $days[0]->priceCents);
        $this->assertSame('normal', $days[0]->rateKey);
    }

    /**
     * Un día se ofrece porque tiene franjas, no porque tenga precio. El calendario lo enseña con
     * `priceCents` nulo y el checkout es quien rechaza la línea (`PAY-12`) — igual que hace la
     * tarificación, y por el mismo motivo: romper la pantalla dejaría al cliente sin poder avanzar
     * ni entender por qué.
     */
    public function test_a_day_without_a_price_is_still_offered_with_a_null_price(): void
    {
        $priceless = TicketType::create([
            'name' => ['es' => 'Sin tarifa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 3,
        ]);

        $days = $this->offer->dates($priceless->id);

        $this->assertCount(1, $days);
        $this->assertNull($days[0]->priceCents);
    }

    public function test_a_product_outside_the_catalog_has_no_availability_at_all(): void
    {
        $entry = $this->entry(990);
        $entry->update(['is_sellable' => false]);

        $this->assertSame([], $this->offer->dates($entry->id));
        $this->assertSame([], $this->offer->times($entry->id, $this->date));
        $this->assertSame(0, $this->offer->maxQuantity($entry->id, $this->date, '10:00:00'));
    }

    public function test_a_product_whose_zone_stopped_operating_has_no_availability(): void
    {
        $entry = $this->entry(990);
        $this->zone->update(['is_active' => false]);

        $this->assertSame([], $this->offer->dates($entry->id));
        $this->assertSame([], $this->offer->times($entry->id, $this->date));
    }

    // ── Horas: la cesta descuenta (AFORO-02) ──────────────────────────────────────────────────

    public function test_the_cart_of_the_client_discounts_the_seats_it_already_holds(): void
    {
        $entry = $this->entry(990);

        $before = $this->offer->times($entry->id, $this->date);
        $this->assertSame(10, $this->timeAt($before, '10:00:00')->available);

        $after = $this->offer->times($entry->id, $this->date, [
            ['ticket_type_id' => $entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 4],
        ]);

        $this->assertSame(
            6, $this->timeAt($after, '10:00:00')->available,
            'la disponibilidad tiene que descontar lo que la propia cesta ya retiene (AFORO-02)'
        );
        // Y solo esa franja: la de las 11:00 no la toca una línea de las 10:00 de 1 h.
        $this->assertSame(10, $this->timeAt($after, '11:00:00')->available);
    }

    public function test_a_cart_line_of_another_day_does_not_discount_anything(): void
    {
        $entry = $this->entry(990);

        $times = $this->offer->times($entry->id, $this->date, [
            ['ticket_type_id' => $entry->id, 'date' => Carbon::parse($this->date)->addDay()->toDateString(), 'time' => '10:00:00', 'qty' => 4],
        ]);

        $this->assertSame(10, $this->timeAt($times, '10:00:00')->available);
    }

    /**
     * Una hora llena se sigue LISTANDO, con `sellable = false`. Ocultarla dejaría al cliente sin
     * entender por qué su hora «ha desaparecido»; enseñarla agotada se explica sola.
     */
    public function test_a_time_filled_by_the_cart_is_still_listed_but_not_sellable(): void
    {
        $entry = $this->entry(990);

        $times = $this->offer->times($entry->id, $this->date, [
            ['ticket_type_id' => $entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 10],
        ]);

        $slot = $this->timeAt($times, '10:00:00');
        $this->assertSame(0, $slot->available);
        $this->assertSame(0, $slot->maxQuantity);
        $this->assertFalse($slot->sellable);
    }

    // ── Packs: contar no es elegir ────────────────────────────────────────────────────────────

    /**
     * **El hallazgo del paso.** Con un cupo de 60 invitados por franja y un pack de máximo 20, las
     * plazas que quedan (60) y los invitados que admite esa fiesta (20) son números distintos. Un
     * selector de cantidad construido sobre el primero dejaría pedir 60 invitados que el checkout
     * rechazaría; `SlotOffer` calculaba los dos y solo publicaba el de mostrar.
     */
    public function test_a_pack_reports_the_free_seats_and_the_selectable_max_as_different_numbers(): void
    {
        $pack = $this->pack(min: 8, max: 20);

        $slot = $this->timeAt($this->offer->times($pack->id, $this->date), '10:00:00');

        $this->assertSame(60, $slot->available, 'available = plazas que le quedan a la franja');
        $this->assertSame(20, $slot->maxQuantity, 'maxQuantity = lo que admite ESTA fiesta');
        $this->assertSame(
            20, $this->offer->maxQuantity($pack->id, $this->date, '10:00:00'),
            'la consulta de una franja concreta tiene que decir lo mismo que la lista'
        );
    }

    public function test_for_an_entry_both_numbers_are_the_same(): void
    {
        $entry = $this->entry(990);

        $slot = $this->timeAt($this->offer->times($entry->id, $this->date), '10:00:00');

        $this->assertSame($slot->available, $slot->maxQuantity);
    }

    /**
     * Un pack cuyo cupo libre no llega a su mínimo de invitados NO se ofrece: no es reservable de
     * ninguna forma, así que listarlo solo confundiría. Aquí lo provoca la propia cesta, que es lo
     * que hace falta demostrar: sin la cesta delante, esa hora se habría ofrecido.
     */
    public function test_a_pack_disappears_when_the_cart_leaves_it_below_its_minimum(): void
    {
        $pack = $this->pack(min: 8, max: 20);

        $this->assertNotNull($this->timeAt($this->offer->times($pack->id, $this->date), '10:00:00'));

        $cart = [
            ['ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 20],
            ['ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 20],
            ['ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 15],
        ];

        $times = $this->offer->times($pack->id, $this->date, $cart);

        $this->assertNull(
            $this->timeAt($times, '10:00:00', orFail: false),
            'con 55 de 60 invitados retenidos por la cesta quedan 5, por debajo del mínimo de 8'
        );
        // Pero preguntando por esa franja concreta sí se puede saber cuánto queda: es la diferencia
        // entre «qué te ofrezco» y «cuánto cabe aquí».
        $this->assertSame(5, $this->offer->maxQuantity($pack->id, $this->date, '10:00:00', $cart));
    }

    /**
     * ⚠️ **Este caso CAMBIÓ DE PREMISA y se reescribió** (`specs/hora-extra.md` §7·D1, `#410` —
     * el precedente es el `SlotOfferTest` de `#324`): afirmaba que «una fiesta no resta plazas de
     * entrada», y eso era verdad SOLO en la derivación de la oferta — lo ALMACENADO dice lo
     * contrario: una línea de pack nace con franja y `seats`, y `occupancyMap` (que NO filtra por
     * tipo, lo documenta el propio `PackAvailability`) la cuenta contra las plazas de entrada de su
     * zona en cuanto existe. El COBRO (`OrderCreator`) también la contaba. O sea que la oferta
     * decía 10, el checkout rechazaba, y este test cementaba esa divergencia (`AFORO-02`) — sin
     * morder en producción solo porque los packs viven en zona propia.
     *
     * Con la derivación ÚNICA (`CartOccupants`), la oferta cuenta lo MISMO que contará la BD:
     * 20 invitados provisionales sobre una franja de 10 plazas → 0 para una entrada.
     * (El CUPO de packs sigue siendo un pool aparte: eso no cambió — lo vigilan los casos de
     * `maxQuantity` de pack de este mismo fichero.)
     */
    public function test_a_pack_in_the_cart_occupies_the_seats_it_will_occupy_once_stored(): void
    {
        $entry = $this->entry(990);
        $pack = $this->pack(min: 8, max: 20);

        $times = $this->offer->times($entry->id, $this->date, [
            ['ticket_type_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 20],
        ]);

        $this->assertSame(0, $this->timeAt($times, '10:00:00')->available);

        // El control de la premisa nueva: sin la fiesta en la cesta, las 10 plazas siguen ahí.
        $this->assertSame(10, $this->timeAt($this->offer->times($entry->id, $this->date), '10:00:00')->available);
    }

    // ── Cuánto cabe en una franja concreta ────────────────────────────────────────────────────

    public function test_the_max_for_a_slot_that_does_not_exist_is_zero(): void
    {
        $entry = $this->entry(990);

        $this->assertSame(0, $this->offer->maxQuantity($entry->id, $this->date, '23:00:00'));
    }

    public function test_the_max_for_a_slot_discounts_the_cart_too(): void
    {
        $entry = $this->entry(990);

        $this->assertSame(3, $this->offer->maxQuantity($entry->id, $this->date, '10:00:00', [
            ['ticket_type_id' => $entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 7],
        ]));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────────────────

    private function entry(int $priceCents): TicketType
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $entry->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        return $entry;
    }

    private function pack(int $min, int $max): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'min_qty' => $min, 'max_qty' => $max,
            'prep_before_min' => 0, 'prep_after_min' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);
        $pack->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 18000]);

        return $pack;
    }

    /** @param  list<OfferedTime>  $times */
    private function timeAt(array $times, string $time, bool $orFail = true): ?OfferedTime
    {
        foreach ($times as $slot) {
            if ($slot->time === $time) {
                return $slot;
            }
        }

        if ($orFail) {
            $this->fail("la hora {$time} no está entre las ofrecidas");
        }

        return null;
    }
}
