<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LAS IMÁGENES DEL BAR** (`DECISIONES #536`, carril de diseño Fase 3 · T3b, artboard `Bar PJP`).
 *
 * ❗❗❗ **LA CARTA SE PUBLICA COMO IMAGEN, NO COMO TEXTO** (`[DECIDIDO owner, 2026-09-12]`). El
 * artboard dibuja la carta plato a plato —dos grupos, tabla con el precio en mono— y eso obligaría
 * al parque a teclear y mantener cada plato y cada precio en tres idiomas. El owner decide subir la
 * foto de su carta, que es lo que ya tiene impreso y lo único que va a mantener de verdad.
 *
 * ⚠️⚠️ **Y eso tiene un coste que hay que saber, porque no se puede arreglar desde el panel**: el
 * texto dentro de una imagen **no lo lee un lector de pantalla, no se traduce, no se indexa y no
 * escala**. Por eso `alt` es OBLIGATORIO —es lo único que un lector de pantalla va a encontrar—, la
 * carta admite VARIAS imágenes (una por cara, sin montajes a mano) y la página deja ampliarlas.
 *
 * ▶ **Una sola tabla para las dos cosas, con `kind`**, porque el owner pidió **un solo sitio** en el
 * panel para las imágenes del bar:
 *   · `menu`  — las caras de la carta. Varias, ordenadas, se activan y desactivan.
 *   · `venue` — la foto del local (las mesas con el parque detrás, 16:9 en el artboard).
 *
 * ⚠️ **La foto del local se publica de UNA en UNA**: si hay varias activas manda la primera por
 * `position`, que es la misma resolución que `#479` le dio a dos tarifas destacadas. No se impone
 * en el esquema: una restricción de unicidad aquí obligaría a borrar la vieja ANTES de subir la
 * nueva, que es justo cuando un parque se queda sin foto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bar_images', function (Blueprint $table) {
            $table->id();
            // `menu` | `venue`. String y no enum: la lista vive en el modelo, que es donde se lee
            // (el precedente de `park_rules.moment`, `#533`).
            $table->string('kind', 16);
            // Ruta relativa dentro del disco `uploads`, como `offers.image`.
            $table->string('image');
            // i18n, como `faqs` y `testimonials`. ⚠️ **Obligatorio en el formulario**, no en el
            // esquema: una fila metida por migración o por SQL no debe reventar, pero nadie puede
            // subir una imagen desde el panel sin decir qué se ve en ella.
            $table->json('alt');
            /*
             * ⚠️ **Las dimensiones REALES del fichero, medidas al guardar.** Sin `width`/`height` en
             * el `<img>` el navegador no puede reservar el hueco y la página SALTA al cargar la
             * imagen — y aquí eso es grave: la carta es la imagen más grande de la web y ocupa la
             * columna entera. El repo lo tiene fichado como hueco general desde `#314` («55
             * imágenes, 51 perezosas y ninguna declara proporción»); esta pieza no lo hereda.
             * ⚠️ Nullable: si `getimagesize()` no puede leer el fichero, la fila se guarda igual y el
             * `<img>` sale sin dimensiones —peor maquetación, no un error.
             */
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Lo que pregunta la página: «dame las de este tipo, activas, en orden».
            $table->index(['kind', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_images');
    }
};
