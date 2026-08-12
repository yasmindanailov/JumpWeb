<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-fase 7.2e cimientos — tabla de ajustes financieros del pedido fuera de Redsys.
 *
 * Modela el dinero que el CLIENTE DEBE AL PARQUE por ediciones del pedido que
 * suben importe (más cantidad, ticket más caro, addon nuevo, etc.) y que se cobra
 * en persona al llegar al parque. Sin paso por Redsys: no es un nuevo cargo
 * online sino un cobro presencial (cumple decisión #149 cerrada en sesión 7.2e).
 *
 * Dimensión ORTOGONAL a `payment_refunds`:
 *  - `payment_refunds` = dinero que SALE del parque hacia el cliente (devolución bancaria
 *    o reconocida manualmente). Siempre asociado a un `Payment` original.
 *  - `order_adjustments` = dinero que el cliente DEBE al parque, gestionado fuera de
 *    Redsys (cobro/contado en puerta). Sin solape conceptual.
 *
 * Tipo `type` queda como enum extensible. En v1 solo se usa `extra_due`. La decisión
 * de "cobrado en puerta" es implícita (al finalizar el slot del item, se asume
 * cobrado — decisión clienta sesión 7.2e). Si en el futuro se necesita registrar
 * explícitamente el cobro presencial, se añade `collected_in_person` sin migración.
 *
 * `order_item_id` nullable: la mayoría de ajustes pertenecen a un item concreto
 * (cambio de cantidad de un item suelto, p. ej.) pero queda abierto a ajustes
 * a nivel Order (ej. recargo administrativo futuro).
 *
 * `context` JSON: información estructurada del cambio que generó el ajuste
 * (slot_change / quantity_change / product_change / addon_change con los valores
 * antiguos y nuevos). Permite reconstruir el historial completo sin parsear el
 * audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('type');                        // 'extra_due' en v1; extensible
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('reason')->nullable();          // motivo libre del operador
            $table->json('context')->nullable();           // diff estructurado del cambio
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['order_id', 'type']);
            $table->index(['order_item_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};
