<?php

namespace Tests\Feature\Admin;

use App\Domain\Platform\Models\AuditLog;
use App\Http\Middleware\SetAdminLocale;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 7.0 — Locale del panel (decisión #123): solo `es` y `zh_CN`, aislado del
 * locale de la web pública (que sigue siendo `es/en/fr`, decisión #40).
 */
class PanelLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_users_table_has_panel_locale_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'panel_locale'));
    }

    public function test_supported_locales_are_es_and_zh_cn(): void
    {
        $this->assertSame(['es', 'zh_CN'], SetAdminLocale::SUPPORTED);
        $this->assertSame('es', SetAdminLocale::DEFAULT);
    }

    public function test_resolve_returns_default_for_user_without_preference(): void
    {
        $user = User::factory()->create(['panel_locale' => null]);
        $this->assertSame('es', SetAdminLocale::resolve($user));
    }

    public function test_resolve_returns_default_for_unauthenticated(): void
    {
        $this->assertSame('es', SetAdminLocale::resolve(null));
    }

    public function test_resolve_returns_user_preference_when_valid(): void
    {
        $user = User::factory()->create(['panel_locale' => 'zh_CN']);
        $this->assertSame('zh_CN', SetAdminLocale::resolve($user));
    }

    public function test_resolve_falls_back_for_invalid_preference(): void
    {
        // Defensa contra dato corrupto en BD: nunca devolver un locale inválido
        // que reviente `App::setLocale()` o cargue un lang file inexistente.
        $user = User::factory()->create(['panel_locale' => 'fr']);
        $this->assertSame('es', SetAdminLocale::resolve($user));

        $user2 = User::factory()->create(['panel_locale' => 'evil_locale']);
        $this->assertSame('es', SetAdminLocale::resolve($user2));
    }

    public function test_switch_endpoint_persists_preference_for_staff(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);

        $this->actingAs($staff)
            ->from('/admin')
            ->post(route('admin.lang.switch', 'zh_CN'))
            ->assertRedirect('/admin');

        $this->assertSame('zh_CN', $staff->fresh()->panel_locale);
    }

    public function test_switch_endpoint_writes_audit_log(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $this->actingAs($admin)
            ->post(route('admin.lang.switch', 'zh_CN'));

        $log = AuditLog::where('action', 'panel.locale_changed')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['previous' => null, 'new' => 'zh_CN'], $log->payload);
    }

    public function test_switch_endpoint_rejects_unsupported_locale(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);

        // Web public supported but NOT panel supported: debe rechazarse.
        $this->actingAs($staff)
            ->post(route('admin.lang.switch', 'fr'))
            ->assertStatus(422);

        // Locale completamente inventado.
        $this->actingAs($staff)
            ->post(route('admin.lang.switch', 'zz_XX'))
            ->assertStatus(422);

        $this->assertNull($staff->fresh()->panel_locale);
    }

    public function test_switch_endpoint_blocks_customer(): void
    {
        $customer = User::factory()->create();
        $customer->roles()->sync([Role::where('name', 'customer')->value('id')]);

        $this->actingAs($customer)
            ->post(route('admin.lang.switch', 'zh_CN'))
            ->assertForbidden();

        $this->assertNull($customer->fresh()->panel_locale);
    }

    public function test_switch_endpoint_requires_auth(): void
    {
        $this->post(route('admin.lang.switch', 'zh_CN'))->assertRedirect(route('login'));
    }

    public function test_panel_request_applies_user_locale(): void
    {
        $staff = User::factory()->create(['panel_locale' => 'zh_CN']);
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);

        // Aprovecho el efecto de App::setLocale durante el ciclo de la petición:
        // verifico el locale registrando un terminating callback que captura
        // el valor al final del handle del request.
        $captured = null;
        Event::listen(RequestHandled::class, function () use (&$captured) {
            $captured = App::getLocale();
        });

        $this->actingAs($staff)->get('/admin')->assertOk();
        $this->assertSame('zh_CN', $captured);
    }

    public function test_panel_locale_is_isolated_from_web_session_locale(): void
    {
        // El staff entra al panel con preferencia zh_CN; en otra pestaña navega
        // a la web pública en `fr`. La sesión guarda `locale=fr` (vía SetLocale
        // de la web); al volver al panel, debe seguir viendo zh_CN porque el
        // panel ignora `session('locale')` y lee `users.panel_locale`.
        $staff = User::factory()->create(['panel_locale' => 'zh_CN']);
        $staff->roles()->sync([Role::where('name', 'staff')->value('id')]);

        session(['locale' => 'fr']);

        $captured = null;
        Event::listen(RequestHandled::class, function () use (&$captured) {
            $captured = App::getLocale();
        });

        $this->actingAs($staff)->get('/admin')->assertOk();
        $this->assertSame('zh_CN', $captured,
            'El panel debe respetar `users.panel_locale`, NO `session.locale` de la web.');
    }

    public function test_full_flow_zh_to_es_and_back_reflects_in_panel_html(): void
    {
        // Regresión: garantiza que cambiar el idioma desde el dropdown del topbar
        // produce HTML actualizado en la siguiente request (sin cache stale).
        // Cubre el ciclo completo POST → next GET con mismo authState.
        $admin = User::factory()->create(['panel_locale' => 'zh_CN']);
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        // 1) Estado inicial: panel en chino.
        $first = $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertStringContainsString('用户菜单', $first->getContent(),
            'Panel debe arrancar en zh_CN cuando users.panel_locale=zh_CN.');
        $this->assertStringContainsString('aria-label="中文"', $first->getContent(),
            'Trigger del switcher debe etiquetar 中文 cuando es el current.');

        // 2) Cambio a es.
        $this->actingAs($admin)
            ->from('/admin')
            ->post(route('admin.lang.switch', 'es'))
            ->assertRedirect('/admin');
        $this->assertSame('es', $admin->fresh()->panel_locale);

        // 3) Siguiente GET con MISMO authState debe reflejar el cambio.
        $second = $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertStringContainsString('Menú del usuario', $second->getContent(),
            'Tras POST→es, la siguiente request debe estar en español.');
        $this->assertStringContainsString('aria-label="Español"', $second->getContent(),
            'Trigger debe etiquetar Español tras cambiar.');

        // 4) Vuelta a zh_CN simétrica.
        $this->actingAs($admin)
            ->from('/admin')
            ->post(route('admin.lang.switch', 'zh_CN'))
            ->assertRedirect('/admin');
        $third = $this->actingAs($admin)->get('/admin')->assertOk();
        $this->assertStringContainsString('aria-label="中文"', $third->getContent());
    }
}
