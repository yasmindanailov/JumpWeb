<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.7 iter.3 — Unicidad de plantilla por (zona, día de la semana, hora de inicio).
 *
 * La tabla nació sin esta restricción; el seeder ya la respetaba con `updateOrCreate`, y el
 * editor de plantillas del panel la valida en app. Este índice la blinda a nivel de BD para
 * que dos plantillas no compitan por la misma franja generada. Verificado sin duplicados antes
 * de aplicarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot_templates', function (Blueprint $table) {
            $table->unique(['zone_id', 'weekday', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::table('slot_templates', function (Blueprint $table) {
            // El índice único empieza por `zone_id`, así que MySQL lo usa para la FK: hay que
            // soltar la FK antes de poder borrar el índice, y recrearla después (regenera su
            // propio índice de columna). En SQLite (tests) no se ejecuta down().
            $table->dropForeign(['zone_id']);
            $table->dropUnique(['zone_id', 'weekday', 'start_time']);
            $table->foreign('zone_id')->references('id')->on('zones')->cascadeOnDelete();
        });
    }
};
