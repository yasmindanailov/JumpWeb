<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Services\IllustrationKit;
use App\Domain\Identity\Models\User;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La sección de normas: dos requisitos y un asomo** (`DECISIONES #309`,
 * `specs/idioma-visual-heredado.md` §3.nonies).
 *
 * Lo que vigila son las tres cosas que se rompen en silencio:
 *
 *  1. **El ancla `#rules` y su consumidor viajan juntos.** El CTA de la sección de tarifas
 *     («Conoce las reglas para venir») es un ancla dentro de la misma página: si alguien le quita
 *     el `id` a la sección, el enlace **no falla** —lleva a la home y no pasa nada visible—, que es
 *     exactamente el modo de fallo por el que el enlace a `#gallery` sobrevivió a su sección.
 *  2. **Los dos requisitos tienen CTA.** Una tarjeta que dice «obligatorio» y no ofrece hacerlo es
 *     un callejón.
 *  3. **Las tres piezas de fachada se pintan.** El kit lo trae la instalación, así que lo que se
 *     asevera es que la VISTA las pide — no que este ordenador las tenga.
 *
 * ⚠️⚠️ **El kit se instala FALSO en un `public/` temporal.** `public/img/client-kit.svg` está
 * gitignorado: un caso que aseverara contra el kit real pasaría aquí y fallaría en un clon limpio,
 * o pasaría por casualidad porque el kit de esta máquina trae justo la clave buscada. Es el mismo
 * aislamiento que usan `ZonesSectionTest` e `IllustrationHoleRenderTest`.
 */
class RulesSectionTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jw-normas-'.getmypid().'-'.uniqid();
        mkdir($this->publicDir.'/img', 0o777, true);
        @symlink(base_path('public/build'), $this->publicDir.'/build');
        $this->app->usePublicPath($this->publicDir);
        IllustrationKit::forget();

        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    protected function tearDown(): void
    {
        IllustrationKit::forget();

        if ($this->publicDir !== '' && is_dir($this->publicDir)) {
            @unlink($this->publicDir.'/build');
            @unlink($this->publicDir.'/'.IllustrationKit::PATH);
            @rmdir($this->publicDir.'/img');
            @rmdir($this->publicDir);
        }

        parent::tearDown();
    }

    private function instalarKit(string ...$claves): void
    {
        $simbolos = '';

        foreach ($claves as $c) {
            $simbolos .= '<symbol id="'.$c.'" viewBox="0 0 64 64"><title>'.$c.'</title>'
                .'<path d="M8 8h48v48H8Z"/></symbol>';
        }

        file_put_contents(
            public_path(IllustrationKit::PATH),
            '<svg xmlns="http://www.w3.org/2000/svg">'.$simbolos.'</svg>',
        );

        IllustrationKit::forget();
        clearstatcache();
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    /** El subárbol de `<section id="rules">`, aislado del resto del documento. */
    private function seccion(): string
    {
        $html = $this->home();

        $this->assertStringContainsString(
            '<section id="rules"',
            $html,
            'La sección de normas perdió su `id`: el CTA de tarifas apunta a `#rules`.',
        );

        preg_match('#<section id="rules".*?</section>#s', $html, $m);

        return $m[0];
    }

    /**
     * **El ancla de normas sigue existiendo, y HOY NO LA ENLAZA NADIE. Queda dicho.**
     *
     * ⚠️⚠️ Aquí se comprobaba que el ancla y su CTA de tarifas viajaran juntos. El CTA se retira en
     * `#479` (`[DECIDIDO owner, 2026-09-09]`: la sección «Cuánto» del canvas no ofrece más salida
     * que comprar, y lo que hay que traer vive en la sección 05 «Antes de venir»), y **era el ÚNICO
     * enlace a `#rules` de toda la web** — medido: el menú ofrece `#zones`, `#rides` e `#info`, y
     * ninguno lleva a normas.
     *
     * ▶ **El caso se conserva invertido a propósito.** El ancla se queda —la sección existe y sigue
     * siendo destino directo desde fuera—, pero a la sección de normas **ya no se llega navegando**.
     * Es una deuda declarada con dos salidas escritas en `DEUDA.md` (entrar en el menú, o esperar a
     * la sección 05), y este caso es lo que impide que se olvide: el día que alguien vuelva a
     * enlazarla, se pone rojo y obliga a cerrar la ficha.
     */
    public function test_el_ancla_de_normas_existe_y_hoy_nadie_la_enlaza(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('<section id="rules"', $html);

        $this->assertStringNotContainsString(
            'href="#rules"', $html,
            "Alguien ha vuelto a enlazar la sección de normas.\n".
            "▶ Es buena noticia: cierra la deuda que `#479` dejó abierta al retirar el CTA de\n".
            '  tarifas. Retira esta comprobación y su ficha de `DEUDA.md`, y di desde dónde se llega.',
        );
    }

    public function test_los_dos_requisitos_se_pintan_con_su_cta(): void
    {
        $seccion = $this->seccion();

        // Registro: título, la frase del consentimiento y una puerta al alta.
        $this->assertStringContainsString(__('landing.rules.register_title'), $seccion);
        $this->assertStringContainsString(__('landing.rules.register_cta'), $seccion);
        $this->assertStringContainsString('href="'.route('registro').'"', $seccion);

        // Calcetines: título, y el CTA lleva a la compra, que es donde se ofrecen como complemento.
        $this->assertStringContainsString(__('landing.rules.socks_title'), $seccion);
        $this->assertStringContainsString(__('landing.rules.socks_cta'), $seccion);
        $this->assertStringContainsString('href="'.route('entradas').'"', $seccion);

        // Y el asomo lleva a la página que las tiene todas.
        $this->assertStringContainsString('href="'.route('normas').'"', $seccion);
        $this->assertStringContainsString(__('landing.rules.all_cta'), $seccion);
    }

    public function test_con_sesion_no_se_ofrece_el_alta_sino_la_cuenta(): void
    {
        // ⚠️ La tarjeta tiene TRES ramas y ésta es la que una guarda del nav ya vigila por el otro
        // lado (`HomePageTest::test_authenticated_nav_hides_ghost…`): a quien ya tiene cuenta no se
        // le ofrece crearla. Aquí se comprueba sobre la tarjeta, que es la rama nueva.
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user);
        $seccion = $this->seccion();

        $this->assertStringNotContainsString(route('registro'), $seccion);
        $this->assertStringContainsString('href="'.route('account').'"', $seccion);
    }

    public function test_las_dos_manchas_de_fachada_se_piden_al_kit(): void
    {
        $this->instalarKit('slot-normas-registro', 'slot-normas-calcetines');

        $seccion = $this->seccion();

        $this->assertStringContainsString('#slot-normas-registro', $seccion);
        $this->assertStringContainsString('#slot-normas-calcetines', $seccion);
    }

    public function test_sin_kit_la_seccion_no_deja_cajas_vacias(): void
    {
        // El modo de fallo elegido es la INVISIBILIDAD (`hueco-ilustracion.md` §7): sin paquete no
        // se emite ni el `<svg>`. ⚠️ Con `setUp` el `public/` temporal está vacío, así que este caso
        // es el estado por defecto — y es el de cualquier instalación sin kit.
        $seccion = $this->seccion();

        $this->assertStringNotContainsString('slot-normas-registro', $seccion);
        $this->assertStringNotContainsString('<svg class="ilu', $seccion);
        // Pero el CONTENIDO sigue entero: el dibujo es decoración, no el mensaje.
        $this->assertStringContainsString(__('landing.rules.register_title'), $seccion);
        $this->assertStringContainsString(__('landing.rules.socks_title'), $seccion);
    }

    /**
     * **La sección de tarifas NO pide ninguna pieza de dibujo, y eso es lo que se vigila ahora.**
     *
     * ⚠️⚠️ Aquí había el caso contrario —que el friso `slot-tarifas` se pidiera al kit— y **se
     * retira CON SU SUJETO** (`#479`, `[DECIDIDO owner, 2026-09-09]`): el artboard de la sección 02
     * no lleva ilustración, así que el friso sale y su ranura se retira de `IllustrationKit::SLOTS`.
     *
     * ▶ **No se borra el caso: se le da la vuelta.** Un `assertStringNotContains` aquí no es celo:
     * la ranura vive en una constante y la sección en una plantilla, y **volver a pintar el friso
     * sin volver a declarar la ranura deja el `<use>` apuntando a un símbolo que el kit ya no
     * construye** — un dibujo vacío de 190×150, que es exactamente el defecto que `#287` midió.
     */
    public function test_la_seccion_de_tarifas_no_pide_ningun_dibujo_al_kit(): void
    {
        $this->instalarKit('slot-zonas');

        $html = $this->home();
        preg_match('#<section id="pricing".*?</section>#s', $html, $m);

        $this->assertNotEmpty($m, 'la sección de tarifas perdió su `id`: este caso miraría el vacío.');
        $this->assertStringNotContainsString('slot-tarifas', $m[0]);
        $this->assertStringNotContainsString('<use', $m[0]);
    }

    public function test_la_seccion_en_directo_ya_no_existe_ni_su_enlace(): void
    {
        // ⚠️ **Las dos mitades.** Retirar la sección y dejar el enlace del pie es un ancla a la nada
        // que **no falla**: lleva a la home. Es el defecto que hubo que buscar a mano al retirarla.
        $html = $this->home();

        $this->assertStringNotContainsString('id="gallery"', $html);
        $this->assertStringNotContainsString('gallery-marquee', $html);
        $this->assertStringNotContainsString('/#gallery', $html);
    }
}
