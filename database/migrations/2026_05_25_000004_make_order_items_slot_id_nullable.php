<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Capa 3b) — Los complementos (#87) no tienen franja. `order_items.slot_id` pasa a
 * NULLABLE: el `order_items` unificado lleva entradas/packs (con franja) y addons (sin ella).
 * La FK a `slots` (cascade) se conserva; solo cambia la nulabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('slot_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('slot_id')->nullable(false)->change();
        });
    }
};
