<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6 · menores a cargo, tanda 4 — la ASIGNACIÓN de una entrada a una persona a cargo
 * (`docs/specs/menores-a-cargo.md` §4.6, §4.10, §9.9.3 D4; `DECISIONES #202`).
 *
 * La tabla es de IDENTITY y referencia el ítem del pedido por su id ENTERO: Booking no puede mirar a
 * Identity, así que la flecha va al revés y por contrato (`Booking\Contracts\CheckoutLines`).
 *
 * Dos claves foráneas con dos políticas, y cada una es una regla del cuerpo de la spec:
 *  - **`dependent_id` → `dependents`, RESTRICT**: una fila de menor con entradas asignadas no se borra
 *    ni por SQL (§4.4: quitar es DESVINCULAR). Es la misma doctrina que la FK de las firmas (`#198`).
 *  - **`order_item_id` → `order_items`, CASCADE**: una asignación sin su línea no significa nada, y así
 *    la limpieza de go-live —que borra los pedidos ANTES que los menores— y los verificadores que
 *    borran `OrderItem` a mano siguen funcionando sin conocerla.
 *
 * Sin columna de posición: es un CONJUNTO acotado por la cantidad de la línea (único por par). Sin
 * `updated_at`: quitar y poner son filas distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependent_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dependent_id')->constrained('dependents')->restrictOnDelete();
            $table->unsignedBigInteger('order_item_id');
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['order_item_id', 'dependent_id']);
            $table->index('dependent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependent_assignments');
    }
};
