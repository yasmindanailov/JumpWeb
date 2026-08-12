<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 7.0 — Smoke tests del panel admin de Filament.
 *
 * Validan que el panel arranca y que el control de acceso (middleware +
 * canAccessPanel) responde correctamente. Resources/Pages reales (operativa
 * puerta, pedidos, etc.) se prueban en sus sub-fases.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §3.7.0.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_admin_login_page_renders(): void
    {
        // Sin sesión, la pantalla de login del panel se sirve directamente (no es
        // un endpoint protegido) — los empleados necesitan poder entrar.
        $this->get('/admin/login')->assertOk();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customer_cannot_access_admin(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        // canAccessPanel devuelve false → Filament responde 403 (no redirige
        // a un panel de cliente porque no existe; el customer usa la web pública).
        $this->actingAs($customer)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_with_no_role_cannot_access_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_staff_can_access_dashboard(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);

        $this->actingAs($staff)
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_does_not_register_a_secondary_login_route(): void
    {
        // Defensa anti-confusión: la web pública NO debe tener login.
        // En cambio el panel SÍ tiene /admin/login. Si en el futuro alguien
        // monta Filament sin path, este test pillará la regresión.
        $this->assertTrue(\Route::has('filament.admin.auth.login'));
        $this->assertSame('/admin/login', route('filament.admin.auth.login', absolute: false));
    }
}
