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

    private function tarifa(string $key, string $label): RateType
    {
        return RateType::query()->updateOrCreate(
            ['key' => $key],
            ['label' => ['es' => $label], 'is_active' => true],
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
        $this->tarifa('special', 'Viernes y festivos');
        $zona = $this->zona(['slug' => 'kids']);
        $this->producto($zona, 'Kids · 1 hora', ['normal' => 640, 'special' => 800]);

        $datos = $this->getJson('/api/v1/prices?lang=es')->assertOk()->json();

        $this->assertSame('EUR', $datos['currency']);
        $this->assertSame(
            [['key' => 'normal', 'label' => 'Lunes a jueves'], ['key' => 'special', 'label' => 'Viernes y festivos']],
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
}
