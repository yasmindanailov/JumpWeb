<?php

namespace Tests\Feature\Admin\ParkRules;

use App\Filament\Resources\ParkRules\Pages\CreateParkRule;
use App\Filament\Resources\ParkRules\Pages\EditParkRule;
use App\Filament\Resources\ParkRules\Pages\ListParkRules;
use App\Filament\Resources\ParkRules\ParkRuleResource;
use App\Models\ParkRule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.9 (iter. 1) — `ParkRuleResource`: gating por `content.manage`, CRUD, limpieza i18n,
 * defaults y borrado con audit.
 */
class ParkRuleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
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
            'name' => ['es' => 'Conducta'],
            'description' => ['es' => 'Respeta al resto.'],
            'position' => 1,
            'is_active' => true,
        ], $overrides);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(ParkRuleResource::canViewAny());
        $this->get('/admin/park-rules')->assertOk();

        $this->actingAs($this->staff())->get('/admin/park-rules')->assertForbidden();
    }

    public function test_create_persists_compacts_i18n_and_audits(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateParkRule::class)
            ->fillForm($this->validForm([
                'name' => ['es' => 'Conducta', 'en' => 'Conduct', 'fr' => ''],
                'description' => ['es' => 'Respeta.', 'en' => '', 'fr' => ''],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = ParkRule::firstOrFail();
        $this->assertSame(['es' => 'Conducta', 'en' => 'Conduct'], $rule->name);
        $this->assertSame(['es' => 'Respeta.'], $rule->description);
        $this->assertTrue($rule->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.rule_created', 'target_id' => $rule->id]);
    }

    public function test_create_defaults_active_when_not_touched(): void
    {
        $form = $this->validForm();
        unset($form['is_active']);

        Livewire::actingAs($this->admin())
            ->test(CreateParkRule::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ParkRule::firstOrFail()->is_active);
    }

    public function test_create_requires_spanish_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateParkRule::class)
            ->fillForm($this->validForm(['name' => ['es' => '']]))
            ->call('create')
            ->assertHasFormErrors(['name.es']);

        $this->assertSame(0, ParkRule::count());
    }

    public function test_edit_updates_and_audits(): void
    {
        $rule = ParkRule::create(['name' => ['es' => 'N'], 'position' => 1]);

        Livewire::actingAs($this->admin())
            ->test(EditParkRule::class, ['record' => $rule->id])
            ->fillForm(['name' => ['es' => 'Norma nueva']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Norma nueva'], $rule->refresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.rule_updated', 'target_id' => $rule->id]);
    }

    public function test_edit_preserves_untouched_locales(): void
    {
        $rule = ParkRule::create(['name' => ['es' => 'Conducta', 'en' => 'Conduct'], 'position' => 1]);

        Livewire::actingAs($this->admin())
            ->test(EditParkRule::class, ['record' => $rule->id])
            ->fillForm(['name' => ['es' => 'Norma nueva']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Norma nueva', 'en' => 'Conduct'], $rule->refresh()->name, 'el inglés no tocado sobrevive');
    }

    public function test_admin_can_reorder_rules(): void
    {
        $first = ParkRule::create(['name' => ['es' => 'Primera'], 'position' => 1]);
        $second = ParkRule::create(['name' => ['es' => 'Segunda'], 'position' => 2]);

        Livewire::actingAs($this->admin())
            ->test(ListParkRules::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertLessThan($first->fresh()->position, $second->fresh()->position);
    }

    public function test_delete_and_audits(): void
    {
        $rule = ParkRule::create(['name' => ['es' => 'N']]);

        Livewire::actingAs($this->admin())
            ->test(EditParkRule::class, ['record' => $rule->id])
            ->callAction('deleteParkRule');

        $this->assertNull($rule->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.rule_deleted', 'target_id' => $rule->id]);
    }
}
