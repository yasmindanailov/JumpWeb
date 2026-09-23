<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\PriceTier;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * **Los PRECIOS por tarifa del menú de hechos** (F5 · T5, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila: que los importes viajen en **céntimos enteros**, que una tarifa en la que un producto
 * **no se vende** se calle en vez de mandar un `0`, que lo apagado en el panel **no tenga precio público**,
 * y que el rótulo de la tarifa salga del panel y no de una cadena escrita en el cliente.
 *
 * ▶ Y desde `#677`, **la escalera de los TRAMOS DE GRUPO**: que diga lo mismo que cobra la cesta, que no
 * anuncie una fila que la compra no alcanza, y que falte cuando el precio no depende de la cantidad.
 * ⚠️ Hereda de `ApiTestCase` desde esa tanda para validar la respuesta contra `openapi/v1.yaml`: hasta
 * entonces ningún test comprobaba que `/prices` casara con su esquema, solo que el esquema fuera estricto.
 */
class PricesFactsTest extends ApiTestCase
{
    /**
     * @param  ?list<int>  $weekdays  los días que ESTA tarifa reclama, `0 = domingo`
     */
    private function tarifa(string $key, string $label, bool $especial = false, ?array $weekdays = null): RateType
    {
        return RateType::query()->updateOrCreate(
            ['key' => $key],
            ['label' => ['es' => $label], 'is_active' => true, 'is_special' => $especial, 'weekdays' => $weekdays],
        );
    }

    private function zona(array $atributos = []): Zone
    {
        return Zone::create([
            'slug' => 'zona-'.str()->random(6),
            'name' => ['es' => 'Una zona'],
            'is_active' => true,
            ...$atributos,
        ]);
    }

    private function producto(Zone $zona, string $nombre, array $precios, bool $activo = true): TicketType
    {
        // ⚠️ Sin factory: el catálogo no tiene una y crearla para esto metería en el repo una forma de
        // producto que ningún otro test comparte. Se crea como lo hacen sus hermanos.
        $producto = TicketType::create([
            'zone_id' => $zona->id,
            'name' => ['es' => $nombre],
            'type' => TicketType::TYPE_ENTRY,
            'is_sellable' => true,
            'is_active' => $activo,
            'seats_per_unit' => 1,
        ]);

        // ⚠️ `prices` es POLIMÓRFICA (`priceable_type`/`priceable_id`) y lleva su propia `currency`: no hay
        // `ticket_type_id`. Se descubrió escribiendo este test, y por eso la moneda del recurso sale de la
        // fila y no de una constante.
        // ⚠️⚠️ Y el tipo es el ALIAS del morphMap forzado (`ticket_type`), no el FQCN: con la clase entera
        // la fila se escribe, el test no falla al insertar… y el producto sale SIN precios, que es como se
        // pierde media hora buscando en el sitio equivocado.
        $this->precios($producto, $precios);

        return $producto;
    }

    /** @param  array<string, int>  $precios  céntimos por clave de tarifa */
    private function precios(TicketType $producto, array $precios): void
    {
        foreach ($precios as $key => $cents) {
            Price::query()->create([
                'priceable_type' => 'ticket_type',
                'priceable_id' => $producto->id,
                'rate_type_id' => RateType::query()->where('key', $key)->value('id'),
                'amount_cents' => $cents,
                'currency' => 'EUR',
            ]);
        }
    }

    /**
     * Un pack de GRUPO, con su mínimo y su máximo de personas. ⚠️ Sin familia de edades: tramos y sello
     * son excluyentes (`#324`), y el modelo lo hace cumplir al guardar un tramo.
     *
     * @param  array<string, int>  $precios  el precio «de siempre», por clave de tarifa
     */
    private function grupo(string $nombre, int $minimo, ?int $maximo, array $precios = [], string $tipo = TicketType::TYPE_PACK): TicketType
    {
        $grupo = TicketType::create([
            'zone_id' => $this->zona()->id,
            'name' => ['es' => $nombre],
            'type' => $tipo,
            'min_qty' => $minimo,
            'max_qty' => $maximo,
            'seats_per_unit' => 1,
            'is_sellable' => true,
            'is_active' => true,
        ]);

        $this->precios($grupo, $precios);

        return $grupo;
    }

    /** @param  array<int, int>  $escalera  céntimos por unidad «desde N» */
    private function tramos(TicketType $producto, string $tarifa, array $escalera): void
    {
        foreach ($escalera as $desde => $cents) {
            PriceTier::create([
                'ticket_type_id' => $producto->id,
                'rate_type_id' => RateType::query()->where('key', $tarifa)->value('id'),
                'min_qty' => $desde,
                'amount_cents' => $cents,
            ]);
        }
    }

    /** @return array<string, mixed> el producto servido con ese nombre */
    private function servido(string $nombre): array
    {
        $producto = collect($this->getJson('/api/v1/prices?lang=es')->assertOk()->json('products'))
            ->firstWhere('name', $nombre);

        $this->assertNotNull($producto, "«{$nombre}» no se sirve: el caso nace sin sujeto");

        return $producto;
    }

    public function test_it_serves_each_price_in_cents_with_its_rate_label(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        // ⚠️ `especial: true` y no solo la clave «special»: el fixture anterior creaba una tarifa
        // LLAMADA especial que el dominio no consideraba especial, y por eso no representaba al
        // catálogo real. Lo destapó publicar `is_special` en `#676`.
        $this->tarifa('special', 'Viernes y festivos', especial: true);
        $zona = $this->zona(['slug' => 'kids']);
        $this->producto($zona, 'Kids · 1 hora', ['normal' => 640, 'special' => 800]);

        $datos = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json();

        $this->assertSame('EUR', $datos['currency']);
        // ⚠️ `special` entra en `#676` y va SIEMPRE: saber si una tarifa es la especial no puede
        // depender de que alguien le haya declarado días. `weekdays` sí falta aquí, porque este
        // fixture no se los da a ninguna.
        $this->assertSame(
            [
                ['key' => 'normal', 'label' => 'Lunes a jueves', 'special' => false],
                ['key' => 'special', 'label' => 'Viernes y festivos', 'special' => true],
            ],
            $datos['rates'],
        );
        $this->assertSame('kids', $datos['products'][0]['zone']);
        $this->assertSame(
            [['rate' => 'normal', 'cents' => 640], ['rate' => 'special', 'cents' => 800]],
            $datos['products'][0]['prices'],
        );
    }

    /**
     * **Una tarifa sin precio se CALLA.** Un `0` es un precio, y uno muy llamativo: una landing pintaría
     * «gratis los festivos» para un producto que esos días no se vende.
     */
    public function test_a_rate_the_product_is_not_sold_in_is_omitted(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $this->tarifa('special', 'Viernes y festivos');
        $zona = $this->zona();
        $this->producto($zona, 'Solo entre semana', ['normal' => 1440]);

        $precios = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json('products.0.prices');

        $this->assertSame([['rate' => 'normal', 'cents' => 1440]], $precios);
    }

    /** Lo apagado en el panel no tiene precio público: anunciarlo sería ofrecer lo que el embudo rechaza. */
    public function test_what_is_not_on_sale_has_no_public_price(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $activa = $this->zona();
        $apagada = $this->zona(['is_active' => false]);

        $this->producto($activa, 'A la venta', ['normal' => 100]);
        $this->producto($activa, 'Producto apagado', ['normal' => 200], activo: false);
        $this->producto($apagada, 'De zona apagada', ['normal' => 300]);

        $nombres = array_column($this->getJson('/api/v1/prices?lang=es')->assertOk()->json('products'), 'name');

        $this->assertSame(['A la venta'], $nombres);
    }

    /**
     * ⚠️ **El «precio de antes» de la promo NO viaja** (`#628`, `#644`): es una chapuza declarada con fecha
     * de caducidad, y un contrato público no se rompe después para desmontar un apaño. Este caso existe
     * para que quien lo añada «porque la web lo enseña» lea antes por qué no está.
     */
    public function test_the_promo_strikethrough_never_reaches_the_contract(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $zona = $this->zona();
        // ⚠️ El nombre no puede contener la palabra que se busca abajo: la primera versión llamó a este
        // producto «Con promo viva» y el test se acusaba a sí mismo.
        $this->producto($zona, 'Una entrada cualquiera', ['normal' => 800]);

        Setting::query()->updateOrCreate(['key' => 'promo.percent'], ['value' => '20', 'group' => 'promo']);
        Setting::flushMemo();

        $crudo = (string) $this->getJson('/api/v1/prices?lang=es')->assertOk()->getContent();

        $this->assertStringNotContainsString('was', $crudo);
        $this->assertStringNotContainsString('promo', $crudo);
    }

    /**
     * **No duplica el «desde» del catálogo.** Si algún día apareciera aquí, habría dos fuentes para el mismo
     * número y divergirían: el catálogo lo calcula con los tramos de volumen (`#324`) y esto no.
     */
    public function test_it_does_not_repeat_the_catalogue_from_price(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $zona = $this->zona();
        $this->producto($zona, 'Uno', ['normal' => 500]);

        $producto = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json('products.0');

        $this->assertArrayNotHasKey('from_price_cents', $producto);
        $this->assertArrayNotHasKey('price_varies', $producto);
    }

    public function test_the_language_is_required_and_the_response_is_cacheable(): void
    {
        $this->getJson('/api/v1/prices')->assertStatus(422);

        $cache = (string) $this->getJson('/api/v1/prices?lang=es')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('public', $cache);
        $this->assertStringContainsString('max-age=300', $cache);
    }

    /**
     * **Los DÍAS de una tarifa viajan, y la derivación también** (`#676`). El censo de `#675` lo
     * fichó como el hueco que no se ve leyendo: `/schedule.weekly` da horario **sin tarifa** y esto
     * daba importes **por tarifa sin decir qué día es cuál**. Cada endpoint tenía una mitad.
     */
    public function test_a_rate_publishes_the_weekdays_it_claims_and_the_plain_ones_are_derived(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $this->tarifa('special', 'Viernes y findes', especial: true, weekdays: [5, 6, 0]);
        $this->producto($this->zona(), 'Uno', ['normal' => 500]);

        $datos = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json();

        $especial = collect($datos['rates'])->firstWhere('key', 'special');
        $this->assertSame([5, 6, 0], $especial['weekdays']);

        // La NORMAL no reclama días: se aplica a lo que sobra, así que no lleva la clave.
        $normal = collect($datos['rates'])->firstWhere('key', 'normal');
        $this->assertArrayNotHasKey('weekdays', $normal);

        // Y la derivación llega hecha: lunes a jueves.
        $this->assertSame([1, 2, 3, 4], $datos['plain_weekdays']);
    }

    /**
     * ❗❗ **`special` se lee del DATO, no de la clave — y este caso nació de un superviviente.**
     *
     * El arnés mutó `$tarifa->is_special` por `$tarifa->key === 'special'` y **no murió**: todos los
     * fixtures llamaban «special» a la tarifa especial, así que las dos expresiones daban lo mismo.
     * Un superviviente es una pregunta sobre el TEST, y la que faltaba es ésta: **una instalación
     * puede llamar a su tarifa especial como quiera**. Con la deducción por clave, un parque cuya
     * tarifa se llame `finde` publicaría `special: false` y una landing anunciaría el fin de semana
     * como día normal.
     */
    public function test_special_is_read_from_the_data_and_not_deduced_from_the_key(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        // La ESPECIAL con otro nombre: es lo que distingue leer el dato de adivinar por la clave.
        $this->tarifa('finde', 'Findes y festivos', especial: true, weekdays: [5, 6, 0]);
        $this->producto($this->zona(), 'Uno', ['normal' => 500]);

        $datos = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json();

        $this->assertTrue(
            collect($datos['rates'])->firstWhere('key', 'finde')['special'],
            'una tarifa especial que no se llama «special» sigue siendo especial'
        );
        // Y la derivación la cuenta como especial, o la semana saldría mal.
        $this->assertSame([1, 2, 3, 4], $datos['plain_weekdays']);
    }

    /**
     * ❗❗ **`plain_weekdays` AUSENTE no significa «ninguno»: significa «no se puede saber».** El caso
     * es una tarifa especial ACTIVA que no declara sus días — ahí el producto no puede afirmar
     * cuáles son normales, y una landing que restara «7 menos los especiales» publicaría una semana
     * inventada. Por eso la clave falta en vez de viajar como lista vacía.
     */
    public function test_an_undecidable_week_omits_the_key_instead_of_guessing(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        // Especial, activa y SIN días declarados: el caso indecidible.
        $this->tarifa('special', 'Viernes y findes', especial: true, weekdays: null);
        $this->producto($this->zona(), 'Uno', ['normal' => 500]);

        $datos = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json();

        $this->assertArrayNotHasKey('plain_weekdays', $datos);
        // CONTROL: la tarifa sigue ahí y declarada como especial, o el caso mediría otra cosa.
        $this->assertTrue(collect($datos['rates'])->firstWhere('key', 'special')['special']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Los TRAMOS DE GRUPO (`#677`)
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **LA ESCALERA DICE LO MISMO QUE COBRA LA CESTA, EN CÉNTIMOS.** Es el cuadro real de la excursión
     * de 2 h (`PriceTierTest`), con un precio «de siempre» de 99 € que con tramos no gana nunca: si la
     * escalera saliera de `prices` en vez de preguntar por cada cantidad, las tres filas dirían 99 €.
     *
     * ⚠️ Los valores esperados van TECLEADOS: derivarlos de los mismos datos compararía el código consigo
     * mismo. Y la respuesta se valida contra el contrato, que es quien dice que la cantidad se llama
     * `from_quantity` y no `from` —que se confundiría con un «precio desde»—.
     */
    public function test_a_group_product_publishes_its_price_ladder_in_cents(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $this->tarifa('special', 'Viernes y festivos', especial: true);
        $excursion = $this->grupo('Excursión 2 h', 30, 100, ['normal' => 9900, 'special' => 9900]);
        $this->tramos($excursion, 'normal', [30 => 1500, 70 => 1300, 100 => 1200]);
        $this->tramos($excursion, 'special', [30 => 1700, 70 => 1500, 100 => 1400]);

        $this->getJson(self::ROOT.'/prices?lang=es')->assertOk()->assertValidRequest()->assertValidResponse(200);
        $producto = $this->servido('Excursión 2 h');

        $this->assertSame([
            ['from_quantity' => 30, 'prices' => [['rate' => 'normal', 'cents' => 1500], ['rate' => 'special', 'cents' => 1700]]],
            ['from_quantity' => 70, 'prices' => [['rate' => 'normal', 'cents' => 1300], ['rate' => 'special', 'cents' => 1500]]],
            ['from_quantity' => 100, 'prices' => [['rate' => 'normal', 'cents' => 1200], ['rate' => 'special', 'cents' => 1400]]],
        ], $producto['tiers']);

        // ⚠️ La primera fila REPITE `prices` a propósito y por construcción: las dos preguntan a la cesta por
        // el mínimo. Si un día divergen, una de las dos está anunciando un precio que no se cobra.
        $this->assertSame([['rate' => 'normal', 'cents' => 1500], ['rate' => 'special', 'cents' => 1700]], $producto['prices']);
        $this->assertSame($producto['prices'], $producto['tiers'][0]['prices']);
    }

    /**
     * **Sin escalera, la clave FALTA — y eso afirma que el precio no depende de la cantidad.** Una escalera
     * de UNA fila sería esa misma afirmación dicha con más bytes: la de un grupo con un solo tramo, en su
     * mínimo, que ya es lo que dice `prices`.
     */
    public function test_a_price_that_does_not_depend_on_the_quantity_publishes_no_ladder(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $this->producto($this->zona(), 'Entrada suelta', ['normal' => 800]);
        $unTramo = $this->grupo('Grupo de un tramo', 20, 60, ['normal' => 1400]);
        $this->tramos($unTramo, 'normal', [20 => 1100]);
        // CONTROL: con un segundo tramo sí hay escalera, o el caso pasaría con la escalera apagada entera.
        $dosTramos = $this->grupo('Grupo de dos tramos', 20, 60, ['normal' => 1400]);
        $this->tramos($dosTramos, 'normal', [20 => 1100, 40 => 900]);

        $this->assertArrayNotHasKey('tiers', $this->servido('Entrada suelta'));
        $this->assertArrayNotHasKey('tiers', $this->servido('Grupo de un tramo'));
        $this->assertSame([['rate' => 'normal', 'cents' => 1100]], $this->servido('Grupo de un tramo')['prices']);
        $this->assertCount(2, $this->servido('Grupo de dos tramos')['tiers']);
    }

    /**
     * ❗ **La escalera empieza en el MÍNIMO contratable** (`#329`): un tramo por debajo no abre fila —por
     * debajo del mínimo no se vende—, pero SÍ pone el precio del mínimo, porque es el tramo que lo cubre.
     * Si la fila del mínimo faltara, la escalera empezaría en 50 y nadie sabría desde cuántos se contrata.
     */
    public function test_the_ladder_starts_at_the_contractable_minimum(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $grupo = $this->grupo('Grupo desde 30', 30, 100, ['normal' => 9900]);
        $this->tramos($grupo, 'normal', [10 => 2000, 50 => 1500]);

        $this->assertSame([
            ['from_quantity' => 30, 'prices' => [['rate' => 'normal', 'cents' => 2000]]],
            ['from_quantity' => 50, 'prices' => [['rate' => 'normal', 'cents' => 1500]]],
        ], $this->servido('Grupo desde 30')['tiers']);
    }

    /**
     * ❗❗ **Una fila que la compra no alcanza NO se publica.** Por encima del máximo de un pack
     * `OrderCreator` rechaza la cantidad —también al operador—, así que el tramo de 150 anunciaría 10 € a
     * quien no puede comprarlos, y una landing sacaría de ahí su «desde». El panel deja guardar ese tramo;
     * la escalera no lo enseña.
     */
    public function test_a_tier_the_cart_cannot_reach_is_not_published(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $conTope = $this->grupo('Grupo hasta 100', 30, 100, ['normal' => 1500]);
        $this->tramos($conTope, 'normal', [30 => 1500, 70 => 1300, 150 => 1000]);
        // CONTROL: el MISMO cuadro sin máximo sí publica el de 150. Sin él, un filtro que tirase cualquier
        // tramo alto —y no los inalcanzables— pasaría este caso.
        $sinTope = $this->grupo('Grupo sin tope', 30, null, ['normal' => 1500]);
        $this->tramos($sinTope, 'normal', [30 => 1500, 70 => 1300, 150 => 1000]);

        $this->assertSame([30, 70], array_column($this->servido('Grupo hasta 100')['tiers'], 'from_quantity'));
        $this->assertSame([30, 70, 150], array_column($this->servido('Grupo sin tope')['tiers'], 'from_quantity'));
    }

    /**
     * **Cada tarifa se resuelve POR SEPARADO en cada fila**, que es lo que hace la cesta: una tarifa sin
     * tramos propios conserva su precio de siempre en todas las filas, y una en la que el grupo no se vende
     * se CALLA en todas —ni un `0`, ni la fila entera fuera—.
     */
    public function test_each_rate_is_resolved_on_its_own_in_every_row(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $this->tarifa('special', 'Viernes y festivos', especial: true);
        $this->tarifa('festivo', 'Festivos', especial: true);
        // `special` tiene precio pero NO tramos; `festivo` no tiene ninguno de los dos.
        $grupo = $this->grupo('Grupo mixto', 30, 100, ['normal' => 1600, 'special' => 1900]);
        $this->tramos($grupo, 'normal', [30 => 1500, 70 => 1300]);

        $this->assertSame([
            ['from_quantity' => 30, 'prices' => [['rate' => 'normal', 'cents' => 1500], ['rate' => 'special', 'cents' => 1900]]],
            ['from_quantity' => 70, 'prices' => [['rate' => 'normal', 'cents' => 1300], ['rate' => 'special', 'cents' => 1900]]],
        ], $this->servido('Grupo mixto')['tiers']);
    }

    /**
     * **Una cantidad en la que ninguna tarifa tiene precio NO abre fila**: no es un tramo, es que ahí no se
     * vende. El grupo no tiene precio de siempre y su primer tramo empieza en 50, así que en su mínimo (30)
     * la cesta no encuentra precio; una fila `{30, []}` diría «desde 30» de algo que no se puede comprar.
     */
    public function test_a_quantity_with_no_price_in_any_rate_opens_no_row(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $grupo = $this->grupo('Grupo sin precio base', 30, 100);
        $this->tramos($grupo, 'normal', [50 => 1500, 70 => 1300]);

        $this->assertSame([
            ['from_quantity' => 50, 'prices' => [['rate' => 'normal', 'cents' => 1500]]],
            ['from_quantity' => 70, 'prices' => [['rate' => 'normal', 'cents' => 1300]]],
        ], $this->servido('Grupo sin precio base')['tiers']);
    }

    /**
     * ⚠️ **Un COMPLEMENTO no tiene escalera aunque tenga filas en `price_tiers`**: los tramos no le aplican
     * (`TicketType::tierPriceCents()`), así que cada fila diría su precio de siempre y la landing pintaría
     * una tabla de descuentos que la cesta no hace.
     */
    public function test_an_addon_never_publishes_a_ladder(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $tarta = $this->grupo('Tarta', 1, null, ['normal' => 1000], TicketType::TYPE_ADDON);
        $this->tramos($tarta, 'normal', [10 => 800]);

        $tarta = $this->servido('Tarta');

        $this->assertArrayNotHasKey('tiers', $tarta);
        $this->assertSame([['rate' => 'normal', 'cents' => 1000]], $tarta['prices']);
    }

    /**
     * **La escalera no cuesta una consulta por producto.** Se mide por PENDIENTE (`api-v1.md` §10·17): el
     * mismo número de consultas con un grupo que con cuatro. Los tramos llegan con la precarga del
     * controlador, y sin ella cada fila de cada producto preguntaría a la base.
     */
    public function test_the_ladder_does_not_query_per_product(): void
    {
        $this->tarifa('normal', 'Lunes a jueves');
        $consultas = function (): int {
            // ⚠️ Se calienta antes: la primera petición paga el `select` de `settings` que `PERF-02` memoiza.
            $this->getJson('/api/v1/prices?lang=es')->assertOk();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/prices?lang=es')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $grupo = $this->grupo('Grupo 1', 30, 100, ['normal' => 1500]);
        $this->tramos($grupo, 'normal', [30 => 1500, 70 => 1300]);
        $conUno = $consultas();

        foreach ([2, 3, 4] as $n) {
            $grupo = $this->grupo("Grupo {$n}", 30, 100, ['normal' => 1500]);
            $this->tramos($grupo, 'normal', [30 => 1500, 70 => 1300]);
        }

        $this->assertCount(4, array_filter(
            $this->getJson('/api/v1/prices?lang=es')->json('products'),
            fn (array $p): bool => isset($p['tiers']),
        ), 'el caso nace sin sujeto: no hay cuatro escaleras');
        $this->assertSame($conUno, $consultas(), 'la escalera consulta por producto');
    }
}
