<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * **T2·6 · Lo que la sección de reseñas TIENE QUE DECIR**
 * (`docs/specs/google-business-profile.md` §4.3·10; `DECISIONES #524`, `#732`).
 *
 * ❗❗❗ **Ninguno de estos casos es de diseño.** Enseñar solo las reseñas positivas **sin decirlo** es
 * una práctica engañosa según la directiva Ómnibus (2019/2161); atribuirle al sitio, en un buscador,
 * reseñas que son de terceros es lo que la política de Google llama tergiversar. Son obligaciones, y
 * la clase de obligación que **no se ve incumplida mirando la página**.
 *
 * ⚠️⚠️ **Esto mide el ANFITRIÓN MÍNIMO, que es el marcado del PRODUCTO.** La portada que ven los
 * clientes de PlayJump vive en el repo de su instancia (`#666`) y **no se puede medir desde aquí**:
 * lo que el producto puede garantizar es que el dato viaja en el contrato y que su propia portada lo
 * pinta. Que la instancia lo pinte es un aviso al carril de plataforma, no un caso.
 */
class ReviewDisclosureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dobla el contrato con lo que se quiera enseñar. Se dobla el CONTRATO y no la fuente: lo que
     * este fichero mide es qué hace la vista con lo que recibe.
     *
     * @param  list<TestimonialData>  $opiniones
     */
    private function conSeccion(array $opiniones, ?ReviewSelection $seleccion, ?Rating $cifra = null): void
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

    private function deGoogle(array $overrides = []): TestimonialData
    {
        return new TestimonialData(
            text: $overrides['text'] ?? 'Los niños salieron encantados.',
            author: $overrides['author'] ?? 'Marta R.',
            rating: 5,
            when: 'hace 2 meses',
            url: null,
            source: TestimonialData::SOURCE_GOOGLE,
            avatarUrl: $overrides['avatarUrl'] ?? null,
            reply: $overrides['reply'] ?? null,
            photos: $overrides['photos'] ?? [],
            anonymous: $overrides['anonymous'] ?? false,
        );
    }

    private function portada(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    // ─────────── La línea del filtro (Ómnibus 2019/2161) ───────────

    public function test_con_filtro_la_seccion_declara_que_filtra(): void
    {
        $this->conSeccion(
            [$this->deGoogle()],
            new ReviewSelection(4, 'https://maps.google.com/?cid=1', 'https://search.google.com/local/writereview?placeid=ChIJ'),
        );

        $html = $this->portada();

        // ❗❗❗ Las tres cosas que §4.3·10 exige en la misma frase: que se filtra y por cuánto, que
        // **nadie verifica** que los autores hayan venido, y dónde están todas.
        $this->assertStringContainsString(__('landing.reviews.filtered', ['stars' => 4]), $html);
        $this->assertStringContainsString('https://maps.google.com/?cid=1', $html);
        $this->assertStringContainsString('https://search.google.com/local/writereview?placeid=ChIJ', $html);
    }

    public function test_sin_filtro_no_se_avisa_de_un_filtro(): void
    {
        // El control, y no es simetría vacía: avisar de un filtro que **no se está aplicando** a lo
        // que se ve es peor que callar — convierte una sección honesta en una sospechosa.
        $this->conSeccion([$this->deGoogle()], null);

        $this->assertStringNotContainsString(__('landing.reviews.filtered', ['stars' => 4]), $this->portada());
    }

    public function test_la_linea_dice_el_minimo_que_de_verdad_se_aplica(): void
    {
        $this->conSeccion([$this->deGoogle()], new ReviewSelection(5, null, null));

        $html = $this->portada();

        $this->assertStringContainsString(__('landing.reviews.filtered', ['stars' => 5]), $html);
        $this->assertStringNotContainsString(__('landing.reviews.filtered', ['stars' => 4]), $html);
    }

    public function test_sin_enlaces_conocidos_la_linea_se_pinta_igual(): void
    {
        // La obligación de declarar el filtro no depende de tener los enlaces: si Google no nos ha
        // dado el `mapsUri`, se dice igual que se filtra.
        $this->conSeccion([$this->deGoogle()], new ReviewSelection(4, null, null));

        $this->assertStringContainsString(__('landing.reviews.filtered', ['stars' => 4]), $this->portada());
    }

    // ─────────── Lo que no puede salir en un buscador ───────────

    public function test_el_bloque_de_resenas_lleva_data_nosnippet(): void
    {
        $this->conSeccion([$this->deGoogle()], null);

        // §4.3·10: son reseñas de terceros y no pueden acabar en el fragmento que un buscador enseña
        // bajo el resultado del parque.
        $this->assertMatchesRegularExpression('/<section[^>]*id="reviews"[^>]*data-nosnippet/', $this->portada());
    }

    public function test_el_json_ld_no_lleva_nota_agregada_ni_resenas(): void
    {
        $this->conSeccion(
            [$this->deGoogle()],
            new ReviewSelection(4, null, null),
            new Rating(4.8, 320, 'https://maps.google.com/?cid=1', TestimonialData::SOURCE_GOOGLE, now()),
        );

        $html = $this->portada();

        // ⚠️⚠️ **CONTROL PRIMERO**: si la portada dejara de emitir JSON-LD, las dos aserciones de
        // abajo pasarían sin mirar nada.
        $this->assertStringContainsString('application/ld+json', $html,
            'la portada ya no emite JSON-LD: este caso no mide nada');

        // ❗❗❗ Publicar `aggregateRating` con la nota de Google es atribuirle al sitio, en el
        // buscador, una reputación que posee un tercero — y la política de Places lo prohíbe
        // expresamente. Que hoy no exista no basta: esto está para que no aparezca mañana.
        $this->assertStringNotContainsString('aggregateRating', $html);
        $this->assertStringNotContainsString('"@type":"Review"', $html);
        $this->assertStringNotContainsString('"@type": "Review"', $html);
    }

    // ─────────── La imagen es nuestra o no hay (§4.3·9) ───────────

    public function test_una_foto_de_la_seccion_nunca_sale_de_un_host_de_terceros(): void
    {
        $this->conSeccion([$this->deGoogle([
            'avatarUrl' => route('resenas.foto', ['fichero' => hash('sha256', 'a').'.png']),
            'photos' => [route('resenas.foto', ['fichero' => hash('sha256', 'b').'.webp'])],
        ])], null);

        $html = $this->portada();

        // Toda la T2 existe para que la portada deje de pedirle nada a Google. Una URL de
        // `googleusercontent` aquí lo deshace **sin romper nada visible**: la foto se vería igual.
        $this->assertStringContainsString(hash('sha256', 'a'), $html);
        $this->assertStringNotContainsString('googleusercontent', $html);
    }
}
