<?php

namespace Tests\Feature\Landing;

use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA COLUMNA DEL MENÚ NO SE QUEDA VACÍA: su imagen es un hueco POR INSTALACIÓN** (`#341`,
 * `[DECIDIDO owner]`: *«las que sean, que no esté vacío»*).
 *
 * Medido en `#341`: con `/servicios` en mantenimiento **ninguno** de los destinos del menú traía foto,
 * así que la vista previa de la columna lateral caía al fondo rayado en todos. Se arregló por dos
 * caminos —las zonas aportaban la suya y un respaldo por instalación cubría al resto—.
 *
 * ⚠️⚠️ **Desde `#521` queda UNO**: las zonas dejaron de ser destinos del menú (`[DECIDIDO owner]`: los
 * destinos son el inventario de páginas y las secciones de la portada), y ninguno de ésos tiene una
 * imagen que sea SUYA en el modelo. Así que la imagen de la columna es la de la instalación, o
 * ninguna. Los casos de zonas se retiraron con su sujeto (ver la nota de abajo).
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
     * Los destinos tal y como llegan a la vista previa (`vistas` del `x-data` del menú), ya con la
     * imagen resuelta. Se leen del HTML porque es donde de verdad acaban.
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

    // ⚠️⚠️ **AQUÍ VIVÍAN CUATRO CASOS DE ZONAS, Y SE RETIRAN CON SU SUJETO** (`#521`,
    // `[DECIDIDO owner, 2026-09-11]`): `the_zones_of_the_menu_come_from_the_database`,
    // `a_zone_outside_the_landing_is_not_offered`, `a_zone_with_a_photo_shows_it` y
    // `the_own_photo_wins_over_the_installation_file`. Las zonas dejaron de ser destinos del menú,
    // así que no hay zona que buscar ni foto propia que gane (la plantilla ya no tiene esa rama).
    // ⚠️ **Y el segundo no fallaba: pasaba EN VACÍO** —«una zona oculta no sale» es cierto cuando no
    // sale ninguna—, que es peor que un rojo: un verde que ya no vigila nada. Lo cazó la suite
    // completa, no la ejecución dirigida, que no incluía este fichero.
    // ▶ Lo que sigue vivo —la fuga de catálogo que `#341` cerró— lo vigila ahora la guarda del
    // inventario (`ArmazonContractTest`), que prohíbe cualquier destino que no sea del inventario.
    // `zones.image` conserva consumidor: la foto de la tarjeta de la sección 01 (`ZoneCards`).

    /**
     * ❗ **EL SUELO DEL PRODUCTO**: sin el fichero de la instalación no se inventa ninguna imagen y la
     * columna se comporta como antes. Es la misma conducta que el logotipo, el icono y el kit — un
     * hueco que falla hacia invisible.
     */
    public function test_without_the_installation_file_nothing_is_painted(): void
    {
        $conImagen = array_filter(array_column($this->vistas(), 'img'));

        $this->assertSame([], $conImagen, 'sin paquete de instalación el menú no puede pintar ninguna foto');
    }

    /** Con el fichero puesto, NINGÚN destino se queda sin imagen. Es el encargo, literal. */
    public function test_the_installation_file_fills_every_destination(): void
    {
        $this->instalarRespaldo();

        $vistas = $this->vistas();
        $this->assertNotEmpty($vistas);

        foreach ($vistas as $v) {
            $this->assertNotNull($v['img'], "«{$v['t']}» se queda sin imagen en la columna del menú");
            $this->assertStringContainsString('client-menu.webp', (string) $v['img']);
        }
    }

    /**
     * ⚠️ **La marca de tiempo del fichero viaja como cache-buster.** Sin ella, sustituir la foto en el
     * servidor no se vería hasta que caducara la caché del navegador — y el owner cambiaría el fichero
     * creyendo que no funciona. Mismo mecanismo que `site.brand`.
     */
    public function test_the_installation_file_carries_its_cache_buster(): void
    {
        $this->instalarRespaldo();

        $img = (string) ($this->vistas()[0]['img'] ?? '');

        $this->assertMatchesRegularExpression('/client-menu\.webp\?v=\d+$/', $img);
    }
}
