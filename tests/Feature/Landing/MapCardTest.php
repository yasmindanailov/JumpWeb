<?php

namespace Tests\Feature\Landing;

use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * #206 — La card de ubicación de la landing embebe el mapa de Google cuando hay una URL de
 * inserción configurada (`address.maps_embed_url`), y cae al marcador decorativo si no la
 * hay o no es una inserción de Google Maps (defensa: solo se embebe ese origen).
 */
class MapCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
    }

    private function setEmbed(string $value): void
    {
        Setting::updateOrCreate(['key' => 'address.maps_embed_url'], ['value' => $value, 'group' => 'contact']);
    }

    public function test_shows_google_map_iframe_when_configured(): void
    {
        $this->setEmbed('https://www.google.com/maps/embed?pb=UNIQUEMAPTOKEN123');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('UNIQUEMAPTOKEN123');
        $response->assertSee('<iframe', false);
    }

    public function test_falls_back_to_pin_when_no_embed(): void
    {
        // Sin configurar (el seeder no siembra la clave): se muestra el marcador decorativo.
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('map-pin', false);
    }

    public function test_ignores_non_google_embed_value(): void
    {
        // Un valor que no es una inserción de Google Maps (p. ej. colado por BD) NO se embebe.
        $this->setEmbed('https://evil.example.com/iframe');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('evil.example.com');
        $response->assertSee('map-pin', false);
    }
}
