<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 6 · subsistema A — permiso `puerta.profile` (`docs/specs/identidad-qr-puerta.md` §4.6, §9.2 A·5):
 * ver la FICHA de puerta del cliente (nombre, waiver, reservas de hoy con lo pendiente de cobrar,
 * menores por edad y exención, carné) y registrar su visita. Es OTRA cosa que `registrations.validate`
 * («¿está registrado?»), igual que `users.search_minimal` es otra cosa que `users.manage`.
 *
 * Idempotente, y asigna al rol `staff` (es la operativa diaria del mostrador). `PermissionSeeder`
 * también lo siembra para entornos nuevos; esta migración cubre los ya desplegados.
 */
return new class extends Migration
{
    private const NAME = 'puerta.profile';

    private const LABEL = 'Ver la ficha de puerta del cliente y registrar su visita';

    public function up(): void
    {
        DB::transaction(function () {
            $permId = DB::table('permissions')->where('name', self::NAME)->value('id');
            if ($permId === null) {
                $permId = DB::table('permissions')->insertGetId([
                    'name' => self::NAME,
                    'label' => self::LABEL,
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
                    DB::table('permission_role')->insert(['permission_id' => $permId, 'role_id' => $staffRoleId]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $permId = DB::table('permissions')->where('name', self::NAME)->value('id');
            if ($permId === null) {
                return;
            }
            DB::table('permission_role')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        });
    }
};
