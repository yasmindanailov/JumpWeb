<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #202 — Retirada del sistema "PREPARADO / NO PREPARADO".
 *
 * Elimina la persistencia del estado de preparación:
 *  - `order_items.prepared_at` / `prepared_by` (sistema digital de preparado,
 *    añadidos en 2026_05_28_000003).
 *  - Columnas DURMIENTES del ciclo de canje en puerta de la tabla `tickets`
 *    (`prepared_at`/`prepared_by`/`redeemed_at`/`redeemed_by`, del plan 7.1b
 *    original nunca activado). `tickets.status` se CONSERVA: `TicketIssuer` lo
 *    escribe (`purchased`) al emitir la entrada.
 *
 * Reversible (`down()` recrea las columnas espejando sus migraciones originales).
 * El dato de `prepared_at` existente se pierde al dropear: es operativa interna
 * transitoria, no histórico contable. Las acciones ya escritas en `audit_logs`
 * (`order_items.prepared`/`unprepared`/`toggle_blocked`) se conservan (tabla
 * inmutable) y siguen siendo legibles vía sus claves i18n del modal de historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn('prepared_at');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropConstrainedForeignId('redeemed_by');
            $table->dropColumn(['prepared_at', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('prepared_at')->nullable()->after('event_data');
            $table->foreignId('prepared_by')->nullable()->after('prepared_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
