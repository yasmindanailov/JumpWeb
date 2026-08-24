<?php

namespace Tests\Feature\Console;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `app:create-admin` — el mecanismo canónico del primer admin (`DECISIONES #103`), que cierra el
 * `[DECISION-PENDIENTE]` de `INSTALACION-CLIENTE.md` §5.
 *
 * ⚠️ **Lo que de verdad hay que fijar aquí no es «crea un usuario», es «crea uno que ENTRA»**: el modo
 * de fallo que motivó el comando es justo el contrario —`make:filament-user` crea una cuenta que
 * `canAccessPanel()` rechaza, porque el rol vive en la pivote `role_user`—. Por eso el caso central
 * asevera contra `canAccessPanel()`, no contra la existencia de la fila.
 *
 * Y la idempotencia se comprueba en sus DOS mitades, que son asimétricas a propósito: re-ejecutar
 * **repara** el rol si falta, pero **NO** toca la contraseña (rotarla en cada redespliegue echaría al
 * owner de su propio panel).
 */
class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    // ── El caso central: la cuenta creada ENTRA al panel ──────────────────────────────────────────

    public function test_it_creates_an_account_that_can_actually_access_the_panel(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld', '--name' => 'Jefa'])
            ->assertExitCode(0);

        $user = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail();

        $this->assertSame('Jefa', $user->name);
        $this->assertTrue($user->hasRole('admin'));
        // La aserción que importa: no «tiene rol», sino «la puerta del panel lo deja pasar».
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_the_new_admin_is_email_verified_so_it_can_operate_immediately(): void
    {
        // Sin esto el primer admin de una instalación quedaría esperando un correo que en staging
        // NO sale (guarda 3 de `ENTORNOS.md` §2: `MAIL_MAILER=log`).
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $this->assertNotNull(User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail()->email_verified_at);
    }

    public function test_the_printed_password_is_the_one_that_actually_authenticates(): void
    {
        // ⚠️ **Esta es la garantía que de verdad importa del comando**, y es más fuerte que «está
        // hasheada»: la contraseña se enseña UNA sola vez y no queda guardada en ningún sitio, así que
        // si lo impreso NO fuera lo que abre, el owner se quedaría fuera **sin forma de recuperarlo**
        // salvo volver a ejecutar el comando. Por eso se captura la salida real y se AUTENTICA con ella.
        $exit = Artisan::call('app:create-admin', ['--email' => 'jefa@cliente.tld']);
        $this->assertSame(0, $exit);

        preg_match('/contraseña:\s*(\S+)/u', Artisan::output(), $m);
        $printed = $m[1] ?? '';

        $this->assertNotSame('', $printed, 'El comando no imprimió ninguna contraseña.');

        $user = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail();

        $this->assertTrue(Hash::isHashed($user->password), 'La contraseña se guardó en claro.');
        $this->assertTrue(
            Hash::check($printed, $user->password),
            'La contraseña IMPRESA no es la que abre la cuenta: el admin quedaría fuera sin recuperación.',
        );
    }

    /**
     * ⚠️⚠️ **Y la contraseña sobrevive a la CONSOLA, que es donde se rompía** (2026-08-25).
     *
     * El caso de arriba genera la contraseña al azar y por eso **solo cazaba este fallo el 0,63 % de
     * las veces**: se leía como un test intermitente —tumbó un `pre-push`— cuando lo que denunciaba
     * era un defecto real. El formateador de Symfony trata `\<` y `\>` como delimitadores de etiqueta
     * **escapados** y se come la barra, y `Str::password(24)` incluye `\`, `<` y `>` en su alfabeto:
     * medido sobre 200.000 generadas, **1.263 se imprimían distintas de como se guardan**. Una de
     * cada 158 instalaciones dejaba al owner fuera de su panel sin recuperación posible.
     *
     * ▶ Este caso fija la pareja EXACTA en vez de esperar a que salga en un sorteo. Un test que solo
     * acierta a veces no es una guarda: es ruido que enseña a re-lanzar el `pre-push`.
     */
    public function test_the_printed_password_survives_the_console_formatter(): void
    {
        // El control anti-filtración (HIBP) no debe llamar a la red: `TestCase` corta las peticiones
        // sueltas, y una contraseña VÁLIDA llega hasta esa comprobación (una inválida corta antes).
        $this->app->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
        {
            public function verify($data): bool
            {
                return true;
            }
        });

        // ⚠️ Las dos parejas que el formateador destruye, en la misma cadena. Sin `\<` NI `\>` este
        // caso pasaría con el defecto puesto.
        $password = 'Zx9\\<qW7\\>rT4mNb2';

        $exit = Artisan::call('app:create-admin', [
            '--email' => 'jefa@cliente.tld',
            '--password' => $password,
        ]);

        $this->assertSame(0, $exit, 'el fixture ya no vale: esa contraseña no pasa la política');

        preg_match('/contraseña:\s*(\S+)/u', Artisan::output(), $m);

        $this->assertSame(
            $password, $m[1] ?? '',
            'la consola ha alterado la contraseña impresa: el admin quedaría fuera sin recuperación',
        );

        // Y lo impreso sigue siendo lo que ABRE — que es la garantía, no que las cadenas coincidan.
        $user = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail();

        $this->assertTrue(Hash::check($m[1] ?? '', $user->password));
    }

    public function test_it_does_not_print_a_password_when_it_did_not_set_one(): void
    {
        // Enseñar una contraseña al reparar una cuenta existente sería MENTIR: la suya no ha cambiado.
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        Artisan::call('app:create-admin', ['--email' => 'jefa@cliente.tld']);

        $this->assertStringNotContainsString('contraseña:', Artisan::output());
    }

    public function test_the_name_defaults_when_not_given(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $this->assertSame(
            'Administrador',
            User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail()->name,
        );
    }

    public function test_it_can_create_a_staff_account_too(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'puerta@cliente.tld', '--role' => 'staff'])
            ->assertExitCode(0);

        $user = User::whereRaw('LOWER(email) = ?', ['puerta@cliente.tld'])->firstOrFail();

        $this->assertTrue($user->hasRole('staff'));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    // ── Idempotencia: repara el rol, pero NO toca la contraseña ───────────────────────────────────

    public function test_rerunning_does_not_duplicate_the_account(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->count());
    }

    public function test_the_email_match_is_case_insensitive(): void
    {
        // `deploy.sh` puede recibir el email escrito de cualquier forma; si el match fuera sensible a
        // mayúsculas, el segundo despliegue crearía una cuenta DUPLICADA en vez de reconocer la suya.
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);
        $this->artisan('app:create-admin', ['--email' => 'JEFA@Cliente.TLD'])->assertExitCode(0);

        $this->assertSame(1, User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->count());
    }

    public function test_rerunning_repairs_a_missing_role(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $user = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail();
        $user->roles()->detach();
        $this->assertFalse($user->fresh()->hasRole('admin'));

        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_rerunning_does_not_rotate_the_password(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $before = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail()->password;

        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $this->assertSame(
            $before,
            User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail()->password,
            'Un redespliegue le cambió la contraseña al admin: eso lo echa de su propio panel.',
        );
    }

    public function test_reset_password_rotates_it_when_asked(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $before = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail()->password;

        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld', '--reset-password' => true])
            ->assertExitCode(0);

        $this->assertNotSame(
            $before,
            User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail()->password,
        );
    }

    public function test_repairing_an_existing_account_keeps_its_other_roles(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        $user = User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->firstOrFail();
        $user->roles()->syncWithoutDetaching([Role::where('name', 'customer')->firstOrFail()->id]);

        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(0);

        // `syncWithoutDetaching`, no `sync`: reparar el acceso no puede desatar lo que ya tenía.
        $this->assertTrue($user->fresh()->hasRole('admin'));
        $this->assertTrue($user->fresh()->hasRole('customer'));
    }

    // ── Las guardas: lo valioso del comando es lo que NO deja hacer ───────────────────────────────

    public function test_it_refuses_a_role_that_does_not_open_the_panel(): void
    {
        // `customer` existe, pero `canAccessPanel()` lo rechaza: crear la cuenta sería crear una que
        // no entra — exactamente el fallo que este comando existe para evitar.
        $this->artisan('app:create-admin', ['--email' => 'cliente@cliente.tld', '--role' => 'customer'])
            ->assertExitCode(1);

        $this->assertSame(0, User::whereRaw('LOWER(email) = ?', ['cliente@cliente.tld'])->count());
    }

    public function test_it_refuses_when_the_role_is_not_seeded(): void
    {
        // Sin `roles` no han corrido los seeders: falta mucho más que el rol, así que el comando
        // aborta en vez de fabricarlo y dejar la instalación en un estado que nadie ha probado.
        Role::query()->delete();

        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld'])->assertExitCode(1);

        $this->assertSame(0, User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->count());
    }

    public function test_it_refuses_without_an_email(): void
    {
        $this->artisan('app:create-admin')->assertExitCode(1);
    }

    public function test_it_refuses_a_malformed_email(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'no-es-un-email'])->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_it_refuses_an_explicit_password_below_the_policy(): void
    {
        // La política es la MISMA que la del registro real (`Register`: `min(8)->uncompromised()`):
        // una cuenta con todos los permisos no puede tener menos exigencia que un cliente.
        $this->artisan('app:create-admin', ['--email' => 'jefa@cliente.tld', '--password' => 'corta'])
            ->assertExitCode(1);

        $this->assertSame(0, User::whereRaw('LOWER(email) = ?', ['jefa@cliente.tld'])->count());
    }
}
