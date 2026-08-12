<?php

namespace Tests\Feature\Admin\Slots;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\WeeklySchedule;
use App\Filament\Resources\Slots\Pages\ListSlots;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.7 iter.3 — Acción «Regenerar franjas» (trait `RegeneratesSlots`) desde el listado de
 * franjas y desde el Horario semanal: genera el horario vigente, poda con seguridad (borra
 * obsoletas sin reservas, cierra las que tienen) y audita los conteos.
 */
class RegenerateSlotsActionTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'JUMP'], 'position' => 1]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    public function test_regenerate_from_list_creates_prunes_and_audits(): void
    {
        $wed = Carbon::now()->next(Carbon::WEDNESDAY);
        SlotTemplate::create([
            'zone_id' => $this->zone->id, 'weekday' => $wed->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);

        // Franja obsoleta (sin plantilla) en una fecha futura del rango, sin reservas.
        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wed->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 60, 'online_capacity' => 40,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListSlots::class)
            ->callAction('regenerateSlots', [
                'from' => Carbon::today()->toDateString(),
                'to' => $wed->toDateString(),
            ]);

        $this->assertNull($obsolete->fresh(), 'la franja obsoleta sin reservas se borra');
        $this->assertDatabaseHas('slots', [
            'zone_id' => $this->zone->id, 'date' => $wed->toDateString(), 'start_time' => '12:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.regenerated']);
    }

    public function test_regenerate_closes_obsolete_slot_with_bookings(): void
    {
        $wed = Carbon::now()->next(Carbon::WEDNESDAY);
        SlotTemplate::create([
            'zone_id' => $this->zone->id, 'weekday' => $wed->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);

        $obsolete = Slot::create([
            'zone_id' => $this->zone->id, 'date' => $wed->toDateString(),
            'start_time' => '09:00:00', 'end_time' => '10:00:00', 'capacity' => 60, 'online_capacity' => 40,
        ]);
        $type = TicketType::create([
            'name' => ['es' => 'Entrada'], 'zone_id' => $this->zone->id, 'type' => TicketType::TYPE_ENTRY,
            'duration_min' => 60, 'is_sellable' => true, 'is_active' => true, 'seats_per_unit' => 1, 'position' => 1,
        ]);
        $order = Order::create(['user_id' => $this->admin()->id, 'code' => 'JJ-'.Str::upper(Str::random(6)), 'status' => Order::STATUS_PAID]);
        $item = $order->items()->create(['ticket_type_id' => $type->id, 'slot_id' => $obsolete->id, 'quantity' => 2, 'unit_price' => 1000, 'seats' => 2]);

        Livewire::actingAs($this->admin())
            ->test(ListSlots::class)
            ->callAction('regenerateSlots', ['from' => Carbon::today()->toDateString(), 'to' => $wed->toDateString()]);

        $fresh = $obsolete->fresh();
        $this->assertNotNull($fresh, 'no se borra (preserva la venta)');
        $this->assertFalse($fresh->online_sales_open);
        $this->assertNotNull($item->fresh());
    }

    public function test_regenerate_allows_a_single_day(): void
    {
        // Caso habitual tras editar una fecha especial concreta: from == to (rango inclusivo).
        $wed = Carbon::now()->next(Carbon::WEDNESDAY);
        SlotTemplate::create([
            'zone_id' => $this->zone->id, 'weekday' => $wed->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListSlots::class)
            ->callAction('regenerateSlots', ['from' => $wed->toDateString(), 'to' => $wed->toDateString()])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('slots', ['date' => $wed->toDateString(), 'start_time' => '12:00:00']);
    }

    public function test_regenerate_rejects_reversed_range_without_side_effects(): void
    {
        $wed = Carbon::now()->next(Carbon::WEDNESDAY);
        SlotTemplate::create([
            'zone_id' => $this->zone->id, 'weekday' => $wed->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);

        // to < from: lo rechaza la validación (afterOrEqual) sin generar ni auditar nada.
        Livewire::actingAs($this->admin())
            ->test(ListSlots::class)
            ->callAction('regenerateSlots', ['from' => $wed->toDateString(), 'to' => Carbon::today()->toDateString()])
            ->assertHasActionErrors(['to']);

        $this->assertSame(0, Slot::count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'slots.regenerated']);
    }

    public function test_regenerate_available_from_weekly_schedule(): void
    {
        $wed = Carbon::now()->next(Carbon::WEDNESDAY);
        SlotTemplate::create([
            'zone_id' => $this->zone->id, 'weekday' => $wed->dayOfWeek,
            'start_time' => '12:00:00', 'duration_min' => 60, 'capacity' => 60, 'online_capacity' => 40, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())
            ->test(WeeklySchedule::class)
            ->callAction('regenerateSlots', ['from' => Carbon::today()->toDateString(), 'to' => $wed->toDateString()]);

        $this->assertDatabaseHas('slots', ['date' => $wed->toDateString(), 'start_time' => '12:00:00']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.regenerated']);
    }
}
