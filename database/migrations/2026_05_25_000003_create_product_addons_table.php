<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Capa 3, complementos) — Pivote producto↔complemento (#87): a qué entradas/packs
 * aplica cada complemento (`type=addon`). Configurable POR PRODUCTO desde el panel (Fase 7);
 * decidido data-driven en vez de un `applies_to` por tipo. Ambas columnas referencian
 * `ticket_types`: `product_id` = la entrada/pack base, `addon_id` = el complemento.
 * Ver docs/PLAN-COMPRA-PRODUCTOS.md (§3,§5E) y docs/DECISIONES.md (#87).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->unique(['product_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_addons');
    }
};
