<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Tests\Feature\Api\ApiTestCase;

/**
 * Fase 3 · paso 1b — `GET /api/v1/catalog/*`.
 *
 * El catálogo de la API y el del flujo de compra de la web salen del MISMO read-model
 * (`Booking\Contracts\ProductCatalog`). Estos tests fijan lo que ese contrato promete: qué entra,
 * en qué orden, qué se cuenta de cada producto y qué NO se cuenta.
 *
 * Heredar de `ApiTestCase` deja CABLEADO `openapi/v1.yaml`, pero la validación hay que PEDIRLA:
 * Spectator solo compara cuando el test llama a `assertValidRequest()`/`assertValidResponse()`. Por
 * eso cada endpoint y cada status tienen aquí al menos un caso que lo pide explícitamente — con
 * `additionalProperties: false` y el `required` completo en el documento, eso convierte un campo
 * renombrado, sobrante o de otro tipo en un fallo (spec §10, punto 5).
 */
class CatalogTest extends ApiTestCase
{
    private Zone $zone;

    private int $normalRateId;

    private int $specialRateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump', 'en' => 'Jump zone'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        $this->normalRateId = (int) RateType::create([
            'key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0,
        ])->id;

        $this->specialRateId = (int) RateType::create([
            'key' => 'special', 'label' => ['es' => 'Especial'], 'weekdays' => [0, 6],
            'priority' => 10, 'is_special' => true,
        ])->id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $name, array $attributes = []): TicketType
    {
        return TicketType::create(array_merge([
            'name' => ['es' => $name],
            'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id,
            'duration_min' => 60,
            'seats_per_unit' => 1,
            'is_sellable' => true,
            'is_active' => true,
            'position' => (int) TicketType::max('position') + 1,
        ], $attributes));
    }

    private function priced(TicketType $product, int $cents, ?int $specialCents = null): TicketType
    {
        $product->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $cents]);

        if ($specialCents !== null) {
            $product->prices()->create(['rate_type_id' => $this->specialRateId, 'amount_cents' => $specialCents]);
        }

        return $product;
    }

    // ── Zonas ─────────────────────────────────────────────────────────────────────────────────

    public function test_zones_lists_only_the_operating_ones_in_configured_order(): void
    {
        Zone::create([
            'slug' => 'closed', 'name' => ['es' => 'Cerrada'], 'accent' => 'kids',
            'color' => '#000000', 'position' => 0, 'is_active' => false,
        ]);
        Zone::create([
            'slug' => 'kids', 'name' => ['es' => 'Kids'], 'accent' => 'kids',
            'color' => '#00A0FF', 'position' => 2, 'is_active' => true,
        ]);

        $response = $this->getJson(self::ROOT.'/catalog/zones')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200);

        $this->assertSame(['jump', 'kids'], array_column($response->json('data'), 'slug'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    /** El catálogo es el escaparate: se mira sin cuenta, igual que en la web. */
    public function test_the_catalog_is_public(): void
    {
        $this->priced($this->product('Entrada'), 990);

        $this->getJson(self::ROOT.'/catalog/zones')->assertOk();
        $this->getJson(self::ROOT.'/catalog/products')->assertOk();
    }

    // ── Lista de productos ────────────────────────────────────────────────────────────────────

    public function test_products_lists_what_is_on_sale_and_nothing_else(): void
    {
        $this->priced($this->product('Entrada 1 h'), 990);
        $this->priced($this->product('Pack cumple', ['type' => TicketType::TYPE_PACK, 'min_qty' => 8, 'max_qty' => 20]), 1500);

        // Fuera del catálogo, cada uno por un motivo distinto.
        $this->priced($this->product('No vendible', ['is_sellable' => false]), 990);
        $this->priced($this->product('Complemento', ['type' => TicketType::TYPE_ADDON, 'zone_id' => null]), 200);
        $closed = Zone::create([
            'slug' => 'closed', 'name' => ['es' => 'Cerrada'], 'accent' => 'kids',
            'color' => '#000000', 'position' => 9, 'is_active' => false,
        ]);
        $this->priced($this->product('De zona cerrada', ['zone_id' => $closed->id]), 990);

        $names = array_column($this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data'), 'name');

        $this->assertSame(['Entrada 1 h', 'Pack cumple'], $names);
    }

    /**
     * `is_active` («visible en la web») y `is_sellable` («en venta») son ejes INDEPENDIENTES (P3):
     * un producto oculto de la landing se sigue vendiendo desde el flujo de compra, así que sigue
     * en el catálogo. Es la regla que aplica `OrderCreator`, y el catálogo no puede contradecirla.
     */
    public function test_a_product_hidden_from_the_landing_is_still_on_sale(): void
    {
        $this->priced($this->product('Oculta pero vendible', ['is_active' => false]), 990);

        $this->getJson(self::ROOT.'/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Oculta pero vendible');
    }

    public function test_products_can_be_filtered_by_type(): void
    {
        $this->priced($this->product('Entrada'), 990);
        $this->priced($this->product('Pack', ['type' => TicketType::TYPE_PACK, 'min_qty' => 8]), 1500);

        $this->assertSame(
            ['Entrada'],
            array_column($this->getJson(self::ROOT.'/catalog/products?type=entry')->assertOk()->json('data'), 'name')
        );
        $this->assertSame(
            ['Pack'],
            array_column($this->getJson(self::ROOT.'/catalog/products?type=pack')->assertOk()->json('data'), 'name')
        );
    }

    /** Un `type` que no es del catálogo no se ignora en silencio: sería devolver otra cosa. */
    public function test_an_unknown_type_filter_is_rejected(): void
    {
        $this->getJson(self::ROOT.'/catalog/products?type=addon')
            ->assertStatus(422)
            ->assertValidResponse(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['fields' => ['type']]]);
    }

    /**
     * El precio de la lista es el MÍNIMO configurado —un «precio desde»—, y `price_varies` dice si
     * anunciarlo sin el «desde» mentiría. Los dos vienen del dominio para que ningún cliente los
     * deduzca por su cuenta.
     */
    public function test_the_list_price_is_the_lowest_and_says_when_it_varies(): void
    {
        $this->priced($this->product('Varía'), 1390, 1690);
        $this->priced($this->product('Precio único'), 990);

        $data = $this->getJson(self::ROOT.'/catalog/products')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->json('data');

        $this->assertSame(1390, $data[0]['from_price_cents']);
        $this->assertTrue($data[0]['price_varies']);
        $this->assertSame(990, $data[1]['from_price_cents']);
        $this->assertFalse($data[1]['price_varies']);
    }

    /**
     * Un producto sin zona es posible (la regla de zona operativa admite `zone_id` nulo), y su
     * `zone` viaja como `null`. Se comprueba CONTRA EL CONTRATO a propósito: en OpenAPI 3.0, un
     * `$ref` no admite `nullable` a su lado, así que el documento tiene que envolverlo en `allOf`
     * para poder anularlo — y eso solo se sabe que funciona ejerciéndolo.
     */
    public function test_a_product_without_zone_is_valid_against_the_contract(): void
    {
        $this->priced($this->product('Sin zona', ['zone_id' => null]), 990);

        $this->getJson(self::ROOT.'/catalog/products')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.zone', null);
    }

    /** Un producto sin ningún precio configurado informa `null`, no un 0 que parecería gratis. */
    public function test_a_product_without_prices_reports_a_null_price(): void
    {
        $this->product('Sin precio');

        $this->getJson(self::ROOT.'/catalog/products')
            ->assertOk()
            ->assertJsonPath('data.0.from_price_cents', null);
    }

    /** La señal se anuncia formateada y con el valor CONFIGURADO (#225 F2); sin señal, `null`. */
    public function test_the_deposit_label_announces_the_configured_deposit(): void
    {
        $this->priced($this->product('Con señal fija', [
            'type' => TicketType::TYPE_PACK, 'min_qty' => 8,
            'deposit_type' => TicketType::DEPOSIT_FIXED, 'deposit_value' => 3000,
        ]), 1500);
        $this->priced($this->product('Con señal porcentual', [
            'type' => TicketType::TYPE_PACK, 'min_qty' => 8,
            'deposit_type' => TicketType::DEPOSIT_PERCENT, 'deposit_value' => 30,
        ]), 1500);
        $this->priced($this->product('Sin señal'), 990);

        $data = $this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data');

        $this->assertSame('30,00 €', $data[0]['deposit_label']);
        $this->assertSame('30 %', $data[1]['deposit_label']);
        $this->assertNull($data[2]['deposit_label']);
    }

    /** Los textos salen en el idioma negociado por `Accept-Language`, sin parámetro propio. */
    public function test_texts_follow_the_negotiated_language(): void
    {
        $this->priced($this->product('Entrada', ['name' => ['es' => 'Entrada', 'en' => 'Ticket']]), 990);

        $this->getJson(self::ROOT.'/catalog/products', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ticket')
            ->assertJsonPath('data.0.zone.name', 'Jump zone');
    }

    // ── Ficha de producto ─────────────────────────────────────────────────────────────────────

    public function test_the_detail_carries_the_contractable_quantities(): void
    {
        $pack = $this->priced($this->product('Pack', [
            'type' => TicketType::TYPE_PACK, 'min_qty' => 8, 'max_qty' => 20,
        ]), 1500);
        $entry = $this->priced($this->product('Entrada'), 990);

        $this->getJson(self::ROOT.'/catalog/products/'.$pack->id)
            ->assertOk()
            ->assertJsonPath('min_quantity', 8)
            ->assertJsonPath('max_quantity', 20);

        // Una entrada no tiene mínimo de grupo ni tope propio: 1 y sin límite configurado.
        $this->getJson(self::ROOT.'/catalog/products/'.$entry->id)
            ->assertOk()
            ->assertJsonPath('min_quantity', 1)
            ->assertJsonPath('max_quantity', null);
    }

    /** La ficha repite los campos de la lista al mismo nivel: un producto, una forma. */
    public function test_the_detail_is_the_list_card_plus_its_own_fields(): void
    {
        $product = $this->priced($this->product('Entrada'), 990);

        $card = $this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data.0');
        $detail = $this->getJson(self::ROOT.'/catalog/products/'.$product->id)->assertOk()->json();

        foreach ($card as $field => $value) {
            $this->assertSame($value, $detail[$field], "el campo `{$field}` difiere entre la lista y la ficha");
        }
    }

    /**
     * Los campos del evento que expone el catálogo son los de la etapa `booking` —los que se piden
     * AL RESERVAR—. Los `postform` son de otra superficie (el formulario por invitado) y mezclarlos
     * llevaría a pedirlos en la pantalla equivocada.
     */
    public function test_the_detail_only_exposes_the_booking_stage_event_fields(): void
    {
        $pack = $this->priced($this->product('Pack', [
            'type' => TicketType::TYPE_PACK, 'min_qty' => 8,
            'event_fields' => [
                ['key' => 'celebrant', 'label' => ['es' => 'Homenajeado'], 'type' => 'text', 'required' => true, 'stage' => TicketType::EVENT_STAGE_BOOKING],
                ['key' => 'allergies', 'label' => ['es' => 'Alergias'], 'type' => 'textarea', 'required' => false, 'stage' => TicketType::EVENT_STAGE_POSTFORM],
            ],
        ]), 1500);

        $fields = $this->getJson(self::ROOT.'/catalog/products/'.$pack->id)
            ->assertOk()
            ->assertValidResponse(200)
            ->json('event_fields');

        $this->assertSame(['celebrant'], array_column($fields, 'key'));
        $this->assertSame('Homenajeado', $fields[0]['label']);
        $this->assertTrue($fields[0]['required']);
    }

    /** Una entrada no pide datos de evento aunque alguien le deje un esquema suelto en BD. */
    public function test_an_entry_has_no_event_fields(): void
    {
        $entry = $this->priced($this->product('Entrada', [
            'event_fields' => [['key' => 'x', 'label' => ['es' => 'X'], 'type' => 'text', 'required' => true]],
        ]), 990);

        $this->getJson(self::ROOT.'/catalog/products/'.$entry->id)
            ->assertOk()
            ->assertJsonPath('event_fields', []);
    }

    /**
     * Los complementos llegan con la config del ENGANCHE, y con la selección por defecto ya
     * resuelta por el dominio: en un grupo excluyente, el incluido; suelto y obligatorio, también.
     */
    public function test_the_detail_describes_the_offered_addons(): void
    {
        $pack = $this->priced($this->product('Pack', ['type' => TicketType::TYPE_PACK, 'min_qty' => 8]), 1500);

        $menuBasic = $this->priced($this->product('Menú básico', ['type' => TicketType::TYPE_ADDON, 'zone_id' => null]), 0);
        $menuPlus = $this->priced($this->product('Menú plus', ['type' => TicketType::TYPE_ADDON, 'zone_id' => null]), 800);
        $cake = $this->priced($this->product('Tarta', ['type' => TicketType::TYPE_ADDON, 'zone_id' => null]), 2500);

        $pack->configurableAddons()->attach($menuBasic->id, [
            'is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'per_guest',
            'choice_group' => 'menu', 'position' => 1,
        ]);
        $pack->configurableAddons()->attach($menuPlus->id, [
            'quantity_mode' => 'per_guest', 'choice_group' => 'menu', 'position' => 2,
        ]);
        $pack->configurableAddons()->attach($cake->id, [
            'quantity_mode' => 'fixed', 'is_mandatory' => true, 'included_quantity' => 1,
            'max_qty' => 3, 'requires_addon_id' => $menuPlus->id, 'position' => 3,
        ]);

        $addons = $this->getJson(self::ROOT.'/catalog/products/'.$pack->id)
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200)
            ->json('addons');

        $this->assertSame(['Menú básico', 'Menú plus', 'Tarta'], array_column($addons, 'name'));

        // El incluido del grupo es el preseleccionado; su compañero de pago, no.
        $this->assertTrue($addons[0]['included']);
        $this->assertTrue($addons[0]['per_guest']);
        $this->assertSame('menu', $addons[0]['choice_group']);
        $this->assertTrue($addons[0]['selected_by_default']);
        $this->assertFalse($addons[1]['selected_by_default']);

        // El obligatorio suelto viaja con su tope y su dependencia.
        $this->assertTrue($addons[2]['mandatory']);
        $this->assertSame(2500, $addons[2]['price_cents']);
        $this->assertSame(3, $addons[2]['max_quantity']);
        $this->assertSame($menuPlus->id, $addons[2]['requires_addon_id']);
        $this->assertTrue($addons[2]['selected_by_default']);
    }

    /**
     * Un complemento de PAGO sin precio para la tarifa no se ofrece: el checkout lo rechazaría, así
     * que anunciarlo sería ofrecer algo que no se puede comprar. Es la misma regla que aplica el
     * modelo de vista del flujo de compra, no una copia escrita para la API.
     *
     * Un complemento INCLUIDO sin precio sí se ofrece: es gratis de verdad.
     */
    public function test_a_paid_addon_without_price_is_not_offered(): void
    {
        $pack = $this->priced($this->product('Pack', ['type' => TicketType::TYPE_PACK, 'min_qty' => 8]), 1500);

        $unpriced = $this->product('Extra sin precio', ['type' => TicketType::TYPE_ADDON, 'zone_id' => null]);
        $freeIncluded = $this->product('Incluido sin precio', ['type' => TicketType::TYPE_ADDON, 'zone_id' => null]);

        $pack->configurableAddons()->attach($unpriced->id, ['quantity_mode' => 'fixed', 'position' => 1]);
        $pack->configurableAddons()->attach($freeIncluded->id, [
            'is_included' => true, 'included_quantity' => 1, 'quantity_mode' => 'fixed', 'position' => 2,
        ]);

        $addons = $this->getJson(self::ROOT.'/catalog/products/'.$pack->id)->assertOk()->json('addons');

        $this->assertSame(['Incluido sin precio'], array_column($addons, 'name'));
        $this->assertSame(0, $addons[0]['price_cents']);
    }

    /** Los complementos NO son productos del catálogo: no se listan ni se abren por su id. */
    public function test_an_addon_is_not_a_catalog_product(): void
    {
        $addon = $this->priced($this->product('Complemento', [
            'type' => TicketType::TYPE_ADDON, 'zone_id' => null,
        ]), 200);

        $this->getJson(self::ROOT.'/catalog/products/'.$addon->id)->assertNotFound();
    }

    /**
     * Las tres razones para no estar en el catálogo dan el MISMO 404: distinguirlas convertiría el
     * endpoint en una forma de enumerar la base de datos.
     */
    public function test_everything_outside_the_catalog_answers_the_same_404(): void
    {
        $unsellable = $this->priced($this->product('No vendible', ['is_sellable' => false]), 990);
        $closed = Zone::create([
            'slug' => 'closed', 'name' => ['es' => 'Cerrada'], 'accent' => 'kids',
            'color' => '#000000', 'position' => 9, 'is_active' => false,
        ]);
        $inClosedZone = $this->priced($this->product('Zona cerrada', ['zone_id' => $closed->id]), 990);

        foreach ([$unsellable->id, $inClosedZone->id, 999999] as $id) {
            $this->getJson(self::ROOT.'/catalog/products/'.$id)
                ->assertNotFound()
                ->assertValidResponse(404)
                ->assertJsonPath('error.code', 'not_found');
        }
    }

    /** Un id que no es un número no llega al controlador: la ruta ya no casa. */
    public function test_a_non_numeric_id_does_not_reach_the_controller(): void
    {
        $this->getJson(self::ROOT.'/catalog/products/abc')->assertNotFound();
    }
}
