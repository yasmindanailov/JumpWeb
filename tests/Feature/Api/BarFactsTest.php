<?php

namespace Tests\Feature\Api;

use App\Domain\Content\Models\BarImage;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * **EL BAR del menú de hechos** (F5, `docs/specs/instancia-y-landing-fuera.md` §4.1).
 *
 * Lo que se vigila: que **el nombre gatee la página entera**, que `free_entry` llegue como booleano y no
 * como el vocabulario del panel, que las **dimensiones** viajen —son lo que evita el salto de la página—,
 * que una carta retirada no vuelva, y que `updated_at` mire **las dos fuentes** del bar.
 */
class BarFactsTest extends TestCase
{
    use RefreshDatabase;

    private function ajuste(string $clave, string $valor): void
    {
        Setting::query()->updateOrCreate(['key' => $clave], ['value' => $valor]);
    }

    private function publicado(): void
    {
        $this->ajuste('bar.name.es', 'Cafetería');
    }

    /**
     * ⚠️⚠️ **El fichero se pone en el disco ANTES de crear la fila, y las dimensiones NO se teclean.**
     * `BarImage` las mide contra el disco en su `saving()`, así que un `width` escrito a mano se
     * sobreescribe con `null` y el caso que lo comprobara no tendría sujeto — lo descubrió este test
     * fallando, y su hermano `BarPageTest` ya lo tenía escrito. Se genera un PNG real con GD.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function imagen(string $tipo, array $atributos = [], int $ancho = 1240, int $alto = 1754): BarImage
    {
        $ruta = (string) ($atributos['image'] ?? 'bar/'.$tipo.'-'.BarImage::query()->count().'.jpg');

        $im = imagecreatetruecolor($ancho, $alto);
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        Storage::disk(BarImage::IMAGE_DISK)->put($ruta, $bytes);

        return BarImage::query()->create([
            'kind' => $tipo,
            'image' => $ruta,
            'alt' => ['es' => 'Una imagen'],
            'is_active' => true,
            'position' => 0,
            ...$atributos,
        ]);
    }

    /**
     * **El NOMBRE gatea la página entera.** Sin él la clave `bar` no viaja: es como una instalación sin
     * bar dice que no tiene bar, sin emitir un sobre vacío que cada landing tendría que distinguir.
     */
    public function test_without_a_name_the_bar_key_does_not_travel(): void
    {
        $this->imagen(BarImage::KIND_MENU);

        $cuerpo = $this->getJson('/api/v1/bar?lang=es')->assertOk();

        $cuerpo->assertJsonPath('lang', 'es');
        $this->assertArrayNotHasKey('bar', $cuerpo->json());

        $this->publicado();

        $this->getJson('/api/v1/bar?lang=es')->assertOk()->assertJsonPath('bar.name', 'Cafetería');
    }

    /**
     * **`free_entry` es un BOOLEANO.** En la tabla es la cadena `yes`/`no` —vocabulario del panel—, y un
     * cliente no tiene por qué aprendérselo. Sin configurar, la clave falta: no se afirma ni que sí ni
     * que no.
     */
    public function test_free_entry_arrives_as_a_boolean_or_not_at_all(): void
    {
        $this->publicado();

        $bar = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar');
        $this->assertArrayNotHasKey('free_entry', $bar);

        $this->ajuste('bar.free_entry', 'yes');
        $this->getJson('/api/v1/bar?lang=es')->assertOk()->assertJsonPath('bar.free_entry', true);

        $this->ajuste('bar.free_entry', 'no');
        $this->getJson('/api/v1/bar?lang=es')->assertOk()->assertJsonPath('bar.free_entry', false);

        // Un valor que el dominio no reconoce no se traduce a `false`: se calla.
        $this->ajuste('bar.free_entry', 'puede');
        $bar = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar');
        $this->assertArrayNotHasKey('free_entry', $bar);
    }

    /**
     * **Las DIMENSIONES son la mitad del valor de este plato**: sin ellas el navegador no puede reservar
     * el hueco y la página salta al cargar la carta, que es la imagen más grande de la web.
     */
    public function test_an_image_travels_with_its_measured_dimensions(): void
    {
        $this->publicado();
        $imagen = $this->imagen(BarImage::KIND_MENU, ['image' => 'bar/carta.jpg'], ancho: 620, alto: 877);

        // El CONTROL: la fila trae lo que el modelo midió del fichero, no lo que tecleó el test.
        $this->assertSame(620, $imagen->fresh()->width, 'el modelo no midió: el caso no tendría sujeto');

        $carta = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar.menu.0');

        $this->assertSame(asset('uploads/bar/carta.jpg'), $carta['url']);
        $this->assertSame(620, $carta['width']);
        $this->assertSame(877, $carta['height']);
    }

    /**
     * **Una imagen sin `alt` SÍ viaja**, y aquí la regla se aparta a propósito de la de `#671`: una duda
     * sin respuesta no publica nada útil, pero una carta sin texto alternativo **sigue siendo la carta**.
     * Esconderla no arregla la accesibilidad, quita el menú.
     */
    public function test_an_image_without_alt_still_travels_because_the_content_is_the_image(): void
    {
        $this->publicado();
        $this->imagen(BarImage::KIND_MENU, ['alt' => ['es' => '']]);

        $carta = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar.menu.0');

        $this->assertArrayNotHasKey('alt', $carta);
        $this->assertArrayHasKey('url', $carta);
    }

