<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.7 — Temporadas: tramos de fechas con un horario propio que SUSTITUYE al horario
 * semanal base (`opening_hours`) mientras están vigentes (p. ej. «Horario de verano»: del
 * 1-jul al 31-ago el parque abre todos los días 11:00–22:00).
 *
 * Orden de resolución del horario efectivo (`App\Support\ParkSchedule`):
 *   fecha especial (día concreto) → TEMPORADA (rango activo) → horario semanal.
 *
 * Una temporada activa abre TODOS los días de su rango con su `open_time`/`close_time`
 * (caso de uso: "en verano abrimos todos los días"). Decisión #207.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // etiqueta del operador (p. ej. "Horario de verano")
            $table->date('start_date');
            $table->date('end_date');
            $table->time('open_time');
            $table->time('close_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
