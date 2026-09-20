<?php

namespace Tests\Feature\Instancia;

use App\Domain\Content\Models\Page;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de los TEXTOS LEGALES** — lo que el producto sirve sin paquete de instancia
 * (F5 · T2b, `DECISIONES #655`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`):
 * las cinco rutas `legal.*` sirven la página del panel con su titular, sus secciones y los datos fiscales
 * ya interpolados.
 */
class AnfitrionLegalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_the_five_legal_routes_serve_the_host_with_the_page_from_the_panel(): void
    {
        foreach (['privacidad', 'condiciones', 'waiver', 'cookies', 'aviso-legal'] as $slug) {
            $pagina = Page::where('slug', $slug)->firstOrFail();

            $this->get('/'.$slug)->assertOk()->assertViewIs('anfitrion.legal')
                ->assertSee('<h1 class="page__title">'.e($pagina->tr('title')).'</h1>', false);
        }
    }

    public function test_the_sections_are_painted_with_the_fiscal_data_resolved(): void
    {
        Setting::query()->updateOrCreate(['key' => 'business.legal_name'], ['value' => 'Parque de Prueba SL', 'group' => 'business']);
        Setting::flushMemo();
        Page::where('slug', 'privacidad')->firstOrFail()->update([
            'body' => ['es' => [
                ['h' => 'Responsable', 'p' => 'El responsable es :legal_name.'],
                ['p' => 'Un párrafo sin titular.'],
            ]],
        ]);

        $html = (string) $this->get('/privacidad')->assertOk()->getContent();

        $this->assertStringContainsString('<h2 class="page__h2">Responsable</h2>', $html);
        $this->assertStringContainsString('El responsable es Parque de Prueba SL.', $html);
        $this->assertStringNotContainsString(':legal_name', $html, 'un marcador sin resolver en un texto legal');
        $this->assertStringContainsString('Un párrafo sin titular.', $html);
        $this->assertStringContainsString('<meta name="description" content="El responsable es Parque de Prueba SL.">', $html, 'la meta no sale del primer párrafo');
    }
}
