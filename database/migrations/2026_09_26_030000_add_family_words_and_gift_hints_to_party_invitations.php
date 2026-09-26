<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1a de `specs/fiesta-sistema-nuevo.md` (§1.4, `#743`): «unas palabras de la familia» y «pistas para el regalo», los
 * dos textos que el anfitrión escribe en «Personalizar» y que la tarjeta de la invitación pinta (la burbuja con su
 * inicial y la línea del regalo). Texto LIBRE que se publica bajo el dominio del parque: pasa por `PublicFreeText`
 * como `honoree_name` y `host_line` (§7.2·R9), y el tope de 90 es el del diseño (`Field maxLength={90}`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_invitations', function (Blueprint $table): void {
            $table->string('family_words', 90)->nullable()->after('host_line');
            $table->string('gift_hints', 90)->nullable()->after('family_words');
        });
    }

    public function down(): void
    {
        Schema::table('party_invitations', function (Blueprint $table): void {
            $table->dropColumn(['family_words', 'gift_hints']);
        });
    }
};
