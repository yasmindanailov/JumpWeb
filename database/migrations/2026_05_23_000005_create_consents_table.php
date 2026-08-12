<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4.1 — Consentimientos legales (auditable: prueba de que se aceptó).
 * Un registro por documento aceptado (waiver/privacy/terms/marketing) con la
 * versión del texto y la IP. Ver docs/04-MODELO-DATOS.md (§3) y docs/SEGURIDAD.md (§7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');              // waiver | privacy | terms | marketing
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->string('version');           // versión del documento aceptado (p. ej. 2026-05-23)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
