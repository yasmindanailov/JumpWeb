<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Entradas emitidas (una por admisión), con su QR y el ciclo de estado
 * interno del flujo físico: purchased → prepared → redeemed (→ void) (#18).
 * `qr_token` aleatorio e impredecible (#62, [DECIDIDO-E]).
 * Ver docs/04-MODELO-DATOS.md (§4) y docs/08-OPERATIVA-FISICA.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->string('qr_token')->unique();
            $table->string('status')->default('purchased'); // purchased|prepared|redeemed|void
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
