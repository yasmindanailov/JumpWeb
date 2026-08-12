<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Capa 3c, #87) — Retira `event_packages`: el cumpleaños es ahora un producto `pack`
 * del catálogo unificado (`ticket_types`, #70). La home y `/cumpleanos` leen el pack; se elimina
 * el modelo `EventPackage` y su seeder. `down()` recrea la tabla (estructura original) por
 * reversibilidad. Usaba `price_cents` (columna propia), no la matriz `prices` → sin huérfanos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('event_packages');
    }

    public function down(): void
    {
        Schema::create('event_packages', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('features')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->json('price_unit')->nullable();
            $table->unsignedInteger('min_guests')->nullable();
            $table->unsignedInteger('max_guests')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
