<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Una reseña copiada que Google enseñaba TRADUCIDA** (`DECISIONES #874`, del owner; el defecto lo midió el carril del
 * SPA sobre `#771`): la copia guarda la traducción de Google, no las palabras de su autor, y publicarla la pondría en su
 * boca. `translated` lo anota el importador (`scripts/resenas-google.mjs` ya lo trae) y una traducida NO SE PUBLICA nunca
 * —ni en las páginas ni en la Puerta—, aunque esté activa; el panel la señala.
 *
 * Aditiva y con su valor por defecto: las que ya están siguen igual hasta la siguiente importación, que las marca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('testimonials', 'translated')) {
            return;
        }

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->boolean('translated')->default(false)->after('reply');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('testimonials', 'translated')) {
            return;
        }

        Schema::table('testimonials', function (Blueprint $table): void {
            $table->dropColumn('translated');
        });
    }
};
