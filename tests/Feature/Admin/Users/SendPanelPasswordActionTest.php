<?php

namespace Tests\Feature\Admin\Users;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PanelPasswordLinks;
use App\Domain\Identity\Services\PasswordPolicy;
use App\Domain\Platform\Models\AuditLog;
use App\Filament\Auth\PanelPassword;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Http\PanelPath;
use App\Notifications\PanelPasswordLink;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **La contraseña del PANEL, desde la ficha** (A5a de `docs/specs/acceso-con-codigo.md` §4.12, `DECISIONES #870`): un
 * administrador envía a una cuenta del panel el enlace para crearla; nadie la recupera por su cuenta, y un CLIENTE no la
 * recibe nunca —ni por el botón, ni por el dominio, ni por la página—.
 *
 * Sustituye a la prueba del botón que la mandaba a CLIENTES (`SendPasswordResetActionTest`), retirado con su sujeto: los
 * clientes entran con un código al correo y no tienen contraseña (`#848`).
 */
class SendPanelPasswordActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    /** El token del enlace, leído del correo: es lo que la persona tendría en la mano. */
    private function tokenOf(PanelPasswordLink $mail, User $to): string
    {
        parse_str((string) parse_url((string) $mail->toMail($to)->actionUrl, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }

    // ── El botón: a quién, y quién lo pulsa ──────────────────────────────────────────────────────

    public function test_an_admin_sees_it_on_every_kind_of_panel_account(): void
    {
        $admin = $this->userWithRole('admin');

        foreach (User::PANEL_ROLES as $role) {
            Livewire::actingAs($admin)
                ->test(ViewUser::class, ['record' => $this->userWithRole($role)->id])
                ->assertActionVisible('sendPanelPassword');
        }
    }

    public function test_it_is_hidden_on_a_customer_on_oneself_and_on_an_anonymized_account(): void
    {
        $admin = $this->userWithRole('admin');
        // La supresión le quita los roles; se le devuelven para que solo la anonimización la deje fuera.
        $gone = $this->userWithRole('staff');
        $gone->anonymize();
        $gone->roles()->sync([Role::where('name', 'staff')->value('id')]);

        // Un cliente, una cuenta sin rol, la propia y la anonimizada.
        foreach ([$this->userWithRole('customer'), User::factory()->create(), $admin, $gone] as $record) {
            Livewire::actingAs($admin)
                ->test(ViewUser::class, ['record' => $record->id])
                ->assertActionHidden('sendPanelPassword');
        }
    }

    public function test_it_needs_access_manage_and_not_just_users_manage(): void
    {
        // Quien VE las fichas (`users.manage`) no da la entrada al panel: eso es de quien da los roles (`access.manage`).
        $role = Role::create(['name' => 'encargada', 'label' => 'Encargada']);
        $role->permissions()->sync(Permission::where('name', 'users.manage')->pluck('id'));
        $manager = User::factory()->create();
        $manager->roles()->sync([$role->id]);
        $this->assertTrue($manager->fresh()->hasPermission('users.manage'));

        Livewire::actingAs($manager->fresh())
            ->test(ViewUser::class, ['record' => $this->userWithRole('staff')->id])
            ->assertActionHidden('sendPanelPassword');
    }

    // ── Enviar ───────────────────────────────────────────────────────────────────────────────────

    public function test_it_sends_the_link_to_the_panel_page_and_audits_without_the_email(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $staff->id])
            ->callAction('sendPanelPassword')
            ->assertHasNoActionErrors();

        Notification::assertSentTo($staff, PanelPasswordLink::class, function (PanelPasswordLink $mail) use ($staff): bool {
            $url = (string) $mail->toMail($staff)->actionUrl;

            // A la página del PANEL, bajo su dirección —no a la web—, firmada y con un token vivo de esta cuenta.
            return parse_url($url, PHP_URL_PATH) === '/'.PanelPath::path().'/contrasena'
                && str_contains($url, 'signature=')
                && Password::broker()->tokenExists($staff, $this->tokenOf($mail, $staff))
                && $mail->secretsInCopy() === [$this->tokenOf($mail, $staff)];
        });

        $log = AuditLog::where('action', 'users.panel_password_link_sent')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($staff->id, (int) $log->target_id);
        $this->assertNull($log->payload, 'el correo, solo como huella');
    }

    public function test_an_account_that_stops_being_of_the_panel_while_the_modal_is_open_gets_nothing(): void
    {
        // `SEC-04`, medido: con el modal abierto, la cuenta deja de ser del panel, y se confirma. No se envía nada y la
        // acción ni corre (ningún rastro): Livewire relee la ficha de la base y Filament re-evalúa `visible()` al confirmar.
        Notification::fake();
        $staff = $this->userWithRole('staff');

        $page = Livewire::actingAs($this->userWithRole('admin'))
            ->test(ViewUser::class, ['record' => $staff->id])
            ->mountAction('sendPanelPassword');
        $staff->roles()->sync([Role::where('name', 'customer')->value('id')]);
        $page->callMountedAction();

        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::whereIn('action', ['users.panel_password_link_sent', 'users.panel_password_link_blocked'])->count());
    }

    public function test_an_operator_who_stops_being_admin_while_the_modal_is_open_sends_nothing(): void
    {
        // `SEC-04`, medido, del otro lado: quien pulsa pierde el rol de administrador con el modal abierto.
        Notification::fake();
        $admin = $this->userWithRole('admin');

        $page = Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $this->userWithRole('staff')->id])
            ->mountAction('sendPanelPassword');
        $admin->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $page->callMountedAction();

        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'users.panel_password_link_sent')->count());
    }

    public function test_a_panel_account_without_an_email_is_told_and_audited(): void
    {
        // Lo que solo sabe el dominio: una cuenta del panel sin correo (una de agenda a la que se dio un rol) no tiene
        // adónde recibirlo. El botón está, y al pulsarlo lo dice y lo deja en el rastro.
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $withoutEmail = User::factory()->create(['email' => null]);
        $withoutEmail->roles()->sync([Role::where('name', 'puerta')->value('id')]);

        Livewire::actingAs($admin)
            ->test(ViewUser::class, ['record' => $withoutEmail->id])
            ->callAction('sendPanelPassword')
            ->assertNotified(__('admin.users.actions.send_panel_password.blocked'));

        Notification::assertNothingSent();
        $this->assertSame(1, AuditLog::where('action', 'users.panel_password_link_blocked')->where('target_id', $withoutEmail->id)->count());
    }

    public function test_the_domain_never_sends_it_to_a_customer(): void
    {
        // Lo dice el DOMINIO, no solo el botón: a una cuenta de cliente le devolvería una contraseña.
        Notification::fake();
        $links = app(PanelPasswordLinks::class);

        $this->assertSame(PanelPasswordLinks::NOT_ALLOWED, $links->send($this->userWithRole('customer')));
        $this->assertSame(PanelPasswordLinks::NOT_ALLOWED, $links->send(User::factory()->create()));
        // Ni a una anonimizada a la que se devolvió un rol del panel.
        $gone = $this->userWithRole('staff');
        $gone->anonymize();
        $gone->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $this->assertSame(PanelPasswordLinks::NOT_ALLOWED, $links->send($gone->fresh()));

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count(), 'ni siquiera un token');
    }

    public function test_a_second_link_within_the_minute_waits(): void
    {
        Notification::fake();
        $staff = $this->userWithRole('staff');
        $links = app(PanelPasswordLinks::class);

        $this->assertSame(PanelPasswordLinks::SENT, $links->send($staff));
        $this->assertSame(PanelPasswordLinks::THROTTLED, $links->send($staff));

        Notification::assertSentToTimes($staff, PanelPasswordLink::class, 1);
    }

    // ── La página del panel ──────────────────────────────────────────────────────────────────────

    public function test_the_page_opens_only_with_the_signed_link(): void
    {
        $staff = $this->userWithRole('staff');
        $params = ['email' => $staff->email, 'token' => Password::broker()->createToken($staff)];

        $this->get(URL::signedRoute(PanelPasswordLink::ROUTE, $params))
            ->assertOk()
            ->assertSee(__('admin.users.panel_password.title'));
        $this->get(route(PanelPasswordLink::ROUTE, $params))->assertForbidden();
    }

    public function test_a_panel_account_sets_its_password_once(): void
    {
        $staff = $this->userWithRole('staff');
        $staff->forceFill(['remember_token' => 'el-de-antes'])->save();
        $token = Password::broker()->createToken($staff);
        $nueva = str_repeat('k', PasswordPolicy::MIN_LENGTH);

        Livewire::test(PanelPassword::class, ['email' => $staff->email, 'token' => $token])
            ->set('password', $nueva)
            ->set('passwordConfirmation', $nueva)
            ->call('resetPassword')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertTrue(Hash::check($nueva, (string) $staff->password));
        $this->assertNotSame('el-de-antes', $staff->remember_token, 'los dispositivos recordados salen');
        $this->assertFalse(Password::broker()->tokenExists($staff, $token), 'el enlace vale una vez');
    }

    public function test_the_page_follows_the_products_policy(): void
    {
        $staff = $this->userWithRole('staff');
        $before = $staff->password;
        $corta = str_repeat('k', PasswordPolicy::MIN_LENGTH - 1);

        Livewire::test(PanelPassword::class, ['email' => $staff->email, 'token' => Password::broker()->createToken($staff)])
            ->set('password', $corta)
            ->set('passwordConfirmation', $corta)
            ->call('resetPassword')
            ->assertHasErrors(['password']);

        $this->assertSame($before, $staff->fresh()->password);
    }

    public function test_a_customer_token_puts_no_password(): void
    {
        // Ningún botón emite un token a un cliente; se fabrica a mano para ver que la PÁGINA tampoco le pone contraseña.
        $customer = $this->userWithRole('customer');
        $customer->forceFill(['password' => null])->save();
        $una = str_repeat('k', PasswordPolicy::MIN_LENGTH);

        Livewire::test(PanelPassword::class, ['email' => $customer->email, 'token' => Password::broker()->createToken($customer)])
            ->set('password', $una)
            ->set('passwordConfirmation', $una)
            ->call('resetPassword');

        $this->assertNull($customer->fresh()->password);
    }
}
