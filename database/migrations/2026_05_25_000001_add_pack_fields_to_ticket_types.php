<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Capa 2, packs) — Campos de PACK en `ticket_types` (catálogo unificado):
 * - min_qty / max_qty: mín/máx de invitados de un cumpleaños (absorbe min_guests/max_guests
 *   de `event_packages`). Nulos en entradas/complementos.
 * - deposit_type / deposit_value: SEÑAL configurable (#83). `none` = pago total (def.);
 *   `percent` = deposit_value % del total; `fixed` = deposit_value céntimos. El precio del
 *   pack es por niño (× invitados) con la matriz `prices` ya existente.
 * Estructura data-driven; valores reales [PENDIENTE], editables en el panel (Fase 7).
 * Ver docs/PLAN-COMPRA-PRODUCTOS.md (§3,§5) y docs/DECISIONES.md (#83).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unsignedInteger('min_qty')->nullable()->after('seats_per_unit');
            $table->unsignedInteger('max_qty')->nullable()->after('min_qty');
            $table->string('deposit_type')->default('none')->after('max_qty'); // none | percent | fixed
            $table->unsignedInteger('deposit_value')->default(0)->after('deposit_type');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn(['min_qty', 'max_qty', 'deposit_type', 'deposit_value']);
        });
    }
};
