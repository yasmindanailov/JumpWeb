<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\RelationManagers\AddonsRelationManager;
use App\Models\TicketType;
use App\Models\Zone;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DetachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fase 7.6 iter. 2 — Gestión del pivote `product_addons` desde la ficha del producto
 * (qué complementos aplican a una entrada/pack): visibilidad, attach, detach y que solo
 * se puedan enganchar complementos (type=addon).
 */
class CatalogAddonsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function makeEntry(): TicketType
    {
        return TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
    }

    private function makeAddon(string $name = 'Calcetines', int $position = 20): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => $position,
        ]);
    }

    // ─── Visibilidad ─────────────────────────────────────────────────────────

    public function test_visible_for_entry_with_permission_hidden_for_addon_and_without_permission(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $entry = $this->makeEntry();
        $addon = $this->makeAddon();

        $this->assertTrue(AddonsRelationManager::canViewForRecord($entry, EditCatalog::class));
        // No sobre un complemento (un addon no tiene sub-complementos).
        $this->assertFalse(AddonsRelationManager::canViewForRecord($addon, EditCatalog::class));

        // Sin permiso `catalog.manage` (staff) → oculto.
        $this->actingAs($this->userWithRole('staff'));
        $this->assertFalse(AddonsRelationManager::canViewForRecord($entry, EditCatalog::class));
    }

    public function test_lists_attached_addons(): void
    {
        $entry = $this->makeEntry();
        $a1 = $this->makeAddon('Calcetines', 20);
        $a2 = $this->makeAddon('Taquilla', 21);
        $other = $this->makeAddon('Tarta', 22);  // no enganchado
        DB::table('product_addons')->insert([
            ['product_id' => $entry->id, 'addon_id' => $a1->id, 'position' => 0],
            ['product_id' => $entry->id, 'addon_id' => $a2->id, 'position' => 1],
        ]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$a1, $a2])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ─── Attach / Detach ─────────────────────────────────────────────────────

    public function test_admin_can_attach_an_addon(): void
    {
        $entry = $this->makeEntry();
        $addon = $this->makeAddon();

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('attach', data: ['recordId' => $addon->id, 'position' => 3]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $addon->id, 'position' => 3,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog.addon_attached', 'target_id' => $entry->id,
        ]);
    }

    public function test_admin_can_detach_an_addon(): void
    {
        $entry = $this->makeEntry();
        $addon = $this->makeAddon();
        DB::table('product_addons')->insert(['product_id' => $entry->id, 'addon_id' => $addon->id, 'position' => 0]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction(DetachAction::class, $addon);

        $this->assertDatabaseMissing('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $addon->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog.addon_detached', 'target_id' => $entry->id,
        ]);
    }

    public function test_staff_without_permission_cannot_attach(): void
    {
        $entry = $this->makeEntry();
        $addon = $this->makeAddon();
        $staff = $this->userWithRole('staff');  // sin `catalog.manage`

        // Defensa en profundidad: aunque alguien dispare la acción directamente sobre el
        // componente Livewire del RM (saltándose el render-gating), no debe poder enganchar.
        try {
            Livewire::actingAs($staff)
                ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
                ->callTableAction('attach', data: ['recordId' => $addon->id, 'position' => 0]);
        } catch (\Throwable $e) {
            // Acción no disponible / prohibida: comportamiento esperado.
        }

        $this->assertDatabaseMissing('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $addon->id,
        ]);
    }

    public function test_attaching_the_same_addon_twice_is_rejected_cleanly(): void
    {
        $entry = $this->makeEntry();
        $addon = $this->makeAddon();
        DB::table('product_addons')->insert(['product_id' => $entry->id, 'addon_id' => $addon->id, 'position' => 0]);

        // Un segundo attach del mismo addon NO debe lanzar una violación de unicidad (500):
        // la guarda lo corta con un aviso. La fila de pivote sigue siendo una sola.
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('attach', data: ['recordId' => $addon->id, 'position' => 5]);

        $this->assertSame(1, DB::table('product_addons')
            ->where('product_id', $entry->id)->where('addon_id', $addon->id)->count());
    }

    public function test_a_non_addon_cannot_be_attached(): void
    {
        $entry = $this->makeEntry();
        $otherEntry = TicketType::create([
            'name' => ['es' => 'Kids · 1 hora'], 'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $this->zone->id, 'duration_min' => 60, 'seats_per_unit' => 1,
            'tax_rate' => 21, 'is_sellable' => true, 'is_active' => true, 'position' => 2,
        ]);

        // Intentar enganchar otra ENTRADA (no addon): el filtro type=addon lo impide.
        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('attach', data: ['recordId' => $otherEntry->id, 'position' => 0]);

        $this->assertDatabaseMissing('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $otherEntry->id,
        ]);
    }

    // ─── Config del enganche (incluido / obligatorio / por-invitado / grupo) ─────

    public function test_attach_persists_the_inclusion_config(): void
    {
        $entry = $this->makeEntry();
        $addon = $this->makeAddon('Menú 1');

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('attach', data: [
                'recordId' => $addon->id, 'position' => 0,
                'is_included' => true, 'is_mandatory' => true,
                'quantity_mode' => 'per_guest', 'choice_group' => 'menu',
            ]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $addon->id,
            'is_included' => true, 'is_mandatory' => true,
            'quantity_mode' => 'per_guest', 'choice_group' => 'menu',
        ]);
    }

    public function test_configure_action_updates_the_pivot_and_audits(): void
    {
        $entry = $this->makeEntry();
        $addon = $this->makeAddon('Tarta');
        DB::table('product_addons')->insert(['product_id' => $entry->id, 'addon_id' => $addon->id, 'position' => 0]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('configure', $addon, data: [
                'is_included' => true, 'included_quantity' => 1, 'is_mandatory' => true,
                'quantity_mode' => 'fixed', 'allow_extra' => true, 'choice_group' => '', 'position' => 0,
            ]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $addon->id,
            'is_included' => true, 'included_quantity' => 1, 'is_mandatory' => true, 'choice_group' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'catalog.addon_configured', 'target_id' => $entry->id,
        ]);
    }

    public function test_configure_persists_the_requires_dependency(): void
    {
        // Dependencia «requiere» (data-driven): «Segunda tarta» requiere «Tarta». Se persiste solo
        // en cantidad fija; el panel ofrece como opciones los OTROS complementos del producto.
        $entry = $this->makeEntry();
        $tarta = $this->makeAddon('Tarta', 22);
        $segunda = $this->makeAddon('Segunda tarta', 23);
        DB::table('product_addons')->insert([
            ['product_id' => $entry->id, 'addon_id' => $tarta->id, 'position' => 0],
            ['product_id' => $entry->id, 'addon_id' => $segunda->id, 'position' => 1],
        ]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('configure', $segunda, data: [
                'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
                'quantity_mode' => 'fixed', 'allow_extra' => false, 'choice_group' => '',
                'requires_addon_id' => $tarta->id, 'position' => 1,
            ]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $segunda->id, 'requires_addon_id' => $tarta->id,
        ]);
    }

    public function test_requires_dependency_is_cleared_when_mode_is_not_fixed(): void
    {
        // La dependencia solo aplica a cantidad fija: al pasar a por-invitado se limpia (null).
        $entry = $this->makeEntry();
        $tarta = $this->makeAddon('Tarta', 22);
        $segunda = $this->makeAddon('Segunda tarta', 23);
        DB::table('product_addons')->insert([
            ['product_id' => $entry->id, 'addon_id' => $tarta->id, 'position' => 0, 'requires_addon_id' => null],
            ['product_id' => $entry->id, 'addon_id' => $segunda->id, 'position' => 1, 'requires_addon_id' => $tarta->id],
        ]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('configure', $segunda, data: [
                'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
                'quantity_mode' => 'per_guest', 'allow_extra' => false, 'choice_group' => '',
                'requires_addon_id' => $tarta->id, 'position' => 1,
            ]);

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $segunda->id, 'requires_addon_id' => null,
        ]);
    }

    public function test_a_group_member_cannot_persist_a_requires_dependency(): void
    {
        // #4/#6: un miembro de un grupo de elección no puede «requerir» otro complemento (su
        // exclusividad ya es su mecanismo; requerir uno dejaría dependientes huérfanos al cambiar).
        $entry = $this->makeEntry();
        $tarta = $this->makeAddon('Tarta', 22);
        $menu = $this->makeAddon('Menú', 23);
        DB::table('product_addons')->insert([
            ['product_id' => $entry->id, 'addon_id' => $tarta->id, 'position' => 0, 'requires_addon_id' => null],
            ['product_id' => $entry->id, 'addon_id' => $menu->id, 'position' => 1, 'requires_addon_id' => null],
        ]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction('configure', $menu, data: [
                'is_included' => false, 'included_quantity' => 1, 'is_mandatory' => false,
                'quantity_mode' => 'fixed', 'allow_extra' => false, 'choice_group' => 'menu',
                'requires_addon_id' => $tarta->id, 'position' => 1,
            ]);

        // choice_group establecido → requires se limpia (null), aunque se envíe.
        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $menu->id, 'choice_group' => 'menu', 'requires_addon_id' => null,
        ]);
    }

    public function test_detaching_a_required_addon_clears_dependents(): void
    {
        // #3: al desenganchar un complemento, las dependencias «requiere» que lo señalaban se limpian
        // (si no, apuntarían a un complemento ya no ofrecible → dependiente en dead-end).
        $entry = $this->makeEntry();
        $tarta = $this->makeAddon('Tarta', 22);
        $segunda = $this->makeAddon('Segunda tarta', 23);
        DB::table('product_addons')->insert([
            ['product_id' => $entry->id, 'addon_id' => $tarta->id, 'position' => 0, 'requires_addon_id' => null],
            ['product_id' => $entry->id, 'addon_id' => $segunda->id, 'position' => 1, 'requires_addon_id' => $tarta->id],
        ]);

        Livewire::actingAs($this->userWithRole('admin'))
            ->test(AddonsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditCatalog::class])
            ->callTableAction(DetachAction::class, $tarta);

        $this->assertDatabaseMissing('product_addons', ['product_id' => $entry->id, 'addon_id' => $tarta->id]);
        $this->assertDatabaseHas('product_addons', [
            'product_id' => $entry->id, 'addon_id' => $segunda->id, 'requires_addon_id' => null,
        ]);
    }
}
