<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA FOTO DE UN PRODUCTO** — la mitad que faltaba de la ficha del catálogo
 * (`docs/specs/instancia-y-landing-fuera.md` §4.1, T6 del menú de hechos; `DECISIONES #632` P1).
 *
 * La zona ya tenía `description` e `image`; el producto solo tenía `description`. Sin esta columna,
 * `#632` no se puede cumplir: «la app nativa (F6) no tiene landing y venderá con lo que dé la API;
 * sin esto habría que empaquetar material de cada cliente en su build».
 *
 * ⚠️⚠️ **Guarda la ruta DENTRO del disco `uploads`, no una ruta a `public/`**, y ahí está la
 * diferencia con `zones.image`, que nació antes (2026-06-11) apuntando a `public/images/...`. El
 * disco `uploads` es `public/uploads`, que está **gitignorado** y que el `rsync --delete` del
 * despliegue excluye a propósito: es el hueco de la instalación. Una foto escrita ahí la pone la
 * clienta desde el panel y NO entra en el repo del producto — que es justo lo que se estaba
 * haciendo mal (35 imágenes del cliente versionadas en `main`, medidas el 19-09).
 *
 * Por eso el modelo resuelve la URL con `asset('uploads/'.$image)` —el mismo `imageUrl()` que
 * `Offer` y `BarImage`— y la zona sigue con `asset($image)` hasta que sus ficheros se muden. Las
 * dos formas conviven a propósito y las dos salen por la API como URL absoluta: el contrato no
 * distingue, y el día que la zona se mude no cambia ni un byte de lo publicado.
 *
 * **Nullable y sin respaldo**: una instalación que no suba foto no tiene foto, y la API se calla el
 * campo en vez de publicar `""` (la receta del menú: lo que no se rellenó no viaja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            // 255 como el resto de rutas de subida de la casa (`offers.image`, `bar_images.image`).
            // Va detrás de `description` porque las dos son la ficha: quien lea el esquema las ve
            // juntas, que es como se editan en el panel.
            $table->string('image', 255)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
