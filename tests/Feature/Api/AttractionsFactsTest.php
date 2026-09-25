<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **LOS JUEGOS del menú de hechos** (F5, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila: que el gate de la zona sea **`show_in_landing` y no `is_active`** —son cosas
 * distintas y confundirlas publica una zona retirada—, que el orden sea el de la web y no baraje, que
 * **el mosaico no se cuele** como si fuera un hecho, y que los dos campos sin consumidor público no se
 * conviertan en contrato.
 */
class AttractionsFactsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function zona(array $atributos = []): Zone
    {
        return Zone::create([
            'slug' => 'zona-'.Zone::query()->count(),
            'name' => ['es' => 'Una zona'],
            'is_active' => true,
            'show_in_landing' => true,
            'position' => 1,
            ...$atributos,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function juego(Zone $zona, array $atributos = []): Attraction
    {
        return Attraction::query()->create([
            'zone_id' => $zona->id,
            'name' => ['es' => 'Un juego'],
            'is_active' => true,
            'position' => 1,
            ...$atributos,
        ]);
    }

    /**
     * @return list<string>
     */
    private function nombres(string $lang = 'es'): array
    {
        return array_column(
            $this->getJson("/api/v1/attractions?lang={$lang}")->assertOk()->json('attractions'),
            'name',
        );
    }

    /**
     * ❗❗ **EL GATE ES `show_in_landing`, NO `is_active`.** La zona de cumpleaños **OPERA** —vende
     * packs— y no sale en la web: se desacoplaron justo por eso. Gatear por `is_active` publicaría las
     * atracciones de una zona que el negocio retiró de su landing.
     */
    public function test_the_zone_gate_is_show_in_landing_and_not_is_active(): void
    {
        $enLaWeb = $this->zona(['slug' => 'jump', 'position' => 1]);
        // Opera (vende) pero NO sale en la web: el caso exacto de la zona de cumpleaños.
        $operaSinSalir = $this->zona(['slug' => 'cumpleanos', 'is_active' => true, 'show_in_landing' => false, 'position' => 2]);

        $this->juego($enLaWeb, ['name' => ['es' => 'Camas elásticas']]);
        $this->juego($operaSinSalir, ['name' => ['es' => 'Sala de fiestas']]);

        $this->assertSame(['Camas elásticas'], $this->nombres());
    }

    /** El orden es el de la web: zona por su posición, y dentro cada juego por la suya. */
    public function test_the_order_is_the_one_of_the_web_zone_then_attraction(): void
    {
        $segunda = $this->zona(['slug' => 'kids', 'position' => 2]);
        $primera = $this->zona(['slug' => 'jump', 'position' => 1]);

        $this->juego($segunda, ['name' => ['es' => 'Kids · dos'], 'position' => 2]);
        $this->juego($segunda, ['name' => ['es' => 'Kids · uno'], 'position' => 1]);
        $this->juego($primera, ['name' => ['es' => 'Jump · uno'], 'position' => 1]);

        $this->assertSame(['Jump · uno', 'Kids · uno', 'Kids · dos'], $this->nombres());
    }

    /** `position` no es única: sin desempate por `id`, dos peticiones idénticas podrían barajar. */
    public function test_a_tie_in_position_is_broken_by_id_and_does_not_shuffle(): void
    {
        $zona = $this->zona();
        $primero = $this->juego($zona, ['name' => ['es' => 'Nació antes'], 'position' => 5]);
        $segundo = $this->juego($zona, ['name' => ['es' => 'Nació después'], 'position' => 5]);

        $this->assertTrue($primero->id < $segundo->id, 'el fixture no construye el empate que dice medir');

        $this->assertSame(['Nació antes', 'Nació después'], $this->nombres());
        $this->assertSame(['Nació antes', 'Nació después'], $this->nombres());
    }

    public function test_a_deactivated_attraction_does_not_come_back_through_the_api(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Vigente']]);
        $this->juego($zona, ['name' => ['es' => 'Retirado'], 'is_active' => false]);

        $this->assertSame(['Vigente'], $this->nombres());
    }

    /**
     * **La lista va PLANA y con el `slug` de su zona.** Agrupar es estructura y el menú no la impone; el
     * orden entregado ya es el de la web, así que agrupar por `zone` lo conserva sin ordenar nada.
     * ▶ Y `zone` es una REFERENCIA: el nombre y la foto de la zona viven en `/catalog/zones`.
     */
    public function test_each_attraction_carries_its_zone_slug_and_the_list_is_flat(): void
    {
        $jump = $this->zona(['slug' => 'jump', 'position' => 1]);
        $this->juego($jump, ['name' => ['es' => 'Camas elásticas']]);

        $cuerpo = $this->getJson('/api/v1/attractions?lang=es')->assertOk();

        $cuerpo->assertJsonPath('attractions.0.zone', 'jump');
        // Plana: la raíz es una lista de juegos, no un mapa de zonas.
        $this->assertIsList($cuerpo->json('attractions'));
        // Y NO se copia la zona: su nombre vive en `/catalog/zones`.
        $this->assertStringNotContainsString('Una zona', (string) $cuerpo->getContent());
    }

    /**
     * ❗❗ **EL MOSAICO NO VIAJA.** `RideMosaic` elige tres con nombre y dos veladas y las coloca en un
     * patrón de celdas: eso es MAQUETA —cuántas caben y cuáles se disuelven—, no un dato del negocio.
     * Publicarlo obligaría a toda landing a heredar el mosaico de ésta.
     */
    public function test_the_mosaic_is_layout_and_never_travels(): void
    {
        $zona = $this->zona();
        foreach (range(1, 8) as $i) {
            $this->juego($zona, ['name' => ['es' => "Juego {$i}"], 'position' => $i]);
        }

        $cuerpo = $this->getJson('/api/v1/attractions?lang=es')->assertOk();

        // Los OCHO viajan: la API no recorta a las cinco del mosaico ni marca ninguna como «velada».
        $this->assertCount(8, $cuerpo->json('attractions'));
        foreach (['veiled', 'velada', 'cell', 'celda', 'mosaic'] as $palabra) {
            $this->assertStringNotContainsString($palabra, (string) $cuerpo->getContent());
        }
    }

    /**
     * **Los dos campos sin consumidor público no viajan.** `is_special` solo lo pinta la tabla del panel
     * y `ticket_type_id` quedó a 0 de 23 cuando `#668` retiró el complemento por atracción. Estar en la
     * tabla no convierte a un campo en contrato público.
     */
    public function test_the_two_columns_without_a_public_consumer_never_travel(): void
    {
        $zona = $this->zona();
        // ⚠️ El nombre NO lleva la palabra que el caso busca: la primera versión llamó «Especial» a este
        // juego y la aserción se acusó a sí misma —«Especial» contiene «special»—. Es la trampa de
        // `#553`: una subcadena sobre el cuerpo acusa al fixture que la nombra.
        $juego = $this->juego($zona, ['name' => ['es' => 'Cama grande'], 'is_special' => true]);

        // CONTROL: la fila sí lo tiene, o el caso estaría comprobando una ausencia que nadie causó.
        $this->assertTrue($juego->fresh()->is_special, 'el fixture no activó la marca que dice medir');

        $servido = $this->getJson('/api/v1/attractions?lang=es')->assertOk()->json('attractions.0');

        // Se aserta sobre las CLAVES servidas, que mide más que buscar una subcadena: así también
        // caza un campo que viajara con otro nombre.
        $this->assertSame(['zone', 'name'], array_keys($servido));
    }

    public function test_an_attraction_without_a_name_does_not_travel(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Con nombre'], 'position' => 1]);
        $this->juego($zona, ['name' => ['es' => '  '], 'position' => 2]);

        $this->assertSame(['Con nombre'], $this->nombres());
    }

    public function test_what_is_not_written_does_not_travel(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Pelado'], 'description' => null, 'age' => null]);

        $juego = $this->getJson('/api/v1/attractions?lang=es')->assertOk()->json('attractions.0');

        $this->assertSame(['zone', 'name'], array_keys($juego));
    }

    public function test_the_image_travels_as_an_absolute_url(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Con foto'], 'image' => 'images/attractions/camas.webp']);

        $this->getJson('/api/v1/attractions?lang=es')
            ->assertOk()
            ->assertJsonPath('attractions.0.image_url', asset('images/attractions/camas.webp'));
    }

    /**
     * **El VÍDEO viaja como URL absoluta del hueco de la instalación, y sin él la clave FALTA** (1.30.0): es lo que
     * decide si la web pinta el «play». ⚠️ A diferencia de la foto, es una SUBIDA (`uploads/`), no una ruta de
     * `public/`: resolverlo como la foto daría una URL que no existe.
     */
    public function test_the_video_travels_from_the_uploads_and_is_absent_without_one(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Con vídeo'], 'image' => 'images/attractions/camas.webp', 'video' => 'atracciones/camas.mp4', 'position' => 1]);
        $this->juego($zona, ['name' => ['es' => 'Sin vídeo'], 'image' => 'images/attractions/bolas.webp', 'position' => 2]);

        $juegos = $this->getJson('/api/v1/attractions?lang=es')->assertOk()->json('attractions');

        $this->assertSame(asset('uploads/atracciones/camas.mp4'), $juegos[0]['video_url']);
        $this->assertArrayNotHasKey('video_url', $juegos[1]);
    }

    /** Cambiar o borrar el vídeo en el panel no deja el viejo en el disco: un vídeo pesa lo que cien fotos. */
    public function test_replacing_or_deleting_the_video_removes_the_old_file(): void
    {
        Storage::fake(Attraction::VIDEO_DISK);
        $disco = Storage::disk(Attraction::VIDEO_DISK);
        $disco->put('atracciones/viejo.mp4', 'v');
        $disco->put('atracciones/nuevo.mp4', 'n');
        $juego = $this->juego($this->zona(), ['video' => 'atracciones/viejo.mp4']);

        $juego->update(['video' => 'atracciones/nuevo.mp4']);
        $this->assertFalse($disco->exists('atracciones/viejo.mp4'));

        $juego->delete();
        $this->assertFalse($disco->exists('atracciones/nuevo.mp4'));
    }

    /** El mismo respaldo que en el resto del menú: escrito solo en español, se sirve en inglés (`#671`). */
    public function test_an_attraction_written_only_in_spanish_still_travels_in_english(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Camas elásticas'], 'age' => ['es' => 'Desde 6 años']]);

        $this->getJson('/api/v1/attractions?lang=en')
            ->assertOk()
            ->assertJsonPath('attractions.0.name', 'Camas elásticas')
            ->assertJsonPath('attractions.0.age', 'Desde 6 años');
    }

    public function test_the_language_is_required_and_validated(): void
    {
        $this->getJson('/api/v1/attractions')->assertStatus(422);
        $this->getJson('/api/v1/attractions?lang=klingon')->assertStatus(422);
    }

    public function test_updated_at_covers_what_is_served_and_nothing_else(): void
    {
        $this->getJson('/api/v1/attractions?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => '  ']]);
        $this->getJson('/api/v1/attractions?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $publicable = $this->juego($zona, ['name' => ['es' => 'Publicable']]);
        $this->getJson('/api/v1/attractions?lang=es')
            ->assertOk()
            ->assertJsonPath('updated_at', $publicable->fresh()->updated_at->toIso8601String());
    }

    public function test_it_is_publicly_cacheable_per_language(): void
    {
        $zona = $this->zona();
        $this->juego($zona, ['name' => ['es' => 'Camas', 'en' => 'Trampolines']]);

        $respuesta = $this->getJson('/api/v1/attractions?lang=es')->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $respuesta->headers->get('Cache-Control'));

        $this->assertNotSame(
            (string) $respuesta->headers->get('ETag'),
            (string) $this->getJson('/api/v1/attractions?lang=en')->assertOk()->headers->get('ETag'),
            'las dos lenguas comparten `ETag`: una caché serviría una por la otra',
        );
    }
}
