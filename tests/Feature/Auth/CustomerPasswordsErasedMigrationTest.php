<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * **Las contraseñas de los clientes que ya existen, borradas** (A5d de `docs/specs/acceso-con-codigo.md` §4.12,
 * `DECISIONES #848`/`#869`; la migración `2026_10_02_210000_erase_customer_passwords`).
 *
 * La frontera es la del panel (`User::PANEL_ROLES`): una cuenta sin ninguno de esos roles se queda sin contraseña, y
 * las del personal —también la de `puerta`, la que una copia vieja de los roles dejaba fuera, y la que además es
 * cliente— salen BYTE A BYTE iguales: con otro hash, nadie del equipo podría entrar al panel la noche del despliegue.
 */
class CustomerPasswordsErasedMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_customers_lose_their_password_and_the_team_keeps_its_own(): void
    {
        $clientes = [
            'con el rol de cliente' => $this->cuenta('cliente-1', ['customer']),
            'sin ningún rol' => $this->cuenta('cliente-2', []),
            // Anonimizada antes de la A5d: llevaba un hash aleatorio, y se queda como la deja hoy `anonymize()`.
            'anonimizada' => $this->cuenta('aleatoria-'.str_repeat('x', 50), [], 'deleted_1@'.User::ANONYMIZED_EMAIL_DOMAIN),
        ];
        $equipo = [
            'admin' => $this->cuenta('equipo-1', ['admin']),
            'staff' => $this->cuenta('equipo-2', ['staff']),
            'puerta' => $this->cuenta('equipo-3', ['puerta']),
            'cliente y staff' => $this->cuenta('equipo-4', ['customer', 'staff']),
        ];
        $antes = $this->contrasenas();
        foreach ([...$clientes, ...$equipo] as $quien => $cuenta) {
            $this->assertNotNull($antes[$cuenta->id], "control: «{$quien}» llega a la migración con contraseña");
        }
        $updatedAt = DB::table('users')->where('id', $clientes['sin ningún rol']->id)->value('updated_at');
        $this->travel(1)->hours();
        Log::spy();

        $this->migracion()->up();

        $despues = $this->contrasenas();
        foreach ($clientes as $quien => $cuenta) {
            $this->assertNull($despues[$cuenta->id], "la cuenta «{$quien}» conserva su contraseña");
        }
        foreach ($equipo as $quien => $cuenta) {
            $this->assertSame($antes[$cuenta->id], $despues[$cuenta->id], "la contraseña de «{$quien}» ha cambiado");
        }
        $this->assertSame($updatedAt, DB::table('users')->where('id', $clientes['sin ningún rol']->id)->value('updated_at'),
            'borrar un dato que ya no sirve no es un cambio de la cuenta');
        Log::shouldHaveReceived('info')->once()->with('users.customer_passwords_erased', ['count' => 3]);
    }

    public function test_a_second_pass_finds_nothing_and_there_is_no_way_back(): void
    {
        $cliente = $this->cuenta('cliente-1', ['customer']);
        $staff = $this->cuenta('equipo-1', ['staff']);
        $hashDelStaff = $this->contrasenas()[$staff->id];
        Log::spy();

        $this->migracion()->up();
        $this->migracion()->up();
        $this->migracion()->down();

        $this->assertNull($this->contrasenas()[$cliente->id], 'deshacer la migración ha devuelto una contraseña');
        $this->assertSame($hashDelStaff, $this->contrasenas()[$staff->id]);
        // Solo la pasada que borró algo deja su línea en el registro.
        Log::shouldHaveReceived('info')->once()->with('users.customer_passwords_erased', ['count' => 1]);
    }

    /** @param  list<string>  $roles */
    private function cuenta(string $contrasena, array $roles, ?string $email = null): User
    {
        $user = User::factory()->create(['password' => $contrasena] + ($email === null ? [] : ['email' => $email]));
        $user->roles()->attach(Role::query()->whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    /** @return array<int, string|null> el hash crudo de cada cuenta, por id */
    private function contrasenas(): array
    {
        return DB::table('users')->pluck('password', 'id')->all();
    }

    private function migracion(): Migration
    {
        return require database_path('migrations/2026_10_02_210000_erase_customer_passwords.php');
    }
}
