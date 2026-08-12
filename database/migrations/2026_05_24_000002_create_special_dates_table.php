<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Excepciones de calendario: festivos, vísperas, cierres y horarios
 * especiales. Marca también qué tarifa aplica ese día (`rate_type_id`). La usa el
 * RateResolver (precio) y el generador de franjas (saltar días cerrados).
 * Ver docs/04-MODELO-DATOS.md (§1, §4bis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_closed')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->foreignId('rate_type_id')->nullable()->constrained()->nullOnDelete();
            $table->json('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_dates');
    }
};
