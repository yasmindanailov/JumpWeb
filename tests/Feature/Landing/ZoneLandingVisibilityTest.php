<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.9 (adelanto) — La landing muestra las zonas por `show_in_landing` (no por `is_active`):
 * una zona operativa pero oculta no aparece como tarjeta de zona; una visible sí.
 */
class ZoneLandingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class); // contenido completo para que la home renderice
        app()->setLocale('es');
    }

    public function test_landing_shows_zones_flagged_for_landing_and_hides_the_rest(): void
    {
        Zone::create([
            'slug' => 'visible-zone', 'name' => ['es' => 'ZonaVisibleTest'],
            'is_active' => true, 'show_in_landing' => true, 'position' => 10,
        ]);
        Zone::create([
            'slug' => 'hidden-zone', 'name' => ['es' => 'ZonaOcultaTest'],
            'is_active' => true, 'show_in_landing' => false, 'position' => 11,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('ZonaVisibleTest');
        $response->assertDontSee('ZonaOcultaTest');
    }

    public function test_an_operational_but_hidden_zone_is_not_shown_as_a_zone_card(): void
    {
        // La zona de cumpleaños opera (vende packs) pero no sale como tarjeta de zona.
        $cumple = Zone::where('slug', 'cumpleanos')->firstOrFail();
        $this->assertTrue($cumple->is_active);
        $this->assertFalse($cumple->show_in_landing);
    }
}
