<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Models\BarImage;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **`/bar`: LA CARTA CORTA, Y NADA MÁS** (`DECISIONES #536`, carril de diseño Fase 3 · T3b,
 * artboard `Bar PJP` 1a/1b).
 *
 * ❗❗❗ **LA CARTA SE PUBLICA COMO IMAGEN** (`[DECIDIDO owner, 2026-09-12]`), no tecleando los platos
 * como dibuja el artboard. Eso trae tres propiedades que **ninguna tabla de platos habría
 * necesitado** y que esta guarda existe para sostener: cada imagen lleva su `alt` —lo único que un
 * lector de pantalla o un buscador van a encontrar—, se pueden subir varias, y cada una es un
 * ENLACE a su fichero para poder ampliarla en un teléfono.
 *
 * ❗❗ **Y la página no existe hasta que el panel dice cómo se llama el bar.** El titular es su
 * nombre; sin él la ruta da 404 y el destino no se ofrece en ninguna superficie. *Falla hacia
 * invisible*: nadie llega navegando a ese 404.
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
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Guarda de la guarda.** Media docena de casos de abajo comprueban AUSENCIAS —que no haya
     * carta, que no haya destino, que no se diga nada del acceso—, y comprobar que algo no está es
     * la forma más fácil de escribir un test que no mira nada.
     */
    public function test_the_scan_reads_a_published_bar_with_its_pieces(): void
    {
        $this->publish();
        $this->sheet();

        $html = $this->html();

        $this->assertStringContainsString('page--bar', $html, 'la página no se sirve o cambió de clase raíz');
        foreach (['bar-sheet', 'bar-counter', 'bar-menu__allergens'] as $pieza) {
            $this->assertStringContainsString($pieza, $html, "falta `{$pieza}`: el localizador se quedó sin sujeto");
        }
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

    /** Con nombre: la página responde y el destino aparece en el menú, en el pie y en la portada. */
    public function test_with_a_name_the_page_answers_and_the_destination_shows_up(): void
    {
        $this->publish();

        $this->get('/bar')->assertOk()->assertSee('El bar de prueba');

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
    //  La carta
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **CADA CARA DE LA CARTA LLEVA SU `alt` Y ES UN ENLACE A SU FICHERO.** Las dos cosas son
     * la contrapartida de publicar la carta como imagen: el `alt` es lo único que encuentra quien no
     * la ve, y el enlace es la única forma de leerla en un móvil sin un visor que mantener.
     * ⚠️ Y **declara sus dimensiones**: sin ellas la página salta al cargar la imagen más grande del
     * sitio.
     */
    public function test_every_menu_sheet_has_its_alt_its_size_and_its_own_link(): void
    {
        $this->publish();
        $this->sheet(alt: 'Carta del bar: bocadillos y bebidas');

        $img = $this->xpath()->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' bar-sheet ')]/img");
        $this->assertSame(1, $img->length, 'la cara de la carta no se pinta');

        $this->assertSame('Carta del bar: bocadillos y bebidas', $img->item(0)?->getAttribute('alt'), 'la carta se publica sin decir qué se ve en ella');
        $this->assertSame('40', $img->item(0)?->getAttribute('width'), 'la imagen no declara su ancho: la página saltará al cargarla');
        $this->assertSame('60', $img->item(0)?->getAttribute('height'), 'la imagen no declara su alto');

        $enlace = $this->xpath()->query("//a[contains(concat(' ', normalize-space(@class), ' '), ' bar-sheet ')]");
        $this->assertSame(1, $enlace->length, 'la carta no es un enlace: en un móvil no hay forma de ampliarla');
        $this->assertStringContainsString('bar/', (string) $enlace->item(0)?->getAttribute('href'), 'el enlace no lleva al fichero de la carta');
    }

    /**
     * **Sin carta subida, la sección de la carta NO se pinta — y no se pone nada en su lugar.**
     * Una disculpa en una página pública («la carta estará pronto») es peor que un hueco: lo que
     * falta lo dice el panel, no la web.
     */
    public function test_with_no_menu_uploaded_the_menu_section_is_not_painted(): void
    {
        $this->publish();

        $html = $this->html();
        $this->assertStringNotContainsString('bar-sheet', $html, 'se pinta una carta que no existe');
        $this->assertStringNotContainsString((string) __('site.bar_menu_title'), $html, 'el rótulo de la carta se pinta sin carta');
    }

    /** Una cara desactivada deja de publicarse sin borrarla. */
    public function test_an_inactive_sheet_is_not_published(): void
    {
        $this->publish();
        $this->sheet(alt: 'Cara A');
        $this->sheet(alt: 'Cara B', active: false, position: 1);

        $html = $this->html();
        $this->assertStringContainsString('Cara A', $html);
        $this->assertStringNotContainsString('Cara B', $html, 'una cara desactivada se sigue publicando');
    }

    /**
     * ⚠️ **Un `kind` que el producto no declara NO se publica.** Si alguien mete `promo` por SQL, la
     * fila se ignora en vez de aparecer en un sitio que nadie ha diseñado.
     */
    public function test_an_unknown_kind_is_not_published(): void
    {
        $this->publish();
        $img = $this->sheet(alt: 'Ni carta ni foto');
        $img->forceFill(['kind' => 'promo'])->saveQuietly();

        $this->assertStringNotContainsString('Ni carta ni foto', $this->html(), 'una fila de tipo desconocido se publica igual');
    }

    /** La línea de alérgenos acompaña a la carta: es obligación legal decir dónde se pregunta. */
    public function test_the_allergens_line_travels_with_the_menu(): void
    {
        $this->publish();
        $this->sheet();

        $this->assertStringContainsString((string) __('site.bar_allergens'), $this->html(), 'la carta se publica sin decir dónde se preguntan los alérgenos');
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
        $this->publish();
        $this->venue(alt: 'La segunda', position: 5);
        $this->venue(alt: 'La primera', position: 0);

        $html = $this->html();
        $this->assertStringContainsString('La primera', $html);
        $this->assertStringNotContainsString('La segunda', $html, 'se publican dos fotos del local: manda la primera por orden');
    }

    /**
     * ❗ **Tres estados, no dos.** Sin decidir en el panel no se dice nada del acceso: afirmar «hace
     * falta entrada» sin que nadie lo haya decidido sería peor que callar.
     */
    public function test_the_free_entry_line_has_three_states(): void
    {
        $this->publish();

        $this->assertStringNotContainsString((string) __('site.bar_free_entry_yes'), $this->html());
        $this->assertStringNotContainsString((string) __('site.bar_free_entry_no'), $this->html());

        foreach (['yes', 'no'] as $valor) {
            Setting::updateOrCreate(['key' => 'bar.free_entry'], ['value' => $valor, 'group' => 'bar']);
            Cache::flush();
            Setting::flushMemo();
            $this->assertStringContainsString((string) __('site.bar_free_entry_'.$valor), $this->html(), "el acceso «{$valor}» no se publica");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El color
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **CERO RELLENO DE ACCIÓN EN LA PÁGINA, y aquí no es estética**: el bar está fuera del
     * modelo de reserva (`[DECIDIDO owner]`), así que nada suyo puede vestirse de compra. El único
     * naranja de la pantalla lo trae la barra del armazón, que no es de esta página.
     */
    public function test_the_page_has_no_action_fill_of_its_own(): void
    {
        $this->publish();
        $this->sheet();

        $main = $this->xpath()->query("//main[contains(concat(' ', normalize-space(@class), ' '), ' page--bar ')]");
        $this->assertSame(1, $main->length);

        $botones = $this->xpath()->query(
            "//main[contains(concat(' ', normalize-space(@class), ' '), ' page--bar ')]"
            ."//*[contains(concat(' ', normalize-space(@class), ' '), ' btn ')]",
        );
        $this->assertSame(0, $botones->length, 'la página del bar estrena un botón: aquí no se compra, se pide en la barra');
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

    private function html(): string
    {
        return $this->get('/bar')->assertOk()->getContent();
    }

    private function xpath(): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->html());
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
