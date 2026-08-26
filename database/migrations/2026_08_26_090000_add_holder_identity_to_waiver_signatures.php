<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · waiver, tanda 2 (`docs/specs/waiver-probatorio.md` §9.6, `DECISIONES #161`).
 *
 * 1. **La identidad del firmante viaja EN la firma** — `[DECIDIDO owner, 2026-08-26]`. Tras
 *    `User::anonymize()` la fila de `users` dice «Cliente eliminado», así que una prueba que solo
 *    guardara el `user_id` dejaría de identificar a la persona: `#142` la quiere «conservada
 *    vinculada, no anonimizada». Se copian `name` y `email` tal y como estaban al firmar, entran en
 *    el hash y quedan bajo el mismo régimen restringido que el resto de la fila.
 * 2. **`canonical_version`**: la serialización canónica del hash gana campos (v1 → v2). La fila
 *    guarda con qué versión se calculó, y `WaiverSignature::canonical()` verifica cada una con la
 *    suya: lo ya firmado sigue verificando aunque el esquema evolucione (spec §4.7).
 * 3. **Permiso `waiver.view`** (§4.6: «permiso propio, cada consulta auditada»), idempotente para
 *    las instalaciones ya desplegadas; `PermissionSeeder` lo siembra en las nuevas. NO se asigna a
 *    `staff` por defecto: el registro probatorio está fuera de toda superficie normal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->string('holder_name', 255)->nullable()->after('subject_id');
            $table->string('holder_email', 255)->nullable()->after('holder_name');
            $table->unsignedTinyInteger('canonical_version')->default(1)->after('hash');
        });

        DB::transaction(function () {
            $exists = DB::table('permissions')->where('name', 'waiver.view')->exists();
            if (! $exists) {
                DB::table('permissions')->insert([
                    'name' => 'waiver.view',
                    'label' => 'Ver el registro probatorio del waiver (firmas y PDF)',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->dropColumn(['holder_name', 'holder_email', 'canonical_version']);
        });
        // El permiso se deja: retirarlo borraría asignaciones hechas a mano en el panel.
    }
};
