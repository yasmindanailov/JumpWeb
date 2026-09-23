<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Support\Carbon;
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

    /**
     * ⚠️ **El reloj se congela en un día LABORABLE, y no es celo: sin esto el fichero falla los fines
     * de semana.** Medido el sábado 2026-08-15: `test_the_detail_describes_the_offered_addons` cayó
     * enseñando **un** complemento de tres. La causa no está en el caso sino en el fixture de abajo —la
     * tarifa `special` aplica `weekdays: [0, 6]`—, combinada con una regla real del read-model:
     * `CatalogReader` resuelve la tarifa de los complementos con `Carbon::today()` (documentado ahí: es
     * lo que hacen también el modelo de vista de la compra y `OrderCreator`) y **descarta el
     * complemento de PAGO que no tiene precio para esa tarifa**, porque ofrecerlo acabaría en un
     * checkout rechazado. Los complementos del caso solo tienen precio `normal`, así que el fin de
     * semana desaparecen — el incluido sobrevive porque no necesita precio.
     *
     * O sea: la conducta es CORRECTA y el test era el que dependía del calendario. Se congela el reloj
     * en vez de darles precio especial, porque el sujeto de este fichero es el catálogo, no las tarifas.
     */
    private const FROZEN_NOW = '2026-08-12 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::FROZEN_NOW);

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

    /**
     * **La FICHA de una zona** (F5 · T6 del menú de hechos, `#632` P1): su descripción y su foto,
     * que es lo que necesita quien vende SIN la landing del producto.
     *
     * ⚠️ La URL es ABSOLUTA. Quien la pinta puede estar en otro dominio o no ser un navegador (la
     * app de F6), y ninguno de los dos puede resolver `images/attractions/park_jump.webp`.
     */
    public function test_a_zone_publishes_its_description_and_photo(): void
    {
        $this->zone->update([
            'description' => ['es' => 'Trampolines de pared a pared.', 'en' => 'Wall to wall trampolines.'],
            'image' => 'images/attractions/park_jump.webp',
        ]);

        $response = $this->getJson(self::ROOT.'/catalog/zones')
            ->assertOk()
            ->assertValidResponse(200);

        $this->assertSame('Trampolines de pared a pared.', $response->json('data.0.description'));
        $this->assertSame(
            asset('images/attractions/park_jump.webp'),
            $response->json('data.0.image_url'),
            'la foto de la zona tiene que viajar como URL absoluta'
        );
    }

    /**
     * ⚠️⚠️ **Lo que la instalación no rellenó NO viaja, ni como `""`** — la receta del menú de
     * hechos. Si esto se relajara, cada landing tendría que distinguir «no hay descripción» de
     * «hay una descripción vacía», que son lo mismo para quien pinta.
     */
    public function test_a_zone_without_ficha_omits_the_keys_instead_of_emitting_empty_ones(): void
    {
        // Caso 1: nunca se rellenó — las columnas están a `null`.
        $sinTocar = $this->getJson(self::ROOT.'/catalog/zones')
            ->assertOk()
            ->assertValidResponse(200)
            ->json('data.0');

        $this->assertSame(['id', 'slug', 'name'], array_keys($sinTocar));

        // ⚠️⚠️ Caso 2, el que de verdad muerde: se escribió y se BORRÓ. El panel no deja `null`, deja
        // la cadena vacía dentro del JSON de traducciones —y una zona con la foto borrada deja `''`
        // en su columna—. Sin este caso, cambiar el `?:` del lector por un `??` publicaría
        // `"description": ""` y ninguna prueba se enteraría: medido el 19-09 con el arnés de
        // mutación, que lo cazó como una mutación que NO muerde.
        $this->zone->update(['description' => ['es' => '', 'en' => ''], 'image' => '']);

        $vaciada = $this->getJson(self::ROOT.'/catalog/zones')
            ->assertOk()
            ->assertValidResponse(200)
            ->json('data.0');

        $this->assertSame(['id', 'slug', 'name'], array_keys($vaciada));
        $this->assertArrayNotHasKey('description', $vaciada);
        $this->assertArrayNotHasKey('image_url', $vaciada);
    }

    /**
     * **La ALTURA viaja con el sentido en el nombre, y la frase ya escrita** (`#676`).
     *
     * ⚠️⚠️ El caso que de verdad muerde es el del SENTIDO: la misma cifra significa lo contrario
     * según la columna, así que se comprueban **las dos zonas** —una «a partir de» y otra «hasta»—.
     * Con `min`/`max` en el contrato, una landing que las intercambiara publicaría lo contrario sin
     * que nada fallara; por eso el caso asevera la CLAVE, no solo el número.
     */
    public function test_a_zone_publishes_its_height_with_the_meaning_in_the_key(): void
    {
        $this->zone->update(['height_min_cm' => 130, 'height_max_cm' => null]);

        $desde = $this->getJson(self::ROOT.'/catalog/zones')->assertOk()->assertValidResponse(200)->json('data.0');

        $this->assertSame(130, $desde['height']['from_cm']);
        $this->assertArrayNotHasKey('up_to_cm', $desde['height'], '«a partir de» no puede salir como «hasta»');
        $this->assertSame(__('landing.zones.height_from', ['h' => '1,30']), $desde['height']['written']);

        // La otra mitad: la misma cifra, la otra columna, el sentido contrario.
        $this->zone->update(['height_min_cm' => null, 'height_max_cm' => 130]);

        $hasta = $this->getJson(self::ROOT.'/catalog/zones')->assertOk()->assertValidResponse(200)->json('data.0');

        $this->assertSame(130, $hasta['height']['up_to_cm']);
        $this->assertArrayNotHasKey('from_cm', $hasta['height']);
        $this->assertNotSame(
            $desde['height']['written'],
            $hasta['height']['written'],
            'la misma cifra en las dos columnas no puede redactarse igual: significan lo contrario'
        );
    }

    /**
     * **La frase la escribe el PRODUCTO, con el separador decimal del idioma** (`#676`). Es la regla
     * que `#660` cazó mal escrita en una landing: «1.30» en una página en español.
     */
    public function test_the_written_height_follows_the_language(): void
    {
        $this->zone->update(['height_min_cm' => 130, 'height_max_cm' => 150]);

        $es = $this->getJson(self::ROOT.'/catalog/zones')->assertOk()->json('data.0.height.written');
        $en = $this->getJson(self::ROOT.'/catalog/zones', ['Accept-Language' => 'en'])
            ->assertOk()->json('data.0.height.written');

        $this->assertStringContainsString('1,30', (string) $es, 'en español el decimal va con coma');
        $this->assertStringContainsString('1.30', (string) $en, 'en inglés el decimal va con punto');
    }

    /** Sin ninguna altura, el bloque falta ENTERO: no viaja `{}` ni una lista vacía. */
    public function test_a_zone_without_heights_omits_the_whole_block(): void
    {
        $this->zone->update(['height_min_cm' => null, 'height_max_cm' => null, 'age_range' => ['es' => '']]);

        $zona = $this->getJson(self::ROOT.'/catalog/zones')->assertOk()->assertValidResponse(200)->json('data.0');

        $this->assertArrayNotHasKey('height', $zona);
        $this->assertArrayNotHasKey('age_range', $zona);
        $this->assertStringNotContainsString('"height"', (string) $this->getJson(self::ROOT.'/catalog/zones')->getContent());
    }

    /** El rango de edad viaja como TEXTO del panel, tal cual, y falta si se borró. */
    public function test_the_age_range_travels_as_the_panel_wrote_it(): void
    {
        $this->zone->update(['age_range' => ['es' => '+8 años']]);

        $this->getJson(self::ROOT.'/catalog/zones')
            ->assertOk()
            ->assertValidResponse(200)
            ->assertJsonPath('data.0.age_range', '+8 años');
    }

    /**
     * **La EDAD y la DURACIÓN que el producto declara, en la LISTA** (`#676`). Van aquí y no solo en
     * la ficha porque quien las necesita son las tarjetas de una página —la portada pinta seis de un
     * tirón— y sacarlas de la ficha costaría una petición por tarjeta.
     *
     * ⚠️ `duration_min` NO es `period_label`: aquél es el rótulo del panel y éste la cifra.
     */
    public function test_a_product_publishes_the_age_and_duration_it_declares(): void
    {
        $this->priced(
            $this->product('Pack Cumple', ['guest_age_min' => 4, 'guest_age_max' => 7, 'duration_min' => 120]),
            1495,
        );

        $producto = $this->getJson(self::ROOT.'/catalog/products')
            ->assertOk()
            ->assertValidResponse(200)
            ->json('data.0');

        $this->assertSame(4, $producto['guest_age_min']);
        $this->assertSame(7, $producto['guest_age_max']);
        $this->assertSame(120, $producto['duration_min']);
    }

    /**
     * **Lo que el producto no declara no viaja** — pero un CERO sí, que es una edad.
     *
     * ⚠️⚠️ El segundo caso es el que muerde: con un filtro que mirase «vacío» en vez de `null`, una
     * edad mínima de 0 —«desde bebés»— desaparecería del catálogo sin que nada fallara.
     */
    public function test_an_undeclared_age_is_absent_but_a_zero_still_travels(): void
    {
        // ⚠️ `duration_min` a `null` EXPLÍCITO: el ayudante pone 60 por defecto, así que sin esto el
        // caso mediría «el fixture trae duración», no «lo no declarado no viaja».
        $producto = $this->priced($this->product('Entrada 1 h', ['duration_min' => null]), 900);

        $sinDeclarar = $this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('guest_age_min', $sinDeclarar);
        $this->assertArrayNotHasKey('duration_min', $sinDeclarar);

        $producto->update(['guest_age_min' => 0]);

        $conCero = $this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data.0');

        $this->assertSame(0, $conCero['guest_age_min'], 'un 0 es una edad, no un «no hay»');
    }

    /** El catálogo es el escaparate: se mira sin cuenta, igual que en la web. */
    public function test_the_catalog_is_public(): void
    {
        $this->priced($this->product('Entrada'), 990);

        $this->getJson(self::ROOT.'/catalog/zones')->assertOk();
        $this->getJson(self::ROOT.'/catalog/products')->assertOk();
    }

    // ── Lista de productos ────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El `type` publicado separa «Entradas» de «Servicios», y no lo fijaba nadie**
     * (`DECISIONES #96`). Medido: cruzar la traducción de `CatalogReader` —entrada↔pack— dejaba este
     * fichero **en verde**; solo caían el diff de árbol y un test del motor que se retira.
     *
     * No es cosmético: es la sección en la que aparece cada producto. Con el tipo cruzado, un pack de
     * cumpleaños se ofrece bajo «Entradas» y una entrada suelta bajo «Servicios», con un árbol
     * perfectamente válido. Y es un campo del CONTRATO —`CatalogProduct::TYPE_*`, no la constante
     * interna del modelo—, así que su sitio es aquí.
     */
    public function test_each_product_publishes_its_own_type(): void
    {
        $this->priced($this->product('Entrada 1 h'), 990);
        $this->priced($this->product('Pack cumple', ['type' => TicketType::TYPE_PACK, 'min_qty' => 8]), 1500);

        $byName = collect($this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data'))
            ->keyBy('name');

        $this->assertSame('entry', $byName['Entrada 1 h']['type'], 'una entrada se publica como entrada');
        $this->assertSame('pack', $byName['Pack cumple']['type'], 'y un pack como pack');
    }

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
     * **La FICHA de un producto, y el REPARTO que la define** (F5 · T6, `#632` P1).
     *
     * ⚠️⚠️ Éste es el caso que fija la decisión de diseño, no un caso de campo: la FOTO va en la
     * lista y la DESCRIPCIÓN solo en el detalle. Un catálogo se recorre mirando fotos —la app de F6
     * no tiene landing y pinta tarjetas con esto—, mientras que la prosa se lee al abrir. Medido el
     * 19-09: la descripción son ~340 bytes en cada producto que la tiene, sobre un payload de 4.079
     * bytes con 24 productos; la URL, ~60. Si alguien mueve la descripción a la lista, el catálogo
     * entero paga la prosa en todas las filas y este caso se pone rojo.
     */
    public function test_the_photo_travels_in_the_list_and_the_description_only_in_the_detail(): void
    {
        $product = $this->priced($this->product('Entrada', [
            'description' => ['es' => 'Una hora de salto libre en la zona Jump.'],
            'image' => 'productos/entrada.webp',
        ]), 990);

        $card = $this->getJson(self::ROOT.'/catalog/products')
            ->assertOk()
            ->assertValidResponse(200)
            ->json('data.0');

        $detail = $this->getJson(self::ROOT.'/catalog/products/'.$product->id)
            ->assertOk()
            ->assertValidResponse(200)
            ->json();

        // La foto, en las dos, y ABSOLUTA: quien la pinta puede no compartir dominio con la API.
        $this->assertSame(asset('uploads/productos/entrada.webp'), $card['image_url']);
        $this->assertSame(asset('uploads/productos/entrada.webp'), $detail['image_url']);

        // La prosa, SOLO en la ficha.
        $this->assertArrayNotHasKey('description', $card, 'la descripción no puede viajar en la lista');
        $this->assertSame('Una hora de salto libre en la zona Jump.', $detail['description']);
    }

    /**
     * Lo que la instalación no rellenó no viaja, tampoco en el producto — y «rellenado y borrado»
     * cuenta como no rellenado, que es el caso que se escapa (ver la gemela de las zonas).
     */
    public function test_a_product_without_ficha_omits_the_keys(): void
    {
        $product = $this->priced($this->product('Entrada'), 990);

        foreach ([[], ['description' => ['es' => ''], 'image' => '']] as $estado) {
            if ($estado !== []) {
                $product->update($estado);
            }

            $card = $this->getJson(self::ROOT.'/catalog/products')->assertOk()->json('data.0');
            $detail = $this->getJson(self::ROOT.'/catalog/products/'.$product->id)->assertOk()->json();

            $this->assertArrayNotHasKey('image_url', $card);
            $this->assertArrayNotHasKey('image_url', $detail);
            $this->assertArrayNotHasKey('description', $detail);
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
