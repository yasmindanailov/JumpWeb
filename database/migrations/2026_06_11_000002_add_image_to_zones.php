<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imagen de zona (feature de la clienta, 2026-06-11) — una zona puede llevar una FOTO,
 * editable desde el panel igual que la de una atracción (ruta relativa a `public/`). La
 * landing pinta la card de la zona con la foto integrada (patrón «Foto integrada en la
 * tarjeta» del mockup `design_mockup/Ejemplos Imagenes Secciones.html`); sin imagen, la
 * card cae al diseño actual (fallback). Espeja `attractions.image`.
 *
 * Columna nullable (la mayoría de zonas pueden no tener foto → fallback). Seed idempotente
 * de las fotos reales de jump/kids para que las instalaciones EXISTENTES las muestren sin
 * re-sembrar; `whereNull` no pisa una ruta ya configurada desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->string('image', 255)->nullable()->after('rides_count');
        });

        // Fotos reales de zona (mismas rutas que el seeder). Solo si está null: no pisa una
        // ruta ya puesta a mano en el panel. En una instalación nueva la tabla aún está vacía
        // (el seeder corre después) → 0 filas; el seeder las crea con la imagen ya incluida.
        DB::table('zones')->where('slug', 'jump')->whereNull('image')
            ->update(['image' => 'images/attractions/park_jump.webp']);
        DB::table('zones')->where('slug', 'kids')->whereNull('image')
            ->update(['image' => 'images/attractions/kids_zone.webp']);
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
