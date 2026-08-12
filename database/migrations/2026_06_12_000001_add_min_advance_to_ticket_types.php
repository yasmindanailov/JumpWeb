<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antelación MÍNIMA de reserva por producto (auditoría Fase 1): con cuánta antelación respecto a
 * HOY se puede reservar este producto. Es distinto de `available_after_open_min`/
 * `available_before_close_min` (esos son la ventana horaria DENTRO del día). El MÁXIMO ya lo da el
 * horizonte global de venta. 0 = sin restricción.
 *
 *  - `min_advance_unit = 'days'`  → días de CALENDARIO (la fecha de visita ≥ hoy + N; "el mismo día no").
 *  - `min_advance_unit = 'hours'` → RODANTE (el inicio de la franja ≥ ahora + N horas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_advance_value')->default(0)->after('available_before_close_min');
            $table->string('min_advance_unit', 8)->default('days')->after('min_advance_value');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['min_advance_value', 'min_advance_unit']);
        });
    }
};
