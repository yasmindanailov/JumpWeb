<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **T2·8 · La tarjeta de una reseña de la ficha** — el ojo del owner del 2026-09-21
 * (`docs/specs/google-business-profile.md` §4.1 y §4.3·5/§4.3·8/§4.3·10; `DECISIONES #734`).
 *
 * El dato llegaba entero desde la T2·6 y la tarjeta no lo pintaba: la anónima salía SIN nombre y con
 * un «·», ni la respuesta del parque ni las fotos, la entradilla decía «no las elegimos nosotros»
 * encima de la línea que dice que filtramos, la cifra sin «a fecha de», el logotipo de Google MAPS
 * sobre datos que no son de Maps, los saltos de línea perdidos y unos enlaces que no parecían enlaces.
 *
 * ⚠️ Mide el ANFITRIÓN MÍNIMO, que es el marcado del producto. La portada de la instancia necesita lo
 * mismo, y eso es un aviso al carril de plataforma, no un caso.
 */
class ReviewCardTest extends TestCase
{
    use ReadsSiteStylesheets;
    use RefreshDatabase;

    /**
     * Dobla el CONTRATO, no la fuente: lo que se mide es qué hace la vista con lo que recibe.
     *
     * @param  list<TestimonialData>  $opiniones
     */
    private function conSeccion(array $opiniones, ?ReviewSelection $seleccion = null, ?Rating $cifra = null): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->app->bind(SocialProof::class, fn () => new class(collect($opiniones), $seleccion, $cifra) implements SocialProof
        {
            public function __construct(
                private Collection $lista,
                private ?ReviewSelection $seleccion,
                private ?Rating $cifra,
            ) {}

            public function rating(): ?Rating
            {
                return $this->cifra;
            }

            public function testimonials(): Collection
            {
                return $this->lista;
            }

            public function reviewsAwaitConsent(): bool
            {
                return false;
            }

            public function reviewsNeedConsent(): bool
            {
                return false;
            }

            public function selection(): ?ReviewSelection
            {
                return $this->seleccion;
            }
        });
    }

    /** Una reseña como la entrega la FICHA (T2·6): atribución por la palabra, sin enlace propio. */
    private function deLaFicha(array $o = []): TestimonialData
    {
        return new TestimonialData(
            text: $o['text'] ?? 'Los niños salieron encantados.',
            author: $o['author'] ?? 'Marta R.',
            rating: 5,
            when: 'hace 2 días',
            url: null,
            source: TestimonialData::SOURCE_GOOGLE,
            avatarUrl: null,
            reply: $o['reply'] ?? null,
            photos: $o['photos'] ?? [],
            anonymous: $o['anonymous'] ?? false,
            attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD,
        );
    }

    /** Una reseña como la entrega PLACES: sin atribución declarada, que es el logotipo de Maps. */
    private function dePlaces(): TestimonialData
    {
        return new TestimonialData(
            text: 'Muy bien.',
            author: 'Luis P.',
            rating: 5,
            when: 'hace un mes',
            url: 'https://maps.google.com/?cid=9',
            source: TestimonialData::SOURCE_GOOGLE,
        );
    }

    private function seleccion(): ReviewSelection
    {
        return new ReviewSelection(4, 'https://maps.google.com/?cid=1', 'https://search.google.com/local/writereview?placeid=ChIJ');
    }

    private function seccion(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        $i = strpos($html, 'id="reviews"');
        $this->assertNotFalse($i, 'la sección de reseñas no se ha pintado: este caso no mide nada');

        return substr($html, $i, strpos($html, '</section>', $i) - $i);
    }

    private function foto(string $semilla, string $ext = 'jpg'): string
    {
        return route('resenas.foto', ['fichero' => hash('sha256', $semilla).'.'.$ext]);
    }

    // ─────────── La anónima (§4.3·5) ───────────

    public function test_una_anonima_sale_como_usuario_de_google(): void
    {
        $this->conSeccion([$this->deLaFicha(['author' => '', 'anonymous' => true])], $this->seleccion());

        $seccion = $this->seccion();

        // ⚠️⚠️ **La expectativa va ESCRITA A MANO y no con `__()`**: con la clave vacía, `__()`
        // devuelve `''` y `assertStringContainsString('')` pasa siempre — el arnés lo cazó (`#734`),
        // y es el mismo defecto que un test que calcula su expectativa desde el código que mide.
        $this->assertStringContainsString('Usuario de Google', $seccion);
        // Y la inicial sale de ese rótulo, en vez del «·» de un nombre vacío.
        $this->assertStringContainsString('aria-hidden="true">U</span>', $seccion);
        $this->assertStringNotContainsString('aria-hidden="true">·</span>', $seccion);
    }

    public function test_una_con_nombre_no_sale_como_anonima(): void
    {
        // El CONTROL: sin él, un rótulo que se pintara siempre pasaría el caso de arriba.
        $this->conSeccion([$this->deLaFicha()], $this->seleccion());

        $seccion = $this->seccion();

        $this->assertStringContainsString('Marta R.', $seccion);
        $this->assertStringNotContainsString(__('landing.reviews.anonymous'), $seccion);
    }

    // ─────────── La respuesta y las fotos (§4.3·10) ───────────

    public function test_la_respuesta_del_parque_sale_debajo_de_la_resena(): void
    {
        $this->conSeccion([$this->deLaFicha(['reply' => 'Gracias, Marta. Os esperamos.'])], $this->seleccion());

        $seccion = $this->seccion();
        $texto = strpos($seccion, 'Los niños salieron encantados.');
        $respuesta = strpos($seccion, 'Gracias, Marta. Os esperamos.');

        $this->assertNotFalse($respuesta, 'la respuesta del parque no se pinta');
        $this->assertStringContainsString(__('landing.reviews.reply'), $seccion);
        $this->assertGreaterThan($texto, $respuesta, 'la respuesta va DEBAJO de la reseña');
    }

    public function test_las_fotos_de_la_resena_se_pintan(): void
    {
        $this->conSeccion([$this->deLaFicha(['photos' => [$this->foto('a'), $this->foto('b', 'webp')]])], $this->seleccion());

        $seccion = $this->seccion();

        $this->assertSame(2, substr_count($seccion, 'class="rev__photo"'));
        $this->assertStringContainsString($this->foto('a'), $seccion);
        $this->assertStringContainsString($this->foto('b', 'webp'), $seccion);
        // Con `alt` vacío, un lector de pantalla se salta la foto de una reseña como si no existiera.
        $this->assertStringContainsString(
            __('landing.reviews.photo_alt', ['n' => 1, 'total' => 2, 'name' => 'Marta R.']),
            $seccion,
        );
    }

    public function test_con_mas_de_cuatro_fotos_se_dice_cuantas_quedan(): void
    {
        $fotos = array_map(fn (int $n): string => $this->foto('f'.$n), range(1, 6));
        $this->conSeccion([$this->deLaFicha(['photos' => $fotos])], $this->seleccion());

        $seccion = $this->seccion();

        $this->assertSame(4, substr_count($seccion, 'class="rev__photo"'));
        $this->assertStringContainsString('+2', $seccion);
    }

    // ─────────── El texto (§4.3·8) ───────────

    public function test_el_texto_conserva_sus_saltos_de_linea_y_su_direccion(): void
    {
        $this->conSeccion([$this->deLaFicha(['text' => "Primera línea.\nSegunda línea."])], $this->seleccion());

        $seccion = $this->seccion();

        $this->assertStringContainsString("Primera línea.\nSegunda línea.", $seccion);
        $this->assertMatchesRegularExpression('/<p class="rev__text"[^>]*dir="auto"/', $seccion);

        $reglas = array_filter($this->siteRules(), fn (array $r): bool => $r['selector'] === '.rev__text');
        $this->assertNotEmpty($reglas, 'no hay regla para `.rev__text`: el instrumento no mide nada');
        $this->assertStringContainsString('white-space: pre-line', implode(' ', array_column($reglas, 'body')));
    }

    // ─────────── La cifra (§4.3·10) ───────────

    public function test_la_cifra_de_la_ficha_dice_a_fecha_de_en_la_hora_del_parque(): void
    {
        // 22:40 UTC del 20 son las 00:40 del 21 en Madrid: la fecha es la del parque.
        $cifra = new Rating(4.6, 37, 'https://maps.google.com/?cid=1', TestimonialData::SOURCE_GOOGLE,
            asOf: Carbon::parse('2026-09-20 22:40:00', 'UTC'), attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD);
        $this->conSeccion([$this->deLaFicha()], $this->seleccion(), $cifra);

        $this->assertStringContainsString(
            __('landing.reviews.as_of', ['date' => '21 de septiembre de 2026']),
            $this->seccion(),
        );
    }

    public function test_una_cifra_sin_fecha_no_se_inventa_una(): void
    {
        // Places es viva y no trae fecha: decir «a fecha de» de algo que no la tiene sería inventarla.
        $cifra = new Rating(4.6, 37, 'https://maps.google.com/?cid=1', TestimonialData::SOURCE_GOOGLE);
        $this->conSeccion([$this->dePlaces()], null, $cifra);

        $this->assertStringNotContainsString(__('landing.reviews.as_of', ['date' => '']), $this->seccion());
    }

    // ─────────── La entradilla (§4.3·10) ───────────

    public function test_con_la_ficha_la_entradilla_no_dice_que_no_las_elegimos(): void
    {
        $this->conSeccion([$this->deLaFicha()], $this->seleccion());

        $seccion = $this->seccion();

        $this->assertStringContainsString(__('landing.reviews.lede_profile'), $seccion);
        $this->assertStringNotContainsString(__('landing.reviews.lede_google'), $seccion);
    }

    public function test_con_places_la_entradilla_sigue_siendo_la_suya(): void
    {
        // Con Places sí es verdad: son las que Google pone primero, y no filtramos.
        $this->conSeccion([$this->dePlaces()]);

        $seccion = $this->seccion();

        $this->assertStringContainsString(__('landing.reviews.lede_google'), $seccion);
        $this->assertStringNotContainsString(__('landing.reviews.lede_profile'), $seccion);
    }

    // ─────────── La atribución con esta fuente (§4.3·10) ───────────

    public function test_la_ficha_se_atribuye_con_la_palabra_y_no_con_el_logotipo_de_maps(): void
    {
        $cifra = new Rating(4.6, 37, 'https://maps.google.com/?cid=1', TestimonialData::SOURCE_GOOGLE,
            asOf: now(), attribution: TestimonialData::ATTRIBUTION_GOOGLE_WORD);
        $this->conSeccion([$this->deLaFicha()], $this->seleccion(), $cifra);

        $seccion = $this->seccion();

        $this->assertStringNotContainsString('google-maps-', $seccion, 'el logotipo de Maps sobre datos que no son de Maps');
        $this->assertStringContainsString(__('landing.reviews.via_google'), $seccion);
        $this->assertStringContainsString(trans_choice('landing.reviews.count_on_google', 37, ['n' => '37']), $seccion);
    }

    public function test_con_places_el_logotipo_de_maps_sigue_siendo_obligatorio(): void
    {
        // El CONTROL, y es licencia: Places sin mapa exige el logotipo de Google Maps (`#494`).
        $this->conSeccion([$this->dePlaces()]);

        $seccion = $this->seccion();

        $this->assertStringContainsString('google-maps-', $seccion);
        $this->assertStringNotContainsString(__('landing.reviews.via_google'), $seccion);
    }

    public function test_las_estrellas_no_van_pegadas_a_la_atribucion(): void
    {
        // «Sin estrellas pegadas al logotipo»: la palabra va al pie, lejos de la nota.
        $this->conSeccion([$this->deLaFicha()], $this->seleccion());

        $seccion = $this->seccion();

        $this->assertGreaterThan(
            strpos($seccion, 'Los niños salieron encantados.'),
            strpos($seccion, __('landing.reviews.via_google')),
            'la atribución va al pie de la tarjeta, debajo del texto',
        );
    }

    // ─────────── Los enlaces de la línea del filtro ───────────

    public function test_los_enlaces_de_la_linea_del_filtro_van_aparte_y_parecen_enlaces(): void
    {
        $this->conSeccion([$this->deLaFicha()], $this->seleccion());

        $this->assertMatchesRegularExpression(
            '#<span class="rev-sec__links">\s*<a[^>]+maps\.google\.com.*?</a>\s*<a[^>]+writereview#s',
            $this->seccion(),
        );

        $reglas = array_filter($this->siteRules(), fn (array $r): bool => $r['selector'] === '.rev-sec__links a');
        $this->assertNotEmpty($reglas, 'los enlaces no tienen regla propia');
        $this->assertStringContainsString('text-decoration: underline', implode(' ', array_column($reglas, 'body')));
    }
}
