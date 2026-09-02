<?php

namespace App\Console\Commands;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PasswordPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Crea (o repara) la cuenta de acceso al panel. Es el **mecanismo canónico del primer admin**, que
 * `INSTALACION-CLIENTE.md` §5 tenía como `[DECISION-PENDIENTE]` y que `DECISIONES #103` convirtió en
 * camino crítico: **ningún seeder crea un admin en producción** —`ProductionSeeder` no crea usuarios
 * y `DatabaseSeeder` solo siembra los `@…test` `if (! isProduction())`— así que sin este comando una
 * instalación recién desplegada **no tiene por dónde entrar al panel**, y por tanto tampoco por dónde
 * configurar las claves de Turnstile, que se leen SOLO de `settings`.
 *
 * ⚠️ **`make:filament-user` NO sirve**: `User::canAccessPanel()` exige `hasRole('admin'|'staff')` y
 * eso vive en la pivote `role_user`, que ese comando no toca. Crearía un usuario que no puede entrar.
 *
 * DISEÑO (las tres decisiones que no son obvias):
 *
 *  1. **La contraseña se GENERA por defecto y se imprime UNA vez.** Pasarla por `--password` la deja
 *     en `ps`, en el historial del shell y en el log del despliegue; generarla no. Por eso `--password`
 *     existe pero no es el camino recomendado.
 *  2. **Idempotente de verdad, y sin sorpresas**: si la cuenta ya existe NO se le cambia la contraseña
 *     (eso echaría al owner de su propio panel en cada redespliegue); solo se le garantiza el rol. Para
 *     rotarla hay que pedirlo con `--password` o `--reset-password`.
 *  3. **Aborta si el rol no existe** en vez de crearlo. Un `admin` sin fila en `roles` significa que
 *     los seeders no han corrido, y entonces falta mucho más que el rol: fabricarlo a medias dejaría
 *     una instalación en un estado que nadie ha probado.
 *
 * Ejecutable por SSH no interactivo (`deploy.sh`): no pregunta nada si recibe `--email` y `--name`.
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--email= : Email de la cuenta (obligatorio en modo no interactivo).}
        {--name= : Nombre para mostrar. Por defecto, «Administrador».}
        {--password= : Contraseña explícita. ⚠️ Queda visible en `ps` y en el historial: por defecto se GENERA una.}
        {--reset-password : Si la cuenta ya existe, rotarle la contraseña (se genera una nueva y se imprime).}
        {--role=admin : Rol a garantizar. Solo `admin` o `staff` abren el panel.}';

    protected $description = 'Crea o repara la cuenta de acceso al panel (primer admin). Idempotente.';

    /** Roles que `User::canAccessPanel()` acepta. Cualquier otro crearía una cuenta que no entra. */
    private const PANEL_ROLES = ['admin', 'staff'];

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) ($this->option('email') ?: '')));
        $roleName = mb_strtolower(trim((string) $this->option('role')));

        if ($email === '') {
            $this->error('Falta --email. Ejemplo: php artisan app:create-admin --email=admin@dominio.tld');

            return self::FAILURE;
        }

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']]);

        if ($validator->fails()) {
            $this->error("«{$email}» no es un email válido.");

            return self::FAILURE;
        }

        if (! in_array($roleName, self::PANEL_ROLES, true)) {
            $this->error("El rol «{$roleName}» no abre el panel. Usa: ".implode(' o ', self::PANEL_ROLES).'.');

            return self::FAILURE;
        }

        // Aborta en vez de crear el rol: si no está, los seeders no han corrido (ver docblock).
        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null) {
            $this->error("El rol «{$roleName}» no existe en la tabla `roles`.");
            $this->warn('Eso significa que los seeders no han corrido. Ejecuta primero:');
            $this->line('  php artisan db:seed --class=Database\\\\Seeders\\\\RoleSeeder --force');

            return self::FAILURE;
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $plainPassword = null;

        if ($existing !== null) {
            $rotate = $this->option('reset-password') || $this->option('password') !== null;

            if ($rotate) {
                $plainPassword = $this->resolvePassword();

                if ($plainPassword === null) {
                    return self::FAILURE;
                }
            }

            $granted = $this->grantRole($existing, $role, $plainPassword);

            $this->info("Cuenta EXISTENTE: {$existing->email} (id {$existing->id}).");
            $this->line($granted
                ? "  · rol «{$roleName}» AÑADIDO (no lo tenía)."
                : "  · rol «{$roleName}» ya lo tenía: nada que hacer.");

            if ($plainPassword !== null) {
                $this->reportPassword($existing->email, $plainPassword);
            } else {
                $this->line('  · contraseña INTACTA (usa --reset-password para rotarla).');
            }

            return self::SUCCESS;
        }

        $plainPassword = $this->resolvePassword();

        if ($plainPassword === null) {
            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: '')) ?: 'Administrador';

        $user = DB::transaction(function () use ($email, $name, $plainPassword, $role): User {
            $user = new User;
            $user->name = $name;
            $user->email = $email;
            $user->password = $plainPassword;   // cast `hashed`: se hashea al asignar.
            // `email_verified_at` NO es asignable en masa (no está en #[Fillable]) y aquí SÍ se
            // quiere puesta: un admin recién creado tiene que poder operar sin pasar por el correo,
            // que además en staging no sale (guarda 3 de ENTORNOS §2).
            $user->email_verified_at = now();
            $user->save();

            $user->roles()->syncWithoutDetaching([$role->id]);

            return $user;
        });

        $this->info("Cuenta CREADA: {$user->email} (id {$user->id}) con rol «{$roleName}».");
        $this->reportPassword($user->email, $plainPassword);

        return self::SUCCESS;
    }

    /**
     * Ata el rol sin tocar los que ya tenga, y rota la contraseña solo si se pidió.
     *
     * @return bool `true` si el rol NO lo tenía y se ha añadido.
     */
    private function grantRole(User $user, Role $role, ?string $plainPassword): bool
    {
        return DB::transaction(function () use ($user, $role, $plainPassword): bool {
            $had = $user->roles()->where('roles.id', $role->id)->exists();

            $user->roles()->syncWithoutDetaching([$role->id]);

            if ($plainPassword !== null) {
                $user->password = $plainPassword;
                $user->save();
            }

            return ! $had;
        });
    }

    /** Devuelve la contraseña en claro, o `null` si la explícita no pasa la política. */
    private function resolvePassword(): ?string
    {
        $explicit = $this->option('password');

        if ($explicit === null) {
            // 24 caracteres del generador de Laravel (letras + números + símbolos, sin espacios):
            // entra en la política por construcción.
            //
            // ⚠️ **Este comentario explicaba por qué aquí NO se consultaba Have I Been Pwned** —una
            // cadena aleatoria de 24 caracteres no está en ningún corpus, y la llamada habría metido
            // una dependencia de red en el camino feliz del despliegue—. Desde `#351` **no lo
            // consulta nadie**: la política del producto lo retiró (`PasswordPolicy`). Se conserva
            // dicho porque el razonamiento sigue valiendo si algún día vuelve.
            return Str::password(24);
        }

        // La explícita SÍ la elige un humano, así que se le exige la MISMA política que al registro
        // real (`Register`/`AuthRegistrationController`): mínimo 8 y no filtrada.
        $validator = Validator::make(
            ['password' => $explicit],
            ['password' => PasswordPolicy::rules()],
        );

        if ($validator->fails()) {
            $this->error('La contraseña de --password no cumple la política: '
                .implode(' ', $validator->errors()->get('password')));

            return null;
        }

        return (string) $explicit;
    }

    /**
     * La contraseña se enseña UNA vez: no se guarda en claro en ningún sitio.
     *
     * ⚠️⚠️ **Se imprime en RAW, y no con `line()`, porque `line()` la CORROMPÍA** (2026-08-25).
     * El formateador de Symfony trata `\<` y `\>` como delimitadores de etiqueta **escapados** y se
     * come la barra, así que una contraseña con esa pareja se imprimía **distinta de como se
     * guarda**. `Str::password(24)` incluye `\`, `<` y `>` en su alfabeto: medido sobre **200.000
     * contraseñas generadas, 1.263 (0,63 %) no sobreviven a la consola** — una de cada 158.
     *
     * ▶ **Y esto no era un detalle cosmético**: es el comando del PRIMER ADMIN de una instalación
     * (`deploy.sh --go --admin-email=…`), la contraseña se enseña una sola vez y no queda en ningún
     * sitio. En esos casos el owner se quedaba **fuera de su propio panel sin recuperación**, que es
     * exactamente el modo de fallo que `CreateAdminTest` dice vigilar — y lo vigilaba, pero solo
     * acertaba el 0,63 % de las veces, así que se leía como un test intermitente.
     */
    private function reportPassword(string $email, string $plain): void
    {
        $this->newLine();
        $this->warn('  ⚠️  Contraseña (se muestra UNA sola vez, no queda guardada en claro):');
        $this->line("      usuario:     {$email}");
        $this->getOutput()->writeln("      contraseña:  {$plain}", OutputInterface::OUTPUT_RAW);
        $this->newLine();
    }
}
