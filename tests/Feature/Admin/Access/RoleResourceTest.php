<?php

namespace Tests\Feature\Admin\Access;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.11 — `RoleResource`: gating por `access.manage` (en la práctica solo admin), sin
 * crear/borrar (roles base inmutables), navegación oculta al staff.
 */
class RoleResourceTest extends TestCase
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

    public function test_admin_can_view_resource(): void
    {
        $this->actingAs($this->admin());

        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(RoleResource::shouldRegisterNavigation());
        $this->get('/admin/roles')->assertSuccessful();
    }

    public function test_staff_is_forbidden(): void
    {
        $staff = $this->staff();

        $this->assertFalse($staff->hasPermission('access.manage'), 'el staff no tiene access.manage');

        $this->actingAs($staff)
            ->get('/admin/roles')
            ->assertForbidden();
    }

    public function test_resource_has_no_create_or_delete(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(RoleResource::canCreate());
        $this->assertFalse(RoleResource::canDelete(Role::where('name', 'staff')->first()));
        $this->assertArrayNotHasKey('create', RoleResource::getPages());
    }
}
