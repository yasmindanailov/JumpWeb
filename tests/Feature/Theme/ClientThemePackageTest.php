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
}
