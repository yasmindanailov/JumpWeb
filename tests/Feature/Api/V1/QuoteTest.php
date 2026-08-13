<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use App\Livewire\Tickets\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 4a — `POST /api/v1/orders/quote`.
 *
 * El presupuesto y el carrito de la web salen del MISMO contrato (`Booking\Contracts\CartPricing`),
 * así que estos tests fijan dos cosas distintas: la FORMA de la respuesta contra `openapi/v1.yaml`
 * y la PARIDAD con lo que la web enseña para la misma cesta.
 *
 * La paridad se prueba con una cesta **mixta y no vacía**, y con su mutación. No es celo: el test de
 * paridad de la v1 del spec usaba la cesta vacía y por eso pasaba por construcción (§6.3), que es la
 * clase de test verde que no prueba nada.
 *
 * Heredar de `ApiTestCase` deja cableado el contrato, pero la validación hay que PEDIRLA con
 * `assertValidRequest()`/`assertValidResponse()` (spec §10.ter 16).
 */
class QuoteTest extends ApiTestCase
{
    private const PATH = self::ROOT.'/orders/quote';

    private Zone $zone;

    private int $normalRateId;

    private string $date;

    /** 180,00 € · señal fija de 30,00 € */
    private TicketType $deposit;

    /** 40,00 € · sin señal */
    private TicketType $full;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        $this->date = Carbon::today()->addDays(2)->toDateString();

        foreach (['10:00:00', '11:00:00'] as $start) {
            Slot::create([
                'zone_id' => $this->zone->id, 'date' => $this->date,
                'start_time' => $start, 'end_time' => Carbon::parse($start)->addHour()->format('H:i:s'),
                'capacity' => 50, 'online_capacity' => 50,
            ]);
        }

