<?php

namespace Tests\Feature\Instancia;

use App\Http\Instancia\InstanceViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **`SEC-12` — la ruta de las vistas de una instancia** (`specs/paquete-de-instancia.md` §4.2, `#647`).
 *
 * ❗❗❗ **Lo que se protege aquí no es «de dónde se leen unos ficheros»: es QUIÉN EJECUTA CÓDIGO EN EL
 * SERVIDOR.** Blade compila a PHP y lo ejecuta, así que registrar un directorio de vistas es registrar
 * un directorio de código. Si esa ruta pudiera venir de una petición, `?paquete=/tmp/lo-que-suba`
 * sería ejecución remota; y si pudiera vivir bajo `public/`, el servidor entregaría el `.blade.php`
 * en crudo con lo que llevara dentro.
 *
 * Por eso los casos de abajo no prueban «validación»: prueban las tres puertas del invariante.
 */
class InstanceViewPathTest extends TestCase
{
    // La página de contacto lee `settings` para pintarse: sin base, el caso del respaldo no llega ni
    // a comprobar lo suyo. Las cinco puertas del invariante no la necesitan, pero el precio es bajo.
    use RefreshDatabase;

    private string $paquete;

    protected function setUp(): void
    {
        parent::setUp();

        // Un paquete de mentira FUERA del docroot, que es donde tiene que estar uno de verdad.
        $this->paquete = sys_get_temp_dir().'/instancia-de-prueba-'.getmypid();
        File::ensureDirectoryExists($this->paquete.'/'.InstanceViews::SUBCARPETA);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->paquete);

