<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LoginCodes;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **EL PANEL SOLO CONFÍA EN SU PROPIO INICIO DE SESIÓN** (`docs/specs/panel-a-salvo.md` §4.1, `DECISIONES #850`).
 *
 * Hasta `#850` el panel y la web compartían el guard `web`: quien entraba por la web con una cuenta del personal quedaba
 * dentro del panel (con Google, ya sin su contraseña), y con el código al correo (`#848`) toda entrada por la web se
 * habría saltado la contraseña del panel y el authenticator de los administradores. Aquí se fija la frontera por las
 * dos puertas REALES —la de la web (`POST /api/v1/auth/login`) y el login de Filament—, no con `actingAs()`.
 *
 * ⚠️ Las demás pruebas del panel siguen con `actingAs()` sin guard, que entra por las dos (`TestCase::be()`): ésta es la
 * que nombra el suyo, porque su sujeto es la frontera.
 */
class PanelOwnGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login-ip|127.0.0.1');
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'equipo@jumpweb.test', 'email_verified_at' => now()]);
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    public function test_a_web_login_does_not_open_the_panel_nor_its_staff_routes(): void
    {
        $admin = $this->admin();

        // La entrada REAL de la web (la del cajón y la isla), con su sesión: con el código al correo (desde la A5, `#869`,
        // la única; antes, también la contraseña).
        $code = app(LoginCodes::class)->issue($admin->email, LoginCode::PURPOSE_LOGIN, '127.0.0.1');
        $this->withHeader('Origin', (string) config('app.url'))
            ->postJson('/api/v1/auth/login', ['email' => $admin->email, 'code' => $code])
            ->assertOk();
        $this->assertTrue(Auth::guard('web')->check(), 'control: la web sí ha entrado');

        $this->get('/admin')->assertRedirect('/admin/login');
        // Y tampoco una de las trece rutas del personal fuera de Filament.
        $this->get('/admin/calendario/eventos?start=2026-09-01&end=2026-10-01')->assertRedirect();
    }

    public function test_the_panel_login_opens_the_panel_and_not_the_web(): void
    {
        $admin = $this->admin();

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('admin')->check(), 'control: el login del panel ha entrado');
        $this->assertFalse(Auth::guard('web')->check(), 'el login del panel no abre la web');
        $this->withHeader('Origin', (string) config('app.url'))->getJson('/api/v1/me')->assertUnauthorized();
        $this->get('/admin')->assertOk();
    }

    public function test_the_maintenance_pass_belongs_to_the_panel_session(): void
    {
        $this->seed(LandingContentSeeder::class);
        $admin = $this->admin();
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);

        $this->actingAs($admin, 'web')->get('/')->assertStatus(503);

        Auth::forgetGuards();
        $this->actingAs($admin, 'admin');
        // Una página PÚBLICA va con el guard de la web por defecto (solo `auth:admin` y Filament cambian a `admin`):
        // `actingAs(…, 'admin')` lo habría dejado en `admin`, y el aviso se vería aunque lo leyera por el guard equivocado.
        $this->app['auth']->shouldUse('web');
        $this->get('/')->assertOk()->assertSee(__('site.maintenance.preview_banner'));
    }

    /**
     * Las rutas del personal FUERA de Filament (`routes/web.php`: la puerta, los PDF, el calendario, la ficha de Google, el
     * CSV…) piden la sesión del panel, todas. La cifra está tecleada a mano: una ruta nueva del personal la sube a
     * sabiendas, y una que se escapara con `auth` a secas la bajaría.
     */
    public function test_every_staff_route_outside_filament_asks_for_the_panel_session(): void
    {
        $staff = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('panel_role', $route->gatherMiddleware(), true));

        $this->assertCount(13, $staff, 'las rutas del personal de `routes/web.php`');
        $sinSuPuerta = $staff->reject(fn ($route): bool => in_array('auth:admin', $route->gatherMiddleware(), true))
            ->map(fn ($route): string => $route->uri())->values()->all();
        $this->assertSame([], $sinSuPuerta, 'rutas del personal con la sesión de la WEB');
    }

    /**
     * El panel se entera de quién es por SU guard porque Filament y `auth:admin` lo hacen el de por defecto
     * (`shouldUse`). Código del panel que nombrara `web` a mano se saltaría eso y leería la sesión de la web.
     */
    public function test_panel_code_never_names_the_web_guard(): void
    {
        $roots = ['app/Filament', 'app/Livewire/Admin', 'app/Http/Controllers/Admin'];
        $offenders = [];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($root), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                if (preg_match("/(auth|guard)\\(\\s*['\"]web['\"]\\s*\\)/", (string) file_get_contents($file->getPathname())) === 1) {
                    $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertSame([], $offenders, 'Código del panel que lee la sesión de la WEB: '.implode(', ', $offenders));
    }
}
