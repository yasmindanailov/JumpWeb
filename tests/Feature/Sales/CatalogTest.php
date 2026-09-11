<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\RateResolver;
use App\Domain\Platform\Services\Money;
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

    /**
     * ⚠️⚠️ **Este caso miraba las `features` de la entrada y desde `#531` la página no las pinta**:
     * la tabla publica lo que el visitante viene a consultar —el nombre y el precio de cada día— y
     * las ventajas siguen en el cajón, que es donde se compra. Lo que el caso SIGUE vigilando es su
     * propiedad de verdad: que la página se escribe desde la BD y no desde una plantilla con
     * cifras. Se re-apunta al dato que sí pinta, que además es el que cuesta dinero equivocar.
     */
    public function test_pricing_page_renders_the_catalog_from_db(): void
    {
        $entrada = TicketType::ofType(TicketType::TYPE_ENTRY)->where('is_active', true)
            ->orderBy('position')->firstOrFail();

        $html = $this->get('/precios')->assertOk()->getContent();

        // El precio de la tarifa NORMAL, escrito como lo escribe la landing (sin decimales cuando
        // son cero): si alguien teclea una cifra en la plantilla, este caso se pone rojo.
        $this->assertStringContainsString(
            Money::showcase($entrada->displayPriceCents()).'&nbsp;€',
            str_replace(' €', '&nbsp;€', $html),
        );
    }

    public function test_price_varies_when_rates_differ(): void
    {
        $ticket = TicketType::with('prices')->where('position', 1)->first();

        // Jump · 1 hora: normal 990, especial 1190 → el precio varía por día.
        $this->assertTrue($ticket->priceVaries());
    }

    /**
     * ⚠️⚠️ **`/precios` YA NO TIENE PESTAÑA DE ZONA desde `#531`**: enseña las dos tablas a la vez,
     * porque quien abre el enlace que le han mandado **no ha elegido zona** —es la prueba que le dio
     * página a esta pantalla—. Lo que este caso vigilaba sigue vivo y se re-apunta: **las dos zonas
     * salen, cada una con su color del panel y con sus entradas dentro**.
     * ⚠️ El color se asevera como VALOR y no por el nombre de una clase: desde `#138` viaja en línea,
     * y una clase solo diría que la plantilla escribió el acento, no que llegara el color.
     */
    public function test_pricing_page_shows_both_zones_with_their_colour(): void
    {
        $html = (string) $this->get('/precios')->assertOk()->getContent();

        foreach (Zone::where('show_in_landing', true)->get() as $zone) {
            $this->assertStringContainsString((string) $zone->tr('name'), $html, 'falta la cabecera de una zona');
            $this->assertStringContainsString('background: '.$zone->color, $html, 'la zona pierde su color');
        }

        // Las entradas de cada zona, con el nombre SIN el prefijo de su zona (`RateTable`).
        $this->assertStringContainsString('1 hora', $html);
        $this->assertStringContainsString('2 horas', $html);
    }

    public function test_each_zone_has_a_top_featured_entry(): void
    {
        $featured = TicketType::where('featured', true)->get();

        $this->assertCount(2, $featured);                                  // una "Top" por zona
        $this->assertSame(2, $featured->pluck('zone_id')->unique()->count()); // Jump y Kids
        $featured->each(fn (TicketType $t) => $this->assertNotNull($t->tr('badge')));
    }
}
