<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\LandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **Las SECCIONES DE SERVICIOS del menú de hechos** (F5, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila es lo que una landing no puede comprobar por su cuenta: que una sección retirada **no
 * vuelva**, que el orden **no baraje**, que la tabla de precios **TECLEADA no se publique** —el owner la
 * jubiló—, que los dos campos sin consumidor **no se conviertan en contrato**, y que lo que viaja de un
 * producto sea su **identificador** y solo si la cesta puede venderlo de verdad.
 */
class ServicesFactsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function seccion(array $atributos = []): LandingService
    {
        return LandingService::query()->create([
            'slug' => 'una-seccion-'.LandingService::query()->count(),
            'title' => ['es' => 'Una sección'],
            'is_active' => true,
            'position' => 1,
            ...$atributos,
        ]);
    }

    /**
     * Un PACK que la cesta puede vender de verdad: activo, vendible, en zona activa y con precio
     * positivo — las cinco condiciones de `isSellablePackForLanding()`.
     *
     * ⚠️ Sin factory (el catálogo no tiene una, `DEUDA.md`): se construye como lo hace su hermano
     * `PricesFactsTest`. ⚠️⚠️ `prices` es POLIMÓRFICA y su `priceable_type` es el **alias** del morphMap
     * (`ticket_type`), no el FQCN: con la clase entera la fila se escribe y el producto sale sin precios.
     */
    private function packVendible(bool $vendible = true): TicketType
    {
        $zona = Zone::create([
            'slug' => 'zona-'.str()->random(6),
            'name' => ['es' => 'Una zona'],
            'is_active' => true,
        ]);

        $pack = TicketType::create([
            'zone_id' => $zona->id,
            'name' => ['es' => 'Un pack'],
            'type' => TicketType::TYPE_PACK,
            'is_sellable' => $vendible,
            'is_active' => true,
            'seats_per_unit' => 1,
        ]);

        Price::query()->create([
            'priceable_type' => 'ticket_type',
            'priceable_id' => $pack->id,
            'rate_type_id' => RateType::query()->updateOrCreate(
                ['key' => 'normal'],
                ['label' => ['es' => 'Lunes a jueves'], 'is_active' => true],
            )->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
        ]);

        return $pack;
    }

    /**
     * @return list<string>
     */
    private function slugs(string $lang = 'es'): array
    {
        return array_column(
            $this->getJson("/api/v1/services?lang={$lang}")->assertOk()->json('services'),
            'slug',
        );
    }

    public function test_the_panel_decides_the_order(): void
    {
        $this->seccion(['slug' => 'segunda', 'position' => 20]);
        $this->seccion(['slug' => 'primera', 'position' => 10]);

        $this->assertSame(['primera', 'segunda'], $this->slugs());
    }

    /** `position` no es única: sin desempate por `id`, dos peticiones idénticas podrían barajar. */
    public function test_a_tie_in_position_is_broken_by_id_and_does_not_shuffle(): void
    {
        $primera = $this->seccion(['slug' => 'nacio-antes', 'position' => 5]);
        $segunda = $this->seccion(['slug' => 'nacio-despues', 'position' => 5]);

        $this->assertTrue($primera->id < $segunda->id, 'el fixture no construye el empate que dice medir');

        $this->assertSame(['nacio-antes', 'nacio-despues'], $this->slugs());
        $this->assertSame(['nacio-antes', 'nacio-despues'], $this->slugs());
    }

    public function test_a_deactivated_section_does_not_come_back_through_the_api(): void
    {
        $this->seccion(['slug' => 'vigente']);
        $this->seccion(['slug' => 'retirada', 'is_active' => false]);

        $this->assertSame(['vigente'], $this->slugs());
    }

    /** Un ancla sin encabezado es una sección en blanco: la familia de la duda sin respuesta (`#671`). */
    public function test_a_section_without_a_title_does_not_travel(): void
    {
        $this->seccion(['slug' => 'con-titulo']);
        $this->seccion(['slug' => 'sin-titulo', 'title' => null]);
        $this->seccion(['slug' => 'titulo-borrado', 'title' => ['es' => '  ']]);

        $this->assertSame(['con-titulo'], $this->slugs());
    }

    /**
     * ❗❗ **LA TABLA TECLEADA NO SE PUBLICA.** El owner la jubiló en favor de los precios del catálogo
     * (`#534`): servirla como hecho sería publicar un precio que el checkout podría no cobrar.
     * ❗ **Y los dos campos sin consumidor tampoco**: `nav_subtitle` y `show_in_nav` están en la base
     * desde `#521` sin que los lea nadie. Estar en la tabla no los convierte en contrato público.
     */
    public function test_the_typed_price_table_and_the_two_orphan_columns_never_travel(): void
    {
        $this->seccion([
            'slug' => 'con-todo',
            'price_table' => ['unit' => 'kids', 'zones' => [['label' => 'Colegios']]],
            'nav_subtitle' => ['es' => 'Un subtítulo'],
            'show_in_nav' => true,
        ]);

        $cuerpo = $this->getJson('/api/v1/services?lang=es')->assertOk()->getContent();

        $this->assertStringNotContainsString('price_table', (string) $cuerpo);
        $this->assertStringNotContainsString('nav_subtitle', (string) $cuerpo);
        $this->assertStringNotContainsString('show_in_nav', (string) $cuerpo);
        // Y no basta con que falte la CLAVE: su contenido tampoco puede salir con otro nombre.
        $this->assertStringNotContainsString('Colegios', (string) $cuerpo);
        $this->assertStringNotContainsString('Un subtítulo', (string) $cuerpo);
    }

    /**
     * **Lo que viaja de un producto es su ID**, y solo si la cesta puede venderlo (`#226`): pack activo,
     * vendible, con zona activa y precio positivo. La lista vacía ya dice «sección de solo-contacto», y por
     * eso NO hay un `purchasable` al lado: sería el mismo hecho dos veces, con opción a contradecirse.
     */
    public function test_only_products_the_cart_can_actually_sell_travel_and_they_travel_as_ids(): void
    {
        $vendible = $this->packVendible();
        $noVendible = $this->packVendible(vendible: false);

        $seccion = $this->seccion(['slug' => 'vende', 'position' => 1]);
        $seccion->products()->attach([$vendible->id, $noVendible->id]);

        $this->seccion(['slug' => 'solo-contacto', 'position' => 9]);

        $cuerpo = $this->getJson('/api/v1/services?lang=es')->assertOk();

        // El no vendible está ENLAZADO —para que el caso mida el filtro y no la ausencia del enlace—
        // y aun así no sale.
        $this->assertSame([$vendible->id, $noVendible->id], $seccion->products()->pluck('ticket_types.id')->sort()->values()->all());
        $cuerpo->assertJsonPath('services.0.products', [$vendible->id]);

        $cuerpo->assertJsonPath('services.1.slug', 'solo-contacto');
        $cuerpo->assertJsonPath('services.1.products', []);
    }

    /** Una ficha necesita sus DOS mitades: un rótulo sin valor no dice nada. */
    public function test_a_spec_needs_both_halves_to_travel(): void
    {
        $this->seccion([
            'slug' => 'con-fichas',
            'specs' => ['es' => [
                ['label' => 'Duración', 'value' => '2 o 3 horas'],
                ['label' => 'Grupo', 'value' => ''],
                ['label' => '', 'value' => 'Huérfano'],
            ]],
        ]);

        $this->getJson('/api/v1/services?lang=es')
            ->assertOk()
            ->assertJsonPath('services.0.specs', [['label' => 'Duración', 'value' => '2 o 3 horas']]);
    }

    /** Sin ninguna ficha publicable, la clave no viaja: no se emite una lista vacía de relleno. */
    public function test_a_section_without_usable_specs_omits_the_key(): void
    {
        $this->seccion(['slug' => 'sin-fichas', 'specs' => ['es' => [['label' => '', 'value' => '']]]]);

        $seccion = $this->getJson('/api/v1/services?lang=es')->assertOk()->json('services.0');

        $this->assertArrayNotHasKey('specs', $seccion);
    }

    /** El mismo respaldo que en `/faqs`: escrito solo en español, se sirve en inglés (`#671`). */
    public function test_a_section_written_only_in_spanish_still_travels_in_english(): void
    {
        $this->seccion(['slug' => 'solo-es', 'title' => ['es' => 'Excursiones de colegio']]);

        $this->getJson('/api/v1/services?lang=en')
            ->assertOk()
            ->assertJsonPath('services.0.title', 'Excursiones de colegio');
    }

    public function test_the_language_is_required_and_validated(): void
    {
        $this->getJson('/api/v1/services')->assertStatus(422);
        $this->getJson('/api/v1/services?lang=klingon')->assertStatus(422);
    }

    /** `updated_at` es de lo servido: una sección que no se publica no puede fechar lo que sí. */
    public function test_updated_at_covers_what_is_served_and_nothing_else(): void
    {
        $this->getJson('/api/v1/services?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $this->seccion(['slug' => 'sin-titulo', 'title' => null]);
        $this->getJson('/api/v1/services?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $publicable = $this->seccion(['slug' => 'publicable']);
        $this->getJson('/api/v1/services?lang=es')
            ->assertOk()
            ->assertJsonPath('updated_at', $publicable->fresh()->updated_at->toIso8601String());
    }

    public function test_the_image_travels_as_an_absolute_url_or_not_at_all(): void
    {
        $this->seccion(['slug' => 'con-foto', 'image' => 'images/una.webp', 'position' => 1]);
        $this->seccion(['slug' => 'sin-foto', 'image' => '', 'position' => 2]);

        $cuerpo = $this->getJson('/api/v1/services?lang=es')->assertOk();

        $cuerpo->assertJsonPath('services.0.image_url', asset('images/una.webp'));
        $this->assertArrayNotHasKey('image_url', $cuerpo->json('services.1'));
    }

    public function test_it_is_publicly_cacheable_per_language(): void
    {
        $this->seccion();

        $respuesta = $this->getJson('/api/v1/services?lang=es')->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $respuesta->headers->get('Cache-Control'));

        $this->assertNotSame(
            (string) $respuesta->headers->get('ETag'),
            (string) $this->getJson('/api/v1/services?lang=en')->assertOk()->headers->get('ETag'),
            'las dos lenguas comparten `ETag`: una caché serviría una por la otra',
        );
    }
}
