<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **El SEGUNDO color de una zona, que hasta ahora vivía en el CSS** (`DECISIONES #138`).
 *
 * La paleta de una zona son DOS colores —en el cliente origen, naranja + amarillo para Jump y lima +
 * rosa para Kids— y solo el primero tenía casa en la BD (`zones.color`). El segundo estaba escrito a
 * mano en `public/css/landing.css` como `--jump-2` / `--kids-2`, así que **una instalación nueva
 * heredaba los colores del primer cliente** en todo lo que lo consume: la insignia de la tarjeta
 * destacada de precios, la decoración de la invitación y el trazo del CTA.
 *
 * ⚠️ **NULLABLE, y el vacío significa «usa el primario»**, no «usa el amarillo de Jump». Una zona sin
 * segundo color se pinta plana —correcta— en vez de pedir prestado el acento de otra marca, que es lo
 * que hacía que dos zonas con el mismo `accent` y distinto `color` divergieran según la superficie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->string('color_secondary', 7)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            $table->dropColumn('color_secondary');
        });
    }
};
