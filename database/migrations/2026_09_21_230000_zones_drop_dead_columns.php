<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **`zones` ADELGAZA: fuera las cuatro columnas que no lee nadie** (F5 · T4,
 * `docs/specs/instancia-y-landing-fuera.md` §1.7 y §4.3; `DECISIONES #639`·D2, `#669`).
 *
 * El censo del 19-09 midió las **26 columnas** de la tabla y separó tres familias: los HECHOS (slug,
 * nombre, alturas, edad, descripción), la PRESENTACIÓN (color, acento, imagen) y la OPERACIÓN
 * (horarios y aforo). Estas cuatro no eran ninguna de las tres: **nadie las lee fuera del formulario
 * del panel**, ni la landing, ni la API, ni el cajón, ni un correo.
 *
 * ❗❗ **El criterio del owner, y es el que hace que esto no sea limpieza**: *un campo que el panel
 * pide y que no sale a ningún sitio se vuelve a rellenar creyendo que sirve*. El coste no es la
 * columna: es el rato que alguien dedica a escribir un subtítulo en tres idiomas que no se publica.
 *
 * ⚠️⚠️ **SE PIERDEN LOS DATOS, y eso es parte de la decisión.** En la instalación de referencia hay
 * subtítulos traducidos, etiquetas de edad, metros cuadrados (5.000 y 2.000) y un recuento de
 * atracciones (15 y 8). No se migran a ningún sitio porque no hay sitio al que migrarlos: no se
 * publican.
 *
 * ❗ **Y `rides_count` merece su párrafo**: medido el 21-09, coincide con el recuento REAL de
 * atracciones activas (15=15, 8=8). O sea que hoy no miente. Pero es un dato **duplicado que alguien
 * mantiene a mano** mientras el producto ya lo calcula donde lo necesita (`ridesTotal`, y la misma
 * cuenta en `AttractionsController`). *Un contador copiado no está mal el día que se copia: está mal
 * el día que alguien añade una atracción y nadie se acuerda de subirlo.*
 *
 * ⚠️ **El `down()` las recrea VACÍAS**, y no puede hacer otra cosa. Revertir esta migración devuelve
 * la forma de la tabla, nunca su contenido: lo que se escribió en ellas se fue con el `drop`.
 */
return new class extends Migration
{
    /** Las cuatro, con lo que eran. */
    private const MUERTAS = [
        'subtitle',     // texto traducible bajo el nombre de la zona; ninguna superficie lo pinta
        'age_label',    // rótulo de la edad; la landing usa `age_range`, que sí es hecho
        'area_sqm',     // metros cuadrados; la tira de cifras que los enseñaba se fue en `#302`
        'rides_count',  // recuento a mano; el producto cuenta las atracciones activas de verdad
    ];

    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            // ⚠️ Se comprueba una a una: una instalación que ya no las tenga —o un `down()` a
            // medias— dejaría la migración reventando en el despliegue, que es el peor sitio.
            foreach (self::MUERTAS as $columna) {
                if (Schema::hasColumn('zones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            if (! Schema::hasColumn('zones', 'subtitle')) {
                $table->json('subtitle')->nullable()->after('name');
            }

            if (! Schema::hasColumn('zones', 'age_label')) {
                $table->json('age_label')->nullable()->after('age_range');
            }

            if (! Schema::hasColumn('zones', 'area_sqm')) {
                $table->unsignedInteger('area_sqm')->nullable();
            }

            if (! Schema::hasColumn('zones', 'rides_count')) {
                $table->unsignedInteger('rides_count')->nullable();
            }
        });
    }
};
