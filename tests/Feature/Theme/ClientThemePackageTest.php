<?php

namespace Tests\Feature\Theme;

use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL PAQUETE DE TEMA DE LA INSTALACIÓN** (`DECISIONES #143`).
 *
 * `specs/landing-white-label.md` §4.5.2 daba por hecho que un cliente podía traer su hoja: la línea
 * era «cambiar color o velocidad ya es un token… no hay que construir nada, hay que usarlo». Medido
 * el 2026-08-25 contra el layout: **no había ningún hueco**. Se cargaban `landing.css`, el
 * `<style id="jj-theme">`, `spinner.css` y `site.css`, y ahí se acababa. Tokenizar el CSS sin este
 * hueco es trabajo que ningún cliente puede usar — no hay por dónde entrar.
 *
 * ⚠️⚠️ **Y el mecanismo tiene TRES piezas, no una.** Las tres se aseveran aquí porque las tres
 * pueden faltar por separado y con dos de ellas parece que funciona:
 *
 *   1. el `<link>` se emite **y va el ÚLTIMO** — si va antes de `site.css`, redefinir un token no
 *      gana en cascada y el paquete se carga sin efecto, que es peor que no cargarse;
 *   2. la hoja **no se versiona** — este repo es el PRODUCTO y no lleva la marca de nadie;
 *   3. `deploy.sh` la **excluye del `rsync --delete`** — sin eso el mecanismo funciona en local y el
 *      primer despliegue borra la hoja del servidor, devolviendo la web al tema del producto **en
 *      silencio**. Es la mitad que no se ve hasta que se pierde.
 */
class ClientThemePackageTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_SHEET = 'css/client.css';

    private string $publicDir = '';

    /**
     * **Estos casos corren sobre un `public/` PROPIO, no sobre el de la máquina.**
     *
     * ⚠️⚠️ **Y eso los arregló DOS veces.** Antes miraban el disco de verdad: escribían y borraban
     * `public/css/client.css`, y se **saltaban** si ya existía —para no borrarle el tema a una
     * instalación—. Al montar el paquete del segundo cliente (2026-08-28) las dos mitades de esa
     * decisión salieron mal:
     *
     *   · **la suite del producto se ponía roja o incompleta en cuanto una máquina era una
     *     INSTALACIÓN**, que es justo lo que este mecanismo existe para permitir; y
     *   · los saltos **movían el contador de aserciones**, así que el `pre-push` de esa máquina
     *     bloqueaba… y el de la máquina sin paquete bloqueaba al revés. Un gate que depende de si
     *     el disco tiene o no el tema de un cliente **no es un gate**.
     *
     * ▶ Con `usePublicPath()` el caso deja de depender del disco: existe o no existe porque **lo
     * decide el test**. Y la hoja del cliente **no se toca jamás**.
     * ⚠️ `build/` se enlaza al real porque el layout resuelve el manifiesto de Vite por
     * `public_path()`: sin él, cualquier página lanzaría «Vite manifest not found».
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jw-public-'.getmypid().'-'.uniqid();
        mkdir($this->publicDir.'/css', 0o777, true);
        @symlink(base_path('public/build'), $this->publicDir.'/build');
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        if ($this->publicDir !== '' && is_dir($this->publicDir)) {
            @unlink($this->publicDir.'/build');
            @unlink($this->publicDir.'/'.self::CLIENT_SHEET);
            @rmdir($this->publicDir.'/css');
            @rmdir($this->publicDir);
        }

        parent::tearDown();
    }

    /** Escribe una hoja de cliente **en el `public/` del test** y la retira al terminar. */
    private function withClientSheet(callable $body): void
    {
        $path = public_path(self::CLIENT_SHEET);

        try {
            file_put_contents($path, ":root{--bg:#001122}\n");
            $body();
        } finally {
            @unlink($path);
        }
    }

    /**
     * **El LOGOTIPO de la instalación entra por el mismo hueco, con las mismas tres piezas.**
     *
     * Y la tercera es la que no se ve: si `deploy.sh` no lo excluye del `--delete`, el primer
     * despliegue lo borra y la marca vuelve a ser texto **en silencio** — exactamente lo que pasó
     * con la hoja de tema antes de `#143`.
     */
    public function test_the_installation_logo_replaces_the_wordmark_and_keeps_its_name(): void
    {
        $this->seed(LandingContentSeeder::class);

        // Sin fichero: el suelo del producto es el nombre en la fuente de rótulo.
        $this->get('/')->assertOk()
            ->assertSee('nav__brand-row', false)
            ->assertDontSee('nav__brand-logo', false);

        $path = public_path('img/client-logo.svg');
        @mkdir(dirname($path), 0o777, true);

        try {
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"></svg>');

            $html = $this->get('/')->assertOk()->getContent();

            $this->assertStringContainsString('nav__brand-logo', $html, 'el logotipo de la instalación no se pinta');
            $this->assertStringNotContainsString('nav__brand-row', $html, 'se pintan el logotipo Y el texto: son alternativas');
            // ⚠️⚠️ **El cache-busting deja de tener sujeto** (`#254`): el logotipo ya no se PIDE por
            // URL, se **incrusta** en el documento —es lo que permite animarlo—, así que no hay
            // caché de navegador que invalidar. La regla que esta aserción protegía —«un cliente
            // que cambia su logotipo ve el cambio»— se cumple ahora *mejor*: el HTML no se cachea
            // (`NoStoreWebResponses`), y el dibujo va dentro.
            // ▶ Lo que se asevera en su lugar es que el DIBUJO llega de verdad, que es lo que la
            // aserción anterior comprobaba de rebote. Sin esto, «se pinta el logotipo» se cumpliría
            // con un `<span>` vacío.
            $this->assertStringContainsString(
                '<svg', $html,
                'el logotipo de la instalación no llega al documento: se sirve en línea desde `#254`.',
            );
            // ❗ **El nombre accesible NO se pierde**: es el único enlace que TODA página tiene.
            // ⚠️ Cambia el PORTADOR, no la regla: al incrustar el SVG no hay `alt` que poner —el
            // contenido de un `<img>` no llegaba al árbol de accesibilidad y el de un `<svg>` sí—,
            // así que el nombre lo declara el envoltorio. Y al `<svg>` se le QUITA el suyo, o
            // serían dos nombres anidados para un solo enlace (`InlineSvg::mute`).
            $this->assertMatchesRegularExpression(
                '/nav__brand-logo--inline"[^>]*role="img"[^>]*aria-label="[^"]+"/', $html,
                'el logotipo va sin nombre accesible: el enlace a la portada se queda mudo',
            );
            $this->assertMatchesRegularExpression(
                '/<svg[^>]*aria-hidden="true"/', $html,
                'el SVG incrustado conserva su propio nombre: son dos nombres para un solo enlace, '.
                'y quien navega por voz oye el del dibujo en vez del del sitio.',
            );
        } finally {
            @unlink($path);
        }
    }

    /** **El logotipo tampoco se versiona**: es marca de un cliente y este repo es el producto. */
    public function test_the_installation_logo_is_not_versioned(): void
    {
        $this->assertMatchesRegularExpression(
            '#^/public/img/client-logo\.svg\s*$#m',
            (string) file_get_contents(base_path('.gitignore')),
            '`.gitignore` no ignora el logotipo de la instalación: la marca de un cliente acabaría '.
            'versionada en el repo del PRODUCTO.',
        );
    }

    /** **Y `deploy.sh` lo excluye del `--delete`**, que es la pieza que no se ve hasta que se pierde. */
    public function test_deploy_excludes_the_installation_logo(): void
    {
        $this->assertMatchesRegularExpression(
            "#^\s*--exclude='/public/img/client-logo\.svg'#m",
            (string) file_get_contents(base_path('scripts/deploy.sh')),
            '`deploy.sh` no excluye el logotipo del `rsync --delete`: el primer despliegue lo borra '.
            'y la marca vuelve a ser texto, en silencio.',
        );
    }

    /**
     * **EL ICONO DE PESTAÑA de la instalación** — el tercer hueco del mismo patrón (`#211`).
     *
     * ⚠️ Con el icono del cliente puesto, **el PNG del producto sale del `<head>`**: un navegador
     * que entienda los dos elegiría el PNG por ser más específico en tamaño y volvería a enseñar la
     * «J» de JumpWeb teniendo el del cliente al lado. El `apple-touch-icon` se queda porque para
     * iOS no hay alternativa vectorial — y eso es una limitación CONOCIDA del alcance elegido
     * (`[DECIDIDO owner, 2026-08-28]`: solo el SVG).
     */
    public function test_the_installation_favicon_replaces_the_product_one(): void
    {
        // Sin fichero: manda el icono del producto, con su PNG de respaldo.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('favicon.svg', $html);
        $this->assertStringContainsString('favicon-64.png', $html);

        $path = public_path('img/client-favicon.svg');
        @mkdir(dirname($path), 0o777, true);

        try {
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"></svg>');

            $html = $this->get('/')->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '/client-favicon\.svg\?v=\d+/', $html,
                'el icono de la instalación no se sirve, o va sin cache-busting',
            );
            // ⚠️ Acotado al fichero del PRODUCTO: `client-favicon.svg` contiene la cadena
            // `favicon.svg`, así que un `assertStringNotContainsString` suelto falla siempre —
            // y su mensaje culpa al código en vez de a la aserción.
            $this->assertDoesNotMatchRegularExpression(
                '#href="[^"]*/favicon\.svg#', $html,
                'se sirven el icono del cliente Y el del producto: son alternativas, no capas',
            );
            $this->assertStringNotContainsString(
                'favicon-64.png', $html,
                'sigue el PNG del producto en el `<head>`: un navegador que entienda los dos '.
                'prefiere el PNG por tamaño y enseñaría la «J» de JumpWeb.',
            );
        } finally {
            @unlink($path);
        }
    }

    /** **Las otras dos piezas del icono**: ni se versiona, ni lo borra el despliegue. */
    public function test_the_installation_favicon_is_not_versioned_and_survives_the_deploy(): void
    {
        $this->assertMatchesRegularExpression(
            '#^/public/img/client-favicon\.svg\s*$#m',
            (string) file_get_contents(base_path('.gitignore')),
            '`.gitignore` no ignora el icono de la instalación: la marca de un cliente acabaría '.
            'versionada en el repo del PRODUCTO.',
        );

        $this->assertMatchesRegularExpression(
            "#^\s*--exclude='/public/img/client-favicon\.svg'#m",
            (string) file_get_contents(base_path('scripts/deploy.sh')),
            '`deploy.sh` no excluye el icono del `rsync --delete`: el primer despliegue lo borra y '.
            'vuelve el icono del producto, en silencio.',
        );
    }

    /**
     * **Y las DOS plantillas usan el mismo hueco.**
     *
     * Si `focused-layout` se quedara con los `<link>` a mano, una instalación pondría su icono y la
     * pantalla de menores seguiría con el del producto — sin fallar y sin avisar. Es el mismo modo
     * de fallo que la hoja de tema tuvo hasta `#143`.
     */
    public function test_both_layouts_take_the_favicon_from_the_same_hole(): void
    {
        foreach (['layout', 'focused-layout'] as $vista) {
            $blade = (string) file_get_contents(resource_path("views/components/{$vista}.blade.php"));

            $this->assertStringContainsString(
                '<x-site.favicon />', $blade,
                "`{$vista}.blade.php` no usa el componente del icono",
            );
            $this->assertStringNotContainsString(
                "asset('favicon.svg')", $blade,
                "`{$vista}.blade.php` sigue enlazando el icono del producto a mano: esa copia no ".
                'pasa por el hueco y se queda con la marca de JumpWeb.',
            );
        }
    }

    /**
     * **Y las DOS plantillas cargan el paquete de tema, y lo cargan el ÚLTIMO.**
     *
     * ⚠️⚠️ `focused-layout` —el post-form y el justificante— **no lo cargaba desde que existe**
     * (`#570`): una instalación con paquete veía esas dos pantallas con los colores del PRODUCTO, y
     * los casos de arriba no podían verlo porque solo renderizan la portada. Lo cazó una captura.
     */
    public function test_both_layouts_load_the_client_sheet_after_the_product_sheets(): void
    {
        foreach (['layout', 'focused-layout'] as $vista) {
            $blade = (string) file_get_contents(resource_path("views/components/{$vista}.blade.php"));
            $clientAt = strpos($blade, "asset('css/client.css')");

            $this->assertNotFalse($clientAt, "`{$vista}.blade.php` no carga el paquete de tema de la instalación");
            $this->assertGreaterThan(
                (int) strpos($blade, "asset('css/site.css')"), $clientAt,
                "`{$vista}.blade.php` carga `client.css` antes que `site.css`: sus tokens pierden la cascada.",
            );
        }
    }

    /**
     * **Sin hoja del cliente, el `<head>` no cambia** — que es el estado de este repo y el de
     * cualquier instalación que aún no tenga tema propio.
     *
     * ⚠️ El `public/` es el del test (ver `setUp`), así que «sin hoja» es un hecho que decide este
     * caso — **no el disco de quien lo corre**. Antes dependía de la máquina, y eso lo hacía saltar
     * o fallar según quién lo ejecutara.
     */
    public function test_without_a_client_sheet_no_link_is_emitted(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertDontSee(self::CLIENT_SHEET, false);
    }

    /**
     * **Con hoja, se enlaza — y con cache-busting.**
     *
     * El `?v=` no es cosmético: las tres hojas del producto lo llevan porque un cliente que retoca su
     * tema y no ve el cambio acaba pidiendo ayuda por un problema de caché.
     */
    public function test_with_a_client_sheet_it_is_linked_with_cache_busting(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->withClientSheet(function (): void {
            $html = $this->get('/')->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '#<link[^>]+href="[^"]*'.preg_quote(self::CLIENT_SHEET, '#').'\?v=\d+"#',
                (string) $html,
                'la hoja del cliente existe pero no se enlaza (o se enlaza sin `?v=`).',
            );
        });
    }

    /**
     * ⚠️⚠️ **Y va la ÚLTIMA de las cuatro.** Es la aserción con más valor de este fichero: un
     * `<link>` colocado antes de `site.css` se carga, no da ningún error, y **no pinta nada** —
     * redefinir `--bg` en el paquete del cliente perdería contra el `:root` del producto—. Un tema
     * que se carga y no se ve es más difícil de diagnosticar que uno que no se carga.
     */
    public function test_the_client_sheet_wins_the_cascade_over_every_product_sheet(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->withClientSheet(function (): void {
            $html = (string) $this->get('/')->assertOk()->getContent();

            preg_match_all('#<link[^>]+href="[^"]*css/([\w.-]+\.css)#', $html, $m);
            $order = $m[1];

            $this->assertContains('client.css', $order, 'la hoja del cliente no aparece entre las del sitio');

            foreach (['landing.css', 'spinner.css', 'site.css'] as $productSheet) {
                $this->assertContains($productSheet, $order, "falta `{$productSheet}` en el `<head>`");

                $this->assertGreaterThan(
                    array_search($productSheet, $order, true),
                    array_search('client.css', $order, true),
                    "`client.css` se carga ANTES que `{$productSheet}`: sus tokens pierden la cascada y ".
                    'el paquete del cliente se carga SIN EFECTO. No falla, no avisa y no se ve.',
                );
            }

            // El `<style id="jj-theme">` (tokens desde BD) también tiene que quedar por delante: es
            // lo que el operador retoca en el panel, y el paquete del cliente es el suelo de marca.
            $themeAt = strpos($html, 'id="jj-theme"');
            $clientAt = strpos($html, 'css/client.css');

            $this->assertNotFalse($themeAt, 'ha desaparecido el bloque de tema inyectado desde BD');
            $this->assertGreaterThan(
                $themeAt, $clientAt,
                '`client.css` va antes del `<style id="jj-theme">`. El tema de BD es lo que el operador '.
                'cambia desde el panel: tiene que poder ganar al paquete instalado.',
            );
        });
    }

    /**
     * **La hoja del cliente no se versiona en el repo del PRODUCTO.**
     *
     * `CLAUDE.md`: «este repo es el PRODUCTO, sin marca de ningún cliente». Una hoja de marca
     * commiteada aquí es exactamente lo que `DECISIONES #1` prohíbe.
     */
    public function test_the_client_sheet_is_not_versioned(): void
    {
        // ⚠️ Ancorado a PRINCIPIO DE LÍNEA, no `assertStringContainsString`. Una regla comentada
        // (`# /public/css/client.css`) contiene la misma subcadena y no ignora nada: la primera
        // versión de este caso pasaba con la regla anulada. Ver el aviso del caso de abajo.
        $this->assertMatchesRegularExpression(
            '#^/public/css/client\.css\s*$#m',
            (string) file_get_contents(base_path('.gitignore')),
            'la hoja de tema del cliente no está ignorada (o su regla está comentada): acabaría '.
            'commiteada en el repo del producto, con la marca de un cliente dentro.',
        );
    }

    /**
     * ⚠️⚠️ **La pieza que no se ve hasta que se pierde: el `rsync --delete`.**
     *
     * `deploy.sh` sincroniza con `--delete`, así que borra en el servidor lo que no esté en local. La
     * hoja del cliente está gitignorada y **no existe en local**: sin su exclusión, el primer
     * despliegue se la lleva y la web vuelve al tema del producto sin que nada falle. Es el mismo
     * motivo por el que ya se excluye `public/uploads/`.
     *
     * ⚠️⚠️ **Este caso nació ROTO y lo cazó su propia mutación.** La primera versión aseveraba
     * `assertStringContainsString("--exclude='/public/css/client.css'")`, y al comentar la línea en
     * `deploy.sh` —`#--exclude='/public/css/client.css'`— **seguía en verde**: la subcadena sigue ahí
     * con el `#` delante. Una exclusión comentada no excluye nada. Por eso se ancla a principio de
     * línea y se comprueba además que vive DENTRO del array que el `rsync` recibe: un `--exclude` en
     * un comentario de la cabecera del script tampoco excluye.
     */
    public function test_the_deploy_does_not_delete_the_client_sheet(): void
    {
        $deploy = (string) file_get_contents(base_path('scripts/deploy.sh'));

        // El array que se le pasa al rsync, y solo él.
        preg_match('/RSYNC_EXCLUDES=\((.*?)^\)/ms', $deploy, $block);

        $this->assertNotEmpty(
            $block,
            'no se ha encontrado el array `RSYNC_EXCLUDES=( … )` en `deploy.sh`: ¿ha cambiado de '.
            'nombre? Sin él esta comprobación no mira nada.',
        );

        $this->assertMatchesRegularExpression(
            "#^\s*--exclude='/public/css/client\.css'#m",
            $block[1],
            "`deploy.sh` no excluye `public/css/client.css` del `rsync --delete` (o la línea está\n".
            "comentada, que es lo mismo).\n".
            "▶ La hoja está gitignorada y no existe en local, así que `--delete` la BORRARÍA del\n".
            "  servidor en el primer despliegue. La web volvería al tema del producto en silencio.\n".
            '▶ Mismo motivo que `--exclude=/public/uploads/`.',
        );
    }

    /**
     * **La guarda de la guarda**: las dos aserciones de fichero caen con su mutación.
     *
     * Se comprueba sobre el texto, sin tocar los ficheros reales: un `--exclude` comentado y una
     * regla de `.gitignore` comentada tienen que dejar de casar. Es lo que la primera versión de
     * este fichero no hacía, y por eso pasaba con el despliegue borrando la hoja del cliente.
     */
    public function test_the_two_file_assertions_reject_a_commented_out_line(): void
    {
        foreach ([
            "#^\s*--exclude='/public/css/client\.css'#m" => [
                "    --exclude='/public/css/client.css'" => true,
                "    #--exclude='/public/css/client.css'" => false,
                "#   --exclude='/public/css/client.css'  (lo de abajo)" => false,
            ],
            '#^/public/css/client\.css\s*$#m' => [
                '/public/css/client.css' => true,
                '# /public/css/client.css' => false,
                '#/public/css/client.css' => false,
            ],
        ] as $pattern => $samples) {
            foreach ($samples as $sample => $shouldMatch) {
                $this->assertSame(
                    $shouldMatch, preg_match($pattern, $sample) === 1,
                    "el patrón «{$pattern}» ".($shouldMatch ? 'ha dejado de cazar' : 'caza').
                    " «{$sample}», y no debería: la guarda vuelve a estar ciega a una línea comentada",
                );
            }
        }
    }

    /**
     * **SI EL PAQUETE REDEFINE LA SOMBRA DURA, TIENE QUE REDEFINIR SUS TRES ESTADOS.**
     *
     * ❗❗❗ **Esto nace de un defecto MEDIDO, no de una precaución** (`#483`). `#478` escribió el
     * hover de la tarjeta de zona con `--shadow-float-hover` y `--shadow-float-press`, y dejó dicho
     * en su propio comentario que *«un paquete de instalación con otra sombra los mueve con ella»*.
     * El paquete **no los declaraba**: solo `--shadow-float`. Así que la tarjeta reposaba con la
     * sombra DURA del cliente (`5px 5px 0`) y al pasar el ratón saltaba a la DIFUSA del producto
     * (`0 6px 14px -8px`) — medido en el navegador, y llevaba así desde que se escribió.
     *
     * ▶ *Un comentario que describe un mecanismo no lo implementa.* Es el mismo patrón que
     * `RhythmScaleTest` fijó para el aire de sección: **los tres o ninguno**.
     *
     * ⚠️ El paquete está **gitignorado**, así que el caso se salta cuando no existe: uno que
     * dependiera de él pasaría aquí y fallaría en un clon limpio.
     */
    public function test_a_package_that_redefines_the_hard_shadow_declares_its_three_states(): void
    {
        $ruta = base_path('public/'.self::CLIENT_SHEET);

        if (! is_file($ruta)) {
            $this->markTestSkipped('no hay paquete de instalación en esta máquina');
        }

        $css = (string) file_get_contents($ruta);

        if (! str_contains($css, '--shadow-float:')) {
            $this->assertTrue(true, 'el paquete no redefine la sombra dura: no hay nada que cuadrar');

            return;
        }

        foreach (['--shadow-float-hover', '--shadow-float-press'] as $token) {
            $this->assertStringContainsString(
                $token.':', $css,
                "el paquete redefine `--shadow-float` y NO declara `{$token}`.\n".
                "▶ Entonces el estado de reposo es el suyo y el de hover cae al del PRODUCTO, que es\n".
                "  difuso: la pegatina reposa con sombra dura y al pasar el ratón se disuelve.\n".
                '  Los tres se declaran juntos, o ninguno.'
            );
        }
    }

    /**
     * **SI EL PAQUETE ACLARA EL VERDE, TIENE QUE DECLARAR SU TINTA** (`#487`).
     *
     * ❗❗❗ **Mismo patrón que la sombra, y otra vez con un número detrás.** La regla dura del sistema
     * dice que *«cian, naranja, lima, amarillo y verde no pueden ser texto sobre claro (< 3,0)»*, y
     * el estado «Abierto ahora» de la sección 07 **es** texto verde sobre una tarjeta blanca.
     *
     * ▶ El producto trae `--ok: #1f7a3d`, que ya es oscuro y se lee, así que `--ok-ink` cae en él y
     * **el defecto del producto es correcto**. Quien lo rompe es un paquete que aclare `--ok` —el de
     * esta instalación lo pone en `#5FA82E`, que sobre blanco da **2,4**— y no declare su tinta.
     * *Y no falla: se ve mal, y solo si alguien mira ese día con el parque abierto.*
     *
     * ⚠️ **`--attn-ink` NO entra en esta guarda**, y es deliberado: su defecto es `--fg`, o sea que
     * un paquete que no lo declare pierde el color pero **nunca la lectura**. Exigirlo sería pedir
     * una línea que no arregla ningún defecto posible.
     */
    public function test_a_package_that_lightens_the_success_green_declares_its_ink(): void
    {
        $ruta = base_path('public/'.self::CLIENT_SHEET);

        if (! is_file($ruta)) {
            $this->markTestSkipped('no hay paquete de instalación en esta máquina');
        }

        $css = (string) file_get_contents($ruta);

        if (! str_contains($css, '--ok:')) {
            $this->assertTrue(true, 'el paquete no redefine el verde: no hay nada que cuadrar');

            return;
        }

        $this->assertStringContainsString(
            '--ok-ink:', $css,
            "el paquete redefine `--ok` y NO declara `--ok-ink`.\n".
            "▶ `--ok-ink` cae entonces en `--ok`, y el verde de una marca casi nunca se lee como\n".
            "  TEXTO sobre blanco: el estado «Abierto ahora» de la sección 07 queda por debajo del\n".
            "  suelo de 3,0 que el propio sistema fija.\n".
            '  El par lo declara quien declara el color; no se deriva (`#434`).'
        );
    }
}
