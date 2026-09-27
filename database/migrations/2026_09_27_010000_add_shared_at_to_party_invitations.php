<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA INVITACIÓN SABE QUE SALIÓ (`specs/fiesta-sistema-nuevo.md` §4.14, `[DECIDIDO owner]` `#753`): el primer envío o
 * recordatorio. Hasta F8 la lista lo DEDUCÍA de las respuestas o de un recordatorio —los enlaces de WhatsApp no dejaban
 * rastro— y a quien la había enviado sin respuestas todavía le seguía ofreciendo enviarla como si no lo hubiera hecho.
 *
 * Solo el PRIMERO: cuántas veces y por qué canal son hechos de la reserva (`invitation_shared`), no estado de la fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_invitations', function (Blueprint $table): void {
            $table->timestamp('shared_at')->nullable()->after('reminded_count');
        });
    }

    public function down(): void
    {
        Schema::table('party_invitations', function (Blueprint $table): void {
            $table->dropColumn('shared_at');
        });
    }
};
