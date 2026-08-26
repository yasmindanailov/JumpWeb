<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · waiver — `[DECIDIDO owner, 2026-08-26]` (`specs/waiver-probatorio.md` §7·5, `DECISIONES #179`):
 * **se exige correo VERIFICADO para firmar**. El alta con la casilla marcada ya no firma al crear la
 * cuenta —lo hacía en la misma transacción, con `email_verified_at = null` (revisión `#169` §10.2·3)—:
 * guarda AQUÍ la aceptación pendiente (qué texto aceptó, y por qué canal llegó) y la firma se registra
 * al VERIFICAR el correo, si ese texto sigue vigente. Dos columnas, y las dos son PII derivada del
 * alta: `anonymize()` las nulifica y el censo de `AnonymizeCoversEveryUserColumnTest` las declara.
 *
 * `waiver_pending_document_id` apunta a una versión INMUTABLE (nunca se borra): FK `RESTRICT`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('waiver_pending_document_id')
                ->nullable()
                ->after('waiver_accepted_at')
                ->constrained('legal_document_versions')
                ->restrictOnDelete();
            $table->string('waiver_pending_channel', 8)->nullable()->after('waiver_pending_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waiver_pending_document_id');
            $table->dropColumn('waiver_pending_channel');
        });
    }
};
