<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Plantilla recurrente de franjas (el horario "tipo" de la semana),
 * por zona. De aquí se generan las franjas concretas (`slots`).
 * NOTA: en docs/04 se llama `session_templates`; en código es `slot_templates`
 * para no colisionar con la tabla `sessions` de Laravel (#63).
 * Ver docs/04-MODELO-DATOS.md (§4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');        // 0=domingo..6=sábado (Carbon dayOfWeek)
            $table->time('start_time');
            $table->unsignedInteger('duration_min');       // longitud de la franja (rejilla de aforo)
            $table->unsignedInteger('capacity');           // aforo total de la franja
            $table->unsignedInteger('online_capacity');    // cupo para venta online (resto = puerta)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_templates');
    }
};
