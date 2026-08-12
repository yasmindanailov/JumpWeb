<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (pulido visual) — Foto de la atracción (ruta relativa a `public/`). Data-driven:
 * editable desde el panel (Fase 7). Valores PLACEHOLDER (fotos optimizadas del parque) para
 * poder visualizar la landing; las definitivas las aportará el cliente `[PENDIENTE]`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->string('image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
