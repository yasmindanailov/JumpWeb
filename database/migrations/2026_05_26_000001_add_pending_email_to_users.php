<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría 2026-05-26 (hallazgo A) — cambio de email en "Mi cuenta" robusto.
 * El patrón estándar: el email viejo NO se sobrescribe hasta que el cliente confirma el nuevo
 * desde su buzón (enlace firmado en el correo). Esto bloquea el clásico vector de takeover
 * (atacante con sesión robada que cambia el email a uno propio para tomar la cuenta).
 *
 * `pending_email` (único, nullable) guarda el nuevo email solicitado. El viejo sigue vivo
 * (login, reset). Al verificar, se pisa `email` + `email_verified_at` y se limpian estos campos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->unique()->after('email');
            $table->timestamp('pending_email_sent_at')->nullable()->after('pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['pending_email']);
            $table->dropColumn(['pending_email', 'pending_email_sent_at']);
        });
    }
};
