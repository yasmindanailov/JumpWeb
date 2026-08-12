<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\LandingService;
use App\Domain\Content\Models\VenueRule;
use Database\Seeders\LandingServicesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder DEDICADO de los servicios de /servicios (#256): crea/actualiza SOLO `landing_services`
 * (los 3 servicios + sus tablas de tarifas), sin tocar otro contenido. Pensado para sembrar los
 * servicios en producción tras el deploy (que migra pero no siembra) sin resembrar el resto.
 */
class LandingServicesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_three_services_with_their_price_tables(): void
    {
        $this->seed(LandingServicesSeeder::class);

        $this->assertSame(3, LandingService::count());
        $this->assertEqualsCanonicalizing(
            ['excursionescolegio', 'teambuilding', 'sesionadultos'],
            LandingService::pluck('slug')->all(),
        );
        // Colegio y empresas traen tabla de tarifas; la sesión de adultos no.
        $this->assertNotNull(LandingService::where('slug', 'excursionescolegio')->first()->price_table);
        $this->assertNotNull(LandingService::where('slug', 'teambuilding')->first()->price_table);
        $this->assertNull(LandingService::where('slug', 'sesionadultos')->first()->price_table);
    }

    public function test_does_not_seed_any_other_content(): void
    {
        // QUIRÚRGICO: solo `landing_services`. NO crea FAQs/normas (que el seeder COMPLETO sí siembra),
        // ni packs de cumpleaños / entradas (TicketType), ni ningún otro contenido.
        $this->seed(LandingServicesSeeder::class);

        $this->assertSame(0, Faq::count());
        $this->assertSame(0, VenueRule::count());
        $this->assertSame(0, TicketType::count());   // cero packs de cumpleaños / entradas creados
    }

    public function test_is_idempotent(): void
    {
        $this->seed(LandingServicesSeeder::class);
        $this->seed(LandingServicesSeeder::class);  // 2.ª pasada no duplica (updateOrCreate por slug)

        $this->assertSame(3, LandingService::count());
    }
}
