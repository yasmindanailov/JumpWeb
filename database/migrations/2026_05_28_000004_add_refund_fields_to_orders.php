<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-fase 7.2b ampliación (decisión #139, 2026-05-28).
 *
 * Modela el reembolso como dimensión INDEPENDIENTE del estado del servicio:
 * `orders.refunded_at` + `orders.refund_amount_cents` viven en paralelo a
 * `orders.status`. Permite la combinatoria operativa real del parque:
 *  - paid + refunded_at NULL → pago en curso, sin devolución.
 *  - paid + refunded_at SET  → se devolvió dinero pero el servicio sigue (p. ej.
 *    el cliente reagendó en persona con entradas físicas).
 *  - cancelled + refunded_at NULL → cancelado sin devolución (acuerdo en parque,
 *    canje por entradas físicas, etc.).
 *  - cancelled + refunded_at SET  → cancelado y reembolsado (caso textbook).
 *
 * `Order::STATUS_REFUNDED` se considera **legacy** desde esta sub-fase: nuevos
 * reembolsos NO usan ese status. Se mantiene la constante en el modelo por
 * compatibilidad con eventual data antigua, pero las acciones del panel emiten
 * la combinatoria nueva (status × refunded_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->unsignedInteger('refund_amount_cents')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['refunded_at', 'refund_amount_cents']);
        });
    }
};
