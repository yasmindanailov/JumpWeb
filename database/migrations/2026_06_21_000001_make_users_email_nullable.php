<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email OPCIONAL para clientes dados de alta a mano desde el panel (reservas de agenda con SOLO
 * teléfono — #263). `users.email` pasa de NOT NULL a NULLABLE conservando el índice UNIQUE: en
 * MySQL/InnoDB un índice único permite MÚLTIPLES filas con `NULL` (NULL ≠ NULL), así que varios
 * clientes sin email conviven sin chocar, y el unique sigue protegiendo los emails REALES contra
 * duplicados (el email sigue siendo la identidad verificada de todo lo nuevo; el registro web NO
 * cambia y exige email).
 *
 * `->change()` solo modifica los atributos de la COLUMNA (tipo/nullable/default); NO toca el índice
 * `users_email_unique`, que es un objeto de esquema independiente y se conserva intacto.
 *
 * El alta sin email DEBE persistir `NULL`, nunca cadena vacía `''` (la cadena vacía sí colisionaría
 * con el unique y emparejaría a todos los sin-email en las búsquedas). Esa normalización vive en
 * `CustomerRegistrar`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revertir exige que no queden emails NULL (los clientes de agenda sin correo). En vez de dejar
        // que falle con un error SQL crudo, abortamos con un mensaje claro para que el operador decida
        // (asignar email o eliminar esas cuentas) antes de revertir.
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException(
                'No se puede revertir make_users_email_nullable: hay usuarios con email NULL '
                .'(clientes de agenda, #263). Asígnales un email o elimínalos antes de revertir.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
