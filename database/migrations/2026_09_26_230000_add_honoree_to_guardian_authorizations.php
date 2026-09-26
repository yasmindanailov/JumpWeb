<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA EXENCIÓN DE QUIEN CUMPLE (`specs/fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]` `#752`): el justificante que
 * firma su padre o madre, ATADO a quien cumple por un puntero y no por su nombre.
 *
 * `honoree` vale `true` o NULL —nunca `false`— y el `UNIQUE (order_item_id, honoree)` lo convierte en «como mucho UNO por
 * reserva» en los DOS motores: dos NULL no chocan ni en MySQL ni en SQLite. Es el respaldo de la base de datos; la regla
 * se comprueba antes bajo el lock del titular (`GuardianAuthorizationSigner`).
 *
 * ⚠️ **Fuera del hash de la prueba**, y no es un descuido: el hash es de la fila de la FIRMA (`waiver_signatures`,
 * `HASHED_FIELDS_BY_VERSION`), no de ésta — la misma doctrina que `invitation_reply_id` (`RGPD-01`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_authorizations', function (Blueprint $table): void {
            $table->boolean('honoree')->nullable()->after('invitation_reply_id');
            $table->unique(['order_item_id', 'honoree']);
        });
    }

    public function down(): void
    {
        Schema::table('guardian_authorizations', function (Blueprint $table): void {
            $table->dropUnique(['order_item_id', 'honoree']);
            $table->dropColumn('honoree');
        });
    }
};
