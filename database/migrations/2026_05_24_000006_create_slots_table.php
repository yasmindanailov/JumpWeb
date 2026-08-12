<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Franja concreta (fecha + zona) contra la que se vende el aforo.
 * El aforo se cuenta por OCUPACIÓN: una entrada ocupa una plaza en cada franja
 * que abarca su duración (#60). NOTA: en docs/04 se llama `sessions`; en código es
 * `slots` para no colisionar con la tabla `sessions` de Laravel (#63).
 * Ver docs/04-MODELO-DATOS.md (§4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity');           // aforo total
            $table->unsignedInteger('online_capacity');    // cupo online (resto = puerta, #16)
            $table->boolean('online_sales_open')->default(true);
            $table->unsignedInteger('seats_taken')->default(0); // cache de display (verdad = pedidos)
            $table->string('status')->default('open');     // open | closed | full
            $table->timestamps();
            $table->unique(['zone_id', 'date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
