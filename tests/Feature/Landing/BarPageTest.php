<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Models\BarImage;
use App\Domain\Content\Services\BarPage;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **`/bar`: la CONDUCTA del producto** (`DECISIONES #536`; partida por lo que afirma en F5 · T2b, `#655`).
 *
 * ❗❗ **Aquí no se lee el HTML de la landing** (`#649`): se afirma sobre lo que `BarPage` publica y sobre
 * lo que hace la ruta. Hasta la mudanza estos casos leían el marcado de la página de PlayJump, que ya no
 * está en el producto; lo que ese marcado garantizaba —la carta como imagen con su `alt`, su tamaño y su
 * enlace, la línea de alérgenos, cero relleno de acción— está en la doc de la instancia (`paginas/bar.md`)
 * y, para el respaldo del producto, en `AnfitrionBarTest`.
 *
 * ❗❗ **Y la página no existe hasta que el panel dice cómo se llama el bar.** Sin nombre la ruta da 404
 * y el destino no se ofrece en ninguna superficie: *falla hacia invisible*.
 */
class BarPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        Setting::flushMemo();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Sin nombre, el bar no existe
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **404 sin nombre, y el destino no se ofrece en NINGUNA superficie.** Las dos mitades van
     * juntas a propósito: un 404 al que se llega desde el menú es un defecto; un 404 al que no lleva
     * ningún enlace es una página que todavía no existe.
     */
    public function test_without_a_name_the_bar_is_a_404_and_nobody_links_to_it(): void
    {
        $this->get('/bar')->assertNotFound();

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('bar'), $home, 'la portada, el menú o el pie ofrecen un destino que responde 404');
        $this->assertStringNotContainsString('bar-door', $home, 'la tarjeta del bar se pinta sin bar publicado');
    }

    /** Con nombre: la página responde con su nombre y el destino aparece en el menú, en el pie y en la portada. */
    public function test_with_a_name_the_page_answers_and_the_destination_shows_up(): void
    {
        $this->publish();

        $this->get('/bar')->assertOk()->assertViewHas('barName', 'El bar de prueba');

        $home = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(route('bar'), $home, 'el destino del bar no aparece en la portada');
        $this->assertStringContainsString('bar-door', $home, 'la tarjeta del bar no vuelve a la sección 03');
    }

    /** El interruptor de mantenimiento alcanza a `/bar` como a las demás páginas del inventario. */
    public function test_the_bar_can_be_put_under_maintenance(): void
    {
        $this->publish();
        Setting::updateOrCreate(['key' => 'maintenance.page.bar'], ['value' => '1', 'group' => 'maintenance']);
        Cache::flush();
        Setting::flushMemo();

        $this->get('/bar')->assertStatus(503);
        $this->assertStringNotContainsString(route('bar'), $this->get('/')->assertOk()->getContent(), 'una página en mantenimiento se sigue ofreciendo');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La carta: lo que `BarPage` publica
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Una cara desactivada deja de publicarse sin borrarla. */
    public function test_an_inactive_sheet_is_not_published(): void
    {
        $this->sheet(alt: 'Cara A');
        $this->sheet(alt: 'Cara B', active: false, position: 1);

        $this->assertSame(['Cara A'], $this->alts(BarPage::menu()), 'una cara desactivada se sigue publicando');
    }

    /**
     * ⚠️ **Un `kind` que el producto no declara NO se publica.** Si alguien mete `promo` por SQL, la
     * fila se ignora en vez de aparecer en un sitio que nadie ha diseñado.
     */
    public function test_an_unknown_kind_is_not_published(): void
    {
        $img = $this->sheet(alt: 'Ni carta ni foto');
        $img->forceFill(['kind' => 'promo'])->saveQuietly();

        $this->assertNotContains('Ni carta ni foto', $this->alts(BarPage::menu()), 'una fila de tipo desconocido se publica como carta');
        $this->assertNull(BarPage::venuePhoto(), 'una fila de tipo desconocido se publica como foto del local');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La foto del local y el acceso
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **De la foto del local se publica UNA, la primera por orden.** No se impone unicidad en el
     * esquema porque eso obligaría a borrar la vieja antes de subir la nueva —justo cuando un parque
     * se queda sin foto—; la resolución es la misma que `#479` dio a dos tarifas destacadas.
     */
    public function test_only_the_first_venue_photo_is_published(): void
    {
        $this->venue(alt: 'La segunda', position: 5);
        $this->venue(alt: 'La primera', position: 0);

        $this->assertSame('La primera', BarPage::venuePhoto()?->tr('alt'), 'se publica otra foto del local: manda la primera por orden');
    }

    /**
     * ❗ **Tres estados, no dos.** Sin decidir en el panel no se dice nada del acceso: afirmar «hace
     * falta entrada» sin que nadie lo haya decidido sería peor que callar. `null` es un estado.
     */
    public function test_the_free_entry_line_has_three_states(): void
    {
        $this->assertNull(BarPage::freeEntry(), 'se dice algo del acceso sin que nadie lo haya decidido');

        foreach (['yes', 'no'] as $valor) {
            Setting::updateOrCreate(['key' => 'bar.free_entry'], ['value' => $valor, 'group' => 'bar']);
            Cache::flush();
            Setting::flushMemo();

            $this->assertSame($valor, BarPage::freeEntry(), "el acceso «{$valor}» no se publica");
        }
    }

    /**
     * ⚠️ **Un valor que el producto no conoce no publica nada.** `bar.free_entry` se escribe desde un
     * desplegable, pero el ajuste se puede fijar por consola o por SQL: sin esta guarda, un «quizá»
     * acabaría pintando `site.bar_free_entry_quizá` en la página. Lo destapó el arnés.
     */
    public function test_an_unknown_free_entry_value_says_nothing(): void
    {
        Setting::updateOrCreate(['key' => 'bar.free_entry'], ['value' => 'quizá', 'group' => 'bar']);
        Cache::flush();
        Setting::flushMemo();

        $this->assertNull(BarPage::freeEntry(), 'un valor desconocido publica una línea de acceso');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El dato: dimensiones y limpieza
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Las dimensiones se MIDEN al subir: nadie las teclea, y sin ellas la página salta. */
    public function test_dimensions_are_measured_on_upload(): void
    {
        $img = $this->sheet();

        $this->assertSame(40, $img->width);
        $this->assertSame(60, $img->height);
    }

    /**
     * **Limpieza de huérfanos**: al reemplazar la imagen se borra el fichero viejo y al borrar la
     * fila se borra el suyo. `FileUpload` no lo hace solo y los ficheros se acumulan en silencio.
     */
    public function test_replacing_and_deleting_clean_up_the_file(): void
    {
        $img = $this->sheet();
        $viejo = (string) $img->image;
        $this->assertTrue(Storage::disk(BarImage::IMAGE_DISK)->exists($viejo));

        $nuevo = $this->putFile('bar/otra.png');
        $img->update(['image' => $nuevo]);
        $this->assertFalse(Storage::disk(BarImage::IMAGE_DISK)->exists($viejo), 'el fichero reemplazado se queda huérfano en el disco');

        $img->delete();
        $this->assertFalse(Storage::disk(BarImage::IMAGE_DISK)->exists($nuevo), 'el fichero de una fila borrada se queda huérfano');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    /** Publica el bar: lo único que hace falta es el nombre. */
    private function publish(): void
    {
        Setting::updateOrCreate(['key' => 'bar.name.es'], ['value' => 'El bar de prueba', 'group' => 'bar']);
        Cache::flush();
        Setting::flushMemo();
    }

    /** @return list<string> */
    private function alts(iterable $imagenes): array
    {
        return collect($imagenes)->map(fn (BarImage $i): string => (string) $i->tr('alt'))->values()->all();
    }

    private function sheet(string $alt = 'La carta', bool $active = true, int $position = 0): BarImage
    {
        return BarImage::create([
            'kind' => BarImage::KIND_MENU,
            'image' => $this->putFile('bar/carta-'.$position.'-'.md5($alt).'.png'),
            'alt' => ['es' => $alt],
            'position' => $position,
            'is_active' => $active,
        ]);
    }

    private function venue(string $alt = 'Las mesas', int $position = 0): BarImage
    {
        return BarImage::create([
            'kind' => BarImage::KIND_VENUE,
            'image' => $this->putFile('bar/local-'.$position.'-'.md5($alt).'.png'),
            'alt' => ['es' => $alt],
            'position' => $position,
            'is_active' => true,
        ]);
    }

    /**
     * Escribe un PNG REAL de 40×60 en el disco de subidas.
     *
     * ⚠️ **Un fichero de mentira no vale aquí**: el modelo MIDE la imagen al guardar, así que con
     * bytes inventados las dimensiones saldrían `null` y el caso que las comprueba no tendría
     * sujeto. Se genera con GD, que es la misma extensión que el resto del producto ya exige.
     */
    private function putFile(string $path): string
    {
        $im = imagecreatetruecolor(40, 60);
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        Storage::disk(BarImage::IMAGE_DISK)->put($path, $bytes);

        return $path;
    }
}
