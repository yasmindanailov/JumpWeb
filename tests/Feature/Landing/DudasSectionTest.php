<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La sección 08 «Dudas»** (`DECISIONES #488`, carril de diseño Fase 2 · T2h).
 * Artboards `Dudas PJP` 1a (móvil) y `Escritorio PJP` 5c (escritorio).
 *
 * ⚠️ **Esto NO duplica a {@see FaqAccordionTest}**, que vigila el MECANISMO del despliegue (que
 * anime la rejilla y no vuelva el tope de 240 px). Aquí se vigila la SECCIÓN: qué se pinta, qué no
 * se pinta y qué no puede volver a aparecer.
 *
 * Lo que se rompe en silencio y por eso tiene caso:
 *
 *  1. **Con el panel vacío la sección entera desaparece.** Es regla dura del sistema para toda
 *     sección cuyo contenido pone el panel: *«ni rótulo, ni titular, ni caja vacía»*. Sin el `@if`
 *     una instalación sin preguntas publica una cabecera que no presenta nada, una tarjeta de cero
 *     filas y un `FAQPage` vacío para Google — y **la página sigue devolviendo 200**.
 *  2. **El titular es una FRASE, no el rótulo.** El titular decía «Dudas», que es lo que hoy dice
 *     el rótulo. `doc/voz.md` fija los ocho titulares entre **3 y 6 palabras** porque «el rótulo ya
 *     dice el eje de la pregunta»; volver a poner una sola palabra no rompe nada.
 *  3. **Todas nacen CERRADAS.** El producto arrancaba en `faqOpen: 0`. Es un valor de JavaScript:
 *     no se ve en el HTML servido y ninguna guarda de marcado puede verlo.
 *  4. **Cero salida.** El cierre está pegado debajo con el teléfono y el pie con el correo, así que
 *     Dudas no repite el canal. Un CTA añadido aquí se vería «bien» y contradiría la decisión.
 *  5. **El filete va ENTRE filas.** Un borde superior en la primera se suma al de la tarjeta y
 *     salen dos líneas seguidas — exactamente lo que el owner señaló en `#486`.
 *  6. **El signo son dos iconos y ninguno gira.** Girar el «+» 45° da una «×», que significa
 *     cerrar, no plegar; y era la conducta anterior, así que es lo que alguien repondría.
 */
class DudasSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
    }

    private function sembrar(int $cuantas = 3): void
    {
        for ($i = 1; $i <= $cuantas; $i++) {
            Faq::query()->create([
                'question' => ['es' => "¿Pregunta {$i}?"],
                'answer' => ['es' => "Respuesta {$i}."],
                'position' => $i,
                'is_active' => true,
            ]);
        }
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    /**
     * El subárbol de `<section id="faq">`, aislado.
     *
     * ⚠️ **Se acota al ELEMENTO, no «hasta la siguiente sección»**: un localizador que depende de
     * qué viene DESPUÉS no acota una sección, acota un tramo de página (`#314`, y `#483` lo volvió
     * a pagar). Y se comprueba que el `id` existe antes de recortar, o el caso miraría el vacío.
     */
    private function seccion(): string
    {
        $html = $this->home();

        $this->assertStringContainsString(
            '<section id="faq"',
            $html,
            'La sección «Dudas» perdió su `id`: este caso miraría el vacío.',
        );

        preg_match('#<section id="faq".*?</section>#s', $html, $m);

        return $m[0];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · El panel vacío
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Sin preguntas activas no se pinta NADA de la sección.**
     *
     * ⚠️ Se comprueban las cuatro piezas por separado —sección, rótulo, titular y datos
     * estructurados— porque cada una tiene su propia forma de sobrevivir: el `@if` puede quedarse
     * envolviendo solo la tarjeta, o solo la cabecera, o dejar fuera el `FAQPage`.
     */
    public function test_con_el_panel_vacio_la_seccion_entera_desaparece(): void
    {
        $this->assertSame(0, Faq::query()->count(), 'el caso arranca con preguntas: no mediría la ausencia');

        $html = $this->home();

        $this->assertStringNotContainsString('<section id="faq"', $html, 'la sección se pinta sin preguntas');
        $this->assertStringNotContainsString('sec-head__eyebrow">Dudas', $html, 'el rótulo sobrevive a la sección');
        $this->assertStringNotContainsString(__('landing.faq.title'), $html, 'el titular sobrevive a la sección');
        $this->assertStringNotContainsString('faq__item', $html, 'queda la caja del acordeón, vacía');
        $this->assertStringNotContainsString('FAQPage', $html, 'se declara un `FAQPage` sin ninguna pregunta dentro');
    }

    /** Y con una sola pregunta activa **sí** se pinta: el control de la anterior. */
    public function test_con_una_sola_pregunta_activa_la_seccion_se_pinta(): void
    {
        $this->sembrar(1);

        $html = $this->home();

        $this->assertStringContainsString('<section id="faq"', $html);
        $this->assertStringContainsString('FAQPage', $html);
    }

    /** Una pregunta desactivada desde el panel no cuenta: es el mismo vacío. */
    public function test_una_pregunta_desactivada_no_levanta_la_seccion(): void
    {
        Faq::query()->create([
            'question' => ['es' => '¿Escondida?'],
            'answer' => ['es' => 'No sale.'],
            'position' => 1,
            'is_active' => false,
        ]);

        $this->assertStringNotContainsString('<section id="faq"', $this->home());
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · La cabecera común de las ocho
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La cabecera es `.sec-head`: rótulo · titular · entradilla.**
     *
     * ⚠️ La vieja era un `<h2 class="rides__title">` suelto, sin rótulo ni entradilla, dentro de la
     * propia rejilla del acordeón.
     */
    public function test_la_seccion_abre_con_la_cabecera_comun_de_las_ocho(): void
    {
        $this->sembrar();
        $seccion = $this->seccion();

        $this->assertStringContainsString('class="sec-head"', $seccion);
        $this->assertStringContainsString('<p class="sec-head__eyebrow">'.__('landing.faq.eyebrow').'</p>', $seccion);
        $this->assertStringContainsString('<h2 class="sec-head__title">'.__('landing.faq.title').'</h2>', $seccion);
        $this->assertStringContainsString('<p class="sec-head__lede">'.__('landing.faq.lede').'</p>', $seccion);
    }

    /**
     * **El titular es una frase de 3 a 6 palabras y NO es el rótulo.**
     *
     * Es la regla del sistema para las ocho secciones, y aquí es donde se rompía: el titular decía
     * literalmente «Dudas», o sea el rótulo, con una sola palabra.
     */
    public function test_el_titular_es_una_frase_y_no_el_rotulo(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            app()->setLocale($locale);

            $titulo = (string) __('landing.faq.title');
            $rotulo = (string) __('landing.faq.eyebrow');
            $palabras = count(preg_split('/\s+/', trim($titulo)) ?: []);

            $this->assertNotSame($rotulo, $titulo, "[{$locale}] el titular repite el rótulo");
            $this->assertGreaterThanOrEqual(3, $palabras, "[{$locale}] el titular tiene menos de 3 palabras: es una etiqueta, no una frase");
            $this->assertLessThanOrEqual(6, $palabras, "[{$locale}] el titular pasa de 6 palabras");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · Todas cerradas al cargar
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **`faqOpen` arranca en −1: ninguna abierta.**
     *
     * ⚠️⚠️ **Se comprueban el FUENTE y el BUNDLE, y no es redundancia.** El valor vive en
     * JavaScript, así que no se ve en el HTML servido; y `public/build` viaja en el repo, de modo
     * que cambiar el fuente sin compilar deja el sitio con la conducta vieja **con el fuente ya
     * corregido y la suite en verde** — la trampa que `#475` pagó por la otra puerta.
     */
    public function test_ninguna_duda_nace_abierta(): void
    {
        $fuente = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertMatchesRegularExpression(
            '/faqOpen:\s*-1\b/',
            $fuente,
            '`faqOpen` ya no arranca en −1: la portada abre una duda al cargar y empuja las demás fuera del pulgar.',
        );

        // ⚠️ `public/build` NO está versionado. El `pre-push` corre `npm run build` ANTES de la
        // suite justo por esto (`PrePushGateTest`), así que en el gate siempre existe; si alguien
        // corre la suite a mano sin construir, el caso lo DICE en vez de fallar en falso — el mismo
        // trato que `SidebarBundleBudgetTest`.
        $bundles = glob(base_path('public/build/assets/app-*.js')) ?: [];
        $this->assertNotEmpty($bundles, 'No hay `public/build`: corre `npm run build` antes de la suite. El `pre-push` ya lo hace.');

        $compilado = (string) file_get_contents($bundles[0]);
        $this->assertMatchesRegularExpression(
            '/faqOpen:\s*-1\b/',
            $compilado,
            'el bundle sigue trayendo el arranque viejo: falta `npm run build`.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  4 · Cero salida
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La sección no ofrece ninguna salida.**
     *
     * `doc/reglas.md`: preguntar vive en el cierre, que está pegado debajo con el teléfono, y el
     * correo está en el pie. Ni enlace, ni CTA, ni relleno de acción: los únicos pulsables son los
     * que pliegan y despliegan, uno por duda.
     */
    public function test_la_seccion_no_ofrece_ninguna_salida(): void
    {
        $this->sembrar(4);
        $seccion = $this->seccion();

        $this->assertSame(0, preg_match_all('/<a\s/', $seccion), 'la sección de Dudas ha ganado un enlace');
        $this->assertSame(
            4,
            preg_match_all('/<button\s/', $seccion),
            'hay más botones que dudas: alguno no es el de plegar.',
        );
        $this->assertStringNotContainsString('btn', $seccion, 'ha entrado un botón de la familia `.btn` en una sección sin salida');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  5 · La tarjeta y su filete
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El acordeón es UNA tarjeta y el filete va ENTRE filas.**
     *
     * ⚠️ Un borde superior en `.faq__item` a secas se suma al borde de la tarjeta y salen dos
     * líneas seguidas en la primera fila: el defecto que el owner señaló en `#486` con la costura
     * entre secciones. Por eso el selector tiene que ser el hermano adyacente.
     */
    public function test_la_tarjeta_lleva_su_borde_y_el_filete_va_entre_filas(): void
    {
        $reglas = $this->reglasCss();

        $this->assertArrayHasKey('.faq', $reglas, 'la tarjeta del acordeón no tiene regla');
        $this->assertMatchesRegularExpression('/background:\s*var\(--bg-card\)/', $reglas['.faq'], 'la tarjeta no es blanca');
        $this->assertMatchesRegularExpression('/border:\s*1px solid var\(--line\)/', $reglas['.faq'], 'la tarjeta perdió su borde de Línea');
        $this->assertMatchesRegularExpression('/border-radius:\s*var\(--r-lg\)/', $reglas['.faq'], 'el canto de la tarjeta dejó de salir de la escala');
        $this->assertMatchesRegularExpression('/overflow:\s*hidden/', $reglas['.faq'], 'sin recorte, la primera y la última fila se salen del radio');

        $this->assertArrayHasKey(
            '.faq__item + .faq__item',
            $reglas,
            'el filete ya no va entre filas: si va en `.faq__item` a secas, la primera suma dos líneas.',
        );
        $this->assertArrayNotHasKey('.faq__item', $reglas, 'ha vuelto una regla de `.faq__item` suelta, que es donde vivía el filete de más');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  6 · El signo
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El signo son dos iconos del set y ninguno gira.**
     *
     * El artboard dibuja «+» y «–»; el producto giraba un solo «+» 45°, que da una «×» — y una «×»
     * significa cerrar, no plegar. Los dos van en el marcado y el estado decide cuál se ve.
     */
    public function test_el_signo_son_dos_iconos_y_ninguno_gira(): void
    {
        $this->sembrar(2);
        $seccion = $this->seccion();

        $this->assertSame(2, preg_match_all('/faq__sign-i--mas/', $seccion), 'falta el «+» en alguna duda');
        $this->assertSame(2, preg_match_all('/faq__sign-i--menos/', $seccion), 'falta el «–» en alguna duda');

        $reglas = $this->reglasCss();
        foreach ($reglas as $selector => $cuerpo) {
            if (! str_contains($selector, 'faq')) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '/rotate\(/',
                $cuerpo,
                "`{$selector}` vuelve a girar el signo: un «+» a 45° es una «×», que significa cerrar.",
            );
        }
    }

    /**
     * **El pulsable mide 64 y por eso no necesita `data-tap`.**
     *
     * El suelo del sistema son 48 «sin excepciones». Si alguien baja el alto, el área táctil se
     * queda corta **y no hay pseudo que la rescate**, porque se retiró a propósito.
     */
    public function test_el_pulsable_de_cada_duda_mide_64(): void
    {
        $reglas = $this->reglasCss();

        $this->assertArrayHasKey('.faq__q', $reglas);
        $this->assertMatchesRegularExpression('/min-height:\s*64px/', $reglas['.faq__q'], 'el pulsable de la duda dejó de medir 64');

        $this->sembrar(1);
        $this->assertStringNotContainsString(
            'data-tap',
            $this->seccion(),
            'ha vuelto `data-tap`: con un pulsable de 64 px por el ancho entero solo añade una capa que se solapa con la fila de al lado.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  7 · La rejilla de escritorio
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **En escritorio la cabecera ocupa cuatro columnas y el acordeón ocho.**
     *
     * ⚠️⚠️ **La rejilla tiene que ser de DOCE pistas, no `1fr 2fr`.** Con doce y 32 de gap sobre la
     * columna de 1120 cada pista mide 64, así que `span 4` da **352** y `span 8` da **736**, que son
     * las dos cifras que `Escritorio PJP` 5c midió en el DOM. Con `1fr 2fr` salen 362,67 y 725,33:
     * se parece, no falla nada y **no es su número**.
     *
     * ⚠️ El acordeón no puede estirarse a las doce: la respuesta más larga daría líneas de 140
     * caracteres contra el máximo de 70 del sistema.
     */
    public function test_en_escritorio_la_cabecera_ocupa_cuatro_columnas_y_el_acordeon_ocho(): void
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));

        $this->assertMatchesRegularExpression(
            '/\.faq-sec\s*\{[^}]*grid-template-columns:\s*repeat\(12,\s*1fr\)/s',
            $css,
            'la rejilla de `.faq-sec` dejó de tener doce pistas: los anchos ya no son 352 y 736.',
        );

        $reglas = $this->reglasCss();
        $this->assertArrayHasKey('.faq-sec > .sec-head', $reglas);
        $this->assertArrayHasKey('.faq-sec > .faq', $reglas);
        $this->assertMatchesRegularExpression('/grid-column:\s*span 4/', $reglas['.faq-sec > .sec-head'], 'la cabecera dejó de ocupar cuatro columnas');
        $this->assertMatchesRegularExpression('/grid-column:\s*span 8/', $reglas['.faq-sec > .faq'], 'el acordeón dejó de ocupar ocho columnas');
        $this->assertMatchesRegularExpression(
            '/margin-bottom:\s*0/',
            $reglas['.faq-sec > .sec-head'],
            'la cabecera conserva su margen inferior dentro de la rejilla: separa de nada y desalinea la primera pregunta.',
        );
    }

    /**
     * Las reglas de `landing.css`, con los comentarios enmascarados.
     *
     * ⚠️ La máscara no es adorno: esta hoja tiene **llaves y nombres de clase dentro de
     * comentarios** (`#482`), y un analizador que no los tape abre reglas donde no las hay.
     *
     * @return array<string, string>
     */
    private function reglasCss(): array
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));
        $ciego = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $ciego, $matches, PREG_SET_ORDER);

        $out = [];
        foreach ($matches as $rule) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
            if ($selector === '' || str_starts_with($selector, '@')) {
                continue;
            }
            $out[$selector] = ($out[$selector] ?? '').' '.trim($rule[2]);
        }

        return $out;
    }
}
