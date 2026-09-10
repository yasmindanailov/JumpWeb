<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\CmsSocialProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La sección 06 «Reseñas», con las opiniones propias** (`DECISIONES #490`, Fase 2 · T2i·a).
 * Artboards `Resenas PJP` 2a y `Escritorio PJP` 5b.
 *
 * Lo que se rompe en silencio y por eso tiene caso:
 *
 *  1. **La sección entera desaparece con cero opiniones.** Regla dura del sistema, y el propio
 *     artboard la dibuja: «la sección no se pinta y la portada pasa de Antes de venir a Visítanos».
 *  2. **La cifra agregada NO se compone con opiniones propias.** Calcularla daría un número real
 *     —la media de lo que el parque ha escrito de sí mismo— y publicarlo junto a las cinco
 *     estrellas del sistema lo haría pasar por la nota de Google. Nadie lo vería fallar.
 *  3. **La atribución de Google NO viaja en una opinión propia.** Es el defecto que un decorador mal
 *     escrito produce —hereda la vista de la otra fuente— y que la spec pide vigilar expresamente
 *     (§6·3.bis). Hoy no hay decorador, así que este caso es la red que lo esperará.
 *  4. **La primera nace con `is-on` puesta por el SERVIDOR**, que es el suelo sin JavaScript. Con
 *     `x-show` + `x-cloak` la sección se queda vacía sin JS, y eso no lo ve ninguna prueba de PHP
 *     que solo mire que el marcado existe.
 *  5. **La vista pide el CONTRATO, no el modelo.** Es lo que hace que el día que entre Google no
 *     haya que tocar ni el controlador ni la plantilla: se cambia el binding.
 */
