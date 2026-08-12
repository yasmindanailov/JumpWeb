<?php

use App\Support\LegacyAddonAdjustmentRepair;
use Illuminate\Database\Migrations\Migration;

/**
 * #193 — Reparación de datos: re-atribuye al COMPLEMENTO (child) los ajustes `extra_due`
 * de complementos que el código previo ataba al producto PRINCIPAL, para que cancelar el
 * complemento anule su cargo (coherencia financiera; caso real JJ-KDKD1W).
 *
 * Es un repair de datos, no de esquema: en una BD sin pedidos editados pre-#193 (p. ej.
 * producción limpia) es un no-op. Irreversible por naturaleza (no se guarda el principal
 * anterior); `down()` es un no-op intencionado.
 */
return new class extends Migration
{
    public function up(): void
    {
        LegacyAddonAdjustmentRepair::run();
    }

    public function down(): void
    {
        // Reparación de datos one-way: no se revierte (re-apuntar al principal reintroduciría
        // el bug y no hay registro del principal anterior).
    }
};
