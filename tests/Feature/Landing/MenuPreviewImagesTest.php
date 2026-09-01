<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA COLUMNA DEL MENÚ DEJA DE ESTAR VACÍA, Y SUS ZONAS SALEN DE LA BD** (`#341`,
 * `[DECIDIDO owner]`: *«las que sean, que no esté vacío»*).
 *
 * Medido antes de tocar nada: con `/servicios` en mantenimiento **ninguno** de los siete destinos del
 * menú traía foto —`img` solo lo tenían los servicios del CMS—, así que la vista previa de la columna
 * lateral caía al fondo rayado en todos.
 *
 * Se arregla por DOS caminos y este fichero vigila los dos:
 *
 *  1. **Las zonas aportan la suya.** Y eso, además de la foto, cierra una fuga: hasta hoy «Zona Kids»
 *     y «Zona Jump» estaban **escritas a mano en los ficheros de idioma del PRODUCTO**, o sea el
 *     catálogo de un cliente dentro del repo — la misma fuga que `#302` cerró en el JS (`zone: 'jump'`).
 *     De paso, `zones.image` recupera un consumidor: lo había perdido en `#302`.
 *  2. **Un respaldo POR INSTALACIÓN** para los destinos que no tienen ninguna imagen que sea SUYA en
 *     el modelo —Entradas, Cumpleaños, Atracciones, Ubicación—. Inventarles una asociación habría sido
 *     quemar otra vez el catálogo de un cliente en el producto.
 *
 * ⚠️⚠️ **El `public/` es TEMPORAL a propósito**: `img/client-menu.webp` está gitignorado, así que un
 * caso que aseverara contra el `public/` real **pasaría en la máquina que tiene el paquete instalado y
 * fallaría en un clon limpio** — o al revés. Es la lección de `#302` y el patrón de
 * `ZonesSectionTest`/`ClientThemePackageTest`.
 */
class MenuPreviewImagesTest extends TestCase
{
    use RefreshDatabase;

    /** Ruta del respaldo dentro de `public/`. Igual que la lee la plantilla del menú. */
    private const RESPALDO = 'img/client-menu.webp';

    private string $publicDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jw-menu-'.getmypid().'-'.uniqid();
        mkdir($this->publicDir.'/img', 0o777, true);
        @symlink(base_path('public/build'), $this->publicDir.'/build');
        $this->app->usePublicPath($this->publicDir);

        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    protected function tearDown(): void
    {
        if ($this->publicDir !== '' && is_dir($this->publicDir)) {
            @unlink($this->publicDir.'/build');
            @unlink($this->publicDir.'/'.self::RESPALDO);
            @rmdir($this->publicDir.'/img');
            @rmdir($this->publicDir);
        }

        parent::tearDown();
    }

    private function instalarRespaldo(): void
    {
        file_put_contents(public_path(self::RESPALDO), 'RIFF....WEBP');
    }

    /**
     * Los destinos tal y como llegan a la vista previa (`vistas` del `x-data` del menú), ya con el
     * respaldo resuelto. Se leen del HTML porque es donde de verdad acaban.
     *
     * @return list<array{t:string,s:string,img:?string}>
     */
    private function vistas(): array
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/vistas: JSON\.parse\(/', $html, 'el menú ya no publica sus destinos como antes: re-apunta este localizador');

        preg_match("/vistas: JSON\.parse\('(.*?)'\)/s", $html, $m);

        // ⚠️⚠️ **Lo que `@js()` emite NO es JSON**: es una cadena de JavaScript cuyas comillas dobles
        // viajan como `"` —para que no cierren el atributo HTML— y cuyas barras llevan DOS capas
        // de escapado (la de JSON y la de la cadena JS). Dárselo a `json_decode` tal cual devuelve
        // `null`, y un `null` aquí dejaría TODOS los casos de abajo aseverando sobre una lista vacía:
        // por eso hay control del localizador, y por eso costó dos intentos.
        // ▶ Las barras se colapsan con un patrón en vez de contarlas: contar barras invertidas a
        // través de PHP, JSON y JavaScript a la vez es justo donde esto se rompe sin avisar.
        $crudo = str_replace('\\u0022', '"', $m[1] ?? '[]');
        $crudo = (string) preg_replace('~\\\\+/~', '/', $crudo);

        return json_decode($crudo, true) ?: [];
    }

    /**
     * ⚠️ El control del propio localizador: si dejara de encontrar destinos, todo lo de abajo
     * pasaría en verde sobre una lista vacía.
     */
    public function test_the_probe_finds_the_menu_destinations(): void
    {
        $this->assertGreaterThanOrEqual(3, count($this->vistas()));
    }

