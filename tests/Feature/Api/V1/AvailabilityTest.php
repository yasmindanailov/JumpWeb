<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Livewire\Tickets\Purchase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 4b — `GET /api/v1/availability/{product}/dates` y
 * `POST /api/v1/availability/{product}/times`.
 *
 * La disponibilidad de la API y la de la web salen del MISMO contrato
 * (`Booking\Contracts\AvailabilityOffer`) sobre la misma fuente de oferta (`SlotOffer`, `AFORO-02`).
 * Estos tests fijan la forma contra `openapi/v1.yaml` y la PARIDAD con lo que la web ofrece para la
 * misma cesta —con cesta NO vacía y con su mutación, que es lo que el spec §6.3 exige después de
 * descubrir que el test de paridad de la v1 pasaba por construcción—.
 */
class AvailabilityTest extends ApiTestCase
{
    private Zone $zone;

    private int $normalRateId;

    private string $date;

    private TicketType $entry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

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

        $this->entry = $this->makeEntry();
    }

    private function datesPath(int $productId): string
    {
        return self::ROOT.'/availability/'.$productId.'/dates';
    }

    private function timesPath(int $productId): string
    {
        return self::ROOT.'/availability/'.$productId.'/times';
    }

    // ── Días ──────────────────────────────────────────────────────────────────────────────────

    public function test_it_lists_the_offered_days_with_their_price_and_matches_the_contract(): void
    {
        $response = $this->getJson($this->datesPath($this->entry->id));

        $response->assertOk()->assertValidResponse(200);

        $response->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.date', $this->date)
            ->assertJsonPath('data.0.price_cents', 990)
            ->assertJsonPath('data.0.rate_key', 'normal');
    }

    /**
     * `price_cents` nulo tiene que validar contra el contrato. En OpenAPI 3.0 eso hay que EJERCERLO
     * con un test, no darlo por escrito (spec §10.ter 15).
     */
    public function test_a_day_without_a_price_is_listed_with_a_null_price(): void
    {
        $priceless = TicketType::create([
            'name' => ['es' => 'Sin tarifa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 8,
        ]);

        $this->getJson($this->datesPath($priceless->id))
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.price_cents', null);
    }

    /**
     * Un producto fuera del catálogo da 404, sin distinguir si no existe, si no se vende o si su
     * zona no opera: la misma respuesta que `GET catalog/products/{id}`, y por el mismo motivo.
     */
    public function test_everything_outside_the_catalog_answers_the_same_404(): void
    {
        $retired = $this->makeEntry();
        $retired->update(['is_sellable' => false]);

        $this->getJson($this->datesPath($retired->id))->assertNotFound()->assertValidResponse(404);
        $this->getJson($this->datesPath(999999))->assertNotFound()->assertValidResponse(404);
        $this->postJson($this->timesPath($retired->id), ['date' => $this->date])->assertNotFound();
    }

    public function test_a_non_numeric_id_does_not_reach_the_controller(): void
    {
        $this->getJson(self::ROOT.'/availability/abc/dates')->assertNotFound();
    }

    // ── Horas ─────────────────────────────────────────────────────────────────────────────────

    public function test_it_lists_the_offered_times_and_matches_the_contract(): void
    {
        $response = $this->postJson($this->timesPath($this->entry->id), ['date' => $this->date]);

        $response->assertOk()->assertValidRequest()->assertValidResponse(200);

        $response->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.time', '10:00:00')
            ->assertJsonPath('data.0.available', 10)
            ->assertJsonPath('data.0.max_quantity', 10)
            ->assertJsonPath('data.0.sellable', true);
    }

    /** La cesta es opcional: la primera compra empieza sin nada elegido. */
    public function test_the_cart_is_optional(): void
    {
        $this->postJson($this->timesPath($this->entry->id), ['date' => $this->date])
            ->assertOk()
            ->assertValidRequest()
            ->assertJsonPath('data.0.available', 10);
    }

    /**
     * El motivo de que este endpoint sea `POST` y lleve la cesta (`AFORO-02`): sin ella, una segunda
     * línea vería libres las plazas que la primera ya retiene y el checkout la rechazaría después.
     */
    public function test_the_cart_of_the_client_discounts_what_it_already_holds(): void
    {
        $response = $this->postJson($this->timesPath($this->entry->id), [
            'date' => $this->date,
            'items' => [[
                'product_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 4,
            ]],
        ]);

        $response->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertJsonPath('data.0.available', 6)
            ->assertJsonPath('data.0.max_quantity', 6)
            // Y solo esa franja: una línea de las 10:00 de una hora no toca la de las 11:00.
            ->assertJsonPath('data.1.available', 10);
    }

    public function test_a_time_the_cart_fills_is_still_listed_as_not_sellable(): void
    {
        $this->postJson($this->timesPath($this->entry->id), [
            'date' => $this->date,
            'items' => [[
                'product_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 10,
            ]],
        ])
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.available', 0)
            ->assertJsonPath('data.0.sellable', false);
    }

    /**
     * El contrato separa los dos números a propósito: en un pack con cupo de 60 y máximo de 20
     * invitados, `available` es 60 y `max_quantity` es 20. Un cliente que acote su selector con el
     * primero dejaría pedir invitados que el checkout rechazaría.
     */
    public function test_a_pack_reports_free_seats_and_selectable_max_as_different_numbers(): void
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'min_qty' => 8, 'max_qty' => 20,
            'prep_before_min' => 0, 'prep_after_min' => 0,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
        ]);
        $pack->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 18000]);

        $this->postJson($this->timesPath($pack->id), ['date' => $this->date])
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.available', 60)
            ->assertJsonPath('data.0.max_quantity', 20);
    }

    // ── Errores del cliente ───────────────────────────────────────────────────────────────────

    public function test_the_date_is_required(): void
    {
        $response = $this->postJson($this->timesPath($this->entry->id), []);

        $response->assertStatus(422)->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertArrayHasKey('date', $response->json('error.fields'));
    }

    public function test_a_malformed_cart_line_names_the_field(): void
    {
        $response = $this->postJson($this->timesPath($this->entry->id), [
            'date' => $this->date,
            'items' => [['product_id' => $this->entry->id, 'date' => '13-08-2026', 'time' => '10:00', 'quantity' => 1]],
        ]);

        $response->assertStatus(422)->assertValidResponse(422);
        $this->assertArrayHasKey('items.0.date', $response->json('error.fields'));
    }

    // ── Paridad con la web (spec §6.3) ────────────────────────────────────────────────────────

    /**
     * Las horas que ofrece la API y las que pinta el sidebar tienen que ser las mismas para la misma
     * cesta, y el máximo que la web deja elegir tiene que ser el `max_quantity` de esa hora. Es la
     * prueba de que el paso 4b retiró la derivación de ocupantes de la capa de UI en vez de darle
     * una copia a la API.
     */
    public function test_the_api_and_the_web_offer_the_same_times_for_the_same_cart(): void
    {
        $cart = [[
            'ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 4,
            'event_data' => [], 'addons' => [],
        ]];

        session()->put('purchase.cart', $cart);

        $web = Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        $api = $this->postJson($this->timesPath($this->entry->id), [
            'date' => $this->date,
            'items' => [[
                'product_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 4,
            ]],
        ])->assertOk();

        $this->assertSame(
            $web->viewData('times'),
            array_column($api->json('data'), 'time'),
            'la web y la API no ofrecen las mismas horas para la misma cesta'
        );
        $this->assertSame(
            $web->viewData('maxQty'),
            $api->json('data.0.max_quantity'),
            'el máximo que la web deja elegir no es el que la API publica'
        );

        // Control: los números no son triviales — la cesta ya ha descontado 4 de las 10 plazas.
        $this->assertSame(6, $api->json('data.0.max_quantity'));
    }

    /**
     * Mutación del anterior: cambiar la cesta tiene que mover a los dos lados a la vez. Sin este
     * control, dos implementaciones que ignorasen la cesta pasarían la paridad.
     */
    public function test_changing_the_cart_moves_both_the_web_and_the_api(): void
    {
        session()->put('purchase.cart', [[
            'ticket_type_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 9,
            'event_data' => [], 'addons' => [],
        ]]);

        $web = Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->date)
            ->call('goToTime')
            ->call('selectTime', '10:00:00');

        $api = $this->postJson($this->timesPath($this->entry->id), [
            'date' => $this->date,
            'items' => [[
                'product_id' => $this->entry->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 9,
            ]],
        ])->assertOk();

        $this->assertSame(1, $api->json('data.0.max_quantity'));
        $this->assertSame($web->viewData('maxQty'), $api->json('data.0.max_quantity'));
    }

    private function makeEntry(): TicketType
    {
        $entry = TicketType::create([
            'name' => ['es' => 'Entrada'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);
        $entry->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 990]);

        return $entry;
    }
}
