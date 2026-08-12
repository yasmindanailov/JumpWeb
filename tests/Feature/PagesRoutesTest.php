<?php

namespace Tests\Feature;

use App\Models\Page;
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
        $this->get('/normas')
            ->assertOk()
            ->assertSee('Conducta'); // norma sembrada (ES); las normas se renovaron (#10)
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
