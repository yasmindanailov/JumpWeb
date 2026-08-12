<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.5a — `payments.gateway_order` (Redsys `Ds_Merchant_Order`).
 *
 * Redsys exige 4 primeros caracteres NUMÉRICOS, máx 12, ÚNICO POR COMERCIO+TERMINAL para
 * siempre (reutilizar dispara el error 0913 "pedido repetido" — ver `docs/PLAN-REDSYS.md`
 * §5). Por eso este campo es independiente de `orders.code` (`JJ-XXXX`, legible para el
 * usuario, que va en `Ds_Merchant_MerchantData`).
 *
 * El valor se genera con un contador atómico en `settings.redsys_next_gateway_order`
 * (incrementado bajo `lockForUpdate` en la transacción de creación del pago). Así
 * sobrevive a `migrate:fresh` en sandbox (que reinicia `orders.id`) y a peticiones
 * concurrentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // 12 chars máx; nullable porque pagos de v1 (placeholder #78) no lo tienen.
            // UNIQUE constraint blinda contra reusos (defensa final).
            $table->string('gateway_order', 12)->nullable()->unique()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway_order']);
            $table->dropColumn('gateway_order');
        });
    }
};
