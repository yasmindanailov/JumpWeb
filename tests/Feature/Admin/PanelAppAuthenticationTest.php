<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Auth\PanelAppAuthentication;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * **EL AUTHENTICATOR, OBLIGATORIO PARA LOS ADMINISTRADORES** (P3 de `docs/specs/panel-a-salvo.md` §4.3; `#847`: «solo
 * administradores»; `#851`) **Y EL LOGIN QUE RECUERDA UN DÍA** (§4.4, `#877`).
 *
 * Filament decide «obligatorio» para todo el panel; `RequiresAdminAppAuthentication` lo exige por ROL: un administrador sin
 * authenticator va a configurarlo —también desde las rutas del personal fuera de Filament—; mostrador y puerta, como hoy.
 * El login de un administrador con authenticator pide el código de su app por la puerta REAL: la que registra el panel
 * (`PanelLogin`, el de Filament con «Recordarme» marcada).
 */
class PanelAppAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // `TestCase` lo apaga para el resto de la suite; aquí se prueba ENCENDIDO, como en producción.
        config(['panel.admin_mfa' => true]);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function withRole(string $role, ?string $secret = null): User
    {
        $user = User::factory()->create(['email' => $role.'@jumpweb.test', 'email_verified_at' => now()]);
        $user->roles()->sync([Role::where('name', $role)->value('id')]);
        if ($secret !== null) {
            $user->saveAppAuthenticationSecret($secret);
        }

        return $user;
    }

    private function setUpUrl(): string
    {
        return (string) Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl();
    }

    /** El login que el panel REGISTRA, no el de Filament a pelo: si el panel vuelve al suyo, estas pruebas lo ven. */
    private function panelLogin(): Testable
    {
        return Livewire::test(Filament::getPanel('admin')->getLoginRouteAction());
    }

    /** La cookie «recuérdame» del panel, en cola y con su caducidad: UN día (`#877`). */
    private function assertRemembersOneDay(): void
    {
        $name = Auth::guard('admin')->getRecallerName();

        $this->assertTrue(Cookie::hasQueued($name), 'deja la cookie «recuérdame» del panel');
        $this->assertEqualsWithDelta(now()->addDay()->getTimestamp(), Cookie::queued($name)->getExpiresTime(), 5,
            'y dura UN día, no los 400 del framework, en los que ni contraseña ni código');
    }

    public function test_an_admin_without_the_authenticator_is_sent_to_set_it_up_also_from_the_staff_routes(): void
    {
        $admin = $this->withRole('admin');

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect($this->setUpUrl());
        $this->get('/admin/calendario/eventos?start=2026-09-01&end=2026-10-01')->assertRedirect($this->setUpUrl());
        $this->get($this->setUpUrl())->assertOk();
    }

    public function test_counter_and_door_staff_do_not_need_it_and_an_admin_with_it_enters(): void
    {
        $this->actingAs($this->withRole('staff'), 'admin')->get('/admin')->assertOk();
        Auth::forgetGuards();
        $this->actingAs($this->withRole('puerta'), 'admin')->get('/admin/puerta/validar')->assertOk();
        Auth::forgetGuards();
        $this->actingAs($this->withRole('admin', (new Google2FA)->generateSecretKey()), 'admin')->get('/admin')->assertOk();
    }

    public function test_the_login_asks_an_admin_with_it_for_the_code_of_the_app(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $admin = $this->withRole('admin', $secret);

        $login = $this->panelLogin()
            ->set('data.email', $admin->email)
            ->set('data.password', 'password')
            ->call('authenticate');
        $this->assertFalse(Auth::guard('admin')->check(), 'con la contraseña sola NO entra: pide el código');

        $login->set('data.multiFactor.app.code', '000000')->call('authenticate');
        $this->assertFalse(Auth::guard('admin')->check(), 'con un código que no es, tampoco');

        $login->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($secret))->call('authenticate');
        $this->assertTrue(Auth::guard('admin')->check(), 'con el código de su app, entra');
        // `#877`: «Recordarme», marcada de serie, sobrevive al paso del código.
        $this->assertRemembersOneDay();
    }

    /**
     * `#877` (el owner: «un día, para todos»): «Recordarme» viene MARCADA y su cookie dura UN día. Vale para todo el panel
     * —aquí, el mostrador, sin authenticator—, y desmarcada (un ordenador compartido) no deja ninguna: la sesión de siempre.
     */
    public function test_the_login_comes_with_remember_me_checked_and_it_lasts_one_day(): void
    {
        $staff = $this->withRole('staff');

        $this->panelLogin()
            ->assertSet('data.remember', true)
            ->set('data.email', $staff->email)
            ->set('data.password', 'password')
            ->call('authenticate');
        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertRemembersOneDay();

        Auth::guard('admin')->logout();
        Cookie::flushQueuedCookies();

        $this->panelLogin()
            ->set('data.email', $staff->email)
            ->set('data.password', 'password')
            ->set('data.remember', false)
            ->call('authenticate');
        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertFalse(Cookie::hasQueued(Auth::guard('admin')->getRecallerName()), 'desmarcada, sin cookie: la sesión de siempre');
    }

    /**
     * `#867`: el código de la app CONTINÚA con su última cifra (`PanelAppAuthentication`), sin tocar «Entrar». Lo que se
     * mira aquí es el enganche que pinta el servidor; que el navegador envíe, lo ve el owner al entrar.
     */
    public function test_the_code_of_the_app_continues_with_its_last_digit(): void
    {
        $admin = $this->withRole('admin', (new Google2FA)->generateSecretKey());
        // Filament lo pinta tal cual (`merge(…, escape: false)` de sus atributos de Alpine): por eso no lleva comillas dobles.
        $enganche = PanelAppAuthentication::SUBMIT_ON_LAST_DIGIT;

        $login = $this->panelLogin();
        $login->assertDontSeeHtml($enganche);

        $login->fillForm(['email' => $admin->email, 'password' => 'password'])->call('authenticate');
        $login->assertSeeHtml('x-on:input.capture="'.$enganche.'"');
    }

    public function test_the_secret_is_encrypted_hidden_and_forgotten_with_the_account(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $admin = $this->withRole('admin', $secret);

        $crudo = (string) DB::table('users')->where('id', $admin->id)->value('app_authentication_secret');
        $this->assertNotSame($secret, $crudo, 'cifrado en reposo');
        $this->assertStringNotContainsString($secret, $crudo);
        $this->assertArrayNotHasKey('app_authentication_secret', $admin->toArray(), 'oculto');

        $admin->anonymize();
        $this->assertNull($admin->fresh()->getAppAuthenticationSecret(), 'la supresión (RGPD-01) borra también esta credencial');
    }

    public function test_the_emergency_command_removes_it_and_leaves_a_trace(): void
    {
        $admin = $this->withRole('admin', (new Google2FA)->generateSecretKey());
        $admin->saveAppAuthenticationRecoveryCodes(['un-hash']);

        $this->artisan('panel:quitar-authenticator', ['email' => 'ADMIN@jumpweb.test '])->assertSuccessful();

        $admin->refresh();
        $this->assertNull($admin->getAppAuthenticationSecret());
        $this->assertNull($admin->getAppAuthenticationRecoveryCodes());
        $this->assertTrue(AuditLog::query()->where('action', 'panel.app_authentication_removed')->where('target_id', $admin->id)->exists());

        $this->artisan('panel:quitar-authenticator', ['email' => 'nadie@jumpweb.test'])->assertFailed();
    }
}
