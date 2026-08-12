<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (transversal §9.1) — Horario semanal del parque (recurrente). Define a qué
 * hora abre/cierra el parque cada día de la semana; las franjas a la venta solo existen
 * DENTRO de ese horario. Las excepciones puntuales (festivos, vísperas, cierres) viven
 * en `special_dates` y tienen prioridad. PLACEHOLDERS editables desde el panel (Fase 7);
 * horarios reales [PENDIENTE] del cliente. Ver docs/PLAN-COMPRA-PRODUCTOS.md (§9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique(); // 0=domingo..6=sábado (Carbon dayOfWeek)
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
