<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\PriceTier;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GroupRateTables;
use App\Domain\Content\Models\LandingService;
use App\Domain\Platform\Services\Money;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **`/servicios`: la CONDUCTA del producto** (`#256`, `#588`; partida por lo que afirma en F5 · T2b, `#660`).
 *
 * ❗❗ **Aquí no se lee el HTML** (`#649`): el contrato con la landing de una instancia son los DATOS que
 * recibe la vista —`services`, `groupRates` (las tablas de grupo ya compuestas), `groupFrom` (el «desde»
 * ya escrito) y `birthdayCards`—, y sobre ellos se afirma. El marcado de la página de PlayJump ya no está
 * en el producto: lo que garantiza vive en `paginas/servicios.md` del paquete, y el anfitrión mínimo tiene
 * su guarda propia (`AnfitrionServiciosTest`).
 *
 * Lo que se rompería EN SILENCIO y esto sostiene:
 *  · **Lo comercial se lee EN VIVO de los productos** (`#588`): la tabla sale de sus tramos, así que un
 *    precio del panel no puede quedarse viejo en la web.
 *  · **El «desde» es el precio más bajo de TODAS sus tablas** (`#329`) y lo escribe el producto (`#660`).
 *  · **Un pack que vende un servicio sale del resumen de cumpleaños** y de su página: fuente única.
 */
class ServicesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    /** @return array<string, mixed> Lo que el controlador le pasa a la vista: el sujeto de esta guarda. */
    private function datos(): array
    {
        return $this->get('/servicios')->assertOk()->original->getData();
    }

    /** Las tablas de grupo de un servicio, por su slug. */
    private function tablas(string $slug): array
    {
        $datos = $this->datos();
        $servicio = $datos['services']->firstWhere('slug', $slug);

        return $servicio === null ? [] : ($datos['groupRates'][$servicio->id] ?? []);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Qué servicios viajan
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_only_active_services_travel_in_the_panels_order(): void
    {
        $esperado = LandingService::active()->ordered()->pluck('slug')->all();

        $this->assertNotEmpty($esperado, 'el caso nace sin sujeto: no hay servicios activos');
        $this->assertSame($esperado, $this->datos()['services']->pluck('slug')->all());

        LandingService::create(['slug' => 'oculto', 'title' => ['es' => 'Servicio oculto'], 'is_active' => false]);

        $this->assertNotContains('oculto', $this->datos()['services']->pluck('slug')->all(),
            'un servicio apagado en el panel sigue viajando a la vista');
    }

    /** Con cero servicios la página NO se queda sin datos: viaja la colección vacía y la vista degrada. */
    public function test_zero_services_travel_as_an_empty_collection(): void
    {
        LandingService::query()->delete();
        $datos = $this->datos();

        $this->assertTrue($datos['services']->isEmpty());
        $this->assertSame([], $datos['groupRates']);
        $this->assertSame([], $datos['groupFrom']);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo comercial, leído EN VIVO de los productos
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La tabla de un servicio sale de los TRAMOS de sus productos** (`#588`): una tabla por producto
     * comprable, una fila por tramo, y el precio que escribe el producto.
     */
    public function test_the_rate_tables_are_read_from_the_products_tiers(): void
    {
        $packs = TicketType::ofType(TicketType::TYPE_PACK)->orderBy('position')->get();
        $normal = RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();

        foreach ($packs as $pack) {
            // Tramos y familia de edades son excluyentes (`#329`): el fixture de cumpleaños la trae.
            $pack->update(['guest_age_family' => null, 'guest_age_min' => null, 'guest_age_max' => null, 'min_qty' => 30]);
            PriceTier::create(['ticket_type_id' => $pack->id, 'rate_type_id' => $normal->id, 'min_qty' => 30, 'amount_cents' => 1500]);
            PriceTier::create(['ticket_type_id' => $pack->id, 'rate_type_id' => $normal->id, 'min_qty' => 70, 'amount_cents' => 1300]);
        }

        LandingService::query()->delete();
        LandingService::create([
            'slug' => 'excursiones',
            'title' => ['es' => 'Excursiones'],
            'is_active' => true,
        ])->products()->attach($packs->pluck('id')->all());

        $tablas = $this->tablas('excursiones');

        $this->assertCount($packs->count(), $tablas, 'una tabla por producto comprable');
        $filas = collect($tablas)->flatMap(fn (array $t): array => $t['rows']);
        $this->assertContains(70, $filas->pluck('from')->all(), 'el tramo de 70 no viaja');
        $this->assertContains("13\u{00A0}€", $filas->pluck('normal')->all(), 'el precio del tramo no viaja escrito');
    }

    /**
     * ❗❗❗ **EL «DESDE» ES EL PRECIO MÁS BAJO DE TODAS SUS TABLAS, Y LO ESCRIBE EL PRODUCTO** (`#329`,
     * `#660`). Las dos mitades vivían en la vista: el mínimo se calculaba a mano y se escribía con
     * `Money::format()` —el registro de transacción—, así que en inglés la misma pantalla mezclaba
     * «from 14.95 €» con «12,00 €». Ahora sale ya escrito, en el registro de ESCAPARATE.
     *
     * ⚠️⚠️ **El caso SIEMBRA dos productos a precios distintos, y lo obligó el arnés**: los servicios del
     * seeder son solo-contacto —sin productos comprables no hay tablas, y `groupFrom` viaja en `null`—, así
     * que la primera versión de este caso se salía por su propia puerta de atrás y los tres mutantes del
     * «desde» sobrevivían. **Y el valor esperado se escribe a mano**: derivarlo de los mismos datos con
     * `min()` habría comparado el mutante consigo mismo.
     */
    public function test_the_from_price_is_the_lowest_of_its_tables_and_the_product_writes_it(): void
    {
        $normal = RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();
        $especial = RateType::where('key', RateType::KEY_SPECIAL)->firstOrFail();
        [$caro, $barato] = TicketType::ofType(TicketType::TYPE_PACK)->orderBy('position')->take(2)->get()->all();

        // ⚠️⚠️ El barato cuesta EUROS EXACTOS, y lo obligó el arnés: con céntimos, el registro de
        // escaparate y el de transacción escriben igual («18,50 €») y el mutante del formato sobrevive.
        foreach ([[$caro, 4000], [$barato, 1800]] as [$pack, $cents]) {
            $pack->update(['guest_age_family' => null, 'guest_age_min' => null, 'guest_age_max' => null, 'min_qty' => 10]);
            $pack->prices()->delete();
            $pack->prices()->create(['rate_type_id' => $normal->id, 'amount_cents' => $cents]);
        }
        $caro->prices()->create(['rate_type_id' => $especial->id, 'amount_cents' => 4500]);

        LandingService::query()->delete();
        LandingService::create(['slug' => 'excursiones', 'title' => ['es' => 'Excursiones'], 'is_active' => true])
            ->products()->attach([$caro->id, $barato->id]);

        $datos = $this->datos();
        $servicio = $datos['services']->firstWhere('slug', 'excursiones');

        $this->assertCount(2, $datos['groupRates'][$servicio->id], 'el caso nace sin sujeto: no hay dos tablas');
        // ⚠️ «18 €» con espacio DURO y con euro EXACTO, las dos cosas a propósito (`#660`, `#661`):
        // el euro exacto distingue el registro de escaparate del de transacción, y el espacio duro
        // distingue la regla del producto de un símbolo puesto a mano en una vista.
        $this->assertSame("18\u{00A0}€", $datos['groupFrom'][$servicio->id],
            'el «desde» no es el precio más bajo de sus tablas, escrito en el registro de escaparate');

        /*
         * ⚠️⚠️ **Y una tabla SIN precio publicado se salta, que es lo que su contrato declara**
         * (`lowest_cents: ?int`). Se llama al servicio DIRECTAMENTE porque por HTTP no se llega: un
         * producto sin precios no es comprable, así que no produce tabla. Sin este caso, quitar el filtro
         * no cambiaba nada y el mutante sobrevivía — el `null` de una tabla se llevaría por delante al
         * mínimo de las demás y la página anunciaría «desde» nada.
         */
        $this->assertSame("18\u{00A0}€", (new GroupRateTables)->lowestWritten([
            ['lowest_cents' => null],
            ['lowest_cents' => 1800],
        ]));

        // Y un servicio SIN tablas no anuncia ningún «desde».
        $suelto = LandingService::create(['slug' => 'solo-contacto', 'title' => ['es' => 'Solo contacto'], 'is_active' => true]);
        $this->assertNull($this->datos()['groupFrom'][$suelto->id], 'sin tablas viaja un «desde» inventado');
    }

    /**
     * **La foto de un servicio la resuelve el PRODUCTO** (`LandingService::imageUrl()`, `#660`): ruta
     * relativa a `public/` como la de zona y la de atracción, y `null` cuando no hay —un `asset('')` daría
     * la raíz del sitio con un roto dentro—.
     */
    public function test_the_service_photo_is_resolved_by_the_product(): void
    {
        $servicio = LandingService::first();
        $servicio->update(['image' => 'images/attractions/park_jump.webp']);

        $this->assertSame(asset('images/attractions/park_jump.webp'), $servicio->fresh()->imageUrl());
        $this->assertStringNotContainsString('uploads/', (string) $servicio->fresh()->imageUrl(),
            'la foto de un servicio no es una subida: no lleva `uploads/`');

        $servicio->update(['image' => null]);
        $this->assertNull($servicio->fresh()->imageUrl());

        $servicio->update(['image' => '  ']);
        $this->assertNull($servicio->fresh()->imageUrl(), 'una ruta en blanco contesta la raíz del sitio');
    }

    /**
     * **La página termina con los cumpleaños resumidos** (`#588`, `[DECIDIDO owner]`), y un pack que vende
     * un servicio sale de ese resumen Y de la página de cumpleaños: la fuente es única.
     */
    public function test_a_pack_that_sells_a_service_leaves_the_birthday_summary(): void
    {
        $jump = TicketType::where('name->es', 'Cumpleaños Jump')->firstOrFail();

        $this->assertCount(2, $this->datos()['birthdayCards'], 'el caso nace sin sujeto');

        LandingService::query()->delete();
        LandingService::create(['slug' => 'excursiones', 'title' => ['es' => 'Excursiones'], 'is_active' => true])
            ->products()->attach($jump->id);

        $this->assertCount(1, $this->datos()['birthdayCards']);
        $this->get('/cumpleanos')->assertOk()->assertDontSee('Cumpleaños Jump');
    }

    /**
     * **El precio de una tarjeta de cumpleaños lo escribe el PRODUCTO, con su símbolo** (`#661`).
     *
     * ❗❗ **Esta guarda nace porque no existía, y se notó al cambiar el dato**: `PartyCards::price`
     * pasó de la cifra pelada al importe con símbolo —sus tres consumidores lo pegaban a mano, uno de
     * ellos dentro del paquete de una instalación— y **la suite entera se quedó verde**. Un formato de
     * dinero que ninguna prueba mira es un formato que cualquiera puede cambiar sin enterarse.
     *
     * Afirma las tres cosas que distinguen esta escritura, y las tres hacen falta:
     *  · el **símbolo viene puesto** — si volviera a pegarse en la vista, cada instancia lo pondría a su aire;
     *  · el **espacio es DURO** — con el blando el importe se parte de renglón (el defecto de `#661`);
     *  · el **registro es de ESCAPARATE** — y por eso el euro es EXACTO: con 14,95 € el de transacción
     *    escribe igual y el caso no distinguiría nada (`#660`, lección 3).
     */
    public function test_the_birthday_card_price_is_written_by_the_product_with_its_symbol(): void
    {
        $kids = TicketType::where('name->es', 'Cumpleaños Kids')->firstOrFail();
        $normal = RateType::where('key', RateType::KEY_NORMAL)->firstOrFail();

        // ⚠️ Los DOS precios los pone el caso: apoyarse en la cifra del sembrador ataría esta guarda a
        // un dato que no vigila, y la primera versión de este caso ya se equivocó tomándola de la BD
        // de desarrollo —que no es la que siembra la suite—.
        $reprecio = function (int $cents) use ($kids, $normal): string {
            $kids->prices()->where('rate_type_id', $normal->id)->delete();
            $kids->prices()->create(['rate_type_id' => $normal->id, 'amount_cents' => $cents]);

            $tarjeta = collect($this->datos()['birthdayCards'])->firstWhere('id', $kids->id);
            $this->assertNotNull($tarjeta, 'el caso nace sin sujeto: la tarjeta del pack no viaja');

            return $tarjeta['price'];
        };

        // (a) Con céntimos: fija el separador del idioma y el espacio duro.
        $this->assertSame("14,95\u{00A0}€", $reprecio(1495));

        // (b) Con un euro EXACTO no se escriben los ceros: «18 €», no «18,00 €».
        $this->assertSame("18\u{00A0}€", $reprecio(1800));
    }

    /** La tabla TECLEADA del panel es un `array` y llega entera: es el respaldo de un servicio sin productos. */
    public function test_the_typed_price_table_is_cast_to_array(): void
    {
        $colegio = LandingService::where('slug', 'excursionescolegio')->first();

        $this->assertIsArray($colegio->price_table);
        // Kids · 2 h · L–V · 30 niños = 12,00 € (céntimos).
        $this->assertSame(1200, $colegio->price_table['zones'][0]['durations'][0]['tiers'][0]['weekday']);
        // Y un servicio sin tabla tecleada no se inventa una.
        $this->assertNull(LandingService::where('slug', 'sesionadultos')->value('price_table'));
    }
}
