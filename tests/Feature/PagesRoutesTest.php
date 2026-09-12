<?php

namespace Tests\Feature;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\VenueRule;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_legal_page_loads_in_spanish(): void
    {
        $this->get('/privacidad')
            ->assertOk()
            ->assertSee('Política de privacidad')
            ->assertSee('Redsys') // contenido legal real (#220)
            ->assertDontSee('Texto provisional pendiente de revisión legal.'); // página revisada → sin aviso de borrador
    }

    public function test_legal_page_respects_locale(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $this->get('/privacidad')
            ->assertOk()
            ->assertSee('Privacy policy');
    }

    public function test_all_legal_routes_respond(): void
    {
        foreach (['privacidad', 'condiciones', 'waiver', 'cookies', 'aviso-legal'] as $slug) {
            $this->get('/'.$slug)->assertOk();
        }
    }

    public function test_rules_page_lists_seeded_rules(): void
    {
        /*
         * ⚠️ **Se asevera lo SEMBRADO, no un nombre escrito a mano.** Este caso decía «Conducta» y
         * se quedó **sin sujeto** en `#533`, cuando las normas se reescribieron desde el artboard
         * (esa misma norma es hoy «Haz caso al monitor»). Leyendo el nombre de la primera norma
         * sembrada, el caso sigue vigilando lo suyo —que la página lista lo que hay en el panel— y
         * no se vuelve a caer el día que el parque cambie un título.
         */
        $primera = VenueRule::where('is_active', true)->orderBy('position')->firstOrFail();

        $this->get('/normas')
            ->assertOk()
            ->assertSee((string) $primera->tr('name'));
    }

    public function test_inactive_page_returns_404(): void
    {
        Page::where('slug', 'cookies')->update(['is_active' => false]);

        $this->get('/cookies')->assertNotFound();
    }

    public function test_footer_links_to_legal_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(url('/privacidad'));
    }
}
