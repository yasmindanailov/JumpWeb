<?php

namespace Tests\Feature\Api;

use App\Domain\Content\Models\Testimonial;
use App\Http\Instancia\PageFacts;

/**
 * **Las OPINIONES publicadas del menú de hechos** (`GET /api/v1/reviews?lang=`, `DECISIONES #771`).
 *
 * Lo que una landing no puede comprobar por su cuenta: que solo viajen las PUBLICADAS (una copiada que el parque no
 * eligió no sale), que cada una diga en qué páginas sale y en el orden del panel, que la marca y el enlace de Google
 * solo los lleve una copiada de Google, que las imágenes sean nuestras, y que la página reciba el MISMO JSON.
 */
class ReviewsFactsTest extends ApiTestCase
{
    /** @return list<array<string, mixed>> */
    private function opiniones(): array
    {
        return $this->getJson(self::ROOT.'/reviews?lang=es')->assertOk()->json('reviews');
    }

    public function test_only_the_published_ones_travel_with_their_pages_in_the_panel_order(): void
    {
        Testimonial::create(['author' => 'Segunda', 'text' => ['es' => 'B'], 'is_active' => true, 'position' => 2, 'tags' => ['jump']]);
        Testimonial::create(['author' => 'Primera', 'text' => ['es' => 'A'], 'is_active' => true, 'position' => 1, 'tags' => ['kids', 'portada']]);
        Testimonial::create(['origin' => Testimonial::ORIGIN_GOOGLE, 'author' => 'Sin elegir', 'text' => ['es' => 'C'], 'is_active' => false]);

        $opiniones = $this->opiniones();

        $this->assertSame(['Primera', 'Segunda'], array_column($opiniones, 'author'));
        $this->assertSame([['kids', 'portada'], ['jump']], array_column($opiniones, 'tags'));
    }

    public function test_a_google_copy_carries_its_mark_link_images_and_reply_and_an_own_one_does_not(): void
    {
        Testimonial::create([
            'origin' => Testimonial::ORIGIN_GOOGLE, 'source_url' => 'https://www.google.com/maps/place/Play+Jump+Park', 'author' => 'Lucía P.',
            'author_meta' => 'Local Guide · 12 reseñas', 'avatar' => 'resenas/a.png', 'photos' => ['resenas/f.png'], 'rating' => 5,
            'published_at' => '2026-07-26', 'text' => ['es' => 'Genial en Kids.'], 'reply' => '¡Gracias!', 'is_active' => true, 'position' => 1, 'tags' => ['kids'],
        ]);
        Testimonial::create(['author' => 'Escrita', 'text' => ['es' => 'Propia'], 'source_url' => 'https://www.google.com/x', 'is_active' => true, 'position' => 2]);

        $this->getJson(self::ROOT.'/reviews?lang=es')->assertOk()->assertValidRequest()->assertValidResponse(200);
        [$copia, $propia] = $this->opiniones();

        $this->assertSame([
            'id' => $copia['id'], 'origin' => 'google', 'author' => 'Lucía P.', 'author_meta' => 'Local Guide · 12 reseñas',
            'avatar_url' => asset('uploads/resenas/a.png'), 'rating' => 5, 'date' => '2026-07-26', 'text' => 'Genial en Kids.',
            'photos' => [asset('uploads/resenas/f.png')], 'url' => 'https://www.google.com/maps/place/Play+Jump+Park', 'reply' => '¡Gracias!', 'tags' => ['kids'],
        ], $copia);
        $this->assertSame('own', $propia['origin']);
        $this->assertArrayNotHasKey('url', $propia, 'una escrita en el panel no lleva la ropa de Google');
        $this->assertSame([[], []], [$propia['photos'], $propia['tags']], 'las listas van siempre, vacías');
    }

    public function test_lang_is_required(): void
    {
        $this->getJson(self::ROOT.'/reviews')->assertStatus(422);
    }

    public function test_the_page_of_the_instance_gets_the_same_json(): void
    {
        Testimonial::create(['author' => 'Una', 'text' => ['es' => 'A'], 'is_active' => true, 'tags' => ['kids']]);
        app()->setLocale('es');

        $this->assertSame($this->getJson(self::ROOT.'/reviews?lang=es')->json(), app(PageFacts::class)->resolver(['reviews'])['reviews']);
    }
}
