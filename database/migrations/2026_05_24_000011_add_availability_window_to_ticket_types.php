<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (transversal §9.2) — Ventana de disponibilidad por producto, con OFFSETS relativos
 * a la apertura/cierre del día (no horas absolutas, para que se adapten a cada horario):
 * - available_after_open_min / available_before_close_min: a partir/hasta cuánto del horario
 *   se puede comprar/entrar este producto (def. 0 = sin restricción → no cambia la Capa 1).
 * - prep_before_min / prep_after_min: SOLO packs (montaje/limpieza que bloquean tiempo y mesa).
 *   Se crean ya como estructura; su lógica de aforo por mesas entra en la Capa 2.
 * Valores reales [PENDIENTE], editables en el panel (Fase 7). Ver docs/PLAN-COMPRA-PRODUCTOS.md (§9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unsignedInteger('available_after_open_min')->default(0)->after('seats_per_unit');
            $table->unsignedInteger('available_before_close_min')->default(0)->after('available_after_open_min');
            $table->unsignedInteger('prep_before_min')->default(0)->after('available_before_close_min');
            $table->unsignedInteger('prep_after_min')->default(0)->after('prep_before_min');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn([
                'available_after_open_min', 'available_before_close_min',
                'prep_before_min', 'prep_after_min',
            ]);
        });
    }
};
