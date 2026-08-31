<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumpleaños MIXTO · T4 — la marca de LÍNEA DE CRÉDITO (`docs/specs/cumple-mixto.md` §24.2).
 *
 * El −X € es el ESPEJO del suplemento (§20.1): una línea hija cuyo subtotal RESTA. Pero
 * `order_items.unit_price` y `quantity` son UNSIGNED (medido, §24.1): la columna no puede llevar
 * el signo. La marca dice «esta línea resta» y el signo lo pone el dominio, en UN solo sitio
 * (`OrderItem::chargedSubtotalCents()`), que es el helper que ya usan todos los consumidores del
 * subtotal — así ninguno multiplica `quantity × unit_price` a mano ni decide el signo por su
 * cuenta.
 *
 * ⚠️ Vive en la FILA y no en el `context` del ajuste gemelo a propósito: el helper lo llaman ~24
 * consumidores que no siempre traen `adjustments` cargada, y leer la marca de una relación sería
 * un N+1 (o una consulta dentro de un helper puro) en las pantallas de listas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_credit')->default(false)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('is_credit');
        });
    }
};