    public function test_the_menu_keeps_the_panel_order_and_hides_what_is_retired(): void
    {
        $this->publicado();
        $this->imagen(BarImage::KIND_MENU, ['image' => 'bar/segunda.jpg', 'position' => 20]);
        $this->imagen(BarImage::KIND_MENU, ['image' => 'bar/primera.jpg', 'position' => 10]);
        $this->imagen(BarImage::KIND_MENU, ['image' => 'bar/retirada.jpg', 'position' => 1, 'is_active' => false]);

        $urls = array_column($this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar.menu'), 'url');

        $this->assertSame([asset('uploads/bar/primera.jpg'), asset('uploads/bar/segunda.jpg')], $urls);
    }

    /** El pie va DENTRO de la foto del local: sin foto no hay nada que pie. */
    public function test_the_caption_lives_inside_the_venue_photo(): void
    {
        $this->publicado();
        $this->ajuste('bar.photo_caption.es', 'La cafetería del parque');

        $bar = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar');
        $this->assertArrayNotHasKey('venue', $bar);
        $this->assertArrayNotHasKey('caption', $bar);

        $this->imagen(BarImage::KIND_VENUE, ['image' => 'bar/mesas.jpg']);

        $this->getJson('/api/v1/bar?lang=es')
            ->assertOk()
            ->assertJsonPath('bar.venue.caption', 'La cafetería del parque')
            ->assertJsonPath('bar.venue.url', asset('uploads/bar/mesas.jpg'));
    }

    /** La carta y el local son tipos distintos: una cara de la carta no puede salir como foto del local. */
    public function test_the_menu_and_the_venue_do_not_mix(): void
    {
        $this->publicado();
        $this->imagen(BarImage::KIND_MENU, ['image' => 'bar/carta.jpg']);
        $this->imagen(BarImage::KIND_VENUE, ['image' => 'bar/mesas.jpg']);

        $bar = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('bar');

        $this->assertSame([asset('uploads/bar/carta.jpg')], array_column($bar['menu'], 'url'));
        $this->assertSame(asset('uploads/bar/mesas.jpg'), $bar['venue']['url']);
    }

    /**
     * ❗❗ **`updated_at` mira LAS DOS FUENTES o miente.** El bar vive mitad en `settings` y mitad en
     * `bar_images`: una fecha calculada sobre una sola diría «sin cambios» justo después de que alguien
     * reescribiera la otra, y quien la use para decidir si su copia sigue valiendo se quedaría la vieja.
     */
    public function test_updated_at_looks_at_both_sources_of_the_bar(): void
    {
        $this->getJson('/api/v1/bar?lang=es')->assertOk()->assertJsonPath('updated_at', null);

        $this->publicado();
        $soloAjustes = $this->getJson('/api/v1/bar?lang=es')->assertOk()->json('updated_at');
        $this->assertNotNull($soloAjustes);

        // Una imagen NUEVA y más reciente tiene que mover la fecha: si solo se mirasen los ajustes,
        // este caso saldría verde con la fecha de antes.
        $this->travel(1)->hour();
        $imagen = $this->imagen(BarImage::KIND_MENU);

        $this->getJson('/api/v1/bar?lang=es')
            ->assertOk()
            ->assertJsonPath('updated_at', $imagen->fresh()->updated_at->toIso8601String());

        $this->assertNotSame($soloAjustes, $imagen->fresh()->updated_at->toIso8601String());
    }

    /** Una carta RETIRADA sigue contando para la fecha: menos cartas también es un cambio del bar. */
    public function test_a_retired_image_still_counts_for_the_date(): void
    {
        $this->publicado();
        $this->travel(1)->hour();
        $imagen = $this->imagen(BarImage::KIND_MENU, ['is_active' => false]);

        $this->getJson('/api/v1/bar?lang=es')
            ->assertOk()
            ->assertJsonPath('updated_at', $imagen->fresh()->updated_at->toIso8601String())
            ->assertJsonPath('bar.menu', []);
    }

    public function test_the_text_falls_back_to_spanish(): void
    {
        $this->publicado();
        $this->ajuste('bar.lede.es', 'Café y algo de picar');

        $this->getJson('/api/v1/bar?lang=en')
            ->assertOk()
            ->assertJsonPath('bar.name', 'Cafetería')
            ->assertJsonPath('bar.lede', 'Café y algo de picar');
    }

    public function test_the_language_is_required_and_validated(): void
    {
        $this->getJson('/api/v1/bar')->assertStatus(422);
        $this->getJson('/api/v1/bar?lang=klingon')->assertStatus(422);
    }

    public function test_it_is_publicly_cacheable_per_language(): void
    {
        $this->publicado();
        $this->ajuste('bar.name.en', 'Cafe');

        $respuesta = $this->getJson('/api/v1/bar?lang=es')->assertOk();

        $this->assertStringContainsString('max-age=300', (string) $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $respuesta->headers->get('Cache-Control'));

        $this->assertNotSame(
            (string) $respuesta->headers->get('ETag'),
            (string) $this->getJson('/api/v1/bar?lang=en')->assertOk()->headers->get('ETag'),
            'las dos lenguas comparten `ETag`: una caché serviría una por la otra',
        );
    }
}
