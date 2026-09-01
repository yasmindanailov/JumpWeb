<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado a una reserva — «waiver offshore»
 * (`docs/specs/waiver-por-reserva.md` §4.2, §4.3; tanda T1).
 *
 * Un adulto SIN cuenta autoriza por escrito la entrada de un menor que **no es menor a cargo** de
 * quien reservó. La prueba sigue siendo `waiver_signatures` —una cadena, un verificador, un PDF, una
 * poda—; lo que nace aquí es la PERSONA y su ancla al pedido.
 *
 * ▶ **Por qué una tabla y no una columna en `dependents`** (§1.3): sus ~14 lectores suponen «una
 * ficha = una persona permanente de esta cuenta» y todos cambiarían de significado sin que fallara
 * nada. Es la lección que `#324` pagó con `prices`.
 *
 * ▶ **Por qué una columna de sujeto PROPIA y no reutilizar `subject_id`** (§1.4·b): `subject_id`
 * tiene FK dura a `dependents` —medido: un id ajeno da `1452`, y SQLite la ejerce igual—, y quitarla
 * regresaría un endurecimiento deliberado (`menores-a-cargo.md` §4.4).
 *
 * Las tres claves foráneas, cada una con su política y su razón:
 *  - **`order_id` → `orders`, RESTRICT**: la prueba de que un adulto autorizó una entrada que ocurrió
 *    no cuelga de un CASCADE. ⚠️ Eso OBLIGA a `PurgeCustomerData` a borrar en tres pasos (§4.2.1):
 *    la limpieza de go-live borra TODOS los pedidos, así que tiene que llevarse antes estas firmas y
 *    estas filas —incluidas las de cuentas que conserva—. Con CASCADE tampoco valdría: la FK de la
 *    firma es RESTRICT y el cascade se estrellaría un peldaño más allá.
 *  - **`waiver_signatures.subject_authorization_id` → aquí, RESTRICT**: misma doctrina que
 *    `subject_id → dependents`. La persona no se borra mientras su prueba exista.
 *
 * Y **`subject_name` pasa de 120 a 255**: `WaiverSigner` ya cortaba a 255 con un comentario que decía
 * «se corta a lo que admite la columna» y la columna eran 120 — medido, un menor a cargo de 120+120
 * da `SQLSTATE[22001] 1406 Data too long` en MySQL **y pasa en verde en SQLite**. Es un defecto VIVO
 * desde `#198`, no de esta feature (`DEUDA.md`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_authorizations', function (Blueprint $table) {
            $table->id();

            // Identity referencia el pedido por su id ENTERO: Booking no puede mirar a Identity, así
            // que la flecha va al revés y por contrato. Mismo patrón que `dependent_assignments` —
            // salvo la política de borrado, que aquí es la de una prueba.
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();

            // El MENOR. Apellidos en columna aparte (`#236`): quien lee la prueba no conoce a la familia.
            $table->string('minor_name', 120);
            $table->string('minor_surname', 120);
            // La clave de unicidad REAL (§4.8). Se calcula en PHP —minúsculas, sin tildes, espacios
            // colapsados— porque el `UNIQUE` sobre los nombres crudos NO es determinista entre motores:
            // `utf8mb4_unicode_ci` iguala «Perez» y «Pérez» y SQLite —donde corre la suite— no. Una
            // guarda que dependa de la colación mide una cosa en el test y otra en producción.
            $table->string('minor_key', 255);
            $table->date('minor_born_on');

            // El ADULTO que firma. `relationship` es lo que sostiene que pueda hacerlo, y por eso es
            // obligatorio; sale de la lista CERRADA `Dependent::RELATIONSHIPS` (máx. 14 caracteres).
            $table->string('guardian_name', 120);
            $table->string('guardian_surname', 120);
            $table->string('guardian_relationship', 16);
            $table->string('guardian_email', 255)->nullable();
            $table->string('guardian_phone', 32)->nullable();

            // Sin `updated_at`: la fila no se edita. Una corrección es una autorización nueva.
            $table->timestamp('created_at')->nullable();

            // «Un niño, un papel» (`[DECIDIDO owner]` §7·9): el segundo progenitor del mismo menor ve
            // que ya está firmado y no se escribe nada.
            $table->unique(['order_id', 'minor_key']);
            $table->index('order_id');
        });

        Schema::table('waiver_signatures', function (Blueprint $table) {
            // El SUJETO nuevo. Nullable porque las otras dos clases no lo tienen.
            $table->unsignedBigInteger('subject_authorization_id')->nullable()->after('subject_id');
            $table->foreign('subject_authorization_id')
                ->references('id')->on('guardian_authorizations')->restrictOnDelete();

            // La identidad del FIRMANTE tal y como estaba al firmar, dentro del hash (esquema v4).
            // `holder_*` sigue siendo el RESPONSABLE (quien reservó) y `subject_*` el MENOR: las tres
            // personas de esta prueba viajan en la propia fila.
            //
            // ⚠️ NO existe `signer_user_id` a propósito: una columna con `ON DELETE SET NULL` no puede
            // estar dentro de un hash que se verifica, y este repo lo tiene MEDIDO —borrar al operador
            // de una firma declarada pone `verifyHash()` en `false` sin que nadie la toque (§10)—.
            $table->string('signer_name', 255)->nullable()->after('subject_born_on');
            $table->string('signer_email', 255)->nullable()->after('signer_name');
            $table->string('signer_phone', 32)->nullable()->after('signer_email');
            $table->string('signer_relationship', 16)->nullable()->after('signer_phone');
        });

        // En su propio `Schema::table`: en SQLite un `change()` reconstruye la tabla, y no conviene
        // mezclarlo con la creación de una clave foránea en la misma pasada.
        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->string('subject_name', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->dropForeign(['subject_authorization_id']);
            $table->dropColumn(['subject_authorization_id', 'signer_name', 'signer_email', 'signer_phone', 'signer_relationship']);
        });

        Schema::table('waiver_signatures', function (Blueprint $table) {
            $table->string('subject_name', 120)->nullable()->change();
        });

        Schema::dropIfExists('guardian_authorizations');
    }
};
