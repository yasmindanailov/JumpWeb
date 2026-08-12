<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-fase 7.2b extendida (decisión #142, 2026-05-28).
 *
 * Cada intento de reembolso tiene su propia fila aquí — éxito, fallo y
 * registro manual conviven con la misma forma. Permite:
 *  - Reembolsos totales y parciales sobre el mismo `Payment` (Redsys §refund:
 *    "podrás realizar tanto una devolución por el importe total… o por un
 *    importe menor"). La sub-fase de gestión por-item reusa esta tabla
 *    rellenando `order_item_id`.
 *  - Reintentos auditados: si la REST call falla por timeout, el operador
 *    puede ver la fila `failed` con la razón y reintentar (idempotencia vía
 *    `gateway_order` reusado del Payment original).
 *  - Modo `manual`: el operador hizo la devolución en el portal de Redsys
 *    aparte y la registra en panel para que el cliente reciba email + audit
 *    log refleje la realidad.
 *
 * Nota de idempotencia: `gateway_order` viene del Payment original (no es
 * único en payment_refunds). Redsys deduplica usando ese campo + amount +
 * transactionType — varios refunds parciales sobre el mismo Payment están
 * permitidos por la pasarela (`Ds_Response 0900` cada vez).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status');           // pending | succeeded | failed
            $table->string('mode');             // rest | manual
            // Reusa el `payments.gateway_order` original (idempotencia REST).
            // Nullable porque el modo `manual` admite Payments sin gateway_order
            // (p. ej. pedidos manuales en efectivo de 7.3 futuro — pendiente).
            $table->string('gateway_order')->nullable();
            $table->string('gateway_response_code')->nullable();  // Ds_Response, '0900' = OK; 'MANUAL' para mode=manual
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('failure_reason')->nullable();   // transport_error_check_portal | gateway_denied | unknown
            $table->text('failure_message')->nullable();    // mensaje humano para el operador
            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
