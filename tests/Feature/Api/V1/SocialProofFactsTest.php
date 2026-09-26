<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Services\CopiedRating;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\ApiTestCase;

/**
 * **`GET /api/v1/social-proof` — la CIFRA de prueba social** (F5 · T6 del menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1; `DECISIONES #616` y `#646`).
 *
 * ▶ **Re-apuntado en `#771`**: los casos sembraban la caché de Places, que se retiró. Ahora siembran la NOTA COPIADA
 * de la ficha ({@see CopiedRating}), que es la fuente de la cifra mientras no haya Perfil de Empresa, y la petición
 * recorre el camino real: la cascada, las opiniones del panel y el recurso. Lo que se afirma del CONTRATO no cambia.
 */
class SocialProofFactsTest extends ApiTestCase
{
    /** La nota que el importador copia de la ficha. */
    private function conCifra(?string $url = 'https://www.google.com/maps/place/Play+Jump+Park'): void
    {
        app(CopiedRating::class)->put(4.8, 320, $url, Carbon::parse('2026-09-26'));
    }

    public function test_the_figure_travels_whole_and_says_who_says_it(): void
    {
        $this->conCifra();

        $respuesta = $this->getJson(self::ROOT.'/social-proof')
            ->assertOk()
            ->assertValidRequest()
            ->assertValidResponse(200);

        $this->assertSame(4.8, $respuesta->json('rating.value'));
        $this->assertSame(320, $respuesta->json('rating.count'));
        $this->assertSame('https://www.google.com/maps/place/Play+Jump+Park', $respuesta->json('rating.url'));
        $this->assertSame('google', $respuesta->json('rating.source'),
            'la fuente viaja con el dato: la cifra no se puede enseñar sin decir de quién es');
    }

    /**
     * ⚠️⚠️ **El sobre vacío es `{}` y NUNCA `[]`** (la trampa de la receta del menú: un array vacío de PHP sale como
     * lista). Se mira el cuerpo CRUDO: `json()` devuelve `[]` para las dos formas.
     */
    public function test_without_a_figure_the_envelope_is_an_empty_object_not_a_list(): void
    {
        $respuesta = $this->getJson(self::ROOT.'/social-proof')
            ->assertOk()
            ->assertValidResponse(200);

        $this->assertSame('{}', $respuesta->getContent(),
            'una instalación sin cifra publica una LISTA vacía: un cliente tipado leería varias cifras');
    }

    /**
     * ❗ **De las reseñas no sale NADA por aquí** (`#646`): ni el texto, ni el autor, ni la foto. Las que el parque
     * publica van por su propio hecho (`/reviews`, `#771`), con sus páginas; ésta es solo la cifra.
     */
    public function test_no_review_leaks_through_the_endpoint(): void
    {
        $this->conCifra();
        Testimonial::create([
            'origin' => Testimonial::ORIGIN_GOOGLE, 'author' => 'Ana Ejemplo', 'text' => ['es' => 'Un sitio estupendo.'],
            'avatar' => 'resenas/a.png', 'is_active' => true, 'tags' => ['portada'],
        ]);

        $cuerpo = $this->getJson(self::ROOT.'/social-proof')
            ->assertOk()
            ->assertValidResponse(200)
            ->getContent();

        $this->assertStringNotContainsString('Ana Ejemplo', $cuerpo, 'viaja el autor de una reseña');
        $this->assertStringNotContainsString('Un sitio estupendo', $cuerpo, 'viaja el texto de una reseña');
        $this->assertSame(['rating'], array_keys((array) json_decode($cuerpo, true)));
    }

    /** Una nota copiada fuera de escala o sin reseñas no es publicable: el sobre queda vacío. */
    public function test_an_impossible_copied_figure_is_not_published(): void
    {
        app(CopiedRating::class)->put(7.5, 10, null, Carbon::parse('2026-09-26'));

        $this->assertSame('{}', $this->getJson(self::ROOT.'/social-proof')->assertOk()->getContent());
    }

    /** **Es pública y se cachea** (`PERF-02`). */
    public function test_it_is_public_and_cacheable_with_an_etag(): void
    {
        $this->conCifra();

        $primera = $this->getJson(self::ROOT.'/social-proof')->assertOk();
        $etag = $primera->headers->get('ETag');

        $this->assertNotNull($etag, 'sin `ETag` cada landing se descarga la cifra entera cada vez');
        $this->assertStringContainsString('public', (string) $primera->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $primera->headers->get('Cache-Control'));

        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson(self::ROOT.'/social-proof')
            ->assertStatus(304);
    }
}
