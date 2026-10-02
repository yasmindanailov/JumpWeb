<?php

namespace Tests\Feature\Orders;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Booking\Services\DailyReservationsSummary;
use App\Domain\Booking\Services\PostFormAddons;
use App\Domain\Booking\Services\ReservationSlip;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Models\Payment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **EL PARQUE VE «SIN ELEGIR»** (P3·4 de `fiesta-sistema-nuevo.md` §4.21; `[DECIDIDO owner]` `#913`: «el parque la ve "sin
 * elegir"», y `#914`). Un grupo de opciones con «hay que elegir» que la fiesta no ha contestado sale escrito, con su título, en
 * los tres sitios con los que el parque prepara la fiesta: el resumen del día (la columna «Merienda»), la ficha de la reserva
 * y el panel. Con sus controles: elegida, opcional o creada después de vender, no sale.
 */
class ChoiceGroupsParkTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $pack;

    private TicketType $pizza;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        RateType::create(['key' => RateType::KEY_NORMAL, 'label' => ['es' => 'Normal'], 'weekdays' => null, 'priority' => 0]);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'is_active' => true]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $this->zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 9,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
        $this->pizza = TicketType::create([
            'name' => ['es' => 'Pizza'], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
    }

    public function test_the_day_summary_says_what_is_still_to_choose_and_then_what_was_chosen(): void
    {
        $this->merienda(required: true);
        $item = $this->party();

        $this->assertSame('¿Qué merienda?: sin elegir', $this->snack($item), 'lo que el parque decide');

        app(PostFormAddons::class)->reconcile($item, [$this->pizza->id => 1], 'signed_link');
        $this->assertSame('Pizza', $this->snack($item), 'elegida: la merienda que será');
    }

    public function test_an_optional_group_or_one_created_after_the_sale_is_not_pending(): void
    {
        $grupo = $this->merienda(required: false);
        $item = $this->party();
        $this->assertNull($this->snack($item), 'sin «hay que elegir», «ninguna» es una respuesta');

        // Obligatorio, pero creado DESPUÉS de vender la fiesta: no se le pide («No se les pide», `#914`).
        $grupo->forceFill(['is_required' => true, 'created_at' => $item->order->created_at->copy()->addMinute()])->save();
        $this->assertNull($this->snack($item));

        // CONTROL: creado antes, sí.
        $grupo->forceFill(['created_at' => $item->order->created_at->copy()->subMinute()])->save();
        $this->assertSame('¿Qué merienda?: sin elegir', $this->snack($item));
    }

    public function test_the_reservation_slip_and_the_panel_say_it_with_the_addons(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->merienda(required: true);
        $item = $this->party();
        $order = $item->order;
        $this->assertNotNull($order);

        $this->assertSame(['¿Qué merienda?: sin elegir'], ReservationSlip::make($order, $item)->unansweredChoices());

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $page = $this->actingAs($staff, 'admin')->get('/admin/orders/'.$order->code)->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<ul[^>]*data-sin-elegir[^>]*>(.*?)</ul>#s', $page, $bloque), 'el bloque, bajo las líneas de la reserva');
        $this->assertStringContainsString('¿Qué merienda?: sin elegir', $bloque[1]);

        // CONTROL: elegida, ninguno de los dos lo dice.
        app(PostFormAddons::class)->reconcile($item->fresh(['ticketType.addons', 'order', 'slot', 'children']), [$this->pizza->id => 1], 'signed_link');
        $this->assertSame([], ReservationSlip::make($order->fresh(), $item->fresh())->unansweredChoices());
        $this->assertStringNotContainsString('data-sin-elegir', $this->actingAs($staff, 'admin')->get('/admin/orders/'.$order->code)->getContent());
    }

    // ── Fixture ─────────────────────────────────────────────────────────────────────────────────

    private function merienda(bool $required): AddonChoiceGroup
    {
        $grupo = AddonChoiceGroup::create(['product_id' => $this->pack->id, 'key' => 'merienda', 'title' => ['es' => '¿Qué merienda?'], 'is_required' => $required]);
        $this->pack->configurableAddons()->attach($this->pizza->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true,
            'stage' => ProductAddon::STAGE_POSTFORM, 'choice_group' => 'merienda',
        ]);
        $this->pack->refresh();

        return $grupo;
    }

    /** Una fiesta pagada de 6 dentro de diez días, como la deja la compra (con su pago). */
    private function party(): OrderItem
    {
        $slot = Slot::create([
            'zone_id' => $this->zone->id, 'date' => now()->addDays(10)->toDateString(),
            'start_time' => '11:00:00', 'end_time' => '13:00:00', 'capacity' => 40, 'online_capacity' => 40,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id, 'code' => 'JJ-PARQ1',
            'status' => Order::STATUS_PAID, 'subtotal' => 15000, 'tax' => 0, 'total' => 15000, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        Payment::create([
            'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id, 'amount' => 15000, 'currency' => 'EUR',
            'provider' => 'redsys', 'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'gateway_order' => '0000600001',
        ]);
        $order->items()->create([
            'ticket_type_id' => $this->pack->id, 'slot_id' => $slot->id, 'quantity' => 6, 'unit_price' => 2500, 'seats' => 6,
        ]);

        return Order::with(['items.children', 'items.slot', 'items.ticketType.addons', 'adjustments', 'payments.refunds'])
            ->findOrFail($order->id)->items->firstWhere('parent_item_id', null);
    }

    /** La columna «Merienda» de su fila en el resumen de su día. */
    private function snack(OrderItem $item): ?string
    {
        $fecha = (string) $item->fresh(['slot'])?->slot?->date?->toDateString();
        $fila = collect(DailyReservationsSummary::for($fecha)->rows())->sole();

        return $fila['snack'];
    }
}