class ReviewsSectionTest extends TestCase
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
            Testimonial::query()->create([
                'text' => ['es' => "Opinión número {$i}."],
                'author' => "Persona {$i}",
                'rating' => 5,
                'published_at' => now()->subMonths($i),
                'position' => $i,
                'is_active' => true,
            ]);
        }
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    /** ⚠️ Se acota al ELEMENTO, no «hasta la siguiente sección» (`#314`). */
    private function seccion(): string
    {
        $html = $this->home();

        $this->assertStringContainsString('<section id="reviews"', $html, 'La sección perdió su `id`: este caso miraría el vacío.');
        preg_match('#<section id="reviews".*?</section>#s', $html, $m);

        return $m[0];
    }

    // ── 1 · El panel vacío ───────────────────────────────────────────────────────────

    public function test_sin_opiniones_la_seccion_entera_desaparece(): void
    {
        $this->assertSame(0, Testimonial::query()->count(), 'el caso arranca con opiniones: no mediría la ausencia');

        $html = $this->home();

        $this->assertStringNotContainsString('<section id="reviews"', $html, 'la sección se pinta sin opiniones');
        $this->assertStringNotContainsString(__('landing.reviews.title'), $html, 'el titular sobrevive a la sección');
        $this->assertStringNotContainsString('rev__card', $html, 'queda la caja del carril, vacía');
    }

    /** El control de la anterior: con una sola opinión la sección SÍ se pinta. */
    public function test_con_una_sola_opinion_la_seccion_se_pinta(): void
    {
        $this->sembrar(1);

        $this->assertStringContainsString('<section id="reviews"', $this->home());
    }

    public function test_una_opinion_desactivada_no_levanta_la_seccion(): void
    {
        Testimonial::query()->create([
            'text' => ['es' => 'Escondida.'], 'author' => 'Nadie', 'position' => 1, 'is_active' => false,
        ]);

        $this->assertStringNotContainsString('<section id="reviews"', $this->home());
    }

    // ── 2 · La cifra agregada ────────────────────────────────────────────────────────

    /**
     * **La media NO se compone con opiniones propias, ni siquiera con cinco de cinco estrellas.**
     *
     * ⚠️ El caso siembra tres con nota para que exista una media que calcular: sin ellas estaría
     * comprobando que `null` es `null` sobre una tabla vacía, que es no comprobar nada.
     */
    public function test_la_cifra_agregada_no_se_compone_con_opiniones_propias(): void
    {
        $this->sembrar();

        $this->assertNull(
            (new CmsSocialProof)->rating(),
            'El respaldo del CMS compone una media agregada: publicarla junto a las estrellas del '.
            'sistema la haría pasar por la nota de Google, que es un número que Google no ha dado.',
        );
    }

    // ── 3 · La atribución no se hereda ───────────────────────────────────────────────

    /**
     * **Una opinión propia sale sin nada de Google**: ni avatar, ni enlace al perfil, ni salida.
     */
    public function test_una_opinion_propia_no_sale_vestida_de_google(): void
    {
        $this->sembrar();
        $seccion = $this->seccion();

        $this->assertStringNotContainsString('googleusercontent', $seccion, 'viaja el avatar de Google en una opinión propia');
        $this->assertStringNotContainsString('maps.google', $seccion, 'viaja un enlace de Google en una opinión propia');
        $this->assertStringNotContainsString('google', mb_strtolower($seccion), 'la sección nombra a Google sirviendo contenido propio');

        // Y la fuente lo dice en el DATO, que es lo que evita que la vista lo deduzca.
        foreach (app(SocialProof::class)->testimonials() as $op) {
            $this->assertSame(TestimonialData::SOURCE_CMS, $op->source);
            $this->assertNull($op->url, 'una opinión propia no tiene «la entera» en ninguna parte');
            $this->assertNull($op->avatarUrl);
        }
    }

    /**
     * **La entradilla NO es la de Google.** La suya —«no las elegimos nosotros»— es lo que hace
     * creíble a Google, y **sobre opiniones propias sería falsa**: éstas sí las elige el parque.
     */
    public function test_la_entradilla_no_afirma_lo_que_solo_vale_para_google(): void
    {
        $this->sembrar();
        $seccion = $this->seccion();

        $this->assertStringContainsString(__('landing.reviews.lede_own'), $seccion);
        $this->assertStringNotContainsString(__('landing.reviews.lede_google'), $seccion);
    }

    // ── 4 · El suelo sin JavaScript ──────────────────────────────────────────────────

    /**
     * **La primera opinión nace visible desde el servidor, y solo la primera.**
     *
     * ⚠️ Sin esto, con `x-show` la sección se queda **vacía** sin JavaScript y parpadea en blanco
     * mientras Alpine arranca. Que el marcado exista no es que se vea.
     */
    public function test_la_primera_opinion_nace_visible_sin_javascript(): void
    {
        $this->sembrar();
        $seccion = $this->seccion();

        $this->assertSame(3, preg_match_all('/class="rev__card/', $seccion), 'no se sirven las tres opiniones');
        $this->assertSame(
            1,
            preg_match_all('/class="rev__card is-on"/', $seccion),
            'o ninguna opinión nace visible sin JS, o nacen varias a la vez.',
        );
    }

    /** Los controles solo existen si hay algo entre lo que pasar. */
    public function test_los_controles_piden_mas_de_una_opinion(): void
    {
        $this->sembrar(1);
        $this->assertStringNotContainsString('rev__nav', $this->seccion(), 'una flecha que no lleva a ninguna parte');

        $this->sembrar(2);
        $seccion = $this->seccion();
        $this->assertStringContainsString('rev__nav', $seccion);
        $this->assertSame(3, preg_match_all('/class="rev__dot"/', $seccion), 'un punto por opinión');
    }

    /**
     * **La nota va como imagen con nombre accesible**: cinco glifos sueltos los lee un lector de
     * pantalla como «estrella estrella estrella…», que no dice la nota.
     */
    public function test_la_nota_tiene_nombre_accesible(): void
    {
        $this->sembrar(1);

        // ⚠️⚠️ **Se aseveran `role="img"` y la etiqueta JUNTOS, sobre el mismo elemento.** Buscar
        // solo el `aria-label` deja pasar la mutación que quita el `role` —lo dijo el arnés—, y sin
        // un rol que admita nombre **el `aria-label` de un `<p>` lo ignora el lector de pantalla**:
        // la etiqueta sigue en el HTML y el nombre accesible ya no existe.
        $this->assertMatchesRegularExpression(
            '/<p class="rev__stars" role="img"\s+aria-label="'.preg_quote(trans_choice('landing.reviews.stars', 5, ['n' => 5]), '/').'"/',
            $this->seccion(),
            'La nota perdió su rol o su etiqueta: cinco glifos sueltos se leen «estrella estrella…».',
        );
    }

    /**
     * **La inicial se corta por CARACTERES, no por bytes.**
     *
     * ⚠️ Con `substr`, un nombre que empieza por acento se parte por la mitad y el círculo pinta un
     * rombo de reemplazo. Es el defecto que `#295` pagó con `trim($s, ' ·')`, y no lo ve ningún
     * caso cuyo sujeto se llame «Persona 1».
     */
    public function test_la_inicial_se_corta_por_caracteres_y_no_por_bytes(): void
    {
        Testimonial::query()->create([
            'text' => ['es' => 'Genial.'], 'author' => 'Ángela M.', 'position' => 1, 'is_active' => true,
        ]);

        $op = app(SocialProof::class)->testimonials()->first();

        $this->assertSame('Á', $op->initial());
        $this->assertStringContainsString('>Á</span>', $this->seccion(), 'la inicial no llega entera a la página');
    }

    /**
     * **Las estrellas leen `--attn-ink`, no `--attn`.**
     *
     * ⚠️ Es exactamente el token que `#487` tuvo que crear: el amarillo del paquete da **1,5** sobre
     * blanco, y aquí las estrellas van sobre la tarjeta blanca. Su defecto cae del lado seguro
     * —sin el token se lee `--fg`, que pierde el color y nunca la lectura—, así que el fallo de
     * volver a `--attn` **no se ve en la suite ni rompe nada: solo deja de leerse**.
     */
    public function test_las_estrellas_leen_la_tinta_del_aviso_y_no_su_relleno(): void
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));
        $ciego = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);

        $this->assertMatchesRegularExpression(
            '/\.rev__stars-on\s*\{[^}]*color:\s*var\(--attn-ink\)/',
            $ciego,
            'Las estrellas de una opinión dejaron de leer `--attn-ink`: sobre la tarjeta blanca, el '.
            'amarillo del paquete da 1,5 de contraste.',
        );
    }

    // ── 5 · La vista pide el contrato ────────────────────────────────────────────────

    /**
     * **El controlador pregunta al CONTRATO y no al modelo**, que es lo que hace que la mitad de
     * Google entre cambiando un binding en vez de reescribiendo la vista.
     */
    public function test_la_portada_pide_la_prueba_social_al_contrato(): void
    {
        $bruto = (string) file_get_contents(base_path('app/Http/Controllers/HomeController.php'));

        // ⚠️⚠️ **Se enmascaran los COMENTARIOS antes de aseverar, y no es celo: este caso se puso
        // rojo con el producto sano.** El comentario del controlador cita `Testimonial::where(...)`
        // como ejemplo de lo que NO hay que hacer, y una búsqueda en el fuente crudo no distingue
        // una advertencia escrita de una llamada real. Es la trampa que `#482` pagó con las llaves
        // dentro de los comentarios de la hoja de estilos, y `#320` con un `{@see}` que Pint
        // convirtió en `use`.
        $fuente = (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $bruto);

        $this->assertStringContainsString('SocialProof::class', $fuente);
        $this->assertStringNotContainsString(
            'Testimonial::',
            $fuente,
            'El controlador consulta el modelo directamente: la landing ha vuelto a conocer a su proveedor.',
        );

        // Y el binding resuelve a la implementación del CMS mientras Google no exista.
        $this->assertInstanceOf(CmsSocialProof::class, app(SocialProof::class));
    }
}
