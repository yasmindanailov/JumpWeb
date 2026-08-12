<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Cobros vía Redsys. Polimórfica (`payable`): sirve para `orders` y, en
 * la Fase 6, `event_bookings`. No se guardan tarjetas; aquí solo el resultado del
 * cobro (firma/idempotencia se gestionan en 5.5, #62). Ver docs/04-MODELO-DATOS.md (§6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');                     // orders | event_bookings
            $table->string('provider')->default('redsys');
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('pending');  // pending|authorized|paid|failed|refunded
            $table->string('transaction_id')->nullable()->index();
            $table->string('auth_code')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