        parent::tearDown();
    }

    /**
     * ❗❗❗ **La puerta 1: la ruta sale de configuración y de NADA MÁS.**
     *
     * Se intenta moverla por los tres caminos que tiene un desconocido para hablarle a la aplicación
     * —un parámetro, una cabecera y el host— y la respuesta tiene que ser la misma en los tres. Si
     * algún día alguien le pasara `$request->input(...)` a la resolución, este caso se pone rojo.
     */
    public function test_the_package_path_cannot_be_moved_by_a_request(): void
    {
        config(['instancia.ruta' => $this->paquete]);
        $esperada = InstanceViews::rutaDelPaquete();

        $this->assertSame(realpath($this->paquete), $esperada);

        $intruso = sys_get_temp_dir().'/instancia-intrusa-'.getmypid();
        File::ensureDirectoryExists($intruso.'/'.InstanceViews::SUBCARPETA);

        try {
            // Un parámetro.
            $this->get('/contacto?instancia_ruta='.urlencode($intruso));
            $this->assertSame($esperada, InstanceViews::rutaDelPaquete(), 'la ruta se movió con un parámetro');

            // Una cabecera.
            $this->withHeaders(['X-Instancia-Ruta' => $intruso])->get('/contacto');
            $this->assertSame($esperada, InstanceViews::rutaDelPaquete(), 'la ruta se movió con una cabecera');

            // El host: el camino por el que un multi-instalación «listo» derivaría la carpeta del
            // subdominio — y con él, cualquiera que controle un DNS elegiría qué código se ejecuta.
            $this->withHeaders(['Host' => 'otra-instancia.example'])->get('/contacto');
            $this->assertSame($esperada, InstanceViews::rutaDelPaquete(), 'la ruta se movió con el host');
        } finally {
            File::deleteDirectory($intruso);
        }
    }

    /**
     * **La puerta 2: fuera del ÁRBOL DEL PRODUCTO.** Es la que de verdad muerde, y cubre dos peligros:
     * bajo `public/` el servidor web sirve el fichero antes de que PHP lo vea —la landing se
     * descargaría en código fuente—, y en cualquier otro sitio del árbol el `rsync --delete` del
     * despliegue se lo lleva, dejando la instalación sin landing sin que nadie sepa por qué.
     *
     * Se prueban los dos sitios, porque el segundo no es evidente y se olvida.
     */
    public function test_a_package_inside_the_product_tree_is_refused(): void
    {
        $sitios = [
            'bajo public/' => public_path('paquete-de-prueba-'.getmypid()),
            'en el árbol, fuera de public/' => base_path('paquete-de-prueba-'.getmypid()),
        ];

        foreach ($sitios as $donde => $ruta) {
            File::ensureDirectoryExists($ruta.'/'.InstanceViews::SUBCARPETA);

            try {
                config(['instancia.ruta' => $ruta]);

                $this->assertNull(
                    InstanceViews::rutaDelPaquete(),
                    "se aceptó un paquete {$donde}: el producto no puede alojar a su instancia"
                );
            } finally {
                File::deleteDirectory($ruta);
            }
        }
    }

    /**
     * ⚠️ Y el vecino de al lado NO está dentro: `html-instancia` empieza igual que `html` y una
     * comparación de prefijos a secas lo descartaría. Sin este caso, la puerta 2 rechazaría la
     * carpeta legítima de al lado, que es justo donde un paquete suele vivir.
     */
    public function test_a_sibling_that_merely_starts_like_the_tree_is_not_inside(): void
    {
        // ⚠️ El árbol se MUEVE a un temporal en vez de crear la carpeta vecina al de verdad: dentro
        // del contenedor, `/var/www/` no lo puede escribir el usuario `sail` (medido: «mkdir():
        // Permission denied»). Lo que se prueba es el mismo prefijo, con las dos carpetas bajo `/tmp`.
        $original = base_path();
        $arbol = sys_get_temp_dir().'/arbol-'.getmypid();
        $vecino = $arbol.'-instancia';

        File::ensureDirectoryExists($arbol);
        File::ensureDirectoryExists($vecino.'/'.InstanceViews::SUBCARPETA);

        try {
            $this->app->setBasePath($arbol);
            config(['instancia.ruta' => $vecino]);

            $this->assertSame(realpath($vecino), InstanceViews::rutaDelPaquete());
        } finally {
            $this->app->setBasePath($original);
            File::deleteDirectory($vecino);
            File::deleteDirectory($arbol);
        }
    }

    /**
     * **La puerta 3: relativa, no.** Se resolvería contra el directorio de trabajo del proceso, que no
     * es el mismo en `php artisan`, en php-fpm y en la cola: tres landings según quién renderice.
     */
    public function test_a_relative_path_is_refused(): void
    {
        // ⚠️⚠️ **La relativa tiene que resolver a algo VÁLIDO por lo demás, o este caso no prueba
        // nada.** Dos intentos fallaron por pasar en verde por el motivo equivocado, y los dos los
        // cazó el arnés (quitar la comprobación de «absoluta» no mordía): `storage/instancia` no
        // existe —moría en `realpath()`— y `storage` está DENTRO del árbol, así que moría en la
        // puerta 2. Tiene que ser una relativa que apunte FUERA del árbol: entonces la única razón
        // para rechazarla es la buena.
        $fuera = $this->paquete;   // ya existe, en `/tmp`, fuera del árbol

        // Cuántos `../` hacen falta para subir desde el cwd (= la raíz del producto) hasta `/`, y de
        // ahí la ruta absoluta sin su barra. Se calcula en vez de escribir `../../tmp/…` para que el
        // caso no dependa de a qué profundidad esté montado el repo.
        $subir = str_repeat('../', substr_count(trim((string) getcwd(), '/'), '/') + 1);
        $relativa = $subir.ltrim($fuera, '/');

        config(['instancia.ruta' => $relativa]);

        $this->assertSame(realpath($fuera), realpath($relativa), 'la relativa del caso tiene que resolver FUERA del árbol');
        $this->assertNull(InstanceViews::rutaDelPaquete());
    }

    /**
     * **Sin paquete, el producto ARRANCA y sirve.** Una instalación recién montada está así, y un 500
     * aquí sería un producto que no levanta hasta que alguien le dé una landing.
     */
    public function test_without_a_package_the_site_still_answers(): void
    {
        config(['instancia.ruta' => null]);

        $this->assertNull(app(InstanceViews::class)->registrar());
        $this->get('/contacto')->assertOk();
    }

    /**
     * Y con paquete, **la vista de la instancia gana** sobre el respaldo del producto — que es todo el
     * propósito de la vía B.
     */
    public function test_with_a_package_the_instance_view_wins(): void
    {
        File::put($this->paquete.'/'.InstanceViews::SUBCARPETA.'/contacto.blade.php', 'la de la instancia');

        config(['instancia.ruta' => $this->paquete]);
        $vistas = app(InstanceViews::class);
        $vistas->registrar();

        $this->assertSame('instancia::contacto', $vistas->pick('contacto', 'pages.contact'));
        // Y lo que la instancia NO trae sigue saliendo del producto, sin que nadie tenga que elegirlo.
        $this->assertSame('pages.pricing', $vistas->pick('precios', 'pages.pricing'));
    }
}
