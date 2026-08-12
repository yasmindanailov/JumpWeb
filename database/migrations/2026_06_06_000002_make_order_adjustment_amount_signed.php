<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Robustez del desglose: `order_adjustments.amount_cents` pasa de UNSIGNED a
 * SIGNED para admitir CRÉDITOS de puerta — filas `extra_due` con importe
 * NEGATIVO que netean los cargos de una subida cuando una edición BAJA la
 * cantidad/importe de un item (sin cargos fantasma al subir y bajar; bug
 * JJ-WIMWJW). Ver {@see Order::applyGateCredit()}.
 *
 * Los importes en céntimos son pequeños (máx ~1e8 para 999.999,99 €) → cero
 * riesgo de overflow con int SIGNED (±2,1e9).
 *
 * Solo aplica a MySQL: SQLite (tests) usa columnas INTEGER dinámicas que ya
 * admiten enteros con signo, así que el cambio es innecesario y se omite para
 * evitar un recreate de tabla con FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('order_adjustments', function (Blueprint $table): void {
            $table->integer('amount_cents')->change();
        });
    }

    public function down(): void
    {
        // No se revierte a UNSIGNED: si existen créditos (filas negativas) el
        // cambio fallaría, y SIGNED es un superconjunto seguro de los valores
        // previos (todos positivos y pequeños). No-op intencionado.
    }
};
