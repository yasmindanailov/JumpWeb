<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.0 — Matriz de permisos finos del panel.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §2.4 y `docs/DECISIONES.md` #118.
 */
class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $expected = array_keys(PermissionSeeder::ALL_PERMISSIONS);
        foreach ($expected as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }

        $this->assertSame(count($expected), Permission::count(),
            'No debe haber permisos extra ni faltantes respecto a la matriz declarada.');
    }

    public function test_seeder_assigns_default_permissions_to_staff(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $staff = Role::where('name', 'staff')->firstOrFail();
        $assigned = $staff->permissions()->pluck('name')->sort()->values()->all();
        $expected = collect(PermissionSeeder::STAFF_DEFAULT_PERMISSIONS)->sort()->values()->all();

        $this->assertSame($expected, $assigned);
    }

    public function test_admin_does_not_need_explicit_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $admin = Role::where('name', 'admin')->firstOrFail();
        $this->assertSame(0, $admin->permissions()->count(),
            'El admin no debe tener permisos asignados: pasa por encima vía Gate::before.');

        $user = User::factory()->create();
        $user->roles()->sync([$admin->id]);

        // Cualquier permiso, incluso uno inexistente, debe ser true para admin.
        $this->assertTrue($user->hasPermission('catalog.manage'));
        $this->assertTrue($user->hasPermission('any.invented.permission'));
    }

    public function test_staff_has_operativa_permissions_but_not_management(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->value('id')]);

        // SÍ
        $this->assertTrue($user->hasPermission('orders.cancel'));
        $this->assertTrue($user->hasPermission('orders.refund'));
        $this->assertTrue($user->hasPermission('registrations.validate'));

        // NO
        $this->assertFalse($user->hasPermission('catalog.manage'));
        $this->assertFalse($user->hasPermission('settings.manage'));
        $this->assertFalse($user->hasPermission('users.manage'));
        $this->assertFalse($user->hasPermission('users.anonymize'));
        $this->assertFalse($user->hasPermission('access.manage'));
    }

    public function test_seeder_includes_per_item_management_permissions_for_staff(): void
    {
        // Sub-fase 7.2e cimientos — 3 permisos nuevos asignados a staff para que
        // el rol pueda editar/cancelar/reembolsar items sueltos desde el panel.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->value('id')]);

        $this->assertTrue($user->hasPermission('orders.edit_item'));
        $this->assertTrue($user->hasPermission('orders.cancel_item'));
        $this->assertTrue($user->hasPermission('orders.refund_item'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertSame(
            count(PermissionSeeder::ALL_PERMISSIONS),
            Permission::count(),
            'Re-sembrar no debe duplicar permisos.'
        );

        $staff = Role::where('name', 'staff')->firstOrFail();
        $this->assertSame(
            count(PermissionSeeder::STAFF_DEFAULT_PERMISSIONS),
            $staff->permissions()->count(),
            'Re-sembrar no debe duplicar asignaciones rol↔permiso.'
        );
    }
}
