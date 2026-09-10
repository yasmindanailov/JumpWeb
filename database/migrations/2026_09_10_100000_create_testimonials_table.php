<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Las opiniones PROPIAS del parque** (`DECISIONES #490`, carril de diseño Fase 2 · T2i·a,
 * `specs/google-reviews.md` §4.4.bis).
 *
 * ❗❗❗ **Esto NO es un plan de emergencia por si Google falla, y llamarlo así es el error que la
 * spec advierte que hará que el parque las deje vacías.** Por §3.3 son lo que ve **todo visitante
 * que no acepta cookies de terceros, cada día** —sin consentimiento no se puede servir ni una
 * reseña de Google: la foto del autor es obligatoria (R3) y vive en su servidor (`RGPD-05`)—, y hoy
 * además son **lo único que la sección puede enseñar**: medido contra la API el 2026-09-10, el
 * parque tiene **una** reseña, por debajo del umbral de 10 que el owner fijó.
 *
 * ⚠️⚠️ **`published_at` es una FECHA y no el `meta` de texto libre que proponía §4.4.bis.** Aquella
 * columna venía del mockup, que pinta «hace 2 meses»; escrito a mano, ese texto **es verdad el día
 * que se teclea y mentira dos meses después**. La frase se DERIVA de la fecha, que es el hecho — la
 * misma disciplina con la que `#489` convirtió el aviso de la fecha especial en una frase derivada.
 *
 * ⚠️ **`rating` es del testimonio, no la cifra agregada.** La media («4,8 sobre 5 · N opiniones»)
 * **solo existe si viene de Google**: componerla con estas filas sería atribuirle a Google un número
 * que Google no ha dado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            // i18n como `faqs` y `park_rules`: el texto lo escribe el panel en los tres idiomas.
            $table->json('text');
            // El autor NO es i18n: un nombre propio no se traduce.
            $table->string('author', 120);
            // 1–5. Nullable a propósito: una opinión sin nota es una opinión, y un `default(5)`
            // afirmaría una nota que nadie ha puesto.
            $table->unsignedTinyInteger('rating')->nullable();
            // La fecha de la visita o de la opinión. Nullable: sin ella no se escribe la línea de
            // «hace N meses», que es mejor que inventarla.
            $table->date('published_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
