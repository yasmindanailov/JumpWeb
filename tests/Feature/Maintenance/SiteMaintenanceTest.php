<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\MaintenanceSettings;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Mantenimiento de SITIO ENTERO (#218, item 2) — middleware `EnsureSiteAvailable` + helper
 * `MaintenanceSettings` + página 503 + banner de bypass.
 *
 * Cubre: fail-safe (un valor corrupto NO cierra la web), 503 + Retry-After + cabeceras de
 * seguridad cuando está activo, exclusión de `/admin` y de las callbacks de Redsys, y el bypass
 * del personal del panel (admin/staff ven la web real; el cliente NO).
 */
class SiteMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(LandingContentSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function enableMaintenance(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);
    }

    // ─── Helper: dirección fail-safe ────────────────────────────────────────────

    public function test_site_is_not_in_maintenance_by_default(): void
    {
        $this->assertFalse(MaintenanceSettings::siteInMaintenance());
    }

    public function test_corrupt_value_is_fail_safe_and_keeps_site_up(): void
    {
        // Cualquier valor que NO sea el literal '1' deja la web arriba (un setting roto no puede
        // cerrar la web entera por accidente). Verificado con varios valores no reconocidos.
        foreach (['yes', '2', 'true', 'on', ''] as $bad) {
            Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => $bad, 'group' => 'maintenance']);
            $this->assertFalse(MaintenanceSettings::siteInMaintenance(), "El valor «{$bad}» NO debe activar el mantenimiento.");
        }
    }

    public function test_only_literal_one_enables_maintenance(): void
    {
        $this->enableMaintenance();
        $this->assertTrue(MaintenanceSettings::siteInMaintenance());
    }

    public function test_site_message_falls_back_to_i18n_when_no_override(): void
    {
        $this->assertSame(__('site.maintenance.body', [], 'es'), MaintenanceSettings::siteMessage('es'));
        $this->assertSame(__('site.maintenance.body', [], 'fr'), MaintenanceSettings::siteMessage('fr'));
    }

    public function test_site_message_uses_per_locale_override(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.message.es'], ['value' => 'Cerrado por reforma hasta el 20 de junio.', 'group' => 'maintenance']);

        $this->assertSame('Cerrado por reforma hasta el 20 de junio.', MaintenanceSettings::siteMessage('es'));
        // Un idioma sin override sigue cayendo al i18n por defecto (independiente del override de otro).
        $this->assertSame(__('site.maintenance.body', [], 'en'), MaintenanceSettings::siteMessage('en'));
    }

    // ─── Middleware: la web pública ─────────────────────────────────────────────

    public function test_home_is_served_normally_when_maintenance_off(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_home_returns_503_with_retry_after_when_maintenance_on(): void
    {
        $this->enableMaintenance();

        $this->withSession(['locale' => 'es']);
        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertHeader('Retry-After', '3600');
        $response->assertSee(__('site.maintenance.title'));        // «Volvemos enseguida»
        $response->assertSee(__('site.maintenance.body', [], 'es'), false);
    }

    public function test_maintenance_page_offers_contact_phone(): void
    {
        $this->enableMaintenance();

        // El teléfono sembrado es «968 22 22 22»; el `tel:` va sin espacios.
        $this->get('/')
            ->assertStatus(503)
            ->assertSee('tel:968222222', false);
    }

    public function test_503_carries_security_headers(): void
    {
        // El 503 va envuelto por `SecurityHeaders` (orden de middleware) → recibe sus cabeceras.
        $this->enableMaintenance();

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_other_public_pages_also_gated(): void
    {
        $this->enableMaintenance();

        $this->get('/precios')->assertStatus(503);
        $this->get('/contacto')->assertStatus(503);
    }

    // ─── Middleware: exclusiones ────────────────────────────────────────────────

    public function test_admin_login_is_never_blocked(): void
    {
        // El panel DEBE seguir vivo para poder desactivar el mantenimiento.
        $this->enableMaintenance();

        $this->get('/admin/login')->assertSuccessful();
    }

    public function test_panel_login_endpoint_is_never_blocked_during_maintenance(): void
    {
        // Regresión (auditoría Fase 1, Sistema 5): el formulario de login de Filament POSTea al
        // endpoint Livewire COMPARTIDO (`*livewire.update`), NO a `/admin/*`. El GET a /admin/login
        // renderiza (path excluido) y da falsa confianza, pero un admin/staff DESLOGUEADO necesita
        // ENVIAR el formulario; si ese endpoint devuelve 503, no puede autenticarse para desactivar
        // el propio mantenimiento (self-lockout). El `test_admin_login_is_never_blocked` (solo GET)
        // NO cubría esto. Aquí probamos el POST real al endpoint de actualización de Livewire.
        $this->enableMaintenance();

        // Como INVITADO (estado del login). CSRF se auto-omite en tests; el POST alcanza el
        // middleware de mantenimiento. Antes del fix → 503; después → Livewire procesa la petición.
        $response = $this->withHeaders(['X-Livewire' => 'true'])
            ->post(Livewire::getUpdateUri());

        $this->assertNotSame(
            503,
            $response->status(),
            'El endpoint Livewire de envío de formularios (incl. el login del panel) no debe bloquearse durante el mantenimiento.',
        );
    }

    public function test_redsys_callback_is_never_blocked(): void
    {
        // Un pago YA iniciado debe poder finalizar aunque la web esté en mantenimiento.
        $this->enableMaintenance();

        $this->assertNotSame(503, $this->get('/pago/redsys/retorno-ok')->status());
    }

    public function test_health_check_is_never_blocked(): void
    {
        $this->enableMaintenance();

        $this->get('/up')->assertOk();
    }

    // ─── Bypass del personal del panel ──────────────────────────────────────────

    public function test_admin_bypasses_maintenance_and_sees_banner(): void
    {
        $this->enableMaintenance();
        $this->withSession(['locale' => 'es']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/')
            ->assertOk()
            ->assertSee(__('site.maintenance.preview_banner'));
    }

    public function test_staff_bypasses_maintenance(): void
    {
        $this->enableMaintenance();

        $this->actingAs($this->userWithRole('staff'))->get('/')->assertOk();
    }

    public function test_customer_does_not_bypass_maintenance(): void
    {
        $this->enableMaintenance();

        $this->actingAs($this->userWithRole('customer'))->get('/')->assertStatus(503);
    }

    public function test_banner_is_absent_when_maintenance_off(): void
    {
        $this->withSession(['locale' => 'es']);

        $this->actingAs($this->userWithRole('admin'))
            ->get('/')
            ->assertOk()
            ->assertDontSee(__('site.maintenance.preview_banner'));
    }
}
