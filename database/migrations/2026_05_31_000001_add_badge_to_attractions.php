<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sub-badge corto y traducible por atracción (#4, 2026-05-31): p. ej. "XL",
     * "Mini", "Nuevo". Diferencia atracciones similares (un tobogán grande vs uno
     * pequeño). Opcional; null = sin badge. Editable en el panel (Fase 7).
     */
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->json('badge')->nullable()->after('age');
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
