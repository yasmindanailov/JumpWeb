<?php

namespace Tests\Feature\Admin\Users;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.5 — Acceso a `UserResource` (decisión #180).
 *
 * Recurso SOLO admin (`users.manage`): el staff queda fuera (a diferencia de
 * `OrderResource`). Solo lectura: sin ruta de creación.
 */
class UserResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access_users_list(): void
    {
        $this->actingAs($this->userWithRole('customer'))->get('/admin/users')->assertForbidden();
    }

    public function test_staff_cannot_access_users_list(): void
    {
        // El staff NO tiene `users.manage` → 403 (este recurso es solo admin).
        $this->actingAs($this->userWithRole('staff'))->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_access_users_list(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/admin/users')->assertOk();
    }

    public function test_admin_can_view_user_detail(): void
    {
        $customer = $this->userWithRole('customer');

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/users/'.$customer->id)
            ->assertOk();
    }

    public function test_create_route_is_disabled(): void
    {
        // UserResource::canCreate() = false → la página /create no existe.
        $this->actingAs($this->userWithRole('admin'))->get('/admin/users/create')->assertNotFound();
    }
}
