<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\OrderCreator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 4a — `POST /api/v1/orders/quote`.
 *
 * El presupuesto y el carrito de la web salen del MISMO contrato (`Booking\Contracts\CartPricing`),
 * así que estos tests fijan dos cosas distintas: la FORMA de la respuesta contra `openapi/v1.yaml`
 * y la CONDUCTA de la tarificación, incluida la agregación de una cesta **mixta y no vacía**.
 *
 * Que la cesta sea mixta y no vacía no es celo: el test de paridad de la v1 del spec usaba la cesta
 * vacía y por eso pasaba por construcción (§6.3), que es la clase de test verde que no prueba nada.
 * Aquella paridad —contra el sidebar Livewire— se re-apuntó en 4.7·2b (`#63`); el caso de agregación
 * mixta que dejó explica ahí mismo qué se conservó y por qué.
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

    // ── Agregación de una cesta MIXTA ─────────────────────────────────────────────────────────

    /**
     * Los dos importes de una cesta de VARIAS líneas con reglas distintas: una con señal y complemento,
     * otra sin señal y con complemento. Es el único caso del fichero que ejerce la SUMA —los demás son
     * de una línea—, y lo que fija es la regla de agregación de `CartPricing`: al total contribuye todo,
     * y a lo que se cobra ahora contribuye **la señal** de la línea que la tiene y **el total** de la
     * que no (Opción A de #225).
     *
     * Los números, para que la aritmética se pueda seguir sin ejecutar nada:
     * · línea 1 — 180,00 (señal fija 30,00) + calcetines 20,00 ×1 → suma 200,00 · ahora 30,00
     * · línea 2 — 40,00 ×2 = 80,00 + bebida 5,00 ×3 = 15,00 → suma 95,00 · ahora 95,00
     * · TOTAL 295,00 · AHORA 125,00
     *
     * ⚠️ **Y el matiz que se midió al escribirlo, porque invita a una resta equivocada**: en la línea
     * SIN señal `deposit_cents` vale 80,00, **no** 95,00 — es la señal del principal, y para un producto
     * sin señal eso es su subtotal, complementos aparte—. Sus 15,00 de complemento sí se cobran online
     * (`CartPricer`: `online += deposit + (hasDeposit ? 0 : addons)`), así que **sumar los
     * `deposit_cents` de las líneas NO da `online_amount_cents`**: 30,00 + 80,00 = 110,00, y se cobran
     * 125,00. El importe agregado se publica ya hecho justo para que ningún cliente lo componga.
     *
     * ⚠️ **Nació como test de PARIDAD con el sidebar Livewire y se re-apuntó en 4.7·2b** (`#63`): la
     * mitad que comparaba `Purchase::cartTotalCents()` con `total_cents` se fue con su motor, y con ella
     * su caso hermano de mutación —cuyo control, 5 × 40,00 = 200,00, no añadía nada a
     * `test_it_quotes_a_cart_and_matches_the_contract`, que ya multiplica 2 × 40,00—. Lo que NO se fue
     * es esto: la agregación mixta, que no la ejerce ningún otro caso. Y que la web pida los importes
     * al contrato en vez de recalcularlos lo sigue vigilando
     * `ModuleContractsTest::test_the_web_cart_gets_its_amounts_from_the_contract`.
     */
    public function test_it_aggregates_a_mixed_cart_charging_only_the_deposit_of_the_line_that_has_one(): void
    {
        $socks = $this->addon($this->deposit, 'Calcetines', 2000);
        $drink = $this->addon($this->full, 'Bebida', 500);

        $response = $this->postJson(self::PATH, ['items' => [
            $this->item($this->deposit, '10:00:00', 1, [['product_id' => $socks->id, 'quantity' => 1]]),
            $this->item($this->full, '11:00:00', 2, [['product_id' => $drink->id, 'quantity' => 3]]),
        ]]);

        $response->assertOk()->assertValidRequest()->assertValidResponse(200)
            ->assertJsonPath('total_cents', 29500)
            ->assertJsonPath('online_amount_cents', 12500)
            // Y el reparto por línea, que es lo que hace verificable la suma en vez de solo comprobarla.
            ->assertJsonPath('lines.0.deposit_cents', 3000)
            ->assertJsonPath('lines.0.gate_remainder_cents', 17000)
            ->assertJsonPath('lines.1.has_deposit', false)
            // El aviso de arriba, fijado: 80,00 y no 95,00, y aun así los 15,00 del complemento van
            // en el agregado de «ahora». Sin este par de líneas la resta equivocada pasa desapercibida.
            ->assertJsonPath('lines.1.deposit_cents', 8000)
            ->assertJsonPath('lines.1.gate_remainder_cents', 0);
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
