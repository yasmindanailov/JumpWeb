<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumpleaños MIXTO · la CONEXIÓN entre productos (`docs/specs/cumple-mixto.md` §9).
 *
 * El encargo del owner («si en un cumple KIDS hay un niño mayor, ese niño abona la diferencia con
 * JUMP») da por hecho algo que el sistema NO tenía: **una relación entre los dos productos**.
 * Medido el 2026-08-29: `ticket_types` no tiene ninguna columna que agrupe productos, ni tabla
 * pivote, ni convención de nombre. «Cumpleaños Kids» y «Cumpleaños Jump» son dos filas
 * independientes que solo comparten zona.
 *
 * `[DECIDIDO owner, 2026-08-29]` la conexión se declara como **familia + tramo de edad**, y no como
 * un puntero «este sube a aquel»:
 *
 *  - `guest_age_family` — slug libre; **los productos que comparten valor son alternativos entre
 *    sí**. Es lo único que los conecta, y por eso es también el interruptor: sin familia, nada de
 *    esto se enciende (una excursión de colegio no distingue edades).
 *  - `guest_age_min` / `guest_age_max` — el tramo que cubre este producto, **con los dos extremos
 *    INCLUIDOS** («de 1 a 6» es `1`–`6`: el de 6 entra, el de 7 no). Nulo = sin tope por ese lado.
 *
 * ▶ **Por qué un tramo y no un escalón.** Un puntero de mejora solo sabe subir un peldaño; con un
 * tercer régimen (adolescentes, premium) no hay forma de decir qué edad va a cuál. Con tramos, el
 * motor busca a qué producto de la familia le toca CADA invitado por su edad, y sirve igual para
 * dos que para N. Y no nombra a ningún cliente: es white-label.
 *
 * ⚠️ **Solo tienen sentido en un PACK.** El veredicto se deriva de las edades declaradas en el
 * post-form, que solo existe en los packs (`guest_fields`); en una entrada la config sería letra
 * muerta. Las columnas viven en `ticket_types` porque es donde vive el producto, pero el formulario
 * las ofrece únicamente en la sección del pack, como `min_qty`.
 *
 * ⚠️ **`unsignedTinyInteger`**: una edad no pasa de 255 por construcción, y el saneo del post-form
 * la acota además a `TicketType::GUEST_AGE_MAX` — de este dato sale un cobro (§8.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->string('guest_age_family', 40)->nullable()->after('guest_fields');
            $table->unsignedTinyInteger('guest_age_min')->nullable()->after('guest_age_family');
            $table->unsignedTinyInteger('guest_age_max')->nullable()->after('guest_age_min');

            // La consulta caliente del veredicto es «dame los hermanos de esta familia»: sin índice
            // sería un barrido del catálogo por cada reserva que se pinta.
            $table->index('guest_age_family');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropIndex(['guest_age_family']);
            $table->dropColumn(['guest_age_family', 'guest_age_min', 'guest_age_max']);
        });
    }
};
