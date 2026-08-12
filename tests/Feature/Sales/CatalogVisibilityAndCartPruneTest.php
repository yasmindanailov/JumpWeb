<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P3 — «Visible en la web» (`is_active`) y «En venta online» (`is_sellable`) son ejes INDEPENDIENTES:
 *   · `is_sellable` gobierna SOLO el catálogo/checkout/panel (`scopeSellable`): un producto OCULTO de
 *     la web (`is_active=false`) pero `is_sellable=true` SÍ se vende; uno visible pero no vendible, no.
 *   · `is_active` gobierna la visibilidad en la LANDING por separado.
 * P8 — el carrito PODA en `mount()` las líneas cuyo producto dejó de ser vendible (no más fantasmas:
 *   antes contaban en el badge, no se renderizaban, sumaban 0 € y rompían el checkout con «—»).
 */
class CatalogVisibilityAndCartPruneTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private int $normalRateId;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-08'); // lunes → tarifa normal
        $this->today = Carbon::today()->toDateString();
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1, 'is_active' => true]);
        Slot::create([
            'zone_id' => $this->zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 10, 'online_capacity' => 5,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function entry(bool $active, bool $sellable, string $name = 'Jump · 1 hora'): TicketType
    {
        $e = TicketType::create([
            'name' => ['es' => $name], 'zone_id' => $this->zone->id, 'duration_min' => 60,
            'is_active' => $active, 'is_sellable' => $sellable, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $e->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 1000]);

        return $e;
    }

    public function test_sellable_scope_ignores_web_visibility(): void
    {
        $hiddenButSellable = $this->entry(active: false, sellable: true, name: 'Oculto vendible');
        $visibleNotSellable = $this->entry(active: true, sellable: false, name: 'Visible no vendible');

        $sellableIds = TicketType::sellable()->pluck('id')->all();

        $this->assertContains($hiddenButSellable->id, $sellableIds);     // oculto de la web pero EN VENTA → vendible
        $this->assertNotContains($visibleNotSellable->id, $sellableIds); // visible pero NO en venta → no vendible
    }

    public function test_hidden_from_web_product_is_not_on_the_landing(): void
    {
        $this->entry(active: true, sellable: true, name: 'EntradaVisible');
        $this->entry(active: false, sellable: true, name: 'EntradaOculta');

        $this->get('/')
            ->assertSee('EntradaVisible')        // visible en web → en la landing
            ->assertDontSee('EntradaOculta');    // oculta de la web → NO en la landing (aunque sea vendible)
    }

    public function test_cart_prunes_a_line_whose_product_became_unsellable(): void
    {
        $entry = $this->entry(active: true, sellable: true);
        session(['purchase.cart' => [[
            'ticket_type_id' => $entry->id, 'qty' => 1,
            'date' => $this->today, 'time' => '10:00:00', 'addons' => [], 'event_data' => [],
        ]]]);

        // El producto deja de estar «En venta online».
        $entry->update(['is_sellable' => false]);

        $component = Livewire::test(Purchase::class);
        $component->assertSet('cart', []);                   // la línea fantasma se podó en mount
        $this->assertSame(0, $component->instance()->cartCount()); // badge a 0 (no «1 ítem a 0 €»)
        $component->assertSet('step', 1);                    // cesta vacía → no fuerza el paso 4

        // La cesta de sesión también queda saneada (no reaparece al recargar).
        $this->assertSame([], session('purchase.cart'));
    }
}
