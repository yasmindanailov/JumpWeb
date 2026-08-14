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
 * Fase 4 · paso 4.0b·5 — `POST /api/v1/catalog/products/{product}/addons`.
 *
 * El hueco más arriesgado del paso y el último por eso (`sidebar-spa.md` §4.4.1 hueco 5 y §4.4.3).
 * Lo que estas guardas protegen es lo que un cliente NO puede deducir de la configuración que
 * publica la ficha del producto:
 *
 *  1. **la partición en grupos excluyentes** y su elegido por defecto;
 *  2. **la poda EN CADENA de las dependencias «requiere»** — si cae C, cae B, cae A;
 *  3. **que un complemento de pago sin tarifa ese día ni se ofrece**;
 *  4. **la selección resuelta** que hay que guardar, con los obligatorios inyectados;
 *  5. y que el **dinero de la línea** sale del mismo cálculo que `orders/quote`, no de una segunda
 *     aritmética compuesta aquí.
 */
class CatalogAddonsTest extends ApiTestCase
{
    private Zone $zone;

    private int $rateId;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true,
            'max_guests_per_slot' => 60, 'max_per_slot' => 0, 'prep_blocks_cupo' => false,
        ]);

        $this->date = Carbon::today()->addDays(2)->toDateString();

        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '12:00:00', 'capacity' => 60, 'online_capacity' => 60,
        ]);
    }

    private function path(int $productId): string
    {
        return self::ROOT.'/catalog/products/'.$productId.'/addons';
    }

    private function pack(int $priceCents = 5000): TicketType
    {
        $pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 6, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => (int) TicketType::max('position') + 1,
        ]);
        $pack->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);

        return $pack;
    }

    /**
     * Un complemento enganchado al producto con la config del pivote que se quiera probar.
     *
     * @param  array<string, mixed>  $pivot
     */
    private function addon(TicketType $product, string $name, ?int $priceCents, array $pivot = []): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 0, 'is_sellable' => true, 'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ]);

        if ($priceCents !== null) {
            $addon->prices()->create(['rate_type_id' => $this->rateId, 'amount_cents' => $priceCents]);
        }

        $product->configurableAddons()->attach($addon->id, array_merge([
            'quantity_mode' => 'fixed',
            'position' => (int) $product->configurableAddons()->count() + 1,
        ], $pivot));

        return $addon;
    }

    /** @param array<string, mixed> $body */
    private function ask(TicketType $product, array $body = []): TestResponse
    {
        return $this->postJson($this->path($product->id), array_merge(['quantity' => 8], $body));
    }

    // ── Lo básico ─────────────────────────────────────────────────────────────────────────────

    public function test_it_resolves_a_plain_addon_against_the_selection(): void
    {
        $pack = $this->pack();
        $socks = $this->addon($pack, 'Calcetines', 300);

        $this->ask($pack, ['addons' => [['product_id' => $socks->id, 'quantity' => 2]]])
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->assertJsonPath('singles.0.product_id', $socks->id)
            ->assertJsonPath('singles.0.product_name', 'Calcetines')
            ->assertJsonPath('singles.0.price_cents', 300)
            ->assertJsonPath('singles.0.selected', true)
            ->assertJsonPath('singles.0.quantity', 2)
            ->assertJsonPath('singles.0.charged_cents', 600)
            ->assertJsonPath('addons_total_cents', 600)
            ->assertJsonPath('selection.0.product_id', $socks->id)
            ->assertJsonPath('selection.0.quantity', 2);
    }

    /** Un producto sin complementos no es un 404: es una oferta vacía, que es otra cosa. */
    public function test_a_product_without_addons_answers_an_empty_offer(): void
    {
        $this->ask($this->pack())
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('groups', [])
            ->assertJsonPath('singles', [])
            ->assertJsonPath('addons_total_cents', 0)
            ->assertJsonPath('selection', []);
    }

    public function test_a_product_out_of_the_catalogue_is_a_404(): void
    {
        $pack = $this->pack();
        $pack->update(['is_sellable' => false]);

        $this->ask($pack)->assertNotFound()->assertValidResponse(404);
    }

    // ── Grupos excluyentes ────────────────────────────────────────────────────────────────────

    /**
     * **La primera llamada no necesita `choices`**: un grupo sin elegido no es un estado que exista,
     * así que se resuelve con su opción por defecto. Sin esto, un cliente pintaría la pantalla con
     * todos los grupos vacíos y un total que no es el real.
     */
    public function test_a_group_without_a_choice_falls_back_to_its_default(): void
    {
        $pack = $this->pack();
        $burger = $this->addon($pack, 'Hamburguesa', 800, ['choice_group' => 'menu']);
        $pizza = $this->addon($pack, 'Pizza', 900, ['choice_group' => 'menu']);

        $response = $this->ask($pack)->assertOk()->assertValidResponse(200)
            ->assertJsonPath('groups.0.key', 'menu')
            ->assertJsonCount(2, 'groups.0.options')
            ->assertJsonPath('singles', []);

        // El primero por orden es el elegido por defecto; el otro queda sin seleccionar.
        $this->assertSame($burger->id, $response->json('groups.0.options.0.product_id'));
        $this->assertTrue($response->json('groups.0.options.0.selected'));
        $this->assertSame($pizza->id, $response->json('groups.0.options.1.product_id'));
        $this->assertFalse($response->json('groups.0.options.1.selected'));
    }

    /** Y elegir un miembro deselecciona al otro: dentro de un grupo hay exactamente uno. */
    public function test_choosing_a_member_deselects_the_rest_of_its_group(): void
    {
        $pack = $this->pack();
        $burger = $this->addon($pack, 'Hamburguesa', 800, ['choice_group' => 'menu']);
        $pizza = $this->addon($pack, 'Pizza', 900, ['choice_group' => 'menu']);

        $response = $this->ask($pack, ['choices' => [['group' => 'menu', 'product_id' => $pizza->id]]])
            ->assertOk()->assertValidResponse(200);

        $options = collect($response->json('groups.0.options'))->keyBy('product_id');
        $this->assertFalse($options[$burger->id]['selected']);
        $this->assertTrue($options[$pizza->id]['selected']);
        $this->assertSame($pizza->id, $response->json('selection.0.product_id'));
    }

    // ── La cadena de dependencias, que es lo que CE-4 prohíbe reimplementar ───────────────────

    /**
     * **La poda es a PUNTO FIJO.** Con A→B→C, quitar C deja a B sin requisito y a A sin el suyo: los
     * tres caen. Un cliente que resolviera la dependencia en un solo nivel dejaría A seleccionado y
     * enviaría una cesta que el checkout rechaza.
     */
    public function test_the_dependency_pruning_follows_the_whole_chain(): void
    {
        $pack = $this->pack();
        // ⚠️ Las POSICIONES van al revés que la cadena a propósito. La poda recorre los
        // complementos en el orden configurado, así que con el orden natural (base → medio → hoja)
        // una sola pasada bastaría: al llegar a la hoja, su requisito ya se habría eliminado. Con el
        // orden invertido la hoja se evalúa PRIMERO, cuando su requisito todavía está en pie, y solo
        // una segunda vuelta la retira. Sin esta inversión el test pasaba igual con una poda de un
        // solo nivel — es decir, no probaba lo que dice probar (verificado por mutación).
        $base = $this->addon($pack, 'Decoración', 500, ['position' => 3]);
        $middle = $this->addon($pack, 'Globos', 300, ['requires_addon_id' => $base->id, 'position' => 2]);
        $leaf = $this->addon($pack, 'Photocall', 200, ['requires_addon_id' => $middle->id, 'position' => 1]);

        // Con la base elegida, la cadena entera está disponible y seleccionada.
        $withBase = $this->ask($pack, ['addons' => [
            ['product_id' => $base->id, 'quantity' => 1],
            ['product_id' => $middle->id, 'quantity' => 1],
            ['product_id' => $leaf->id, 'quantity' => 1],
        ]])->assertOk()->assertValidResponse(200);

        $this->assertSame([true, true, true], collect($withBase->json('singles'))->pluck('selected')->all());
        $this->assertCount(3, $withBase->json('selection'));

        // Sin la base: caen los dos dependientes, no solo el primero.
        $withoutBase = $this->ask($pack, ['addons' => [
            ['product_id' => $middle->id, 'quantity' => 1],
            ['product_id' => $leaf->id, 'quantity' => 1],
        ]])->assertOk()->assertValidResponse(200);

        $rows = collect($withoutBase->json('singles'))->keyBy('product_id');
        $this->assertFalse($rows[$middle->id]['selected'], 'el dependiente directo debe caer');
        $this->assertFalse($rows[$leaf->id]['selected'], 'el dependiente del dependiente TAMBIÉN debe caer');
        $this->assertSame([], $withoutBase->json('selection'));
    }

    /** Y el que no está disponible dice de qué depende, para poder enseñar «Requiere: X». */
    public function test_an_unavailable_addon_names_what_it_requires(): void
    {
        $pack = $this->pack();
        $base = $this->addon($pack, 'Decoración', 500);
        $dependent = $this->addon($pack, 'Globos', 300, ['requires_addon_id' => $base->id]);

        $response = $this->ask($pack)->assertOk()->assertValidResponse(200);

        $rows = collect($response->json('singles'))->keyBy('product_id');
        $this->assertFalse($rows[$dependent->id]['available']);
        $this->assertSame('Decoración', $rows[$dependent->id]['requires_name']);
        $this->assertTrue($rows[$base->id]['available']);
        $this->assertNull($rows[$base->id]['requires_name']);
    }

    // ── Reglas que no se ven desde la configuración ──────────────────────────────────────────

    /**
     * Un complemento DE PAGO sin tarifa ese día **no se ofrece siquiera**: el checkout lo rechazaría,
     * así que enseñarlo sería ofrecer algo que no se puede comprar. Uno INCLUIDO sin precio sí, que
     * es gratis igualmente.
     */
    public function test_a_paid_addon_without_a_price_is_not_offered_but_an_included_one_is(): void
    {
        $pack = $this->pack();
        $this->addon($pack, 'Sin tarifa', null);
        $included = $this->addon($pack, 'Incluido sin precio', null, ['is_included' => true, 'included_quantity' => 1]);

        $response = $this->ask($pack)->assertOk()->assertValidResponse(200);

        $ids = collect($response->json('singles'))->pluck('product_id')->all();
        $this->assertSame([$included->id], $ids);
    }

    /** Un obligatorio entra en la selección aunque el cliente no lo mande: por eso se publica. */
    public function test_a_mandatory_addon_is_injected_into_the_selection(): void
    {
        $pack = $this->pack();
        $mandatory = $this->addon($pack, 'Seguro', 400, ['is_mandatory' => true, 'included_quantity' => 1]);

        $response = $this->ask($pack)->assertOk()->assertValidResponse(200)
            ->assertJsonPath('singles.0.is_mandatory', true)
            ->assertJsonPath('singles.0.selected', true);

        $this->assertSame($mandatory->id, $response->json('selection.0.product_id'));
    }

    /** Un incluido reparte unidades gratis: `charged_cents` no es `quantity × price_cents`. */
    public function test_included_units_are_free_and_the_extras_are_charged(): void
    {
        $pack = $this->pack();
        $cake = $this->addon($pack, 'Tarta', 1000, [
            'is_included' => true, 'included_quantity' => 1, 'allow_extra' => true,
        ]);

        $this->ask($pack, ['addons' => [['product_id' => $cake->id, 'quantity' => 3]]])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('singles.0.quantity', 3)
            ->assertJsonPath('singles.0.free_quantity', 1)
            ->assertJsonPath('singles.0.charged_cents', 2000)
            ->assertJsonPath('singles.0.badge', 'included');
    }

    /** Un por-invitado toma la cantidad de la LÍNEA, no la que mande el cliente. */
    public function test_a_per_guest_addon_takes_the_line_quantity(): void
    {
        $pack = $this->pack();
        $meal = $this->addon($pack, 'Comida', 700, ['quantity_mode' => 'per_guest']);

        $this->ask($pack, ['quantity' => 8, 'addons' => [['product_id' => $meal->id, 'quantity' => 2]]])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('singles.0.per_guest', true)
            ->assertJsonPath('singles.0.quantity', 8)
            ->assertJsonPath('singles.0.charged_cents', 5600);
    }

    // ── El dinero de la línea ─────────────────────────────────────────────────────────────────

    /**
     * El pie llega en la MISMA respuesta —es la pantalla con más clics del embudo— y sale del mismo
     * cálculo que `orders/quote`. Se comprueba comparándolo con él: si algún día alguien compusiera
     * el total a mano aquí, los dos importes dejarían de coincidir.
     */
    public function test_the_line_total_matches_what_quoting_the_same_cart_returns(): void
    {
        $pack = $this->pack(5000);
        $socks = $this->addon($pack, 'Calcetines', 300);

        $body = ['quantity' => 8, 'date' => $this->date, 'time' => '10:00:00',
            'addons' => [['product_id' => $socks->id, 'quantity' => 2]]];

        $mine = $this->ask($pack, $body)->assertOk()->assertValidResponse(200);

        $quote = $this->postJson(self::ROOT.'/orders/quote', ['items' => [[
            'product_id' => $pack->id, 'date' => $this->date, 'time' => '10:00:00', 'quantity' => 8,
            'addons' => [['product_id' => $socks->id, 'quantity' => 2]],
        ]]])->assertOk()->assertValidResponse(200);

        $this->assertSame($quote->json('lines.0.subtotal_cents'), $mine->json('line.subtotal_cents'));
        $this->assertSame($quote->json('lines.0.unit_price_cents'), $mine->json('line.unit_price_cents'));
        $this->assertSame($quote->json('lines.0.deposit_cents'), $mine->json('line.deposit_cents'));
        $this->assertSame($quote->json('lines.0.gate_remainder_cents'), $mine->json('line.gate_remainder_cents'));
        $this->assertSame($quote->json('lines.0.addons'), $mine->json('line.addons'));
    }

    /**
     * Sin día y hora no hay precio del producto base, así que `line` viaja nula en vez de un importe
     * calculado sobre una fecha inventada. Los complementos sí se resuelven: no dependen del día.
     */
    public function test_without_a_date_the_addons_resolve_but_the_line_total_is_null(): void
    {
        $pack = $this->pack();
        $socks = $this->addon($pack, 'Calcetines', 300);

        $this->ask($pack, ['addons' => [['product_id' => $socks->id, 'quantity' => 2]]])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('line', null)
            ->assertJsonPath('addons_total_cents', 600);
    }

    /**
     * **El fallo silencioso que este endpoint cierra** (spec §4.4.3): `CartPricer` captura los
     * errores del resolutor y tarifica la línea SIN complementos, así que una selección inconsistente
     * daría un pie sin ellos mientras las filas muestran sus importes. Aquí lo que se tarifica es la
     * selección que el propio dominio acaba de resolver, así que un id que no es de este producto se
     * descarta antes de llegar al cálculo y el pie sigue cuadrando.
     */
    public function test_an_addon_of_another_product_cannot_break_the_line_total(): void
    {
        $pack = $this->pack();
        $socks = $this->addon($pack, 'Calcetines', 300);
        $foreign = $this->addon($this->pack(), 'De otro producto', 900);

        $response = $this->ask($pack, [
            'date' => $this->date, 'time' => '10:00:00',
            'addons' => [
                ['product_id' => $socks->id, 'quantity' => 1],
                ['product_id' => $foreign->id, 'quantity' => 1],
            ],
        ])->assertOk()->assertValidResponse(200);

        // El ajeno ni se ofrece ni entra en la selección…
        $this->assertSame([$socks->id], collect($response->json('singles'))->pluck('product_id')->all());
        $this->assertSame([$socks->id], collect($response->json('selection'))->pluck('product_id')->all());
        // …y el pie sigue contando el que sí es suyo, en vez de quedarse sin complementos.
        $this->assertSame(300, $response->json('line.addons.0.subtotal_cents'));
    }

    // ── Forma y acceso ────────────────────────────────────────────────────────────────────────

    /** La cantidad de la línea decide la de los por-invitado: no puede faltar. */
    public function test_the_line_quantity_is_required(): void
    {
        $response = $this->postJson($this->path($this->pack()->id), [])
            ->assertStatus(422)->assertValidResponse(422);

        $this->assertArrayHasKey('quantity', $response->json('error.fields'));
    }

    /** Una cantidad 0 en un complemento vale: es «este ya no lo quiero», no un error. */
    public function test_a_zero_quantity_removes_the_addon_without_a_422(): void
    {
        $pack = $this->pack();
        $socks = $this->addon($pack, 'Calcetines', 300);

        $this->ask($pack, ['addons' => [['product_id' => $socks->id, 'quantity' => 0]]])
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('singles.0.selected', false)
            ->assertJsonPath('selection', []);
    }

    /** Es público: la web deja llegar hasta el pago sin cuenta, y elegir complementos también. */
    public function test_it_is_public(): void
    {
        $this->ask($this->pack())->assertOk();
    }
}
