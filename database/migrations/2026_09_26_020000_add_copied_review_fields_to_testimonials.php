<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Las reseñas COPIADAS de la ficha de Google, como opiniones del panel** (`DECISIONES #771`, el owner 26-09: «las
 * reseñas son mías, yo administro esa empresa»; corrige `#616` en esto). Mientras Google no aprueba la conexión con
 * el Perfil de Empresa, el parque copia las de SU ficha y elige cuáles salen en cada página.
 *
 *  · `origin`: `own` (escrita en el panel, lo de siempre) o `google` (copiada de la ficha: sale con la marca de Google
 *    y el enlace al original, que es lo que el diseño pide de una reseña).
 *  · `source_ref`: el id de la reseña en Google, ÚNICO: reimportar actualiza en vez de duplicar.
 *  · `source_url`: adónde lleva «Ver en Google» (la ficha). `author_meta`: la línea del autor («Local Guide · 12
 *    reseñas»). `reply`: la respuesta del propietario, tal cual.
 *  · `avatar` y `photos`: rutas del disco `uploads` (el hueco de la instalación, fuera del repo): las imágenes se
 *    DESCARGAN al importar y se sirven desde nuestro servidor —el visitante no le pide nada a Google, así que no hace
 *    falta su consentimiento—.
 *  · `tags`: en qué páginas sale («kids», «jump», «cumpleanos»…). Vacío = en ninguna página nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('testimonials', 'origin')) {
            return;
        }

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->string('origin', 16)->default('own')->after('id');
            $table->string('source_ref', 191)->nullable()->unique()->after('origin');
            $table->string('source_url', 500)->nullable()->after('source_ref');
            $table->string('author_meta', 160)->nullable()->after('author');
            $table->string('avatar')->nullable()->after('author_meta');
            $table->json('photos')->nullable()->after('text');
            $table->text('reply')->nullable()->after('photos');
            $table->json('tags')->nullable()->after('reply');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('testimonials', 'origin')) {
            return;
        }

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->dropUnique(['source_ref']);
            $table->dropColumn(['origin', 'source_ref', 'source_url', 'author_meta', 'avatar', 'photos', 'reply', 'tags']);
        });
    }
};
