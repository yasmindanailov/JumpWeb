<?php

namespace Tests\Feature\Maintenance;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\MaintenanceSettings;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mantenimiento POR PÁGINA (#218, item 1) — helper + middleware `EnsureSiteAvailable`.
 *
 * Una página concreta puede ponerse en mantenimiento (503) sin afectar al resto: la vista usa el
 * layout COMPLETO (nav/pie) para seguir navegando. Cubre el mapeo ruta→clave, el aislamiento entre
 * páginas, el bypass del personal y la PRECEDENCIA del mantenimiento de sitio sobre el de página.
 */
class PageMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function setPage(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.page.'.$key], ['value' => $value, 'group' => 'maintenance']);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────────

    public function test_pages_available_by_default(): void
    {
        $this->assertFalse(MaintenanceSettings::pageInMaintenance('precios'));
    }

    public function test_unknown_key_is_never_in_maintenance(): void
    {
        $this->setPage('precios', '1');
        $this->assertFalse(MaintenanceSettings::pageInMaintenance('inventada'));
        $this->assertFalse(MaintenanceSettings::pageInMaintenance('admin'));
    }

    public function test_corrupt_value_is_fail_safe(): void
    {
        $this->setPage('precios', 'maybe');
        $this->assertFalse(MaintenanceSettings::pageInMaintenance('precios'));
    }

    // ─── Middleware ────────────────────────────────────────────────────────────────

    public function test_page_served_normally_when_not_in_maintenance(): void
    {
        $this->get('/precios')->assertOk();
    }

    public function test_page_returns_503_with_full_layout_when_in_maintenance(): void
    {
        $this->setPage('precios', '1');

        $response = $this->get('/precios');

        $response->assertStatus(503);
        $response->assertHeader('Retry-After', '3600');
        $response->assertSee(__('site.page_maintenance.title'));
        // Layout COMPLETO: el nav permite navegar a otras secciones que sí funcionan.
        $response->assertSee(route('cumpleanos'), false);
        // Cabeceras de seguridad presentes (SecurityHeaders envuelve la respuesta 503).
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        // SEO: el 503 NO debe indexarse (el layout completo, por defecto «index», recibe noindex).
        $response->assertSee('noindex, nofollow', false);
    }

    public function test_only_the_targeted_page_is_affected(): void
    {
        $this->setPage('precios', '1');

        $this->get('/precios')->assertStatus(503);
        $this->get('/')->assertOk();          // la home sigue arriba
        $this->get('/cumpleanos')->assertOk(); // otra página sigue arriba
    }

    public function test_entradas_deep_link_shares_home_state(): void
    {
        // `/entradas` renderiza la home → debe gatearse con la clave `home`.
        $this->setPage('home', '1');

        $this->get('/entradas')->assertStatus(503);
    }

    public function test_staff_bypasses_page_maintenance(): void
    {
        $this->setPage('precios', '1');

        $this->actingAs($this->userWithRole('admin'))->get('/precios')->assertOk();
        $this->actingAs($this->userWithRole('staff'))->get('/precios')->assertOk();
    }

    public function test_customer_does_not_bypass_page_maintenance(): void
    {
        $this->setPage('precios', '1');

        $this->actingAs($this->userWithRole('customer'))->get('/precios')->assertStatus(503);
    }

    public function test_site_maintenance_takes_precedence_over_page(): void
    {
        // Sitio entero + página: gana el sitio (503 standalone «Volvemos enseguida»), no el de página.
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);
        $this->setPage('precios', '1');

        $this->get('/precios')
            ->assertStatus(503)
            ->assertSee(__('site.maintenance.title'))
            ->assertDontSee(__('site.page_maintenance.title'));
    }
}
