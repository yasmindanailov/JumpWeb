<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Precios (matriz producto × tarifa, #59). Polimórfica: sirve para
 * `ticket_types` y `event_packages` (igual que `payments`). El precio NO se guarda
 * en el producto: es la única fuente de verdad (#61). Ver docs/04-MODELO-DATOS.md (§4bis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->morphs('priceable');                  // priceable_type + priceable_id
            $table->foreignId('rate_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('deposit_cents')->nullable(); // señal (eventos)
            $table->timestamps();
            $table->unique(['priceable_type', 'priceable_id', 'rate_type_id'], 'prices_priceable_rate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
