<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Capa 2d) — Datos del evento de un PACK, configurables POR PACK (data-driven, #86):
 * - ticket_types.event_fields: ESQUEMA de campos que pide el pack al reservar (JSON). Cada campo:
 *   {key, label (traducible), type (text|number|textarea), required}. Editable en el panel
 *   (Fase 7); cada pack puede pedir campos distintos. Null en entradas/complementos.
 * - order_items.event_data: RESPUESTAS del cliente para esa línea de pack (JSON {key: valor}).
 * Ver docs/PLAN-COMPRA-PRODUCTOS.md (§3) y docs/DECISIONES.md (#86).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->json('event_fields')->nullable()->after('deposit_value');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('event_data')->nullable()->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('event_fields');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('event_data');
        });
    }
};
