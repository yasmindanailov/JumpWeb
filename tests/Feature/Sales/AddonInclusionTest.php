<?php

namespace Tests\Feature\Sales;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\AddonResolver;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Complementos avanzados: incluidos (1.ª unidad gratis), obligatorios (auto-inyectados), por
 * invitado (cantidad = unidades de la línea) y grupos de elección EXCLUYENTE (Menú 1 ⊻ Menú 2).
 *
 * Se prueba a través de `OrderCreator`, que delega en `AddonResolver` (el mismo camino que la
 * compra pública y el alta manual). La pieza financiera clave es `OrderItem.free_quantity` y
 * `chargedSubtotalCents()`: lo INCLUIDO no se cobra ni se puede reembolsar.
 *
 * Para simplificar el aforo se usa una ENTRADA (no un pack); la cantidad de la línea hace de
 * "invitados" para los complementos `per_guest` (misma mecánica).
 */
class AddonInclusionTest extends TestCase
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

        $this->creator = app(OrderCreator::class);
        $this->user = User::factory()->create();

        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->normalRateId = (int) RateType::where('key', 'normal')->value('id');

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP']]);
        $this->date = Carbon::today()->addDays(2)->toDateString();
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

    /**
     * Crea un complemento enganchado al producto con la config de pivote indicada.
     *
     * @param  array<string, mixed>  $pivot
     */
    private function makeAddon(string $name, ?int $price, array $pivot = []): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'is_sellable' => true, 'is_active' => true, 'position' => 50,
        ]);
        if ($price !== null) {
            $addon->prices()->create(['rate_type_id' => $this->normalRateId, 'amount_cents' => $price]);
        }

        DB::table('product_addons')->insert(array_merge([
            'product_id' => $this->product->id, 'addon_id' => $addon->id, 'position' => 0,
            'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true, 'choice_group' => null,
        ], $pivot));

        return $addon;
    }

    /**
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $addons
     */
    private function order(array $addons, int $qty = 1): Order
    {
        return $this->creator->createPendingOrder($this->user, [[
            'ticket_type_id' => $this->product->id, 'date' => $this->date, 'time' => '10:00:00', 'qty' => $qty,
            'addons' => $addons,
        ]]);
    }

    public function test_included_fixed_addon_first_unit_is_free(): void
    {
        $cake = $this->makeAddon('Tarta', 1500, ['is_included' => true, 'included_quantity' => 1]);

        $order = $this->order([['ticket_type_id' => $cake->id, 'qty' => 1]]);
        $item = $order->items->firstWhere('ticket_type_id', $cake->id);

        $this->assertSame(1, $item->quantity);
        $this->assertSame(1, $item->free_quantity);
        $this->assertSame(1500, $item->unit_price);          // guarda el precio normal (para extras)
        $this->assertSame(0, $item->chargedSubtotalCents());  // pero no se cobra
        $this->assertSame(1000, $order->total);               // solo la entrada
    }

    public function test_included_fixed_addon_charges_only_the_extras(): void
    {
        $cake = $this->makeAddon('Tarta', 1500, ['is_included' => true, 'included_quantity' => 1]);

        $order = $this->order([['ticket_type_id' => $cake->id, 'qty' => 3]]);
        $item = $order->items->firstWhere('ticket_type_id', $cake->id);

        $this->assertSame(3, $item->quantity);
        $this->assertSame(1, $item->free_quantity);
        $this->assertSame(3000, $item->chargedSubtotalCents()); // 2 × 1500
        $this->assertSame(4000, $order->total);                 // 1000 + 3000
    }

    public function test_mandatory_addon_is_auto_injected_when_the_client_omits_it(): void
    {
        $cake = $this->makeAddon('Tarta', 1500, ['is_included' => true, 'is_mandatory' => true, 'included_quantity' => 1]);

        $order = $this->order([]); // el cliente no lo añade
        $item = $order->items->firstWhere('ticket_type_id', $cake->id);

        $this->assertNotNull($item);
        $this->assertSame(1, $item->quantity);
        $this->assertSame(1, $item->free_quantity);
        $this->assertSame(1000, $order->total);
    }

    public function test_per_guest_included_addon_is_free_for_every_guest(): void
    {
        $menu = $this->makeAddon('Menú 1', 0, [
            'is_included' => true, 'is_mandatory' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST,
        ]);

        $order = $this->order([], qty: 5);
        $item = $order->items->firstWhere('ticket_type_id', $menu->id);

        $this->assertSame(5, $item->quantity);       // una por unidad de la línea
        $this->assertSame(5, $item->free_quantity);
        $this->assertSame(0, $item->chargedSubtotalCents());
        $this->assertSame(5000, $order->total);      // 5 × 1000 entradas, menús gratis
    }

    public function test_exclusive_group_injects_the_free_default_when_none_chosen(): void
    {
        $menu1 = $this->makeAddon('Menú 1', 0, ['is_included' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => 'menu']);
        $menu2 = $this->makeAddon('Menú 2', 800, ['quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => 'menu']);

        $order = $this->order([], qty: 4);

        $this->assertNotNull($order->items->firstWhere('ticket_type_id', $menu1->id));
        $this->assertNull($order->items->firstWhere('ticket_type_id', $menu2->id));
        $this->assertSame(4000, $order->total); // menú 1 gratis
    }

    public function test_exclusive_group_paid_choice_replaces_the_free_default(): void
    {
        $menu1 = $this->makeAddon('Menú 1', 0, ['is_included' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => 'menu']);
        $menu2 = $this->makeAddon('Menú 2', 800, ['quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => 'menu']);

        $order = $this->order([['ticket_type_id' => $menu2->id, 'qty' => 1]], qty: 4);

        $this->assertNull($order->items->firstWhere('ticket_type_id', $menu1->id)); // invalidado
        $m2 = $order->items->firstWhere('ticket_type_id', $menu2->id);
        $this->assertSame(4, $m2->quantity);                 // por invitado
        $this->assertSame(0, $m2->free_quantity);
        $this->assertSame(3200, $m2->chargedSubtotalCents()); // 4 × 800
        $this->assertSame(7200, $order->total);               // 4000 + 3200
    }

    public function test_exclusive_group_rejects_two_members_at_once(): void
    {
        $menu1 = $this->makeAddon('Menú 1', 0, ['is_included' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => 'menu']);
        $menu2 = $this->makeAddon('Menú 2', 800, ['quantity_mode' => ProductAddon::MODE_PER_GUEST, 'choice_group' => 'menu']);

        $this->expectException(ReservationException::class);
        $this->order([
            ['ticket_type_id' => $menu1->id, 'qty' => 1],
            ['ticket_type_id' => $menu2->id, 'qty' => 1],
        ], qty: 3);
    }

    public function test_optional_addon_is_backward_compatible(): void
    {
        $socks = $this->makeAddon('Calcetines', 200); // sin flags = opcional de pago

        $order = $this->order([['ticket_type_id' => $socks->id, 'qty' => 2]]);
        $item = $order->items->firstWhere('ticket_type_id', $socks->id);

        $this->assertSame(2, $item->quantity);
        $this->assertSame(0, $item->free_quantity);
        $this->assertSame(400, $item->chargedSubtotalCents());
        $this->assertSame(1400, $order->total);
    }

    public function test_refund_capacity_excludes_the_free_units(): void
    {
        $cake = $this->makeAddon('Tarta', 1500, ['is_included' => true, 'included_quantity' => 1]);

        $order = $this->order([['ticket_type_id' => $cake->id, 'qty' => 2]]); // 1 gratis + 1 de pago
        $item = $order->items->firstWhere('ticket_type_id', $cake->id);

        // La base reembolsable es solo la unidad de pago, no la incluida.
        $this->assertSame(1500, $order->itemOriginalTotal($item));
    }

    public function test_read_surface_badge_and_partial_note(): void
    {
        // Incluido con extras (1 gratis + 2 de pago): badge INCLUIDO + nota "1 incluido gratis".
        $cake = $this->makeAddon('Tarta', 1500, ['is_included' => true, 'included_quantity' => 1]);
        $order = $this->order([['ticket_type_id' => $cake->id, 'qty' => 3]]);
        $item = $order->items->firstWhere('ticket_type_id', $cake->id);
        $this->assertSame('included', $item->addonBadgeKey());
        $this->assertSame(__('tickets.addon_included_partial', ['count' => 1]), $item->partialFreeNote());

        // De pago: sin badge ni nota.
        $socks = $this->makeAddon('Calcetines', 200);
        $order2 = $this->order([['ticket_type_id' => $socks->id, 'qty' => 2]]);
        $sItem = $order2->items->firstWhere('ticket_type_id', $socks->id);
        $this->assertNull($sItem->addonBadgeKey());
        $this->assertNull($sItem->partialFreeNote());

        // Precio 0 (no incluido): badge GRATIS, sin nota parcial.
        $free = $this->makeAddon('Pegatina', 0);
        $order3 = $this->order([['ticket_type_id' => $free->id, 'qty' => 2]]);
        $fItem = $order3->items->firstWhere('ticket_type_id', $free->id);
        $this->assertSame('free', $fItem->addonBadgeKey());
        $this->assertNull($fItem->partialFreeNote());
    }

    public function test_resolver_static_helpers(): void
    {
        $fixedIncluded = (new ProductAddon)->forceFill([
            'is_included' => true, 'included_quantity' => 2, 'is_mandatory' => false,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
        ]);
        $this->assertSame(2, AddonResolver::freeUnits($fixedIncluded, 5));
        $this->assertSame(5, AddonResolver::effectiveQuantity($fixedIncluded, 5, 10));

        $perGuestIncluded = (new ProductAddon)->forceFill([
            'is_included' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_mandatory' => true,
        ]);
        $this->assertSame(8, AddonResolver::freeUnits($perGuestIncluded, 8));
        $this->assertSame(8, AddonResolver::effectiveQuantity($perGuestIncluded, 1, 8)); // ignora la pedida

        $mandatoryFixed = (new ProductAddon)->forceFill([
            'is_included' => true, 'included_quantity' => 1, 'is_mandatory' => true,
            'quantity_mode' => ProductAddon::MODE_FIXED, 'allow_extra' => true,
        ]);
        $this->assertSame(1, AddonResolver::effectiveQuantity($mandatoryFixed, 0, 5)); // sube al mínimo

        $notIncluded = (new ProductAddon)->forceFill([
            'is_included' => false, 'quantity_mode' => ProductAddon::MODE_FIXED,
        ]);
        $this->assertSame(0, AddonResolver::freeUnits($notIncluded, 3));
    }
}
