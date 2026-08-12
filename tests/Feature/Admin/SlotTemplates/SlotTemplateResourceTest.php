<?php

namespace Tests\Feature\Admin\SlotTemplates;

use App\Filament\Resources\SlotTemplates\Pages\CreateSlotTemplate;
use App\Filament\Resources\SlotTemplates\Pages\EditSlotTemplate;
use App\Filament\Resources\SlotTemplates\SlotTemplateResource;
use App\Models\Role;
use App\Models\SlotTemplate;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.7 iter.3 — `SlotTemplateResource`: gating por `slots.manage`, CRUD, invariantes
 * (online ≤ total, unicidad zona/día/hora) y normalización de la hora a 'H:i:s' + weekday int.
 */
class SlotTemplateResourceTest extends TestCase
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

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    /** @return array<string,mixed> */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'zone_id' => $this->zone->id,
            'weekday' => 3,
            'start_time' => '10:00',
            'duration_min' => 60,
            'capacity' => 50,
            'online_capacity' => 30,
            'is_active' => true,
        ], $overrides);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(SlotTemplateResource::canViewAny());

        $this->actingAs($this->staff())->get('/admin/slot-templates')->assertForbidden();
    }

    public function test_create_persists_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSlotTemplate::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $template = SlotTemplate::firstOrFail();
        $this->assertSame($this->zone->id, $template->zone_id);
        $this->assertSame(3, $template->weekday);
        $this->assertSame('10:00:00', $template->start_time, 'la hora se normaliza a H:i:s');
        $this->assertTrue($template->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.template_created', 'target_id' => $template->id]);
    }

    public function test_create_rejects_online_above_total(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSlotTemplate::class)
            ->fillForm($this->validForm(['capacity' => 30, 'online_capacity' => 50]))
            ->call('create');

        $this->assertSame(0, SlotTemplate::count());
    }

    public function test_create_rejects_duplicate(): void
    {
        SlotTemplate::create($this->validForm(['start_time' => '10:00:00']));

        Livewire::actingAs($this->admin())
            ->test(CreateSlotTemplate::class)
            ->fillForm($this->validForm())
            ->call('create');

        $this->assertSame(1, SlotTemplate::count(), 'no se duplica la plantilla zona/día/hora');
    }

    public function test_edit_updates_and_allows_saving_itself(): void
    {
        $template = SlotTemplate::create($this->validForm(['start_time' => '10:00:00']));

        Livewire::actingAs($this->admin())
            ->test(EditSlotTemplate::class, ['record' => $template->id])
            ->fillForm(['online_capacity' => 40])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(40, $template->refresh()->online_capacity);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.template_updated', 'target_id' => $template->id]);
    }

    public function test_delete_removes_and_audits(): void
    {
        $template = SlotTemplate::create($this->validForm(['start_time' => '10:00:00']));

        Livewire::actingAs($this->admin())
            ->test(EditSlotTemplate::class, ['record' => $template->id])
            ->callAction('deleteTemplate');

        $this->assertDatabaseMissing('slot_templates', ['id' => $template->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'slots.template_deleted', 'target_id' => $template->id]);
    }
}
