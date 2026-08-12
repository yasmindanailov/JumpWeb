<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sub-fase 7.2e cimientos — permisos finos de gestión per-item.
 *
 * Tres permisos separados (granularidad para futuro role tuning vía panel):
 *  - `orders.edit_item`    — gestión del modal "Gestionar" (cambiar fecha,
 *                            cantidad, producto, datos del evento, addons).
 *  - `orders.cancel_item`  — icono 🗑️ de cancelar item suelto (soft-cancel +
 *                            refund REST automático del importe del item).
 *  - `orders.refund_item`  — icono ↩️ de reembolsar item (refund parcial,
 *                            con opción "También cancelar este item" en el modal,
 *                            réplica del flujo de #142 a nivel item).
 *
 * Los tres se asignan a `staff` por defecto (operativa diaria del parque).
 *
 * Idempotente: si los permisos o los pivots ya existen, no falla ni duplica.
 * `PermissionSeeder` también los siembra para entornos nuevos. Esta migración
 * cubre los entornos ya desplegados sin tener que re-seedear.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'orders.edit_item' => 'Editar item del pedido (fecha, cantidad, producto, datos, complementos)',
        'orders.cancel_item' => 'Cancelar item suelto del pedido (con reembolso automático)',
        'orders.refund_item' => 'Reembolsar item suelto del pedido',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $staffRoleId = DB::table('roles')->where('name', 'staff')->value('id');

            foreach (self::PERMISSIONS as $name => $label) {
                $permId = DB::table('permissions')->where('name', $name)->value('id');
                if ($permId === null) {
                    $permId = DB::table('permissions')->insertGetId([
                        'name' => $name,
                        'label' => $label,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($staffRoleId === null) {
                    continue;
                }

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
            foreach (array_keys(self::PERMISSIONS) as $name) {
                $permId = DB::table('permissions')->where('name', $name)->value('id');
                if ($permId === null) {
                    continue;
                }
                DB::table('permission_role')->where('permission_id', $permId)->delete();
                DB::table('permissions')->where('id', $permId)->delete();
            }
        });
    }
};
