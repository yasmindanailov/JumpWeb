<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Los PRECIOS por tarifa del menú de hechos** (F5 · T5, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila: que los importes viajen en **céntimos enteros**, que una tarifa en la que un producto
 * **no se vende** se calle en vez de mandar un `0`, que lo apagado en el panel **no tenga precio público**,
 * y que el rótulo de la tarifa salga del panel y no de una cadena escrita en el cliente.
 */
class PricesFactsTest extends TestCase
{
    use RefreshDatabase;

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
        foreach ($precios as $key => $cents) {
            Price::query()->create([
                'priceable_type' => 'ticket_type',
                'priceable_id' => $producto->id,
                'rate_type_id' => RateType::query()->where('key', $key)->value('id'),
                'amount_cents' => $cents,
                'currency' => 'EUR',
            ]);
        }

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
}
