<?php

namespace Tests\Feature\Admin\Slots;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Slots\Pages\EditSlot;
use App\Filament\Resources\Slots\SlotResource;
use App\Models\Order;
use App\Models\Slot;
use App\Models\SlotTemplate;
use App\Models\TicketType;
use App\Models\Zone;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.7 iter.3 — `SlotResource`: gating por `slots.manage`, edición del toggle de venta
 * (en tándem con `status`), excepción de aforo con invariantes (≥ ocupación, ≤ total) + marca
 * «ajustado a mano», zona de cumpleaños sin aforo editable, y restablecer aforo a la plantilla.
 */
class SlotResourceTest extends TestCase
{
    use RefreshDatabase;

    private Zone $entryZone;

    private Zone $packZone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->entryZone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
        $this->packZone = Zone::create(['slug' => 'cumpleanos', 'name' => ['es' => 'Cumpleaños'], 'position' => 2]);

        // Un pack en la zona de cumpleaños la convierte en "zona de cupo" (detección data-driven).
        TicketType::create([
            'name' => ['es' => 'Cumple Jump'], 'zone_id' => $this->packZone->id,
            'type' => TicketType::TYPE_PACK, 'duration_min' => 120, 'min_qty' => 8, 'max_qty' => 20,
            'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    private function entrySlot(int $capacity = 60, int $online = 40): Slot
    {
        return Slot::create([
            'zone_id' => $this->entryZone->id,
            'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '12:00:00', 'end_time' => '13:00:00',
            'capacity' => $capacity, 'online_capacity' => $online,
        ]);
    }

    private function occupy(Slot $slot, int $seats): void
    {
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'zone_id' => $slot->zone_id, 'type' => TicketType::TYPE_ENTRY,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 5,
        ]);
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID,
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $seats, 'unit_price' => 1000, 'seats' => $seats,
        ]);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(SlotResource::canViewAny());
        $this->assertFalse(SlotResource::canCreate());
        $this->assertFalse(SlotResource::canDelete($this->entrySlot()));

        $this->actingAs($this->staff())->get('/admin/slots')->assertForbidden();
    }

    public function test_toggle_closes_and_reopens_in_tandem_with_status(): void
    {
        $slot = $this->entrySlot();

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->fillForm(['online_sales_open' => false, 'online_capacity' => 40])
            ->call('save')
            ->assertHasNoFormErrors();

        $slot->refresh();
        $this->assertFalse($slot->online_sales_open);
        $this->assertSame(Slot::STATUS_CLOSED, $slot->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.slot_updated', 'target_id' => $slot->id]);

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->fillForm(['online_sales_open' => true, 'online_capacity' => 40])
            ->call('save')
            ->assertHasNoFormErrors();

        $slot->refresh();
        $this->assertTrue($slot->online_sales_open);
        $this->assertSame(Slot::STATUS_OPEN, $slot->status);
    }

    public function test_changing_capacity_pins_it_as_overridden(): void
    {
        $slot = $this->entrySlot(online: 40);

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->fillForm(['online_sales_open' => true, 'online_capacity' => 20])
            ->call('save')
            ->assertHasNoFormErrors();

        $slot->refresh();
        $this->assertSame(20, $slot->online_capacity);
        $this->assertTrue($slot->capacity_overridden);
    }

    public function test_capacity_cannot_go_below_live_occupancy(): void
    {
        $slot = $this->entrySlot(online: 40);
        $this->occupy($slot, 10);

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->fillForm(['online_sales_open' => true, 'online_capacity' => 5])
            ->call('save');

        $slot->refresh();
        $this->assertSame(40, $slot->online_capacity, 'no se guarda un aforo por debajo de lo ya vendido');
        $this->assertFalse($slot->capacity_overridden);
    }

    public function test_capacity_cannot_exceed_total(): void
    {
        $slot = $this->entrySlot(capacity: 60, online: 40);

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->fillForm(['online_sales_open' => true, 'online_capacity' => 100])
            ->call('save');

        $this->assertSame(40, $slot->refresh()->online_capacity);
    }

    public function test_pack_zone_slot_has_no_editable_capacity(): void
    {
        $slot = Slot::create([
            'zone_id' => $this->packZone->id, 'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '18:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->assertFormFieldIsHidden('online_capacity')
            ->fillForm(['online_sales_open' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $slot->refresh();
        $this->assertFalse($slot->online_sales_open);
        $this->assertSame(200, $slot->online_capacity, 'el aforo de cupo no se toca');
        $this->assertFalse($slot->capacity_overridden);
    }

    public function test_reset_restores_template_capacity_and_clears_override(): void
    {
        $date = Carbon::tomorrow();
        SlotTemplate::create([
            'zone_id' => $this->entryZone->id, 'weekday' => $date->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);
        $slot = Slot::create([
            'zone_id' => $this->entryZone->id, 'date' => $date->toDateString(),
            'start_time' => '12:00:00', 'end_time' => '13:00:00',
            'capacity' => 60, 'online_capacity' => 5, 'capacity_overridden' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->callAction('resetCapacity')
            ->assertSchemaStateSet(['online_capacity' => 40]); // el formulario muestra el valor nuevo, no el stale

        $slot->refresh();
        $this->assertSame(40, $slot->online_capacity);
        $this->assertFalse($slot->capacity_overridden);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.capacity_override_cleared', 'target_id' => $slot->id]);
    }

    public function test_reset_then_save_keeps_the_template_capacity(): void
    {
        // Regresión: tras restablecer, un guardado posterior NO debe re-fijar el override (valor stale).
        $date = Carbon::tomorrow();
        SlotTemplate::create([
            'zone_id' => $this->entryZone->id, 'weekday' => $date->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);
        $slot = Slot::create([
            'zone_id' => $this->entryZone->id, 'date' => $date->toDateString(),
            'start_time' => '12:00:00', 'end_time' => '13:00:00',
            'capacity' => 60, 'online_capacity' => 5, 'capacity_overridden' => true,
        ]);

        $component = Livewire::actingAs($this->admin())->test(EditSlot::class, ['record' => $slot->id]);
        $component->callAction('resetCapacity')->call('save')->assertHasNoFormErrors();

        $slot->refresh();
        $this->assertSame(40, $slot->online_capacity);
        $this->assertFalse($slot->capacity_overridden, 'guardar tras restablecer no re-fija el override');
    }

    public function test_reset_is_blocked_when_template_below_live_occupancy(): void
    {
        $date = Carbon::tomorrow();
        SlotTemplate::create([
            'zone_id' => $this->entryZone->id, 'weekday' => $date->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 30, 'is_active' => true,
        ]);
        $slot = Slot::create([
            'zone_id' => $this->entryZone->id, 'date' => $date->toDateString(),
            'start_time' => '12:00:00', 'end_time' => '13:00:00',
            'capacity' => 60, 'online_capacity' => 50, 'capacity_overridden' => true,
        ]);
        $this->occupy($slot, 40); // 40 vendidas > 30 de la plantilla

        Livewire::actingAs($this->admin())
            ->test(EditSlot::class, ['record' => $slot->id])
            ->callAction('resetCapacity');

        $slot->refresh();
        $this->assertSame(50, $slot->online_capacity, 'no se restablece por debajo de lo ya vendido');
        $this->assertTrue($slot->capacity_overridden);
    }
}
