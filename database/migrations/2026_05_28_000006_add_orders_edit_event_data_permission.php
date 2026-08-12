<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 7.2c — permiso `orders.edit_event_data` para que el rol staff pueda
 * corregir los datos del evento de un OrderItem de un pack (homenajeado, edad,
 * notas) desde el panel cuando el cliente lo pide en puerta o por teléfono.
 *
 * Idempotente: si el permiso o el pivot ya existen, no falla ni duplica.
 * `PermissionSeeder` también lo siembra para entornos nuevos, pero esta
 * migración cubre los entornos ya desplegados sin tener que re-seedear.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $permId = DB::table('permissions')->where('name', 'orders.edit_event_data')->value('id');
            if ($permId === null) {
                $permId = DB::table('permissions')->insertGetId([
                    'name' => 'orders.edit_event_data',
                    'label' => 'Editar datos del evento del pedido (homenajeado, edad, notas)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $staffRoleId = DB::table('roles')->where('name', 'staff')->value('id');
            if ($staffRoleId !== null) {
                $exists = DB::table('permission_role')
                    ->where('permission_id', $permId)
                    ->where('role_id', $staffRoleId)
                    ->exists();
                if (! $exists) {
                    DB::table('permission_role')->insert([
                        'permission_id' => $permId,
                        'role_id' => $staffRoleId,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $permId = DB::table('permissions')->where('name', 'orders.edit_event_data')->value('id');
            if ($permId === null) {
                return;
            }
            DB::table('permission_role')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        });
    }
};
