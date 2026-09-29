<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * **EL AUTHENTICATOR, OBLIGATORIO PARA LOS ADMINISTRADORES** (P3 de `docs/specs/panel-a-salvo.md` §4.3; `#847`: «solo
 * administradores»; `#851`).
 *
 * Filament decide «obligatorio» para todo el panel; `RequiresAdminAppAuthentication` lo exige por ROL: un administrador sin
 * authenticator va a configurarlo —también desde las rutas del personal fuera de Filament—; mostrador y puerta, como hoy.
 * El login de un administrador con authenticator pide el código de su app por la puerta REAL (el `Login` de Filament).
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

        $login = Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate');
        $this->assertFalse(Auth::guard('admin')->check(), 'con la contraseña sola NO entra: pide el código');

        $login->set('data.multiFactor.app.code', '000000')->call('authenticate');
        $this->assertFalse(Auth::guard('admin')->check(), 'con un código que no es, tampoco');

        $login->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($secret))->call('authenticate');
        $this->assertTrue(Auth::guard('admin')->check(), 'con el código de su app, entra');
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
