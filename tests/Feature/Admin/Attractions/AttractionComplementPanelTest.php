<?php

namespace Tests\Feature\Admin\Attractions;

use App\Domain\Content\Models\Attraction;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Models\RateType;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * #228 — `AttractionResource`: vincular una atracción a un complemento de pago (`ticket_type_id`)
 * + marcarla como «Destacada» (`is_special`), con aviso si el complemento no está enganchado a
 * una entrada vendible de la zona de la atracción.
 */
class AttractionComplementPanelTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $u;
    }

    private function sellableAddon(): TicketType
    {
        $addon = TicketType::create([
            'name' => ['es' => 'Tirolina'], 'type' => TicketType::TYPE_ADDON,
            'is_active' => true, 'is_sellable' => true, 'seats_per_unit' => 1, 'position' => 20,
        ]);
        $addon->prices()->create([
            'rate_type_id' => RateType::firstOrCreate(['key' => RateType::KEY_NORMAL], ['label' => ['es' => 'Normal'], 'priority' => 0])->id,
            'amount_cents' => 500,
        ]);

        return $addon;
    }

    private function sellableEntry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Entrada Jump'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'is_active' => true, 'is_sellable' => true,
            'seats_per_unit' => 1, 'position' => 1,
        ]);
    }

    public function test_create_with_complement_and_special_persists_and_audits(): void
    {
        $addon = $this->sellableAddon();

        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm([
                'zone_id' => $this->zone->id,
                'name' => ['es' => 'Tirolina aérea'],
                'position' => 1,
                'is_active' => true,
                'is_special' => true,
                'ticket_type_id' => $addon->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $attraction = Attraction::firstOrFail();
        $this->assertSame($addon->id, $attraction->ticket_type_id);
        $this->assertTrue($attraction->is_special);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.attraction_created', 'target_id' => $attraction->id,
        ]);
    }

    public function test_is_special_defaults_to_false_when_untouched(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm(['zone_id' => $this->zone->id, 'name' => ['es' => 'Foam Pit'], 'position' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Attraction::firstOrFail()->is_special);
    }

    public function test_warning_shown_when_complement_not_attached_to_a_zone_entry(): void
    {
        // Addon vendible pero SIN enganchar a ninguna entrada de la zona → la landing no podría venderlo.
        $addon = $this->sellableAddon();
        $attraction = Attraction::create([
            'zone_id' => $this->zone->id, 'name' => ['es' => 'A'], 'position' => 1,
            'ticket_type_id' => $addon->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->assertSee(__('admin.attractions.complement_not_attached_warning'));
    }

    public function test_no_warning_when_complement_is_attached_to_a_sellable_zone_entry(): void
    {
        $addon = $this->sellableAddon();
        $this->sellableEntry()->configurableAddons()->attach($addon->id, ['position' => 1]);
        $attraction = Attraction::create([
            'zone_id' => $this->zone->id, 'name' => ['es' => 'A'], 'position' => 1,
            'ticket_type_id' => $addon->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->assertDontSee(__('admin.attractions.complement_not_attached_warning'));
    }
}
