<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de tarifas de grupo (INFORMATIVA) para una sección de /servicios — caso «Excursiones de
 * colegio» (#256 + petición clienta 2026-06-16).
 *
 * Es SOLO PRESENTACIÓN: NO toca el flujo de compra ni el `PLAN-OFERTAS-CANTIDAD` (precio por tramos
 * en el dinero). Un JSON estructurado (zonas → duraciones → tramos de cantidad con precio L–V /
 * finde, en CÉNTIMOS) que el blade pinta como tablas con pestañas de zona. NULL = la sección no
 * muestra tabla de tarifas (el caso por defecto del resto de servicios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_services', function (Blueprint $table) {
            $table->json('price_table')->nullable()->after('specs');
        });
    }

    public function down(): void
    {
        Schema::table('landing_services', function (Blueprint $table) {
            $table->dropColumn('price_table');
        });
    }
};
