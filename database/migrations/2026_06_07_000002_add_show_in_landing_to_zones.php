<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.9 (adelanto) — Separar "operativa" de "visible en la landing" en las zonas.
 *
 * Hasta ahora `zones.is_active` hacía DOBLE función: la usaban tanto la operativa como el filtro
 * de la landing. La zona de cumpleaños estaba `is_active=false` SOLO para ocultarla de la landing,
 * aunque opera (vende packs). Se desacopla: `is_active` = la zona opera; `show_in_landing` = se
 * muestra en la landing.
 *
 * Backfill conservador: el valor que hasta ahora controlaba la landing (el viejo `is_active`) se
 * traslada al flag nuevo; después todas las zonas pasan a `is_active=true` (todas operan — la
 * única que estaba en false lo estaba por la landing, no por estar deshabilitada). No-op en BD
 * vacía (instalación nueva: el seeder fija los valores correctos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->boolean('show_in_landing')->default(true)->after('is_active');
        });

        // Traslada el significado-landing del viejo is_active al flag nuevo, y deja is_active
        // como "opera" (todas true). Orden importante: copiar antes de sobreescribir.
        DB::table('zones')->update(['show_in_landing' => DB::raw('is_active')]);
        DB::table('zones')->update(['is_active' => true]);
    }

    public function down(): void
    {
        // Reconstruye el viejo is_active (la zona se ocultaba de la landing con is_active=false).
        // NOTA: el rollback es con pérdida si existiera una zona is_active=false (no opera) Y
        // show_in_landing=true — los dos booleanos se colapsan en uno. No ocurre con los datos
        // sembrados (cumpleaños = is_active=true/show_in_landing=false; jump/kids = ambos true).
        DB::table('zones')->update(['is_active' => DB::raw('show_in_landing')]);

        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('show_in_landing');
        });
    }
};
