<?php

namespace Tests\Feature\Instancia;

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
 * **El ANFITRIÓN MÍNIMO de `/bar`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #655`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`).
 *
 * ❗❗ Lo que conserva no es diseño, es la contrapartida de publicar la carta como IMAGEN: cada cara con
 * su `alt` (lo único que encuentra quien no la ve), sus dimensiones (sin ellas la página salta) y su
 * enlace al fichero (la única forma de leerla en un móvil); y la línea de alérgenos, que es obligación
 * legal. Sin carta, nada en su lugar; sin decisión sobre el acceso, ni una palabra.
 *
 * ⚠️ Es también el sujeto de los mutantes de vista de `mutar-bar.py`.
 */
class AnfitrionBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Setting::updateOrCreate(['key' => 'bar.name.es'], ['value' => 'El bar de prueba', 'group' => 'bar']);
        Cache::flush();
        Setting::flushMemo();
    }

    public function test_every_menu_sheet_has_its_alt_its_size_and_its_own_link_and_the_allergens_line(): void
    {
        $this->sheet(alt: 'Carta del bar: bocadillos y bebidas');

        $html = $this->html();
        $x = $this->xpath($html);

        $img = $x->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' bar-sheet ')]/img");
        $this->assertSame(1, $img->length, 'la cara de la carta no se pinta');
        $this->assertSame('Carta del bar: bocadillos y bebidas', $img->item(0)?->getAttribute('alt'), 'la carta se publica sin decir qué se ve en ella');
        $this->assertSame('40', $img->item(0)?->getAttribute('width'), 'la imagen no declara su ancho: la página saltará al cargarla');
        $this->assertSame('60', $img->item(0)?->getAttribute('height'), 'la imagen no declara su alto');

        $enlace = $x->query("//a[contains(concat(' ', normalize-space(@class), ' '), ' bar-sheet ')]");
        $this->assertSame(1, $enlace->length, 'la carta no es un enlace: en un móvil no hay forma de ampliarla');
        $this->assertStringContainsString('bar/', (string) $enlace->item(0)?->getAttribute('href'), 'el enlace no lleva al fichero de la carta');

        $this->assertStringContainsString((string) __('site.bar_allergens'), $html, 'la carta se publica sin decir dónde se preguntan los alérgenos');
    }

    /** Sin carta subida, la sección NO se pinta y no se pone nada en su lugar. */
    public function test_with_no_menu_uploaded_the_menu_section_is_not_painted(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('bar-sheet', $html, 'se pinta una carta que no existe');
        $this->assertStringNotContainsString((string) __('site.bar_menu_title'), $html, 'el rótulo de la carta se pinta sin carta');
    }

    /** Sin decidir en el panel no se escribe nada del acceso; decidido, se escribe la frase de ese estado. */
    public function test_the_free_entry_line_is_painted_only_when_decided(): void
    {
        $this->assertSame(0, $this->entryLines(), 'se dice algo del acceso sin que nadie lo haya decidido');

        Setting::updateOrCreate(['key' => 'bar.free_entry'], ['value' => 'no', 'group' => 'bar']);
        Cache::flush();
        Setting::flushMemo();

        $this->assertSame(1, $this->entryLines());
        $this->assertStringContainsString((string) __('site.bar_free_entry_no'), $this->html());
    }

    private function sheet(string $alt): BarImage
    {
        $im = imagecreatetruecolor(40, 60);
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        Storage::disk(BarImage::IMAGE_DISK)->put('bar/carta-'.md5($alt).'.png', $bytes);

        return BarImage::create([
            'kind' => BarImage::KIND_MENU,
            'image' => 'bar/carta-'.md5($alt).'.png',
            'alt' => ['es' => $alt],
            'position' => 0,
            'is_active' => true,
        ]);
    }

    private function entryLines(): int
    {
        return $this->xpath($this->html())
            ->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' bar-counter__entry ')]")
            ->length;
    }

    private function html(): string
    {
        return (string) $this->get('/bar')->assertOk()->assertViewIs('anfitrion.bar')->getContent();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
