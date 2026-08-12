<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Roles del proyecto.
 *
 * - `admin` (Fase 4.1, super-admin vía `Gate::before` en `AppServiceProvider`).
 * - `customer` (Fase 4.1, el cliente normal).
 * - `staff` (Fase 7.0, el empleado del parque con permisos limitados).
 *
 * Los permisos finos de `staff` los siembra `PermissionSeeder` (Fase 7.0).
 * El label se puede cambiar desde el panel sin tocar código.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'label' => 'Administrador'],
            ['name' => 'customer', 'label' => 'Cliente'],
            ['name' => 'staff', 'label' => 'Empleado'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], ['label' => $role['label']]);
        }
    }
}
