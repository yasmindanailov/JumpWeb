<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Livewire\Tickets\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dependencia «requiere» entre complementos (data-driven): un complemento DEPENDIENTE solo es
 * seleccionable/vendible cuando su requisito también está elegido. Caso real: «Segunda tarta»
 * requiere «Tarta». La autoridad vive en `AddonResolver` y es COMPARTIDA por la oferta
 * (`viewModel`/`buildSelection`) y el cobro (`resolve`, vía `OrderCreator`): lo que se MUESTRA
 * deshabilitado es exactamente lo que el servidor RECHAZA, aunque se forje la cesta.
 */
class AddonDependencyTest extends TestCase
{
    use RefreshDatabase;

    private OrderCreator $creator;

    private User $user;

    private TicketType $product;

    private string $date;

    private int $normalRateId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08'); // lunes → tarifa normal, fechas deterministas
        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->date = Carbon::today()->toDateString();
        Slot::create([
            'zone_id' => $zone->id, 'date' => $this->date,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 50, 'online_capacity' => 50,
        ]);

        $this->product = TicketType::create([
            'name' => ['es' => 'Entrada'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->product->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string,mixed> $pivot */
    private function makeAddon(string $name, ?int $price, array $pivot = [], int $position = 0): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50 + $position,
        ]);
        if ($price !== null) {
            $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $price]);
        }
        DB::table('product_addons')->insert(array_merge([
            'product_id' => $this->product->id, 'addon_id' => $addon->id, 'position' => $position,
            'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
            'requires_addon_id' => null,
        ], $pivot));

        return $addon;
    }

    /** @param array<int, array{ticket_type_id:int, qty:int}> $addons */
    private function order(array $addons, int $qty = 1): Order
    {
        return $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->product->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty,
            'addons' => $addons,
        ]]);
    }

    /** Complementos ofrecibles del producto (con pivote + precios), como los lee el flujo de compra. */
    private function offered()
    {
        return $this->product->addons()->with('prices.rateType')->get();
    }

    // ─── Autoridad de COBRO (resolve, vía OrderCreator) ────────────────────────────────────────

    public function test_dependent_addon_is_dropped_when_its_requirement_is_absent(): void
    {
        $cake = $this->makeAddon('Tarta', 2000, [], 0);
        $second = $this->makeAddon('Segunda tarta', 1500, ['requires_addon_id' => $cake->id], 1);

        // Cesta FORJADA: pide la 2.ª tarta sin la 1.ª. El servidor la descarta (no se cobra).
        $order = $this->order([['ticket_type_id' => $second->id, 'qty' => 1]]);

        $this->assertNull($order->items->firstWhere('ticket_type_id', $second->id));
        $this->assertNull($order->items->firstWhere('ticket_type_id', $cake->id));
        $this->assertSame(1000, $order->total); // solo la entrada
    }

    public function test_dependent_addon_is_kept_when_its_requirement_is_present(): void
    {
        $cake = $this->makeAddon('Tarta', 2000, [], 0);
        $second = $this->makeAddon('Segunda tarta', 1500, ['requires_addon_id' => $cake->id], 1);

        $order = $this->order([
            ['ticket_type_id' => $cake->id, 'qty' => 1],
            ['ticket_type_id' => $second->id, 'qty' => 1],
        ]);

        $this->assertNotNull($order->items->firstWhere('ticket_type_id', $cake->id));
        $this->assertNotNull($order->items->firstWhere('ticket_type_id', $second->id));
        $this->assertSame(4500, $order->total); // 1000 + 2000 + 1500
    }

    // ─── Autoridad de OFERTA (viewModel) ───────────────────────────────────────────────────────

    public function test_view_model_marks_the_dependent_unavailable_until_the_requirement_is_chosen(): void
    {
        $cake = $this->makeAddon('Tarta', 2000, [], 0);
        $second = $this->makeAddon('Segunda tarta', 1500, ['requires_addon_id' => $cake->id], 1);

        $resolver = app(AddonResolver::class);

        // Solo la 2.ª en el qtyMap (requisito ausente): aparece DESHABILITADA con la nota «Requiere».
        $vm = $resolver->viewModel($this->offered(), [$second->id => 1], [], 1, false, Carbon::today());
        $secondRow = collect($vm['singles'])->firstWhere('id', $second->id);
        $this->assertFalse($secondRow['available']);
        $this->assertFalse($secondRow['selected']);
        $this->assertSame('Tarta', $secondRow['requires_name']);
        $this->assertFalse($secondRow['can_inc']);
        $this->assertSame(0, $vm['total']); // la 2.ª huérfana no suma

        // Con la 1.ª elegida, la 2.ª pasa a disponible y se cobra.
        $vm2 = $resolver->viewModel($this->offered(), [$cake->id => 1, $second->id => 1], [], 1, false, Carbon::today());
        $secondRow2 = collect($vm2['singles'])->firstWhere('id', $second->id);
        $this->assertTrue($secondRow2['available']);
        $this->assertTrue($secondRow2['selected']);
        $this->assertNull($secondRow2['requires_name']);
        $this->assertSame(3500, $vm2['total']); // 2000 + 1500
    }

    // ─── buildSelection (lo que la UI manda al servidor) ────────────────────────────────────────

    public function test_build_selection_excludes_an_orphan_dependent(): void
    {
        $cake = $this->makeAddon('Tarta', 2000, [], 0);
        $second = $this->makeAddon('Segunda tarta', 1500, ['requires_addon_id' => $cake->id], 1);

        $offered = $this->offered();

        $orphan = AddonResolver::buildSelection($offered, [$second->id => 1], [], 1);
        $this->assertSame([], $orphan);

        $both = AddonResolver::buildSelection($offered, [$cake->id => 1, $second->id => 1], [], 1);
        $ids = array_column($both, 'ticket_type_id');
        $this->assertContains($cake->id, $ids);
        $this->assertContains($second->id, $ids);
    }

    // ─── Cadenas A→B→C: la poda es a PUNTO FIJO ─────────────────────────────────────────────────

    public function test_dependency_chains_are_pruned_transitively(): void
    {
        $c = $this->makeAddon('C', 100, [], 0);
        $b = $this->makeAddon('B', 100, ['requires_addon_id' => $c->id], 1);
        $a = $this->makeAddon('A', 100, ['requires_addon_id' => $b->id], 2);

        $offered = $this->offered();

        // A y B elegidos, pero falta C: al caer C cae B, y al caer B cae A (punto fijo) → nada.
        $this->assertSame([], AddonResolver::resolveSelectedIds($offered, [$a->id => 1, $b->id => 1], [], 1));

        // Con C también elegido, la cadena entera se sostiene.
        $all = AddonResolver::resolveSelectedIds($offered, [$a->id => 1, $b->id => 1, $c->id => 1], [], 1);
        sort($all);
        $expected = [$a->id, $b->id, $c->id];
        sort($expected);
        $this->assertSame($expected, $all);
    }

    // ─── Checkout público (Livewire): la dependencia se respeta de punta a punta ─────────────────

    public function test_checkout_does_not_carry_an_orphan_dependent_into_the_cart(): void
    {
        $cake = $this->makeAddon('Tarta', 2000, [], 0);
        $second = $this->makeAddon('Segunda tarta', 1500, ['requires_addon_id' => $cake->id], 1);

        // El cliente fuerza la cantidad de la 2.ª sin elegir la 1.ª; el carrito NO la lleva.
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->product->id)
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('incAddon', $second->id)        // intenta añadir la 2.ª (sin la 1.ª)
            ->call('addToCart')
            ->assertSet('step', 4);

        $addons = collect($component->get('cart'))->last()['addons'] ?? [];
        $this->assertFalse(collect($addons)->contains('ticket_type_id', $second->id));
    }

    public function test_checkout_carries_the_dependent_once_its_requirement_is_chosen(): void
    {
        $cake = $this->makeAddon('Tarta', 2000, [], 0);
        $second = $this->makeAddon('Segunda tarta', 1500, ['requires_addon_id' => $cake->id], 1);

        // Con la 1.ª elegida, la 2.ª pasa a poder añadirse y ambas viajan al carrito.
        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->product->id)
            ->call('selectDate', $this->date)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('incAddon', $cake->id)
            ->call('incAddon', $second->id)
            ->call('addToCart')
            ->assertSet('step', 4);

        $addons = collect(collect($component->get('cart'))->last()['addons'] ?? []);
        $this->assertTrue($addons->contains('ticket_type_id', $cake->id));
        $this->assertTrue($addons->contains('ticket_type_id', $second->id));
    }
}
