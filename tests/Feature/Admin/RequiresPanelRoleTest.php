<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Fase 7.0 — Middleware `panel_role` y gate `User::canAccessPanel()`.
 *
 * Cubre defense in depth: ambos checks (middleware + canAccessPanel) deben
 * cerrar a un customer y abrir a admin/staff. Ver `docs/PLAN-FASE-7-PANEL.md` §1.3.
 *
 * El rol `staff` se siembra en este test ad-hoc; la sub-fase del PermissionSeeder
 * lo hará oficial y los tests reales del panel lo asumirán sembrado por defecto.
 */
class RequiresPanelRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Role::firstOrCreate(['name' => 'staff'], ['label' => 'Empleado']);

        // Ruta de prueba protegida: el behavior real del middleware no depende
        // del panel concreto, solo de la combinación auth + rol.
        Route::middleware(['web', 'auth', 'panel_role'])
            ->get('/test-staff-area', fn () => response('ok'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/test-staff-area')->assertRedirect(route('login'));
    }

    public function test_customer_role_gets_403(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $this->actingAs($customer)
            ->get('/test-staff-area')
            ->assertForbidden();
    }

    public function test_user_with_no_role_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/test-staff-area')
            ->assertForbidden();
    }

    public function test_admin_passes(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->get('/test-staff-area')
            ->assertOk();
    }

    public function test_staff_passes(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);

        $this->actingAs($staff)
            ->get('/test-staff-area')
            ->assertOk();
    }

    public function test_can_access_panel_mirrors_middleware(): void
    {
        // El gate de Filament debe permitir/negar exactamente lo mismo que el
        // middleware — sin ese mirroring, defense in depth no se cumple.
        // Stub, no mock: no se configura ninguna expectativa sobre el panel (PHPUnit 12 lo avisaba
        // como el único «PHPUnit notice» de la suite; `DECISIONES #182` lo dejó por identificar).
        $panel = $this->createStub(Panel::class);

        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);
        $this->assertTrue($admin->canAccessPanel($panel));

        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $this->assertTrue($staff->canAccessPanel($panel));

        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $this->assertFalse($customer->canAccessPanel($panel));

        $noRole = User::factory()->create();
        $this->assertFalse($noRole->canAccessPanel($panel));
    }
}
