<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ofertas (#270) — widget «caja de regalo» en la landing: solo se pinta si hay ofertas ACTIVAS,
 * muestra título (escapado, i18n) + ruta de imagen perezosa, y NO existe en el DOM sin ofertas.
 */
class OffersWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_absent_without_active_offers(): void
    {
        // Sin ninguna oferta.
        $this->get('/')->assertOk()->assertDontSee('offersWidget(', false);

        // Con una oferta INACTIVA tampoco aparece.
        Offer::create(['title' => ['es' => 'Oculta'], 'image' => 'ofertas/x.webp', 'is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('offersWidget(', false);
    }

    public function test_widget_present_with_active_offer(): void
    {
        Offer::create([
            'title' => ['es' => 'Gran oferta', 'en' => 'Big deal'],
            'image' => 'ofertas/promo.webp',
            'is_active' => true,
            'position' => 0,
        ]);

        $res = $this->get('/')->assertOk();
        $res->assertSee('offersWidget(1)', false);         // componente Alpine del widget (1 oferta)
        $res->assertSee('class="offw"', false);
        $res->assertSee('offw-badge', false);              // badge visible TAMBIÉN con 1 oferta (#270 punto 3)
        $res->assertSee('Gran oferta');                    // título en el idioma activo (es)
        $res->assertSee('uploads/ofertas/promo.webp', false); // ruta servida de la imagen (data-src, perezosa)
    }

    public function test_offer_title_is_escaped_against_xss(): void
    {
        Offer::create([
            'title' => ['es' => '<script>alert(1)</script>Promo'],
            'image' => 'ofertas/x.webp',
            'is_active' => true,
        ]);

        $res = $this->get('/')->assertOk();
        $res->assertDontSee('<script>alert(1)</script>', false); // no se inyecta crudo
        $res->assertSee('&lt;script&gt;', false);                // sale escapado por Blade {{ }}
    }

    public function test_badge_counts_only_active_offers(): void
    {
        Offer::create(['title' => ['es' => 'A'], 'image' => 'ofertas/a.webp', 'is_active' => true, 'position' => 0]);
        Offer::create(['title' => ['es' => 'B'], 'image' => 'ofertas/b.webp', 'is_active' => true, 'position' => 1]);
        Offer::create(['title' => ['es' => 'C'], 'image' => 'ofertas/c.webp', 'is_active' => false, 'position' => 2]);

        // 2 activas → el componente recibe count=2 y las flechas/dots se pintan.
        $this->get('/')->assertOk()->assertSee('offersWidget(2)', false);
    }
}
