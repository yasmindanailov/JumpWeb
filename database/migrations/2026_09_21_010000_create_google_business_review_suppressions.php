<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA LISTA DE SUPRESIÓN DE RESEÑAS** (T2·5,
 * `docs/specs/google-business-profile.md` §4.3·7; `DECISIONES #524`, `#731`).
 *
 * Lo pidió la revisión de privacidad, y resuelve un problema que la tabla de reseñas **no puede**
 * resolver sola: si ocultar fuera solo borrar la fila, **la pasada de mañana la traería otra vez**.
 * La reseña sigue publicada en Google —nosotros no la podemos retirar de ahí— así que lo único que
 * se puede prometer es que no la volvemos a publicar nosotros, y eso necesita memoria.
 *
 * ❗❗❗ **Y por eso esta tabla guarda un HASH y nada más.** Es la única del sistema que sobrevive a la
 * purga de los 30 días, así que es la única donde un descuido duraría para siempre. Sin nombre, sin
 * texto, sin foto, sin identificador legible: con el `sha256` del nombre de recurso basta para
 * reconocer la misma reseña en la pasada siguiente, y no sirve para nada más.
 *
 * ⚠️ **Sin FK a `users`, a diferencia de la conexión**: quién ocultó y cuándo ya lo guarda
 * `audit_logs`, que es el sitio de la casa para eso. Repetirlo aquí crearía un segundo dueño de la
 * misma verdad en una tabla que no caduca.
 *
 * ⚠️ **El motivo es TASADO** (§4.3·7): petición del autor · menores · salud o terceros · otros. No es
 * un campo libre a propósito — un texto libre en una tabla que no caduca acaba con el nombre de
 * alguien dentro, escrito por quien no pensaba que eso era un dato personal.
 *
 * **RGPD**: `RGPD-01` — una petición de supresión de un cliente se comprueba **también** contra las
 * reseñas, y el procedimiento es éste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_business_review_suppressions', function (Blueprint $table) {
            $table->id();

            // `sha256` del nombre de recurso de la reseña (`accounts/…/locations/…/reviews/…`).
            // UNIQUE porque ocultar dos veces la misma reseña es la misma orden, no dos.
            $table->char('review_hash', 64)->unique();

            // El motivo tasado (`GoogleReviewSuppressionReason`).
            $table->string('reason', 32);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_business_review_suppressions');
    }
};
