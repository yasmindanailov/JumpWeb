<?php

namespace Tests\Feature\Admin\Orders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.1b — Acceso a `OrderResource` Filament (decisión #127).
 *
 * Cubre que el listado y la vista de detalle solo son accesibles para usuarios
 * con permiso `orders.view`, y que las acciones de creación/edición/borrado están
 * desactivadas en 7.1b (no procede crear o editar orders aquí; eso es 7.3/7.2).
 */
class OrderResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/orders')->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access_orders_list(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
    }

    public function test_staff_with_permission_can_access_orders_list(): void
    {
        $this->actingAs($this->staff())->get('/admin/orders')->assertOk();
    }

    public function test_staff_without_specific_permission_gets_403(): void
    {
        $staff = $this->staff();
        $perm = Permission::where('name', 'orders.view')->value('id');
        $staff->roles->first()->permissions()->detach($perm);

        $this->actingAs($staff)->get('/admin/orders')->assertForbidden();
    }

    public function test_create_route_is_disabled(): void
    {
        // OrderResource::canCreate() returns false → la página /create no existe.
        $this->actingAs($this->staff())->get('/admin/orders/create')->assertNotFound();
    }
}
