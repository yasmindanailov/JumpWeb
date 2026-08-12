<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Tarifas por tipo de día (matriz configurable, #59).
 * `rate_types` define las tarifas (v1: `normal` y `special`); el precio concreto
 * de cada producto vive en `prices`. Un servicio (RateResolver) decide qué tarifa
 * aplica a una fecha. Ver docs/04-MODELO-DATOS.md (§4bis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();              // normal | special
            $table->json('label');                        // etiqueta legible (trad.)
            $table->boolean('is_special')->default(false);
            $table->json('weekdays')->nullable();         // días que la activan (0=domingo..6=sábado, Carbon dayOfWeek)
            $table->unsignedInteger('priority')->default(0); // mayor prioridad gana al resolver
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_types');
    }
};
