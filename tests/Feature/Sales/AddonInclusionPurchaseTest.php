<?php

namespace Tests\Feature\Sales;

use App\Livewire\Tickets\Purchase;
use App\Models\ProductAddon;
use App\Models\RateType;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Compra pública (sidebar Livewire) — el puente UI → carrito de los complementos avanzados:
 * los obligatorios/incluidos arrancan seleccionados, el grupo excluyente arranca en su default
 * (el incluido) y se puede cambiar al de pago, y el carrito que se manda a `OrderCreator` refleja
 * exactamente esa selección (la lógica de precio/gratis la prueba `AddonInclusionTest`).
 */
class AddonInclusionPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private string $today;

    private TicketType $entry;

    private int $normalRateId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-08'); // lunes → tarifa normal, fechas deterministas
        $this->today = Carbon::today()->toDateString();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        Slot::create([
            'zone_id' => $zone->id, 'date' => $this->today,
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 10, 'online_capacity' => 5,
        ]);

        $this->entry = TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'zone_id' => $zone->id, 'duration_min' => 60,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $this->entry->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param array<string,mixed> $pivot */
    private function attachAddon(string $name, ?int $price, array $pivot, int $position): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50 + $position,
        ]);
        if ($price !== null) {
            $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $price]);
        }
        DB::table('product_addons')->insert(array_merge([
            'product_id' => $this->entry->id, 'addon_id' => $addon->id, 'position' => $position,
            'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
        ], $pivot));

        return $addon;
    }

    public function test_selecting_the_product_sets_mandatory_and_group_defaults(): void
    {
        $cake = $this->attachAddon('Tarta', 1500, ['is_included' => true, 'is_mandatory' => true], 0);
        $menu1 = $this->attachAddon('Menú 1', 0, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu'], 1);
        $menu2 = $this->attachAddon('Menú 2', 800, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu'], 2);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->assertSet("addonQty.{$cake->id}", 1)            // obligatorio incluido arranca a 1
            ->assertSet('addonGroupChoice.menu', $menu1->id)  // default del grupo = el incluido
            ->call('selectAddonOption', 'menu', $menu2->id)
            ->assertSet('addonGroupChoice.menu', $menu2->id); // cambia al de pago
    }

    public function test_mandatory_included_addon_cannot_be_removed_below_its_minimum(): void
    {
        $cake = $this->attachAddon('Tarta', 1500, ['is_included' => true, 'is_mandatory' => true], 0);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('decAddon', $cake->id)                     // intenta bajar de 1
            ->assertSet("addonQty.{$cake->id}", 1)            // se queda en el mínimo
            ->call('incAddon', $cake->id)
            ->assertSet("addonQty.{$cake->id}", 2);           // pero sí puede añadir extras
    }

    public function test_unpriced_paid_addon_is_not_offered_as_free(): void
    {
        $this->attachAddon('Calcetines', 200, [], 0);       // de pago, con precio
        $this->attachAddon('Sin precio', null, [], 1);      // de pago, SIN tarifa → no vendible hoy

        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->assertSee('Calcetines')      // el priceado SÍ se ofrece
            ->assertDontSee('Sin precio');  // el de pago sin precio NO aparece como gratis
    }

    public function test_cart_carries_the_included_and_per_guest_selection(): void
    {
        $cake = $this->attachAddon('Tarta', 1500, ['is_included' => true, 'is_mandatory' => true], 0);
        $menu1 = $this->attachAddon('Menú 1', 0, ['is_included' => true, 'quantity_mode' => 'per_guest', 'choice_group' => 'menu'], 1);
        $menu2 = $this->attachAddon('Menú 2', 800, ['quantity_mode' => 'per_guest', 'choice_group' => 'menu'], 2);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('inc')                       // qty 1 → 2 (hace de "invitados")
            ->call('selectAddonOption', 'menu', $menu2->id)
            ->call('addToCart')
            ->assertSet('step', 4);

        $cart = $component->get('cart');
        $addons = collect($cart[0]['addons']);

        // El obligatorio incluido va; el menú elegido es el 2 (no el 1); el menú per_guest = invitados (2).
        $this->assertTrue($addons->contains('ticket_type_id', $cake->id));
        $this->assertTrue($addons->contains('ticket_type_id', $menu2->id));
        $this->assertFalse($addons->contains('ticket_type_id', $menu1->id));
        $this->assertSame(2, $addons->firstWhere('ticket_type_id', $menu2->id)['qty']);
    }

    public function test_per_guest_optional_addon_is_activated_with_a_checkbox_not_a_counter(): void
    {
        // Complemento per-invitado OPCIONAL de pago: su cantidad es automática (= invitados), así que
        // se activa con CHECKBOX (toggleAddon), no con contador. Antes no tenía ningún control y era
        // imposible de seleccionar.
        $socks = $this->attachAddon('Calcetines', 200, ['quantity_mode' => 'per_guest', 'allow_extra' => false], 0);

        $component = Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('selectDate', $this->today)->call('goToTime')
            ->call('selectTime', '10:00:00')
            ->call('inc'); // qty 1 → 2 (hace de "invitados")

        // El contador NO lo activa (per_guest no es de cantidad libre).
        $component->call('incAddon', $socks->id);
        $this->assertSame(0, (int) ($component->get('addonQty')[$socks->id] ?? 0));

        // El checkbox SÍ lo activa; en el carrito va con qty = invitados (2), no 1.
        $component->call('toggleAddon', $socks->id)
            ->assertSet("addonQty.{$socks->id}", 1)
            ->call('addToCart')->assertSet('step', 4);
        $addons = collect($component->get('cart')[0]['addons']);
        $this->assertSame(2, $addons->firstWhere('ticket_type_id', $socks->id)['qty']);
    }

    public function test_addon_max_qty_caps_the_quantity(): void
    {
        // P9: un complemento de pago topado a 1 por reserva. El contador no pasa de 1.
        $socks = $this->attachAddon('Calcetines', 200, ['max_qty' => 1], 0);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('incAddon', $socks->id)
            ->assertSet("addonQty.{$socks->id}", 1)
            ->call('incAddon', $socks->id)            // intenta subir a 2…
            ->assertSet("addonQty.{$socks->id}", 1);  // …topado a 1 (max_qty)
    }

    public function test_toggle_per_guest_addon_off_returns_to_zero(): void
    {
        $socks = $this->attachAddon('Calcetines', 200, ['quantity_mode' => 'per_guest', 'allow_extra' => false], 0);

        Livewire::test(Purchase::class)
            ->call('selectType', $this->entry->id)
            ->call('toggleAddon', $socks->id)
            ->assertSet("addonQty.{$socks->id}", 1)
            ->call('toggleAddon', $socks->id)
            ->assertSet("addonQty.{$socks->id}", 0);
    }
}
