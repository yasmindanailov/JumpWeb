<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.5 (decisión #180) — Retirada de la bandeja de contacto.
 *
 * La clienta decidió que el formulario de contacto de la landing SOLO envíe un
 * email al administrador, sin persistir en BD ni mostrar una bandeja en el panel.
 * Limpieza total:
 *  - se elimina la tabla `contact_messages` (el modelo y el `ContactMessageResource`
 *    nunca llegó a construirse);
 *  - se retira el permiso huérfano `messages.view` (su pivote con `staff` cae por
 *    el `cascadeOnDelete` de `permission_role`). `PermissionSeeder` ya no lo siembra.
 *
 * No toca la tabla `pages` (creada en el mismo fichero original que `contact_messages`).
 *
 * Idempotente y reversible: `down()` recrea la tabla con su esquema original y
 * restaura el permiso `messages.view` asignado a `staff` (estado previo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contact_messages');

        // Permiso huérfano `messages.view` (la bandeja ya no existe). El pivote
        // `permission_role` cae por cascadeOnDelete al borrar la fila del permiso.
        DB::table('permissions')->where('name', 'messages.view')->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->text('message');
                $table->string('type')->default('contact'); // contact | group
                $table->string('locale', 5)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('read_at')->nullable(); // null = sin leer
                $table->timestamps();
            });
        }

        // Restaura el permiso y su asignación a staff (estado previo a #180).
        $permId = DB::table('permissions')->where('name', 'messages.view')->value('id');
        if ($permId === null) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'messages.view',
                'label' => 'Ver bandeja de contacto',
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
    }
};
