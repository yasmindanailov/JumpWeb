<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría 2026-05-26 (hallazgo C) — borrado RGPD compatible con AEAT.
 *
 * Cambiamos `orders.user_id` de cascadeOnDelete a RESTRICT: borrar un usuario con pedidos ahora
 * FALLA a nivel de BD. El flujo correcto (RGPD) es ANONIMIZAR la cuenta (pisar email/name/phone
 * con valores neutros y bloquear la contraseña), conservando la fila `users` para que los pedidos
 * sigan vinculados — la factura debe conservarse ≥4 años (AEAT).
 *
 * `DeleteAccount` se reescribe en consecuencia (M3.3). Si por error un admin futuro intenta hard
 * delete con pedidos, la BD lo bloqueará (defensa final).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
