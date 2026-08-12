<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plan B · L1 — Tras RETIRAR el cluster «Configuración», los recursos/páginas de administración
 * cuelgan directamente de grupos del sidebar (Programación / Catálogo y precios / Contenido web /
 * Sistema) con URL `/admin/{slug}` (antes `/admin/configuracion/{slug}`). El gating fino por permiso
 * de cada recurso se conserva (lo cubren además sus tests propios); aquí verificamos que el admin
 * alcanza TODAS las pantallas de administración en sus nuevas URLs, y que sin permisos no.
 */
class ConfiguracionClusterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function withRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    /** @return list<string> Slugs de los recursos/páginas de administración (sin el viejo prefijo de cluster). */
    private function adminSlugs(): array
    {
        return ['catalog', 'rate-types', 'seasons', 'special-dates', 'slots', 'slot-templates', 'horario', 'settings', 'users', 'zones'];
    }

    public function test_admin_reaches_all_admin_pages_at_their_new_urls(): void
    {
        $admin = $this->withRole('admin');

        foreach ($this->adminSlugs() as $slug) {
            $this->actingAs($admin)->get("/admin/{$slug}")->assertOk();
        }
    }

    public function test_staff_without_management_permissions_is_forbidden(): void
    {
        // El staff no tiene los permisos de gestión (catalog/settings/users) → 403 en cada recurso.
        $staff = $this->withRole('staff');

        foreach (['catalog', 'settings', 'users'] as $slug) {
            $this->actingAs($staff)->get("/admin/{$slug}")->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/settings')->assertRedirect('/admin/login');
    }
}
