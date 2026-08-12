<?php

namespace Tests\Feature\Site;

use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Página 404 con identidad de marca (#216) + rediseño rico (#218, item 4): badge grande, enlaces a
 * las secciones más buscadas y CTA de «Reservar» que abre el sidecart (el aviso de mantenimiento,
 * si las reservas están en pausa, vive dentro del sidecart).
 */
class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
    }

    public function test_404_renders_branded_page_with_helpful_links(): void
    {
        $response = $this->get('/esta-pagina-no-existe-'.uniqid());

        $response->assertNotFound(); // 404 real
        $response->assertSee(__('site.e404_title'));
        $response->assertSee(__('site.e404_popular'));
        // Enlaces a las secciones más buscadas (layout completo: el visitante encuentra salida).
        $response->assertSee(route('cumpleanos'), false);
        $response->assertSee(route('precios'), false);
        $response->assertSee(route('normas'), false);
        $response->assertSee(route('contacto'), false);
    }

    public function test_404_book_cta_opens_the_sidecart(): void
    {
        // El botón «Reservar» del 404 abre el sidecart como en el resto de la web (no cambia con la
        // pausa; el aviso de mantenimiento vive DENTRO del sidecart, cubierto en ReservationPauseTest).
        $this->get('/no-existe-'.uniqid())
            ->assertNotFound()
            ->assertSee(__('site.e404_book'))
            ->assertSee('$store.purchase.open(', false);
    }
}
