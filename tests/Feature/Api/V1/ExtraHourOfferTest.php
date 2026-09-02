<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\ApiTestCase;

/**
 * La OFERTA de la hora extra (`specs/hora-extra.md` §4.5, `#410`): el complemento que OCUPA la
 * franja siguiente, resuelto por `POST /catalog/products/{id}/addons` CON la hora de la línea
 * delante. El cliente elige las 12:00, añade la hora extra, y la franja de las 13:00 puede estar
 * llena o no existir — si la oferta no lo dice AQUÍ, se ofrece lo que el checkout rechaza (el primo
 * de `AFORO-02`, la trampa exacta que motivó §4.5).
 *
 * Lo que se fija: a una hora donde no aterriza, el ocupante NO SE OFRECE y la `selection` lo suelta
 * (la misma regla que el complemento de pago sin tarifa) · el que aterriza justo sale con
 * `max_quantity`/`can_increase` capados por sus plazas reales Y por «no se quedan más de los que
 * entran» (§4.4·5) · y sin fecha/hora la oferta pasa sin decorar, porque sin la hora no hay franja
 * siguiente que consultar — la autoridad sigue siendo `OrderCreator` bajo lock.
 *
 * El cajón manda `date`/`time` en cada petición y re-resuelve al cambiar de hora
 * (`PurchaseSection::selectTime → refreshAddons`), así que esto cierra el bucle sin una línea de
 * cliente: las filas ya respetan `can_increase` y una fila omitida no se pinta.
 */
class ExtraHourOfferTest extends ApiTestCase
{
    private Zone $zone;

    private int $rateId;

    private string $date;

    private TicketType $entry;

    private TicketType $extraHour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00', '12:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->entry = TicketType::create([
            'name' => ['es' => 'Entrada 1h'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 1000]);

        $this->extraHour = TicketType::create([
            'name' => ['es' => 'Hora extra'], 'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
            'duration_min' => 60, 'occupies_after_parent' => true,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1,
            'position' => (int) TicketType::max('position') + 1,
        ]);
        $this->extraHour->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => 500]);

        $this->entry->addons()->attach($this->extraHour->id, [
            'position' => 1, 'is_included' => false, 'included_quantity' => 1,
            'is_mandatory' => false, 'quantity_mode' => 'fixed', 'allow_extra' => true,
            'choice_group' => null, 'max_qty' => null, 'requires_addon_id' => null,
        ]);
    }

    public function test_at_a_good_hour_the_extra_is_offered_capped_by_the_next_slot(): void
    {
        // La franja de la hija (11:00) se deja con 3 plazas: el techo real de «cuántos se quedan».
        $this->slotAt('11:00:00')->update(['capacity' => 3, 'online_capacity' => 3]);

        $row = $this->extraRow($this->ask(['date' => $this->date, 'time' => '10:00:00', 'quantity' => 4]));

        $this->assertNotNull($row, 'a una hora donde aterriza, la hora extra se ofrece');
        $this->assertSame(3, $row['max_quantity'], 'el techo son las plazas REALES de su franja, no el del pivote (nulo = sin límite)');
    }

    public function test_at_the_last_hour_the_extra_is_not_offered_and_the_selection_drops_it(): void
    {
        $response = $this->ask([
            'date' => $this->date, 'time' => '12:00:00', 'quantity' => 4,
            'addons' => [['product_id' => $this->extraHour->id, 'quantity' => 2]],
        ]);

        $this->assertNull($this->extraRow($response), 'a las 12:00 no hay franja siguiente: ni se ofrece');
        $this->assertNotContains(
            $this->extraHour->id,
            array_column($response->json('selection'), 'ticket_type_id'),
            'y la selección resuelta lo SUELTA aunque el cliente lo pidiera — lo que se guarda no puede comprarse',
        );
    }

    public function test_a_full_next_slot_also_removes_the_offer(): void
    {
        $this->slotAt('11:00:00')->update(['online_capacity' => 0]);

        $this->assertNull(
            $this->extraRow($this->ask(['date' => $this->date, 'time' => '10:00:00', 'quantity' => 4])),
            'una franja siguiente LLENA es el mismo «no se ofrece» que una inexistente',
        );
    }

    public function test_the_stepper_stops_at_the_line_quantity(): void
    {
        // Aforo de sobra (50) y línea de 2: el tope que muerde es «no se quedan más de los que
        // entran», contado sobre la selección — la mitad de §4.4·5 que la UI tiene que decir igual.
        $row = $this->extraRow($this->ask([
            'date' => $this->date, 'time' => '10:00:00', 'quantity' => 2,
            'addons' => [['product_id' => $this->extraHour->id, 'quantity' => 2]],
        ]));

        $this->assertSame(2, $row['max_quantity']);
        $this->assertFalse($row['can_increase'], 'con 2 de 2 quedándose, el «+» se apaga');
    }

    public function test_without_date_and_time_the_offer_passes_undecorated(): void
    {
        // El CONTROL de la decoración: sin la hora de la línea no hay franja siguiente que
        // consultar — se ofrece sin capar, y la autoridad (OrderCreator, bajo lock) hará el resto.
        $row = $this->extraRow($this->ask(['quantity' => 4]));

        $this->assertNotNull($row);
        $this->assertNull($row['max_quantity'], 'sin hora, el techo vuelve a ser el del pivote (nulo)');
    }

    // ─── Fixture ────────────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $body */
    private function ask(array $body): TestResponse
    {
        return $this->postJson(self::ROOT.'/catalog/products/'.$this->entry->id.'/addons', $body)
            ->assertOk();
    }

    /** La fila de la hora extra en la respuesta, o `null` si no se ofrece. */
    private function extraRow(TestResponse $response): ?array
    {
        foreach ($response->json('singles') as $row) {
            if ((int) $row['product_id'] === (int) $this->extraHour->id) {
                return $row;
            }
        }

        return null;
    }

    private function slotAt(string $time): Slot
    {
        return Slot::where('zone_id', $this->zone->id)->where('date', $this->date)
            ->where('start_time', $time)->firstOrFail();
    }
}
