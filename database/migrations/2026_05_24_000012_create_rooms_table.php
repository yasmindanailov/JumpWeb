<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (transversal §9.3) — Mesas/salas para packs (cumpleaños). El aforo de los packs
 * NO va por las plazas de las entradas (`slots.online_capacity`), sino por un recurso físico
 * distinto: un pack ocupa UNA mesa durante su duración + su preparación. Esta tabla es la
 * ESTRUCTURA; la lógica de aforo por mesas (servicio + buffers de preparación) entra en la
 * Capa 2. Valores reales (nº de mesas, capacidades) [PENDIENTE]; editables en el panel (Fase 7).
 * Ver docs/PLAN-COMPRA-PRODUCTOS.md (§9.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->json('name');                              // traducible (HasTranslations)
            $table->unsignedInteger('capacity')->nullable();   // nº de personas que admite la mesa (opcional)
            $table->json('note')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
