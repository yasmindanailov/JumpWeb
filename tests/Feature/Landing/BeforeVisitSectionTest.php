<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Services\IllustrationKit;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\SampleQrCode;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La sección 05 «Antes de venir»: el registro ES el QR** (`DECISIONES #485`, carril de diseño
 * Fase 2 · T2f).
 *
 * ⚠️⚠️ **SUSTITUYE A `RulesSectionTest`**, que se retiró con su sujeto: la sección de normas de la
 * portada (`#309`) ya no existe. De aquélla sobreviven aquí los dos casos que **no eran suyos** —el
 * de «En directo» y el del friso de tarifas—, porque su sujeto sigue vivo y el arnés del kit que
 * necesitan vive en este fichero.
 *
 * Lo que vigila son las cosas que se rompen **en silencio**:
 *
 *  1. **El código es de EJEMPLO.** Si alguien lo cambia por el generador de verdad, la portada pasa
 *     a emitir un código que escanea y el rótulo «de ejemplo» se vuelve mentira. No falla nada.
 *  2. **Con sesión no se ofrece crear cuenta.** Es el defecto que una guarda del nav ya cazó una vez
 *     (`#309`): ofrecer el alta a quien ya la tiene.
 *  3. **La salida a `/normas` existe.** Es la que cierra el agujero de `#480` —donde quedó la única
 *     entrada a esa página fuera del pie—, y un enlace retirado no rompe nada visible.
 *  4. **La línea del niño invitado es DATO.** Se pinta si y solo si algún producto ofrece el
 *     justificante; escribirla siempre prometería algo que el catálogo no emite.
 *
 * ⚠️ **El kit se instala FALSO en un `public/` temporal**, como hacía `RulesSectionTest`:
 * `public/img/client-kit.svg` está gitignorado, así que un caso que aseverara contra el kit real
 * pasaría aquí y fallaría en un clon limpio.
 */
class BeforeVisitSectionTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jw-antes-'.getmypid().'-'.uniqid();
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

    /**
     * El subárbol de `<section id="before">`, aislado.
     *
     * ⚠️ **Se acota al ELEMENTO y no a «hasta la siguiente sección»**: un localizador que dependa de
     * qué viene DESPUÉS no acota una sección, acota un tramo de página — la trampa que `#314` pagó
     * con `ZonesSectionTest` y `#483` volvió a pagar con `RateRailSectionTest`.
     */
    private function seccion(): string
    {
        $html = $this->home();

        $this->assertStringContainsString(
            '<section id="before"',
            $html,
            'La sección «Antes de venir» perdió su `id`: este caso miraría el vacío.',
        );

        preg_match('#<section id="before".*?</section>#s', $html, $m);

        return $m[0];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El código de ejemplo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El código que se pinta es el de EJEMPLO, no el generador de verdad**
     * (`[DECIDIDO owner, 2026-09-10]`: «de ejemplo, no lleva a nada»).
     *
     * ⚠️⚠️ Se comprueba comparando con el trazado que emite {@see SampleQrCode}, no buscando una
     * cadena suelta: si alguien sustituye la pieza por `QrCode::svg()`, el `<path>` deja de
     * coincidir y esto se pone rojo. Aseverar que existe «un svg» pasaría con las dos.
     */
    public function test_el_codigo_es_el_de_ejemplo_y_no_el_generador_de_verdad(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString(SampleQrCode::path(), $seccion);
        $this->assertStringContainsString('viewBox="0 0 '.SampleQrCode::MODULES.' '.SampleQrCode::MODULES.'"', $seccion);
    }

    /**
     * **El nombre accesible dice «de ejemplo».**
     *
     * Sin eso, un lector de pantalla anuncia un código y quien lo oiga entenderá que hay algo que
     * escanear. No lo hay, y es la única forma de decirlo a quien no ve la etiqueta mono.
     */
    public function test_el_nombre_accesible_avisa_de_que_es_un_ejemplo(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString('aria-label="'.__('landing.before.qr_aria').'"', $seccion);
        $this->assertStringContainsString(__('landing.before.qr_sample'), $seccion);
    }

    /**
     * **El código NO puede decodificarse, y esto lo comprueba por ESTRUCTURA.**
     *
     * La banda de la **información de formato** —los 15 módulos que rodean los localizadores y que
     * dicen nivel de corrección y máscara— queda vacía, y es lo que hace que ningún lector llegue
     * siquiera a leer datos: rechaza el símbolo antes. Si alguien «mejorara» el generador
     * rellenándola, el dibujo pasaría a ser un símbolo con formato, y el rótulo «de ejemplo» dejaría
     * de ser verdad.
     *
     * ⚠️⚠️ **Se comprueba sobre la REJILLA, no sobre la cadena del trazado, y lo obligó el arnés.**
     * La primera versión miraba dos coordenadas concretas (`M0 7h…`) y **una mutación que rellenaba
     * la zona reservada pasó en verde**: aseverar dos puntos de un dibujo no vigila una región.
     */
    public function test_el_dibujo_deja_vacia_la_banda_del_formato(): void
    {
        $rejilla = $this->rejillaDelCodigo();
        $n = SampleQrCode::MODULES;

        // Guarda de la guarda: sin módulos oscuros, todo lo de abajo pasaría mirando el vacío.
        $oscuros = array_sum(array_map('array_sum', $rejilla));
        $this->assertGreaterThan(200, $oscuros, 'el dibujo salió vacío: las comprobaciones de abajo no miran nada');

        // La banda de formato del localizador superior izquierdo: la columna 8 y la fila 8, más los
        // separadores de 1 módulo que rodean a los tres localizadores.
        // ⚠️ **La línea de SINCRONÍA cruza la banda de formato y ahí sí va oscura**: en un símbolo
        // real (8,6) y (6,8) son módulos de sincronía, no de formato. Excluirlos no afloja nada —los
        // vigila el caso de la anatomía— y sin ellos esta lista acusaba al producto sano.
        $vacios = [];
        // La banda del localizador ↖: columna 8 y fila 8, de 0 a 8.
        foreach (range(0, 8) as $i) {
            if ($i === 6) {
                continue;
            }
            $vacios[] = [8, $i];
            $vacios[] = [$i, 8];
        }
        // Las bandas de los otros dos: los ocho módulos pegados a cada localizador.
        foreach (range($n - 8, $n - 1) as $i) {
            $vacios[] = [8, $i];
            $vacios[] = [$i, 8];
        }
        foreach (range(0, 7) as $i) {
            $vacios[] = [7, $i];          // separador vertical del localizador ↖
            $vacios[] = [$i, 7];          // separador horizontal del localizador ↖
            $vacios[] = [$n - 8, $i];     // separador del localizador ↗
            $vacios[] = [$i, $n - 8];     // separador del localizador ↙
        }

        foreach ($vacios as [$x, $y]) {
            $this->assertSame(
                0, $rejilla[$y][$x],
                "El módulo ({$x}, {$y}) está oscuro, y esa zona tiene que quedar VACÍA: es donde vive\n".
                "la información de formato y los separadores de los localizadores.\n".
                '▶ Rellenarla convierte el dibujo en un símbolo que un lector empieza a interpretar.',
            );
        }
    }

    /**
     * La rejilla de módulos que el trazado dibuja, reconstruida desde su atributo `d`.
     *
     * ⚠️ El generador funde los módulos contiguos de una fila en un solo tramo (`h{n}`), así que hay
     * que expandirlos: contar «M» daría módulos y no es lo mismo.
     *
     * @return array<int, array<int, int>>
     */
    private function rejillaDelCodigo(): array
    {
        $n = SampleQrCode::MODULES;
        $rejilla = array_fill(0, $n, array_fill(0, $n, 0));

        preg_match_all('/M(\d+) (\d+)h(\d+)/', SampleQrCode::path(), $tramos, PREG_SET_ORDER);
        foreach ($tramos as [, $x, $y, $ancho]) {
            for ($i = 0; $i < (int) $ancho; $i++) {
                $rejilla[(int) $y][(int) $x + $i] = 1;
            }
        }

        return $rejilla;
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Las dos salidas
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **La sección lleva a `/normas`**, que es la entrada que `#480` dejó rota. */
    public function test_la_seccion_lleva_a_normas(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString('href="'.route('normas').'"', $seccion);
        $this->assertStringContainsString(__('landing.before.all_rules'), $seccion);
    }

    /** Sin sesión, la salida es el alta — con su suelo sin JavaScript. */
    public function test_sin_sesion_se_ofrece_crear_la_cuenta(): void
    {
        $seccion = $this->seccion();

        $this->assertStringContainsString('href="'.route('registro').'"', $seccion);
        $this->assertStringContainsString(__('landing.before.cta'), $seccion);
    }

    /**
     * **Con sesión NO se ofrece crear cuenta: se ofrece ver el QR.**
     *
     * ⚠️ Y abre el cajón en su zona `card`, no en el índice: la sección habla del código, así que
     * llevar a la portada de la cuenta obligaría a un segundo clic para llegar a lo que se acaba de
     * enseñar.
     */
    public function test_con_sesion_se_ofrece_ver_el_qr_y_no_el_alta(): void
    {
        $this->actingAs(User::factory()->create());

        $seccion = $this->seccion();

        $this->assertStringNotContainsString(route('registro'), $seccion);
        $this->assertStringContainsString('href="'.route('account').'"', $seccion);
        $this->assertStringContainsString("openAccount(\$event, 'card')", $seccion);
        $this->assertStringContainsString(__('landing.before.cta_account'), $seccion);
    }

    /**
     * **Con registro EXTERNO manda su URL**, que es la tercera rama de `<x-site.cta-pair>`: si la
     * instalación tiene su propio sistema de registro, el alta interna no se ofrece.
     */
    public function test_con_registro_externo_la_salida_es_su_url(): void
    {
        Setting::updateOrCreate(['key' => 'registration.url'], ['value' => 'https://registro.example.com/alta']);
        Setting::flushMemo();

        $seccion = $this->seccion();

        $this->assertStringContainsString('https://registro.example.com/alta', $seccion);
        $this->assertStringNotContainsString(route('registro'), $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que es DATO
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La línea del niño invitado sale si y solo si algún producto ofrece el justificante.**
     *
     * ⚠️ Las dos mitades. Solo el `assertDontSee` pasaría en verde con la línea borrada del Blade,
     * y solo el `assertSee` pasaría en verde con la línea escrita a fuego.
     */
    public function test_la_linea_del_nino_invitado_sigue_al_catalogo(): void
    {
        $this->assertStringNotContainsString(__('landing.before.guest_text'), $this->seccion());

        // ⚠️ **Sin `limit()` y comprobando la escritura.** `UPDATE … LIMIT` no está disponible en
        // todas las compilaciones de SQLite, así que un `->limit(1)->update()` puede no escribir
        // nada y dejar el caso montando un escenario que no existe — la lección de `#480` por otra
        // puerta. Se toma una fila concreta y se afirma que quedó escrita.
        $producto = TicketType::query()->firstOrFail();
        $producto->forceFill(['guardian_authorization' => 'optional'])->save();
        $this->assertSame('optional', $producto->fresh()->guardian_authorization);

        $this->assertStringContainsString(__('landing.before.guest_text'), $this->seccion());
    }

    /**
     * **La sección NO pide ninguna pieza de dibujo al kit** (`#485`).
     *
     * ⚠️ Es el caso invertido que `#479` dejó escrito para la sección de tarifas, por el mismo
     * motivo: la ranura vive en una constante y la sección en una plantilla, así que **volver a
     * pintar un dibujo sin volver a declarar su ranura deja el `<use>` apuntando a un símbolo que el
     * kit ya no construye** — un `<svg>` vacío de 190×150, el defecto que `#287` midió.
     */
    public function test_la_seccion_no_pide_ningun_dibujo_al_kit(): void
    {
        // ⚠️⚠️ **El kit se instala CON las ranuras retiradas, y lo obligó el arnés.** La primera
        // versión instalaba solo `slot-zonas` y **una mutación que devolvía el dibujo a la sección
        // pasó en verde**: sin la clave en el kit, `<x-site.ilu>` no emite nada (el modo de fallo
        // invisible de `#287`), así que la guarda miraba una ausencia que el propio arnés causaba.
        // ▶ Con la clave presente, si alguien vuelve a pintar un dibujo aquí, se ve.
        $this->instalarKit('slot-zonas', 'slot-normas-registro', 'slot-normas-calcetines');

        $seccion = $this->seccion();

        $this->assertStringNotContainsString('slot-normas', $seccion);
        $this->assertStringNotContainsString('<use', $seccion);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Heredados de `RulesSectionTest`: su sujeto NO era la sección de normas
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La sección de tarifas NO pide ninguna pieza de dibujo al kit** (`#479`).
     *
     * Se conserva tal cual: su sujeto es la sección 02, que sigue viva.
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

    /** **«En directo» sigue sin existir, ni ella ni su enlace del pie** (`#309`). */
    public function test_la_seccion_en_directo_ya_no_existe_ni_su_enlace(): void
    {
        $html = $this->home();

        $this->assertStringNotContainsString('id="gallery"', $html);
        $this->assertStringNotContainsString('gallery-marquee', $html);
        $this->assertStringNotContainsString('/#gallery', $html);
    }

    /**
     * **Y el ancla `#rules` se ha ido con su sección, sin dejar ningún enlace roto** (`#485`).
     *
     * ⚠️ Medido antes de retirarla: cero `href="#rules"` en todo el repo —el único lo quitó `#479`—.
     * Este caso es lo que impide que alguien vuelva a enlazarla sin darse cuenta de que ya no está.
     */
    public function test_el_ancla_de_normas_se_fue_y_nadie_la_enlaza(): void
    {
        $html = $this->home();

        // ⚠️ **Se asevera el ANCLA, no el `<section>`, y lo obligó el arnés.** La primera versión
        // decía `'<section id="rules"'` y una mutación que devolvía el ancla en un `<span>` pasó en
        // verde: *lo que la propiedad dice es que ese destino ya no existe, no que no exista una
        // sección con ese nombre.*
        $this->assertStringNotContainsString('id="rules"', $html);
        $this->assertStringNotContainsString('href="#rules"', $html);
    }
}
