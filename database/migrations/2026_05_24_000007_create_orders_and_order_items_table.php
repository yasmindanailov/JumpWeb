<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Pedido (cesta confirmada) y sus líneas. El pedido nace `pending` con
 * `expires_at` (retención de plaza durante el pago); pasa a `paid` con la
 * confirmación de Redsys. Importes en céntimos, calculados en servidor.
 * Ver docs/04-MODELO-DATOS.md (§4) y docs/PLAN-FASE-5-VENTA.md (5.3–5.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();              // referencia legible
            $table->string('status')->default('pending');  // pending|paid|cancelled|refunded|expired
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('tax')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('expires_at')->nullable();   // retención de plaza
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');         // céntimos, con la tarifa aplicada
            $table->unsignedInteger('seats');              // plazas que ocupa (quantity × seats_per_unit)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
