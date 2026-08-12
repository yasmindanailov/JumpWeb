<?php

use App\Support\LegacyGateAdjustmentReconciliation;
use Illuminate\Database\Migrations\Migration;

/**
 * Robustez del desglose — reparación de datos: NETEA los cargos de puerta
 * `extra_due` fantasma que el código previo dejaba al subir y bajar la cantidad
 * de un item (cada subida creaba una fila append-only; las bajadas reembolsaban
 * online en vez de acreditar el cargo → "A cobrar en el parque" inflado; bug
 * JJ-WIMWJW). Ver {@see LegacyGateAdjustmentReconciliation}.
 *
 * Repair de datos, no de esquema: en una BD sin pedidos editados con este patrón
 * es un no-op. Idempotente. Irreversible por naturaleza (el crédito appendeado
 * es histórico); `down()` es un no-op intencionado.
 *
 * Debe correr DESPUÉS de hacer `amount_cents` SIGNED (migración hermana
 * `..._000002_make_order_adjustment_amount_signed`).
 */
return new class extends Migration
{
    public function up(): void
    {
        LegacyGateAdjustmentReconciliation::run();
    }

    public function down(): void
    {
        // Reparación de datos one-way: no se revierte (eliminar los créditos
        // reintroduciría los cargos fantasma).
    }
};