        $this->deposit = $this->product('Cumpleaños', 18000, TicketType::DEPOSIT_FIXED, 3000);
        $this->full = $this->product('Entrada', 4000, TicketType::DEPOSIT_NONE, 0);
    }

    // ── Forma y contrato ──────────────────────────────────────────────────────────────────────

    public function test_it_quotes_a_cart_and_matches_the_contract(): void
    {
        $response = $this->postJson(self::PATH, ['items' => [
            $this->item($this->full, '10:00:00', 2),
        ]]);

        $response->assertOk()->assertValidRequest()->assertValidResponse(200);

        $response->assertJson([
            'total_cents' => 8000,
            'online_amount_cents' => 8000,
            'lines' => [[
                'index' => 0,
                'product_id' => $this->full->id,
                'product_name' => 'Entrada',
                'is_pack' => false,
                'date' => $this->date,
                'time' => '10:00:00',
                'quantity' => 2,
                'unit_price_cents' => 4000,
                'subtotal_cents' => 8000,
                'has_deposit' => false,
                'deposit_cents' => 8000,
                'gate_remainder_cents' => 0,
                'addons' => [],
            ]],
        ]);
    }

    public function test_a_deposit_line_reports_what_is_charged_now_and_what_is_left_for_the_gate(): void
    {
        $socks = $this->addon($this->deposit, 'Calcetines', 2000);

        $response = $this->postJson(self::PATH, ['items' => [
            $this->item($this->deposit, '10:00:00', 1, [['product_id' => $socks->id, 'quantity' => 1]]),
        ]]);

        $response->assertOk()->assertValidResponse(200);

        // Opción A de #225: online = solo la señal; el resto del principal y el complemento entero
        // se cobran en el parque.
        $response->assertJsonPath('total_cents', 20000)
            ->assertJsonPath('online_amount_cents', 3000)
            ->assertJsonPath('lines.0.has_deposit', true)
            ->assertJsonPath('lines.0.deposit_cents', 3000)
            ->assertJsonPath('lines.0.gate_remainder_cents', 17000)
            ->assertJsonPath('lines.0.subtotal_cents', 18000)
            ->assertJsonPath('lines.0.addons.0.product_name', 'Calcetines')
            ->assertJsonPath('lines.0.addons.0.subtotal_cents', 2000);
    }

    /**
     * El complemento que viaja es el RESUELTO, no el pedido: las unidades incluidas salen gratis.
     * Es lo que permite a un cliente escribir «incluido» en vez de cobrarlo dos veces.
     */
    public function test_addons_travel_as_the_server_resolves_them(): void
    {
        $meal = $this->addon($this->full, 'Comida', 1000, included: true);

        $response = $this->postJson(self::PATH, ['items' => [
            $this->item($this->full, '10:00:00', 1, [['product_id' => $meal->id, 'quantity' => 3]]),
        ]]);

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('lines.0.addons.0.quantity', 3)
            ->assertJsonPath('lines.0.addons.0.free_quantity', 1)
            ->assertJsonPath('lines.0.addons.0.subtotal_cents', 2000);
    }

    /**
     * `unit_price_cents` nulo NO es un error del cliente: es «este producto no se vende ese día».
     * Se puede presupuestar y no se puede comprar, y el contrato tiene que admitir el nulo — en
     * OpenAPI 3.0 eso hay que EJERCERLO con un test, no darlo por escrito (spec §10.ter 15).
     */
    public function test_a_product_without_a_price_for_the_day_is_quoted_as_null(): void
    {
        $priceless = TicketType::create([
            'name' => ['es' => 'Sin tarifa'], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
        ]);

        $response = $this->postJson(self::PATH, ['items' => [$this->item($priceless, '10:00:00', 2)]]);

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonPath('lines.0.unit_price_cents', null)
            ->assertJsonPath('lines.0.subtotal_cents', 0)
            ->assertJsonPath('total_cents', 0);
    }

    /**
     * Una línea cuyo producto ya no se vende no se tarifica, y el `index` de las demás sigue siendo
     * el de la cesta ENVIADA: es lo único que le permite al cliente saber cuál de las suyas cayó.
     */
    public function test_a_line_whose_product_is_no_longer_sold_disappears_keeping_the_other_indexes(): void
    {
        $retired = $this->product('Retirado', 1500, TicketType::DEPOSIT_NONE, 0);
        $retired->update(['is_sellable' => false]);

        $response = $this->postJson(self::PATH, ['items' => [
            $this->item($retired, '10:00:00', 1),
            $this->item($this->full, '11:00:00', 1),
        ]]);

        $response->assertOk()->assertValidResponse(200)
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.index', 1)
            ->assertJsonPath('total_cents', 4000);
    }

    /**
     * Se acepta `HH:MM` además de `HH:MM:SS`. Sin la normalización, una hora sin segundos no casaría
     * con ninguna franja al crear el pedido: fallaría en silencio en vez de dar un error.
     */
    public function test_the_time_is_accepted_without_seconds_and_comes_back_canonical(): void
    {
        $response = $this->postJson(self::PATH, ['items' => [
            ['product_id' => $this->full->id, 'date' => $this->date, 'time' => '10:00', 'quantity' => 1],
        ]]);

        $response->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertJsonPath('lines.0.time', '10:00:00');
    }

    /**
     * Los datos del evento se ACEPTAN —para que el mismo cuerpo sirva para crear el pedido— y NO se
     * devuelven: son datos personales de un menor y este endpoint es público (`RGPD` §3).
     */
    public function test_event_data_is_accepted_and_never_echoed_back(): void
    {
        $response = $this->postJson(self::PATH, ['items' => [
            $this->item($this->deposit, '10:00:00', 1) + ['event_data' => ['child_name' => 'Ana', 'allergies' => 'frutos secos']],
        ]]);

        $response->assertOk()->assertValidRequest()->assertValidResponse(200);

        $this->assertStringNotContainsString('Ana', $response->getContent() ?: '');
        $this->assertStringNotContainsString('frutos secos', $response->getContent() ?: '');
    }

    // ── Errores del cliente ───────────────────────────────────────────────────────────────────

    public function test_a_malformed_line_is_a_422_that_names_the_field_instead_of_a_silent_drop(): void
    {
        $response = $this->postJson(self::PATH, ['items' => [
            ['product_id' => $this->full->id, 'date' => '13-08-2026', 'time' => '10:00:00', 'quantity' => 1],
        ]]);

        $response->assertStatus(422)->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertArrayHasKey('items.0.date', $response->json('error.fields'));
    }

    public function test_an_empty_cart_is_rejected(): void
    {
        $this->postJson(self::PATH, ['items' => []])
            ->assertStatus(422)
            ->assertValidResponse(422);
    }

    /**
     * El tope de líneas del cuerpo espeja el invariante de servidor `PAY-12`: una cesta desmedida
     * bloquearía muchas franjas a la vez al comprarla, así que se corta antes de recorrerla.
     */
    public function test_a_cart_over_the_server_line_cap_is_rejected(): void
    {
        $items = array_fill(0, OrderCreator::MAX_LINES_PER_CART + 1, $this->item($this->full, '10:00:00', 1));

        $this->postJson(self::PATH, ['items' => $items])
            ->assertStatus(422)
            ->assertValidResponse(422);
    }

    // ── Paridad con la web (spec §6.3) ────────────────────────────────────────────────────────

    /**
     * La misma cesta, en la web y en la API, tiene que dar los mismos dos importes. Es la prueba de
     * que el paso 4a hizo lo que dice: retirar la aritmética del componente de UI en vez de darle
     * una copia a la API.
     */
    public function test_the_api_and_the_web_price_the_same_mixed_cart_identically(): void
    {
        $socks = $this->addon($this->deposit, 'Calcetines', 2000);
        $drink = $this->addon($this->full, 'Bebida', 500);

        $webCart = [
            ['ticket_type_id' => $this->deposit->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 1,
                'event_data' => [], 'addons' => [['ticket_type_id' => $socks->id, 'qty' => 1]]],
            ['ticket_type_id' => $this->full->id, 'date' => $this->date, 'time' => '11:00:00', 'qty' => 2,
                'event_data' => [], 'addons' => [['ticket_type_id' => $drink->id, 'qty' => 3]]],
        ];

        session()->put('purchase.cart', $webCart);
        $web = Livewire::test(Purchase::class);

        $api = $this->postJson(self::PATH, ['items' => [
            $this->item($this->deposit, '10:00:00', 1, [['product_id' => $socks->id, 'quantity' => 1]]),
            $this->item($this->full, '11:00:00', 2, [['product_id' => $drink->id, 'quantity' => 3]]),
        ]])->assertOk();

        $this->assertSame(
            $web->instance()->cartTotalCents(),
            $api->json('total_cents'),
            'la web y la API no valoran igual la misma cesta'
        );
        $this->assertSame(
            $web->instance()->cartDepositCents(),
            $api->json('online_amount_cents'),
            'la web y la API no cobran lo mismo por la misma cesta'
        );

        // Control: los importes no son cero, así que la igualdad significa algo.
        $this->assertSame(29500, $api->json('total_cents'));
        $this->assertSame(12500, $api->json('online_amount_cents'));
    }

    /**
     * Mutación del test anterior: si la cesta cambia, los dos lados tienen que cambiar A LA VEZ. Sin
     * esto, dos implementaciones que devolvieran siempre lo mismo pasarían la paridad.
     */
    public function test_changing_the_cart_moves_both_the_web_and_the_api(): void
    {
        $webCart = [[
            'ticket_type_id' => $this->full->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => 5,
            'event_data' => [], 'addons' => [],
        ]];

        session()->put('purchase.cart', $webCart);
        $web = Livewire::test(Purchase::class);

        $api = $this->postJson(self::PATH, ['items' => [$this->item($this->full, '10:00:00', 5)]])->assertOk();

        $this->assertSame(20000, $api->json('total_cents'));
        $this->assertSame($web->instance()->cartTotalCents(), $api->json('total_cents'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────────────────

    private function product(string $name, int $priceCents, string $depositType, int $depositValue): TicketType
    {
        $product = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ENTRY, 'zone_id' => $this->zone->id,
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
            'deposit_type' => $depositType, 'deposit_value' => $depositValue,
        ]);
        $product->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        return $product;
    }

    private function addon(TicketType $product, string $name, int $priceCents, bool $included = false): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
        ]);
        $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $priceCents]);

        DB::table('product_addons')->insert([
            'product_id' => $product->id, 'addon_id' => $addon->id, 'position' => 0,
            'is_included' => $included, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
        ]);

        return $addon;
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $addons
     * @return array<string, mixed>
     */
    private function item(TicketType $product, string $time, int $quantity, array $addons = []): array
    {
        $item = [
            'product_id' => $product->id,
            'date' => $this->date,
            'time' => $time,
            'quantity' => $quantity,
        ];

        return $addons === [] ? $item : $item + ['addons' => $addons];
    }
}
