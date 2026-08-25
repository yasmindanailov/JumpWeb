<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Content\Services\ThemeSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 5.0 — Catálogo vendible (entradas por zona/duración) con precio desde la
 * matriz de tarifas (#58, #59, #61). El seeder crea 8 entradas vendibles.
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_seeds_eight_sellable_entries(): void
    {
        // 8 entradas vendibles (jump/kids × 4 duraciones). El pack de cumpleaños se cuenta aparte.
        $this->assertSame(8, TicketType::sellable()->ofType(TicketType::TYPE_ENTRY)->count());
    }

    public function test_sellable_scope_excludes_non_sellable(): void
    {
        $hidden = TicketType::create([
            'name' => ['es' => 'Bono (no vendible aún)'],
            'is_sellable' => false,
            'is_active' => true,
            'position' => 99,
        ]);

        $this->assertSame(8, TicketType::sellable()->ofType(TicketType::TYPE_ENTRY)->count());
        $this->assertTrue(TicketType::sellable()->where('id', $hidden->id)->doesntExist());
    }

    public function test_display_price_comes_from_normal_rate(): void
    {
        // Jump · 1 hora (posición 1): normal 990, especial 1190.
        $ticket = TicketType::with('prices.rateType')->where('position', 1)->first();

        $this->assertSame(990, $ticket->displayPriceCents());
        $this->assertSame('9', $ticket->euros());
        $this->assertSame('90', $ticket->cents());
    }

    public function test_special_day_price_uses_special_rate(): void
    {
        $ticket = TicketType::where('position', 1)->first();
        $saturday = Carbon::now()->next(Carbon::SATURDAY);

        $this->assertSame(1190, (new RateResolver)->priceCents($ticket, $saturday));
    }

    public function test_pricing_page_renders_features_from_db(): void
    {
        $this->get('/precios')
            ->assertOk()
            ->assertSee('Reserva online recomendada'); // feature sembrada de la entrada (ES). Los
        // calcetines dejaron de ir "incluidos" en la entrada (#6): ahora son un complemento de 2€.
    }

    public function test_price_varies_when_rates_differ(): void
    {
        $ticket = TicketType::with('prices')->where('position', 1)->first();

        // Jump · 1 hora: normal 990, especial 1190 → el precio varía por día.
        $this->assertTrue($ticket->priceVaries());
    }

    public function test_pricing_page_shows_zone_switch_and_from_label(): void
    {
        $response = $this->get('/precios')->assertOk();

        $response->assertSee('desde');           // etiqueta de precio "desde" (ES)
        $response->assertSee('Entradas JUMP');   // label del switcher (zona Jump)
        $response->assertSee('Entradas KIDS');   // label del switcher (zona Kids)
        // ⚠️ El tinte por zona se comprobaba por el NOMBRE DE LA CLASE (`zone-tab--jump`), que solo
        // decía que la plantilla escribió el acento — no que llegara el color. Desde `DECISIONES #138`
        // el color viaja en línea, así que aquí se asevera EL COLOR de cada zona, que es lo que el
        // visitante ve: más fuerte que lo anterior y sin acoplar el CSS a los datos.
        foreach (Zone::where('show_in_landing', true)->get() as $zone) {
            $response->assertSee(
                ThemeSettings::zoneStyle($zone->color, $zone->color_secondary, $zone->accent),
                false,
            );
        }
        $response->assertSee('Jump · 1 hora');   // entradas de la zona Jump en el DOM
        $response->assertSee('Kids · 1 hora');   // entradas de la zona Kids en el DOM
    }

    public function test_each_zone_has_a_top_featured_entry(): void
    {
        $featured = TicketType::where('featured', true)->get();

        $this->assertCount(2, $featured);                                  // una "Top" por zona
        $this->assertSame(2, $featured->pluck('zone_id')->unique()->count()); // Jump y Kids
        $featured->each(fn (TicketType $t) => $this->assertNotNull($t->tr('badge')));
    }
}
