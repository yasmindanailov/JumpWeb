<?php

namespace Tests\Feature\Admin\Attractions;

use App\Domain\Content\Models\Attraction;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Attractions\AttractionResource;
use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.9 (iter. 1) — `AttractionResource`: gating por `content.manage`, CRUD con zona +
 * imagen (ruta), limpieza i18n (badge vacío → null), normalización de la ruta y borrado.
 */
class AttractionResourceTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Zona Jump'], 'position' => 1]);
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
            'name' => ['es' => 'Tirolina'],
            'position' => 1,
            'is_active' => true,
        ], $overrides);
    }

    public function test_gating(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(AttractionResource::canViewAny());
        $this->get('/admin/attractions')->assertOk();

        $this->actingAs($this->staff())->get('/admin/attractions')->assertForbidden();
    }

    public function test_create_persists_with_zone_image_and_compacts_i18n(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm($this->validForm([
                'image' => '  images/attractions/attraction-03.jpg  ',
                'name' => ['es' => 'Tirolina', 'en' => 'Zipline', 'fr' => ''],
                'badge' => ['es' => '', 'en' => '', 'fr' => ''],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $attraction = Attraction::firstOrFail();
        $this->assertSame($this->zone->id, $attraction->zone_id);
        $this->assertSame('images/attractions/attraction-03.jpg', $attraction->image, 'ruta recortada');
        $this->assertSame(['es' => 'Tirolina', 'en' => 'Zipline'], $attraction->name);
        $this->assertNull($attraction->badge, 'badge en blanco en los 3 idiomas = null (sin etiqueta)');
        $this->assertTrue($attraction->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.attraction_created', 'target_id' => $attraction->id]);
    }

    public function test_blank_image_is_stored_as_null(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm($this->validForm(['image' => '   ']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Attraction::firstOrFail()->image);
    }

    public function test_create_requires_zone(): void
    {
        $form = $this->validForm();
        unset($form['zone_id']);

        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasFormErrors(['zone_id']);

        $this->assertSame(0, Attraction::count());
    }

    public function test_create_requires_spanish_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateAttraction::class)
            ->fillForm($this->validForm(['name' => ['es' => '']]))
            ->call('create')
            ->assertHasFormErrors(['name.es']);
    }

    public function test_edit_updates_and_audits(): void
    {
        $attraction = Attraction::create([
            'zone_id' => $this->zone->id, 'name' => ['es' => 'A'], 'position' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->fillForm(['name' => ['es' => 'Atracción nueva'], 'badge' => ['es' => 'XL']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Atracción nueva'], $attraction->refresh()->name);
        $this->assertSame(['es' => 'XL'], $attraction->badge);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.attraction_updated', 'target_id' => $attraction->id]);
    }

    public function test_edit_preserves_untouched_locales(): void
    {
        $attraction = Attraction::create([
            'zone_id' => $this->zone->id, 'name' => ['es' => 'Tirolina', 'en' => 'Zipline'], 'position' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->fillForm(['name' => ['es' => 'Tirolina nueva']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['es' => 'Tirolina nueva', 'en' => 'Zipline'], $attraction->refresh()->name, 'el inglés no tocado sobrevive');
    }

    public function test_edit_can_change_zone(): void
    {
        $zone2 = Zone::create(['slug' => 'kids', 'name' => ['es' => 'Zona Kids'], 'position' => 2]);
        $attraction = Attraction::create(['zone_id' => $this->zone->id, 'name' => ['es' => 'A'], 'position' => 1]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->fillForm(['zone_id' => $zone2->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($zone2->id, $attraction->refresh()->zone_id);
    }

    public function test_admin_can_reorder_attractions(): void
    {
        $first = Attraction::create(['zone_id' => $this->zone->id, 'name' => ['es' => 'Primera'], 'position' => 1]);
        $second = Attraction::create(['zone_id' => $this->zone->id, 'name' => ['es' => 'Segunda'], 'position' => 2]);

        Livewire::actingAs($this->admin())
            ->test(ListAttractions::class)
            ->call('reorderTable', [$second->id, $first->id]);

        $this->assertLessThan($first->fresh()->position, $second->fresh()->position);
    }

    public function test_delete_and_audits(): void
    {
        $attraction = Attraction::create(['zone_id' => $this->zone->id, 'name' => ['es' => 'A']]);

        Livewire::actingAs($this->admin())
            ->test(EditAttraction::class, ['record' => $attraction->id])
            ->callAction('deleteAttraction');

        $this->assertNull($attraction->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.attraction_deleted', 'target_id' => $attraction->id]);
    }
}
