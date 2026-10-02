<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\RelationManagers\AddonsRelationManager;
use App\Filament\Resources\Catalog\RelationManagers\ChoiceGroupsRelationManager;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **LOS GRUPOS DE OPCIONES en el panel** (P3·2 de `fiesta-sistema-nuevo.md` §4.21, `[DECIDIDO owner]` `DECISIONES #914`).
 *
 * El grupo se configura UNA vez en «Grupos de opciones» (su pregunta, si hay que elegir, su orden) y sus opciones se ELIGEN
 * en «Complementos». Lo que estos casos garantizan: que el grupo se crea, se edita sin poder cambiar su clave y no se borra
 * con opciones; y que en la lista una opción de grupo puede ser incluida y por invitado, mientras una suelta no (control).
 */
class ChoiceGroupsPanelTest extends TestCase
{
    use RefreshDatabase;

    private TicketType $pack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $zone = Zone::create(['slug' => 'jump', 'name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $this->pack = TicketType::create([
            'name' => ['es' => 'Cumpleaños Jump'], 'type' => TicketType::TYPE_PACK, 'zone_id' => $zone->id,
            'duration_min' => 120, 'min_qty' => 2, 'max_qty' => 20, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
            'guest_fields' => [['key' => 'name', 'type' => 'text', 'required' => true, 'label' => ['es' => 'Nombre']]],
        ]);
    }

    public function test_a_group_is_created_from_the_product_with_its_question_and_rule(): void
    {
        $this->groupsManager()->callTableAction('create', data: [
            'key' => 'merienda', 'title' => ['es' => '¿Qué merienda?', 'en' => 'Which snack?'], 'is_required' => true, 'position' => 1,
        ])->assertHasNoTableActionErrors();

        $group = AddonChoiceGroup::query()->where('product_id', $this->pack->id)->sole();
        $this->assertSame('merienda', $group->key);
        $this->assertSame('¿Qué merienda?', $group->tr('title'));
        $this->assertTrue($group->is_required);
    }

    public function test_the_key_is_unique_per_product_and_cannot_change_once_created(): void
    {
        $group = $this->group('merienda');

        $this->groupsManager()->callTableAction('create', data: ['key' => 'merienda', 'title' => ['es' => 'Otra']])
            ->assertHasTableActionErrors(['key']);

        $this->groupsManager()->callTableAction('edit', $group, data: ['key' => 'otra', 'title' => ['es' => '¿Qué merienda?'], 'is_required' => true])
            ->assertHasNoTableActionErrors();
        $this->assertSame('merienda', $group->fresh()?->key, 'la clave es la unión con sus opciones: no se mueve');
        $this->assertTrue($group->fresh()?->is_required, 'lo demás sí se edita');
    }

    public function test_a_group_with_options_is_not_deleted_and_says_why(): void
    {
        $group = $this->group('merienda');
        $snack = $this->addon('Sándwich');
        $this->pack->configurableAddons()->attach($snack->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true,
            'stage' => ProductAddon::STAGE_POSTFORM, 'choice_group' => 'merienda',
        ]);

        $this->groupsManager()->callTableAction('delete', $group)->assertNotified();
        $this->assertNotNull($group->fresh(), 'con opciones, se queda');

        // CONTROL: sin opciones, se borra.
        ProductAddon::query()->where('addon_id', $snack->id)->delete();
        $this->groupsManager()->callTableAction('delete', $group);
        $this->assertNull($group->fresh());
    }

    public function test_in_the_list_an_option_of_a_group_may_be_included_and_one_per_guest(): void
    {
        $this->group('merienda');
        $snack = $this->addon('Sándwich');

        $this->addonsManager()->callTableAction('attach', data: [
            'recordId' => $snack->id, 'position' => 1, 'stage' => ProductAddon::STAGE_POSTFORM,
            'choice_group' => 'merienda', 'is_included' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST,
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_addons', [
            'product_id' => $this->pack->id, 'addon_id' => $snack->id, 'stage' => ProductAddon::STAGE_POSTFORM,
            'choice_group' => 'merienda', 'is_included' => true, 'quantity_mode' => ProductAddon::MODE_PER_GUEST,
        ]);
    }

    public function test_a_loose_option_in_the_list_cannot_be_included(): void
    {
        $snack = $this->addon('Sándwich');

        $this->addonsManager()->callTableAction('attach', data: [
            'recordId' => $snack->id, 'position' => 1, 'stage' => ProductAddon::STAGE_POSTFORM,
            'is_included' => true, 'quantity_mode' => ProductAddon::MODE_FIXED, 'max_qty' => 1,
        ])->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_addons', ['addon_id' => $snack->id, 'is_included' => false, 'choice_group' => null]);
    }

    public function test_an_old_key_of_the_booking_stage_survives_configuring_another_field(): void
    {
        // El Menú de producción: una clave tecleada antes de `#914`, sin fila en la tabla.
        $menu = $this->addon('Menú 1');
        $this->pack->configurableAddons()->attach($menu->id, [
            'position' => 1, 'quantity_mode' => ProductAddon::MODE_PER_GUEST, 'is_included' => true, 'choice_group' => 'menu',
        ]);

        $component = $this->addonsManager();
        $component->mountTableAction('configure', $menu)->setTableActionData(['position' => 5])->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_addons', ['addon_id' => $menu->id, 'choice_group' => 'menu', 'position' => 5]);
    }

    // ── Fixture ─────────────────────────────────────────────────────────────────────────────────

    private function group(string $key): AddonChoiceGroup
    {
        return AddonChoiceGroup::create(['product_id' => $this->pack->id, 'key' => $key, 'title' => ['es' => 'Merienda']]);
    }

    private function addon(string $name): TicketType
    {
        return TicketType::create([
            'name' => ['es' => $name], 'type' => TicketType::TYPE_ADDON,
            'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 20,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    private function groupsManager(): Testable
    {
        return Livewire::actingAs($this->admin())
            ->test(ChoiceGroupsRelationManager::class, ['ownerRecord' => $this->pack, 'pageClass' => EditCatalog::class]);
    }

    private function addonsManager(): Testable
    {
        return Livewire::actingAs($this->admin())
            ->test(AddonsRelationManager::class, ['ownerRecord' => $this->pack, 'pageClass' => EditCatalog::class]);
    }
}
