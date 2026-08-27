<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · menores a cargo, tanda 1 (`docs/specs/menores-a-cargo.md` §4.1, §4.2, §4.4, §4.6).
 *
 * `dependents`: las PERSONAS A CARGO que un titular declara —hoy, menores—, en Identity. Cuatro
 * columnas de dato y nada más (§4.2: «cada columna que se añada aquí es dato personal de un menor»):
 *  - `name`: la etiqueta con la que el titular lo distingue («el nombre que use en casa»); la
 *    puerta no la enseña nunca.
 *  - `born_on`: la fecha de nacimiento. ⚠️ La EDAD no existe como columna: se DERIVA
 *    (`Dependent::ageOn()`), porque cambia sola cada año y guardarla sería una mentira con caducidad.
 *  - `removed_at`: «quitar» es DESVINCULAR cuando hay un waiver firmado o una reserva detrás (§4.4,
 *    la misma tensión que `DELETE /me` → `anonymize()`); sin referencias la fila se borra de verdad
 *    y esta columna no llega a escribirse.
 *  - `user_id` es RESTRICT por la misma razón que `waiver_signatures.user_id` (waiver §8.6): una
 *    fila que tiene que sobrevivir a la supresión de la cuenta no cuelga de un CASCADE. La limpieza
 *    de go-live (`PurgeCustomerData`) la borra explícitamente ANTES que los usuarios.
 *
 * Sin FK desde `waiver_signatures.subject_id` todavía: llega con la primera firma de menor (tanda 2),
 * junto con la decisión NUC-3 de `DEUDA.md` que esa primera firma exige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->date('born_on');
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependents');
    }
};
