<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Los índices del cuadro de mando** (`docs/specs/analitica.md` §4.5, T2a; `DECISIONES #735`).
 *
 * El informe del dinero corta por FECHA DE COBRO (`orders.paid_at`, `payments.paid_at`), por fecha de
 * devolución (`payment_refunds.processed_at`) y por fecha de alta (`users.created_at`), y ninguna de las
 * cuatro tenía índice: `orders` solo indexaba `(status, expires_at)` y `(attribution_source, created_at)`,
 * `payments` el `gateway_order` y el morfo, `payment_refunds` `(payment_id, status)` y `users` ninguna
 * fecha (medido con `SHOW INDEX` el 24-09). Con pocas filas no se nota; con dos temporadas, cada periodo
 * del cuadro sería un recorrido completo de las cuatro tablas.
 *
 * Aditivos y reversibles: índices compuestos que empiezan por el ESTADO que el cuadro filtra siempre y
 * siguen por la fecha, para que el rango se resuelva dentro del índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'paid_at'], 'orders_status_paid_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_at'], 'payments_status_paid_at_index');
        });

        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->index(['status', 'processed_at'], 'payment_refunds_status_processed_at_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_paid_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_paid_at_index');
        });

        Schema::table('payment_refunds', function (Blueprint $table) {
            $table->dropIndex('payment_refunds_status_processed_at_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_created_at_index');
        });
    }
};
