<?php

namespace Tests\Feature\Admin\Catalog;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.6 — Acceso a `CatalogResource` (iter. 1).
 *
 * El catálogo es **solo admin** (`catalog.manage`, que no está en los permisos por
 * defecto del staff). Crear producto nuevo está habilitado (7.6 iter. 2, #192) y
 * gateado por el mismo permiso `catalog.manage`.
 */
class CatalogResourceAccessTest extends TestCase
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

    private function product(): TicketType
    {
        $zone = Zone::create([
            'slug' => 'jump',
            'name' => ['es' => 'Jump'],
            'accent' => 'jump',
            'color' => '#FF5B22',
            'position' => 1,
            'is_active' => true,
        ]);

        return TicketType::create([
            'name' => ['es' => 'Jump · 1 hora'],
            'type' => TicketType::TYPE_ENTRY,
            'zone_id' => $zone->id,
            'duration_min' => 60,
            'seats_per_unit' => 1,
            'tax_rate' => 21,
            'is_sellable' => true,
            'is_active' => true,
            'position' => 1,
        ]);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/catalog')->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access_catalog(): void
    {
        $this->actingAs($this->userWithRole('customer'))->get('/admin/catalog')->assertForbidden();
    }

    public function test_staff_cannot_access_catalog(): void
    {
        // El staff NO tiene `catalog.manage` (es solo admin).
        $this->actingAs($this->userWithRole('staff'))->get('/admin/catalog')->assertForbidden();
    }

    public function test_admin_can_access_catalog_list(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get('/admin/catalog')->assertOk();
    }

    public function test_admin_can_access_edit_page(): void
    {
        $product = $this->product();

        $this->actingAs($this->userWithRole('admin'))
            ->get('/admin/catalog/'.$product->id.'/edit')
            ->assertOk();
    }

    public function test_admin_can_access_create_page(): void
    {
        // 7.6 iter. 2 (#192): el alta de producto nuevo está habilitada para admin.
        $this->actingAs($this->userWithRole('admin'))->get('/admin/catalog/create')->assertOk();
    }

    public function test_staff_cannot_access_create_page(): void
    {
        // El staff NO tiene `catalog.manage` → la página de alta también le está vedada.
        $this->actingAs($this->userWithRole('staff'))->get('/admin/catalog/create')->assertForbidden();
    }

    public function test_customer_cannot_access_create_page(): void
    {
        $this->actingAs($this->userWithRole('customer'))->get('/admin/catalog/create')->assertForbidden();
    }
}
