<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · waiver con valor probatorio (`docs/specs/waiver-probatorio.md` §4.2, §4.3, §8.5, §8.6).
 *
 * Dos tablas INMUTABLES, en Identity:
 *  - `legal_document_versions`: una fila por (documento, idioma, versión). Publicar CREA fila; nada
 *    la edita ni la borra (guarda de modelo + test). El idioma es parte de la prueba (§4.2): lo que
 *    se conserva es el texto en el idioma en que se le ENSEÑÓ a la persona, ya interpolado.
 *  - `waiver_signatures`: el registro de firma, append-only, con hash canónico y `prev_hash`
 *    encadenado POR TITULAR (§8.5). `user_id` es RESTRICT a propósito (§8.6): una tabla cuyo
 *    propósito es sobrevivir al titular no cuelga de un CASCADE. `document_hash` copia el hash de
 *    la versión firmada para que la fila se verifique sola.
 *
 * No se persiste «retención_hasta» (§4.3 la listaba): el plazo es un ajuste por instalación y
 * retroactivo, y una columna que haya que reescribir no cabe en una fila que no se actualiza. El
 * plazo se aplica al podar (`WaiverSignature::prunable()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->string('locale', 8);
            $table->unsignedInteger('version');
            $table->string('title', 200);
            $table->json('body');                          // [{h, p}] YA interpolado: lo que se enseñó
            $table->char('body_hash', 64);                 // sha256 canónico de título + secciones
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('created_at')->nullable();   // sin updated_at: la fila nunca cambia

            $table->unique(['slug', 'locale', 'version']);
            $table->index(['slug', 'version']);
        });

        Schema::create('waiver_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 16);            // holder | dependent (§4.3: «sujeto»)
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('legal_document_version_id')->constrained()->restrictOnDelete();
            $table->char('document_hash', 64);             // copia del body_hash de la versión firmada
            $table->timestamp('accepted_at');
            $table->string('accepted_tz', 64);             // «fecha/hora con zona» (#142)
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('channel', 16);                 // web | api | panel
            $table->foreignId('declared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('prev_hash', 64)->nullable();     // null solo en la primera firma del titular
            $table->char('hash', 64)->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'id']);
            $table->index(['subject_type', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiver_signatures');
        Schema::dropIfExists('legal_document_versions');
    }
};
