<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **El VÍDEO de una atracción** (el owner, 25-09: «falta el botón de play para los vídeos de las atracciones»).
 *
 * El diseño pone el triángulo de «play» SOLO sobre un vídeo de verdad (`ClipTile.jsx`: «un triángulo sobre una foto
 * promete un vídeo que no existe»), así que el play llega con el dato: un plano vertical corto que el operador SUBE en
 * el panel. Es una ruta dentro del disco `uploads` (`public/uploads`, gitignorado y fuera del `rsync --delete`), como
 * la foto de la ficha del producto: lo que sube la instalación no entra en el repo. `null` = sin vídeo, y la web pinta
 * la foto sin play. Idempotente, como las demás de esta banda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attractions', 'video')) {
            return;
        }

        Schema::table('attractions', function (Blueprint $table): void {
            $table->string('video')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('attractions', 'video')) {
            return;
        }

        Schema::table('attractions', function (Blueprint $table): void {
            $table->dropColumn('video');
        });
    }
};
