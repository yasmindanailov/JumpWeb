<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.2c iteración cosmética (decisión #148) — color de zona aplicable a
 * sub-cards de OrderItem en el panel admin (y al calendario futuro). Reusa
 * los colores oficiales de marca ya establecidos en `public/css/landing.css`:
 *
 *  - JUMP → `--jump-1: #FF5B22` (naranja)
 *  - KIDS → `--kids-1: #C6FF3A` (lima)
 *
 * La columna es nullable: zonas sin color (futuras zonas no asignadas a
 * paleta) caerán al fallback gris (#9CA3AF) en el render del blade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('accent');
        });

        // Seed idempotente — solo si está null (no pisa colores ya configurados).
        DB::table('zones')->where('slug', 'jump')->whereNull('color')->update(['color' => '#FF5B22']);
        DB::table('zones')->where('slug', 'kids')->whereNull('color')->update(['color' => '#C6FF3A']);
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
