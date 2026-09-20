<?php

namespace Tests\Feature\GoogleBusiness;

use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Services\GoogleReviewImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * **T2·4 · Las imágenes de una reseña: traerlas, guardarlas, servirlas y borrarlas**
 * (`docs/specs/google-business-profile.md` §4.3·6 y §4.3·9; `DECISIONES #524`, `#730`).
 *
 * ❗❗ Aquí se traen **bytes de un tercero**, se guardan y se sirven. Lo que fija este caso es que el
 * descargador no se pueda usar como puerta: ni para salir a otro host, ni para llenar el disco, ni
 * para colar algo que no sea una imagen.
 *
 * ⚠️ `Http::preventStrayRequests()` en `setUp()`: ningún caso habla con Google.
 */
class GoogleReviewImagesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://lh3.googleusercontent.com/a/ACg8ocK';

    /** Un PNG de 1×1 de verdad. */
    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n\x2d\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake(GoogleReviewImages::DISK);
    }

    private function images(): GoogleReviewImages
    {
        return app(GoogleReviewImages::class);
    }

    /**
     * ⚠️⚠️ **Con un CIERRE y no con un `Http::response()` suelto**, y no es estilo: un doble estático
     * es **un solo objeto reutilizado** en todas las peticiones que casen, y el descargador lee el
     * cuerpo **en flujo**. La primera lectura lo consume y la segunda llega vacía — medido el
     * 2026-09-21, con un caso que acusaba al código de no deduplicar. En producción cada respuesta
     * trae su flujo.
     */
    private function fakeBody(string $bytes, int $status = 200): void
    {
        Http::fake(['https://lh*.googleusercontent.com/*' => fn () => Http::response($bytes, $status)]);
    }

    private function disk()
    {
        return Storage::disk(GoogleReviewImages::DISK);
    }

    // ─────────── Lo que sí se trae ───────────

    public function test_una_imagen_buena_se_guarda_con_el_hash_por_nombre(): void
    {
        $this->fakeBody(self::PNG);

        $ruta = $this->images()->fetch(self::URL);

        $this->assertSame(hash('sha256', self::PNG).'.png', $ruta);
        $this->disk()->assertExists($ruta);
        $this->assertSame(self::PNG, $this->disk()->get($ruta));
    }

    public function test_el_nombre_no_lleva_nada_del_autor_ni_de_la_url(): void
    {
        $this->fakeBody(self::PNG);

        $ruta = $this->images()->fetch('https://lh3.googleusercontent.com/a/MARTA-RODRIGUEZ-1234');

        // §4.3·6: el nombre sale del CONTENIDO. Derivarlo del autor pondría su nombre en una URL
        // pública, que es exactamente lo que no puede pasar con un dato de un tercero.
        $this->assertStringNotContainsString('MARTA', $ruta);
        $this->assertTrue(GoogleReviewImages::isOwnName($ruta));
    }

    public function test_la_misma_imagen_no_se_guarda_dos_veces(): void
    {
        $this->fakeBody(self::PNG);

        $primera = $this->images()->fetch(self::URL);
        $segunda = $this->images()->fetch('https://lh4.googleusercontent.com/a/OTRA');

        // De regalo del hash por contenido: dos reseñas con la misma foto comparten fichero.
        $this->assertSame($primera, $segunda);
        $this->assertCount(1, $this->disk()->files());
    }

    public function test_no_quedan_ficheros_a_medio_escribir(): void
    {
        $this->fakeBody(self::PNG);

        $this->images()->fetch(self::URL);

        // La escritura es atómica: se escribe con nombre temporal y se renombra. Si quedaran restos,
        // el barrido de huérfanos se encontraría ficheros que no sabe de quién son.
        foreach ($this->disk()->files() as $fichero) {
            $this->assertTrue(GoogleReviewImages::isOwnName($fichero), "«{$fichero}» no es un fichero terminado");
        }
    }

    /**
     * @return array<string,array{0: string, 1: string}>
     */
    public static function tiposAceptados(): array
    {
        return [
            'png' => ["\x89PNG\r\n\x1A\nloquesea", 'png'],
            'jpeg' => ["\xFF\xD8\xFFloquesea", 'jpg'],
            'gif' => ['GIF89aloquesea', 'gif'],
            'webp' => ['RIFF1234WEBPloquesea', 'webp'],
        ];
    }

    #[DataProvider('tiposAceptados')]
    public function test_los_cuatro_tipos_de_imagen_se_aceptan(string $bytes, string $extension): void
    {
        // El control positivo del sniffer: sin esto, uno que lo rechazara TODO pasaría los casos de
        // abajo y nadie lo notaría.
        $this->fakeBody($bytes);

        $this->assertSame(hash('sha256', $bytes).'.'.$extension, $this->images()->fetch(self::URL));
    }

    // ─────────── Lo que no entra ───────────

    /**
     * @return array<string,array{0: string}>
     */
    public static function urlsQueNoValen(): array
    {
        return [
            'otro host pegado por detrás' => ['https://lh3.googleusercontent.com.malo.net/a/x'],
            'otro host pegado por delante' => ['https://evil.lh3.googleusercontent.com/a/x'],
            'sin https' => ['http://lh3.googleusercontent.com/a/x'],
            'un host cualquiera' => ['https://ejemplo.net/a/x'],
            'sin esquema' => ['//lh3.googleusercontent.com/a/x'],
        ];
    }

    #[DataProvider('urlsQueNoValen')]
    public function test_una_url_fuera_de_la_lista_ni_se_pide(string $url): void
    {
        // Ni se pide: `preventStrayRequests` haría saltar el caso si saliera una petición, así que
        // esto mide **que no se abre el socket**, no solo que no se guarda nada.
        $this->assertNull($this->images()->fetch($url));
        Http::assertNothingSent();
    }

    public function test_un_svg_no_es_una_imagen_que_aceptemos(): void
    {
        // ❗❗ Un SVG es XML con scripts dentro. Cae solo porque la lista es BLANCA: no empieza por
        // ninguna firma conocida. Tiene caso propio porque es lo que la spec nombra.
        $this->fakeBody('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->assertNull($this->images()->fetch(self::URL));
        $this->assertSame([], $this->disk()->files());
    }

    public function test_lo_que_no_es_una_imagen_no_entra(): void
    {
        $this->fakeBody('<!doctype html><html><body>no soy una imagen</body></html>');

        $this->assertNull($this->images()->fetch(self::URL));
    }

    public function test_el_tipo_lo_dicen_los_bytes_y_no_la_cabecera(): void
    {
        Http::fake(['https://lh*' => Http::response(
            '<svg/>',
            200,
            ['Content-Type' => 'image/png'],
        )]);

        // La cabecera la escribe quien sirve el fichero, y aquí quien lo sirve es de fuera.
        $this->assertNull($this->images()->fetch(self::URL));
    }

    public function test_una_respuesta_demasiado_grande_se_corta(): void
    {
        // ⚠️ Empieza con firma de PNG **a propósito**: si el tope no mordiera, esto se guardaría como
        // una imagen válida. Así el caso mide el TOPE y no el sniffer.
        $enorme = "\x89PNG\r\n\x1A\n".str_repeat('x', GoogleReviewImages::MAX_BYTES);
        $this->fakeBody($enorme);

        $this->assertNull($this->images()->fetch(self::URL));
        $this->assertSame([], $this->disk()->files());
    }

    public function test_una_respuesta_que_no_es_un_200_no_deja_nada(): void
    {
        // ⚠️⚠️ **El cuerpo es un PNG VÁLIDO a propósito.** Con un 404 de cuerpo vacío este caso
        // pasaba igual sin mirar el estado —no había bytes que guardar— y la guarda sobrevivía a su
        // mutación. Un servicio real contesta un error CON cuerpo, y eso es lo que hay que rechazar.
        $this->fakeBody(self::PNG, 404);

        $this->assertNull($this->images()->fetch(self::URL));
        $this->assertSame([], $this->disk()->files());
    }

    public function test_no_se_siguen_redirecciones(): void
    {
        // ❗❗❗ Un 302 es la forma barata de sacar la petición de la lista blanca: la comprobación
        // vale para la URL que escribimos nosotros, no para la que nos diga que visitemos un
        // tercero. Con `allow_redirects => false` la respuesta que llega es el 302 —que no es un
        // 200— y ahí se acaba.
        Http::fake(['https://lh*' => Http::sequence()
            ->push('', 302, ['Location' => 'https://evil.example.net/carga-util.png'])
            ->push(self::PNG, 200),
        ]);

        $this->assertNull($this->images()->fetch(self::URL));
        $this->assertSame([], $this->disk()->files());
        // Y una sola petición: si se siguiera el salto, habría dos y la segunda iría a `evil`.
        Http::assertSentCount(1);
    }

    // ─────────── El barrido de huérfanos (§4.3·6) ───────────

    public function test_el_barrido_se_lleva_un_huerfano_viejo(): void
    {
        $this->fakeBody(self::PNG);
        $ruta = $this->images()->fetch(self::URL);

        // Sin fila que lo referencie y con más de una hora.
        $this->travel(2)->hours();

        $this->assertSame(1, $this->images()->sweep([]));
        $this->disk()->assertMissing($ruta);
    }

    public function test_el_barrido_no_toca_lo_que_esta_en_uso(): void
    {
        $this->fakeBody(self::PNG);
        $ruta = $this->images()->fetch(self::URL);
        $this->travel(2)->hours();

        $this->assertSame(0, $this->images()->sweep([$ruta]));
        $this->disk()->assertExists($ruta);
    }

    public function test_el_barrido_no_toca_lo_recien_escrito(): void
    {
        $this->fakeBody(self::PNG);
        $ruta = $this->images()->fetch(self::URL);

        // ⚠️⚠️ La carrera real: entre que la descarga escribe el fichero y la transacción guarda la
        // fila pasa un instante. Un barrido que corriera justo ahí borraría la foto de una reseña
        // que se está guardando BIEN.
        $this->assertSame(0, $this->images()->sweep([]));
        $this->disk()->assertExists($ruta);
    }

    public function test_el_comando_del_barrido_respeta_lo_que_dicen_las_filas(): void
    {
        $this->fakeBody(self::PNG);
        $enUso = $this->images()->fetch(self::URL);
        $huerfano = $this->images()->fetch('https://lh3.googleusercontent.com/a/OTRA');
        $this->disk()->put($huerfano = hash('sha256', 'otra').'.png', 'GIF8huerfano');

        GoogleBusinessReview::create([
            'review_name' => 'accounts/1/locations/9/reviews/r1',
            'author_name' => 'Marta R.',
            'author_photo_path' => $enUso,
            'star_rating' => 5,
            'comment' => 'Bien.',
            'review_created_at' => now(),
            'fetched_at' => now(),
        ]);

        $this->travel(2)->hours();
        $this->artisan('business-profile:sweep-photos')->assertExitCode(0);

        $this->disk()->assertExists($enUso);
        $this->disk()->assertMissing($huerfano);
    }

    public function test_el_barrido_mira_tambien_las_fotos_de_la_resena(): void
    {
        $this->disk()->put($foto = hash('sha256', 'foto').'.png', 'x');

        GoogleBusinessReview::create([
            'review_name' => 'accounts/1/locations/9/reviews/r1',
            'author_name' => 'Marta R.',
            'photos' => [$foto],
            'star_rating' => 5,
            'comment' => 'Bien.',
            'review_created_at' => now(),
            'fetched_at' => now(),
        ]);

        $this->travel(2)->hours();
        $this->artisan('business-profile:sweep-photos');

        // No solo la del autor: las que adjuntó también están en uso.
        $this->disk()->assertExists($foto);
    }

    // ─────────── La ruta que las sirve (§4.3·6) ───────────

    public function test_la_ruta_sirve_el_fichero_con_su_propia_politica(): void
    {
        $this->disk()->put($nombre = hash('sha256', 'x').'.png', self::PNG);

        $respuesta = $this->get(route('resenas.foto', ['fichero' => $nombre]));

        $respuesta->assertOk();
        // ❗❗ Son bytes de un tercero: lo más cerrado que admite una imagen. `sandbox` le quita el
        // origen aunque el navegador acabe interpretándola como un documento.
        $respuesta->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        // ⚠️ §4.3·6 pide una caché «corta» y lo que hay es `no-store`, que es más estricto: lo pone
        // `NoStoreWebResponses`, global e incondicional por `RGPD-04`. Se asevera **la propiedad**
        // —que no se guarde— y no una cadena exacta, por lo mismo que hace su propia guarda: fijar
        // la cadena la haría fallar por un motivo que no es el que vigila.
        // ▶ El coste: cada visita vuelve a pedir cada foto. Anotado para la T2·6 (`PERF-02`).
        $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
    }

    public function test_la_politica_del_sitio_no_pisa_la_de_la_imagen(): void
    {
        $this->disk()->put($nombre = hash('sha256', 'x').'.png', self::PNG);

        $cabecera = $this->get(route('resenas.foto', ['fichero' => $nombre]))->headers->get('Content-Security-Policy');

        // ⚠️ `SecurityHeaders` solo pone la CSP del sitio **si no viene una puesta**. Si ese `if`
        // desapareciera, estas imágenes pasarían a correr con la CSP de la web —que permite
        // scripts—, y no lo notaría nadie. Por eso se afirma lo que NO tiene, además de lo que tiene.
        $this->assertStringNotContainsString('script-src', (string) $cabecera);
        $this->assertStringContainsString("default-src 'none'", (string) $cabecera);
    }

    public function test_un_fichero_que_no_existe_da_404(): void
    {
        $this->get(route('resenas.foto', ['fichero' => hash('sha256', 'no-existe').'.png']))->assertNotFound();
    }

    /**
     * @return array<string,array{0: string}>
     */
    public static function nombresQueNoSonNuestros(): array
    {
        return [
            'recorrido de directorio' => ['../../../.env'],
            'con barra dentro' => ['aa/bb.png'],
            'hash corto' => ['abc.png'],
            'extensión que no admitimos' => [hash('sha256', 'x').'.svg'],
            'sin extensión' => [hash('sha256', 'x')],
        ];
    }

    #[DataProvider('nombresQueNoSonNuestros')]
    public function test_un_nombre_que_no_es_nuestro_no_se_sirve(string $nombre): void
    {
        // La ruta comprueba la FORMA del nombre, no busca `..`: esa lista no se acaba nunca. Lo que
        // no tenga 64 hexadecimales y una extensión de la lista no llega ni al disco.
        $this->get('/resenas/foto/'.$nombre)->assertNotFound();
    }

    public function test_un_fichero_del_disco_que_no_tiene_nuestro_nombre_no_se_sirve(): void
    {
        // ❗❗ **El caso que faltaba, y lo destapó el arnés**: con los nombres inexistentes de arriba,
        // quitar la comprobación no cambiaba nada —el 404 lo daba el «no existe»—, así que la guarda
        // sobrevivía a su mutación. Aquí los ficheros SÍ están, y lo único que los separa del
        // navegador es que su nombre no es uno de los que escribimos nosotros.
        $this->disk()->put($svg = hash('sha256', 'x').'.svg', '<svg onload="alert(1)"/>');
        $this->disk()->put($suelto = 'abc.png', self::PNG);

        // La extensión, que no es de las cuatro…
        $this->get('/resenas/foto/'.$svg)->assertNotFound();
        // …y la forma del nombre, que no es un hash.
        $this->get('/resenas/foto/'.$suelto)->assertNotFound();
    }

    // ─────────── El borrado con la fila (§4.3·6) ───────────

    public function test_borrar_la_fila_se_lleva_sus_ficheros(): void
    {
        $this->disk()->put($autor = hash('sha256', 'autor').'.png', 'x');
        $this->disk()->put($foto = hash('sha256', 'foto').'.png', 'y');

        $resena = GoogleBusinessReview::create([
            'review_name' => 'accounts/1/locations/9/reviews/r1',
            'author_name' => 'Marta R.',
            'author_photo_path' => $autor,
            'photos' => [$foto],
            'star_rating' => 5,
            'comment' => 'Bien.',
            'review_created_at' => now(),
            'fetched_at' => now(),
        ]);

        $resena->delete();

        // ❗ La del autor **y** las suyas, en la misma operación.
        $this->disk()->assertMissing($autor);
        $this->disk()->assertMissing($foto);
    }

    public function test_el_borrado_no_se_lleva_un_fichero_que_no_es_nuestro(): void
    {
        $this->disk()->put('.gitkeep', '');

        // La forma del nombre es lo que hace imposible el recorrido de directorios: no se comprueba
        // que «no tenga `..`» —esa lista no se acaba nunca— sino que sea exactamente la nuestra.
        $this->images()->forget(['../../.env', '.gitkeep', 'loquesea.png']);

        $this->disk()->assertExists('.gitkeep');
    }
}
