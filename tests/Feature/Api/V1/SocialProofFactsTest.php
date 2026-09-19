<?php

namespace Tests\Feature\Api\V1;

use App\Domain\Content\Services\GoogleSocialProof;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Api\ApiTestCase;

/**
 * **`GET /api/v1/social-proof` — la CIFRA de prueba social** (F5 · T6 del menú de hechos,
 * `docs/specs/instancia-y-landing-fuera.md` §4.1; `DECISIONES #616` y `#646`).
 *
 * ⚠️ **Los casos siembran la CACHÉ de `GoogleSocialProof`, no doblan el contrato**, y es deliberado:
 * `GoogleAttributionTest` ya pagó esa lección —*«doblar el contrato prueba la vista y deja el
 * traductor sin cubrir»*—. Sembrando la caché, la petición recorre el camino real: el decorador de
 * la cascada, el servicio de Google y el recurso.
 */
class SocialProofFactsTest extends ApiTestCase
{
    /** Lo que Google deja en la caché tras un refresco, tal y como lo escribe `normalize()`. */
    private function conCifra(?string $url = 'https://maps.google.com/?cid=1', array $reviews = []): void
    {
        Cache::put(GoogleSocialProof::cacheKey(), [
            'value' => 4.8,
            'count' => 320,
            'url' => $url,
            'lang' => GoogleSocialProof::sourceLocale(),
            'reviews' => $reviews,
        ], 600);
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
        $this->assertSame('https://maps.google.com/?cid=1', $respuesta->json('rating.url'));
        $this->assertSame('google', $respuesta->json('rating.source'),
            'la fuente viaja con el dato: la cifra no se puede enseñar sin decir de quién es');
    }

    /**
     * ⚠️⚠️ **El sobre vacío es `{}` y NUNCA `[]`**, y este caso existe porque la primera versión
     * publicaba `[]`: es la trampa escrita en la receta del menú —un array vacío de PHP sale como
     * lista— y aquí cae en la RAÍZ, donde el `(object)` por bloque de los hermanos no llega.
     *
     * Se mira el cuerpo CRUDO a propósito: `json()` devuelve `[]` para las dos formas, así que un
     * caso escrito sobre él habría pasado con el defecto delante.
     */
    public function test_without_a_figure_the_envelope_is_an_empty_object_not_a_list(): void
    {
        Cache::forget(GoogleSocialProof::cacheKey());

        $respuesta = $this->getJson(self::ROOT.'/social-proof')
            ->assertOk()
            ->assertValidResponse(200);

        $this->assertSame('{}', $respuesta->getContent(),
            'una instalación sin cifra publica una LISTA vacía: un cliente tipado leería varias cifras');
    }

    /**
     * ❗❗❗ **La decisión de `#646`, con guarda: de las reseñas no sale NADA todavía**, ni el texto,
     * ni el autor, ni —sobre todo— el avatar.
     *
     * Su fuente está a mitad de cambio (Places → Business Profile, `#524`) y su forma cambia con
     * ella, así que publicarlas hoy obligaría a romper el contrato después. Y `#616` fija además que
     * cuando lleguen **viajarán sin avatares**: una foto en `lh3.googleusercontent.com` es una
     * petición del visitante a un tercero, y por API la haría una landing que no controlamos.
     *
     * Este caso se pone rojo el día que alguien las añada sin pasar por esa decisión.
     */
    public function test_no_review_and_no_third_party_photo_leaks_through_the_endpoint(): void
    {
        $this->conCifra(reviews: [[
            'text' => 'Un sitio estupendo.',
            'author' => 'Ana Ejemplo',
            'rating' => 5,
            'url' => 'https://maps.google.com/reviews/1',
            'avatar' => 'https://lh3.googleusercontent.com/a/foto',
            'authorUrl' => 'https://maps.google.com/contrib/1',
        ]]);

        $cuerpo = $this->getJson(self::ROOT.'/social-proof')
            ->assertOk()
            ->assertValidResponse(200)
            ->getContent();

        $this->assertStringNotContainsString('lh3.googleusercontent.com', $cuerpo, 'viaja el avatar de un tercero');
        $this->assertStringNotContainsString('Ana Ejemplo', $cuerpo, 'viaja el autor de una reseña');
        $this->assertStringNotContainsString('Un sitio estupendo', $cuerpo, 'viaja el texto de una reseña');
        $this->assertSame(['rating'], array_keys((array) json_decode($cuerpo, true)));
    }

    /**
     * **Es pública y se cachea** (`PERF-02`): la pinta una landing de instancia, que no tiene sesión
     * en este dominio, y la cifra cambia de hora en hora, no de segundo en segundo.
     */
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
