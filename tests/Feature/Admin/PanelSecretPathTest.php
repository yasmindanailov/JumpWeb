<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Http\PanelPath;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * **EL PANEL EN SU DIRECCIÓN SECRETA** (P2 de `docs/specs/panel-a-salvo.md` §4.2, `DECISIONES #850`).
 *
 * La dirección se lee al ARRANCAR (las rutas y el panel se registran entonces), así que esta prueba crea la aplicación
 * con `PANEL_PATH` puesta y la devuelve a la de la suite al terminar (`phpunit.xml`: `admin`). Lo que se fija: `/admin`
 * ya no existe, el panel entero —también las trece rutas del personal— vive en la suya, y lo que lo excluye o enlaza por
 * ruta (el mantenimiento, su aviso, la vuelta de la puerta) va a la suya.
 */
class PanelSecretPathTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETA = 'gestion-sonda-7q2x';

    private ?string $antes = null;

    public function createApplication()
    {
        $this->antes = $_SERVER['PANEL_PATH'] ?? null;
        putenv('PANEL_PATH='.self::SECRETA);
        $_ENV['PANEL_PATH'] = $_SERVER['PANEL_PATH'] = self::SECRETA;

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // La de la suite, para la prueba siguiente de este proceso (paratest reutiliza procesos).
        $valor = $this->antes ?? 'admin';
        putenv('PANEL_PATH='.$valor);
        $_ENV['PANEL_PATH'] = $_SERVER['PANEL_PATH'] = $valor;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->sync([Role::where('name', 'admin')->value('id')]);

        return $user;
    }

    public function test_the_panel_lives_at_its_secret_address_and_admin_no_longer_exists(): void
    {
        $this->assertSame(self::SECRETA, PanelPath::path(), 'control: la aplicación arrancó con la dirección secreta');

        $this->get('/'.self::SECRETA.'/login')->assertOk();
        $this->get('/admin')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();
        $this->actingAs($this->admin(), 'admin')->get('/'.self::SECRETA)->assertOk();
    }

    public function test_every_staff_route_hangs_from_the_secret_address(): void
    {
        $staff = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => in_array('panel_role', $route->gatherMiddleware(), true));

        $this->assertCount(13, $staff);
        $fuera = $staff->reject(fn ($route): bool => str_starts_with($route->uri(), self::SECRETA.'/'))
            ->map(fn ($route): string => $route->uri())->values()->all();
        $this->assertSame([], $fuera, 'rutas del personal fuera de la dirección del panel');
        $this->assertStringStartsWith(url('/'.self::SECRETA.'/puerta/validar'), route('admin.puerta.validar'));
    }

    public function test_what_excludes_or_links_the_panel_by_path_follows_its_address(): void
    {
        $this->assertTrue(PanelPath::matches(Request::create('/'.self::SECRETA.'/orders')));
        $this->assertFalse(PanelPath::matches(Request::create('/admin/orders')), '`/admin` ya no es el panel');

        // El mantenimiento deja pasar al panel en SU dirección (`SEC-02`), y su aviso enlaza allí. ⚠️ Dónde DECIDE esa
        // exclusión, medido con el arnés (dos intentos vacíos): el login de Filament no va por el grupo `web`; sin sesión,
        // `auth:admin` corre ANTES (Laravel lo sube por prioridad) y redirige igual; `admin` y `staff` se lo saltan por su
        // guard. Queda el rol `puerta`: con la sesión del panel y sin paso libre, en mantenimiento tiene que seguir validando.
        $this->seed(LandingContentSeeder::class);
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);
        $puerta = User::factory()->create(['email_verified_at' => now()]);
        $puerta->roles()->sync([Role::where('name', 'puerta')->value('id')]);
        $this->actingAs($puerta, 'admin')->get('/'.self::SECRETA.'/puerta/validar')->assertOk();
        Auth::forgetGuards();

        $admin = $this->admin();
        $this->actingAs($admin, 'admin');
        $this->app['auth']->shouldUse('web');
        $this->get('/')->assertOk()->assertSee(url('/'.self::SECRETA.'/configuracion/maintenance'), false);

        // La vuelta de la puerta al panel.
        $this->actingAs($admin, 'admin')->get('/'.self::SECRETA.'/puerta/validar')->assertOk()
            ->assertSee('href="'.url('/'.self::SECRETA).'"', false);
    }

    /**
     * La analítica deja el panel en paz EN SU DIRECCIÓN: no le acuña visitante (`ResolveVisitor`) ni toma una visita con la
     * marca de un correo por un clic (`RecordEmailClick`). La puerta es una ruta del personal del grupo `web`, así que pasa
     * por los dos; la portada es el control, donde los dos actúan.
     */
    public function test_the_analytics_middleware_leave_the_panel_alone_at_its_address(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->get('/')->assertOk()->assertCookie('visitor_id');
        $this->get('/?jw_e=sonda')->assertRedirect();

        $this->actingAs($this->admin(), 'admin');
        $this->get('/'.self::SECRETA.'/puerta/validar')->assertOk()->assertCookieMissing('visitor_id');
        $this->get('/'.self::SECRETA.'/puerta/validar?jw_e=sonda')->assertOk();
    }
}
