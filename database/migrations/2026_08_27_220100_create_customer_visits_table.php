<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · subsistema A — la VISITA ACREDITADA (`docs/specs/identidad-qr-puerta.md` §8.3, §9.2 A·4;
 * `lealtad-jumppoints.md` §8.1).
 *
 * `customer_visits`: el HECHO OBSERVABLE que JumpPoints no tenía —«el sistema no sabe si alguien
 * vino»: `tickets` tiene el ciclo y cero escritores—. Lo escribe la pantalla de puerta con un acto
 * EXPLÍCITO del empleado («registrar visita»), nunca al abrir la ficha, porque la ficha se abre varias
 * veces por cliente y también tecleando un correo. Una fila por (titular, día): el único hace la
 * idempotencia por construcción y `GateVisits::register()` solo audita cuando escribe.
 *
 *  - `registered_by` nullOnDelete: la visita sobrevive al operador que la registró.
 *  - `user_id` CASCADE: sin titular la visita no vale puntos a nadie; la fuente «visita» de JumpPoints
 *    leerá de aquí cuando exista (`A → D` es dependencia dura).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('visited_on');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'visited_on']);
            $table->index('visited_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
