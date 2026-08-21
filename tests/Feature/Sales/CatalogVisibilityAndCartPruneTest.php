<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    /*
     * ⚠️ **Aquí vivía `test_cart_prunes_a_line_whose_product_became_unsellable`, y murió con
     * `Tickets\Purchase`** (4.7·2b·3, ejecutando la clasificación que `DECISIONES #87` dejó medida
     * el 2026-08-15: «borrar este y dejar los de arriba»).
     *
     * Su sujeto no era la regla sino **dónde se aplicaba**: la poda vivía en `Purchase::mount()`,
     * que leía la cesta de la SESIÓN. En el cajón SPA esa misma regla vive en otro sitio y ya tiene
     * red —lo comprobó aquella medición mutando el filtro `sellable()` de `CartPricer`—:
     *
     *  · que el servidor **no tarifique** una línea retirada → caen `Api\V1\QuoteTest`,
     *    `Sales\CartPricerTest` y `Sidebar\SidebarCartParityTest`, los tres supervivientes;
     *  · que el cliente **descarte** la línea que no volvió tarificada → `cart.js::reconcile()`,
     *    con sus casos en `cart.test.js`.
     */
}
