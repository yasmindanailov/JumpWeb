<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LandingContentSeeder::class,
            SalesSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            // Ejemplo de atracción de pago (#228) — solo demo/desarrollo, fuera del seeder de
            // contenido para no alterar los conteos del catálogo que verifican los tests.
            DemoPaidAttractionSeeder::class,
        ]);

        // Usuarios de PRUEBA — solo fuera de producción (desarrollo local).
        // Credenciales de DESARROLLO (placeholder).
        if (! app()->isProduction()) {
            $admin = User::firstOrCreate(
                ['email' => 'admin@jumpweb.test'],
                [
                    'name' => 'Admin (prueba)',
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'locale' => 'es',
                ],
            );
            $admin->roles()->syncWithoutDetaching(
                Role::where('name', 'admin')->pluck('id'),
            );

            $staff = User::firstOrCreate(
                ['email' => 'empleado@jumpweb.test'],
                [
                    'name' => 'Empleado (prueba)',
                    'password' => 'password',
                    'email_verified_at' => now(),
                    'locale' => 'es',
                ],
            );
            $staff->roles()->syncWithoutDetaching(
                Role::where('name', 'staff')->pluck('id'),
            );
        }
    }
}