    /** Las zonas del menú son las de la BD, con su nombre y su edad — no un texto del producto. */
    public function test_the_zones_of_the_menu_come_from_the_database(): void
    {
        Zone::where('slug', 'jump')->update([
            'name' => json_encode(['es' => 'ZONA DE PRUEBA']),
            'age_range' => json_encode(['es' => '+99 años']),
            'show_in_landing' => true,
            'is_active' => true,
        ]);

        $titulos = array_column($this->vistas(), 't');

        $this->assertContains('ZONA DE PRUEBA', $titulos, 'el menú sigue pintando un nombre de zona que no sale de la BD');
        $this->assertSame('+99 años', collect($this->vistas())->firstWhere('t', 'ZONA DE PRUEBA')['s']);
    }

    /**
     * ⚠️ Y el CONTROL de lo anterior: una zona que el panel no marca para la landing no entra en el
     * menú. Sin este caso, «salen de la BD» se cumpliría también pintándolas todas.
     */
    public function test_a_zone_outside_the_landing_is_not_offered(): void
    {
        Zone::where('slug', 'jump')->update(['name' => json_encode(['es' => 'ZONA OCULTA']), 'show_in_landing' => false]);

        $this->assertNotContains('ZONA OCULTA', array_column($this->vistas(), 't'));
    }

    /** La foto de la zona llena su vista previa: es el consumidor que `zones.image` había perdido. */
    public function test_a_zone_with_a_photo_shows_it(): void
    {
        Zone::where('slug', 'jump')->update([
            'name' => json_encode(['es' => 'CON FOTO']),
            'image' => 'images/attractions/park_jump.webp',
            'show_in_landing' => true,
            'is_active' => true,
        ]);

        $vista = collect($this->vistas())->firstWhere('t', 'CON FOTO');

        $this->assertNotNull($vista);
        $this->assertStringContainsString('park_jump.webp', (string) $vista['img']);
    }

    /**
     * ❗ **EL SUELO DEL PRODUCTO**: sin el fichero de la instalación no se inventa ninguna imagen y la
     * columna se comporta como antes. Es la misma conducta que el logotipo, el icono y el kit — un
     * hueco que falla hacia invisible.
     */
    public function test_without_the_installation_file_nothing_is_painted(): void
    {
        Zone::query()->update(['image' => null]);

        $conImagen = array_filter(array_column($this->vistas(), 'img'));

        $this->assertSame([], $conImagen, 'sin paquete de instalación el menú no puede pintar ninguna foto');
    }

    /** Con el fichero puesto, NINGÚN destino se queda sin imagen. Es el encargo, literal. */
    public function test_the_installation_file_fills_every_destination(): void
    {
        Zone::query()->update(['image' => null]);
        $this->instalarRespaldo();

        $vistas = $this->vistas();
        $this->assertNotEmpty($vistas);

        foreach ($vistas as $v) {
            $this->assertNotNull($v['img'], "«{$v['t']}» se queda sin imagen en la columna del menú");
            $this->assertStringContainsString('client-menu.webp', (string) $v['img']);
        }
    }

    /**
     * ⚠️ Y el respaldo es RESPALDO: la foto propia de una zona le gana. Sin este caso, resolverlo al
     * revés —el respaldo pisando a todos— pasaría en verde y la columna enseñaría la misma imagen
     * para los seis destinos.
     */
    public function test_the_own_photo_wins_over_the_installation_file(): void
    {
        $this->instalarRespaldo();
        Zone::where('slug', 'jump')->update([
            'name' => json_encode(['es' => 'MANDA LA SUYA']),
            'image' => 'images/attractions/park_jump.webp',
            'show_in_landing' => true,
            'is_active' => true,
        ]);

        $vista = collect($this->vistas())->firstWhere('t', 'MANDA LA SUYA');

        $this->assertStringContainsString('park_jump.webp', (string) $vista['img']);
        $this->assertStringNotContainsString('client-menu.webp', (string) $vista['img']);
    }

    /**
     * ⚠️ **La marca de tiempo del fichero viaja como cache-buster.** Sin ella, sustituir la foto en el
     * servidor no se vería hasta que caducara la caché del navegador — y el owner cambiaría el fichero
     * creyendo que no funciona. Mismo mecanismo que `site.brand`.
     */
    public function test_the_installation_file_carries_its_cache_buster(): void
    {
        Zone::query()->update(['image' => null]);
        $this->instalarRespaldo();

        $img = (string) ($this->vistas()[0]['img'] ?? '');

        $this->assertMatchesRegularExpression('/client-menu\.webp\?v=\d+$/', $img);
    }
}
