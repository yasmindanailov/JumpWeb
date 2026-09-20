<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LAS RESEÑAS DE LA FICHA, Y SU RESUMEN** (T2·1,
 * `docs/specs/google-business-profile.md` §4.3·2 → §4.3·6; `DECISIONES #524`, `#727`).
 *
 * Lo que la política de Business Profile permite guardar es *«limited amounts of Content»* **hasta 30
 * días**, *«stored securely»* y *«cannot be manipulated or aggregated»* (§1.3). De ahí sale la forma de
 * estas dos tablas, y de ahí sale también lo que NO tienen.
 *
 * ⚠️⚠️ **NO es un espejo de la ficha: solo caben las CANDIDATAS** (§4.3·2). La pasada recorre todas las
 * páginas en memoria y persiste una docena de filas —las que tienen texto, llegan al mínimo de
 * estrellas y no están ocultas—, más el resumen. Del resto no queda nada. Una tabla que guardara las
 * 300 reseñas del parque *«para tenerlas»* sería justo lo que la política llama agregar, y ninguna de
 * las 294 que sobran se llegaría a pintar.
 *
 * ⚠️⚠️ **DOS tablas y no una, aunque el resumen sea una sola fila**: `total_review_count` y
 * `average_rating` se cuentan sobre TODAS las reseñas y las filas de aquí son solo las candidatas. Con
 * la media metida en cada fila, la portada tendría dos sitios de donde sacarla y el día que el filtro
 * de estrellas dejara la tabla vacía **la cifra se iría con ellas** — y §4.3·10 dice lo contrario: *«la
 * media y el total nunca se filtran»*.
 *
 * ⚠️ **El resumen repite `maps_uri` y `new_review_uri`, que ya están en `google_business_connections`**,
 * y es a propósito: la portada no debe tocar la fila de la conexión, que lleva el **token de refresco
 * cifrado**. Leerla para pintar dos enlaces pasearía la credencial del parque por la memoria de cada
 * visita a `/` (`PERF-02` y §4.2·5). Aquí se copian al sincronizar y se leen sin secreto al lado.
 *
 * **RGPD — tratamiento NUEVO** (§4.3·11, interés legítimo, arts. 6.1.f, 14 y 21): `author_name` y
 * `author_photo_path` son datos personales de un TERCERO que nunca ha tratado con el parque. Por eso:
 *
 *   · el plazo no se confía a la purga, sino que **se aplica AL LEER** (§4.3·4) — `fetched_at` está
 *     indexado porque es la columna del plazo, no porque ordene nada;
 *   · **anónima no se guarda con nombre** (§4.3·5): el `null` entra antes del INSERT, no al pintar;
 *   · **la foto es una ruta NUESTRA o `null`, jamás un host de Google** (§4.3·6 y §4.3·9), y eso tiene
 *     guarda propia: una URL de tercero aquí volvería a hacer que la sección dependa del
 *     consentimiento de `maps`, que es exactamente lo que esta tanda quita (`RGPD-05`);
 *   · **prohibido cruzar esta tabla con clientes o pedidos** (§4.3·11): no hay ni una FK, y no es un
 *     descuido — es la forma de que el cruce no se pueda escribir por accidente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_business_reviews', function (Blueprint $table) {
            $table->id();

            // El nombre de RECURSO de la reseña en Google
            // (`accounts/{a}/locations/{l}/reviews/{r}`). Es la identidad con la que §4.3·3 deduplica
            // y la que sobrevive a una re-sincronización, así que el UNIQUE es la regla, no un índice
            // de rendimiento: sin él, una pasada que se solape con otra duplicaría la misma reseña.
            $table->string('review_name')->unique();

            // El autor. `null` es un valor NORMAL y significa anónima (§4.3·5): Google manda
            // `isAnonymous`, y el nombre se tira ANTES de guardar.
            $table->string('author_name')->nullable();
            // ⚠️ RUTA en nuestro disco privado, nunca una URL. La rellena la T2·4; hasta entonces
            // siempre `null` y la tarjeta pinta la inicial.
            $table->string('author_photo_path')->nullable();
            $table->boolean('anonymous')->default(false);

            // 1–5. Google lo manda como TEXTO (`FIVE`, `FOUR`…) y se traduce al entrar: la escala vive
            // en `Content\Contracts\Rating::MAX` y una columna de texto obligaría a cada lector a
            // re-decidir el orden de «ONE» contra «TWO».
            $table->unsignedTinyInteger('star_rating');

            // El texto que se publica. Es el ORIGINAL del autor (§4.3·8): cuando Google mezcla su
            // traducción, el analizador separa y se queda con el original; si las marcas son
            // ambiguas **falla cerrado**, guarda el crudo y lo dice en `text_ambiguous`.
            $table->text('comment');
            $table->boolean('text_ambiguous')->default(false);

            // La respuesta del parque, que §4.3·10 pinta debajo de la reseña. No es dato de tercero:
            // lo escribe el propio parque en su ficha.
            $table->text('reply_comment')->nullable();
            $table->timestamp('reply_at')->nullable();

            // Las fotos de la reseña (`reviewMediaItems`), ya descargadas: una lista de RUTAS
            // nuestras. La rellena la T2·4. Los vídeos no se traen (§4.3·6, pendiente del owner).
            $table->json('photos')->nullable();

            // Las fechas que da Google. `review_created_at` es por la que §4.3·10 ordena («las 6 más
            // recientes») y de la que sale el «hace 2 meses»; se guarda la fecha y no el rótulo, que
            // se deriva al pintar y depende del idioma de la página.
            $table->timestamp('review_created_at');
            $table->timestamp('review_updated_at')->nullable();

            // ⚠️⚠️ **La columna del PLAZO.** Se renueva en toda fila que devuelva una pasada, cambie o
            // no (§4.3·3), así que es «cuándo se vio por última vez en la ficha» y no «cuándo se
            // insertó». Indexada porque la filtra CADA lectura de la portada, no solo la purga.
            $table->timestamp('fetched_at')->index();

            $table->timestamps();
        });

        Schema::create('google_business_review_summaries', function (Blueprint $table) {
            $table->id();

            // El mismo candado de fila única que `google_business_connections`: una instalación es un
            // parque y un parque es una ficha. Como invariante de BD, no como costumbre.
            $table->boolean('singleton')->default(true);
            $table->unique('singleton');

            // `null` mientras no haya habido una pasada coherente. Nunca se compone a partir de las
            // candidatas: sería atribuirle a Google un número que Google no ha dado.
            $table->decimal('average_rating', 2, 1)->nullable();
            $table->unsignedInteger('total_review_count')->default(0);

            // Copiados de la ficha al sincronizar, para que la portada no toque la conexión.
            $table->string('maps_uri')->nullable();
            $table->string('new_review_uri')->nullable();

            // Cuándo terminó la última pasada COHERENTE (§4.3·3). `null` = no ha habido ninguna, que
            // es distinto de «la última salió vacía».
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_business_review_summaries');
        Schema::dropIfExists('google_business_reviews');
    }
};
