<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Decisión A, catálogo unificado) — Tipo de producto en `ticket_types`:
 * `entry` (entrada) / `pack` (cumpleaños) / `addon` (complemento). Por defecto `entry`,
 * así el catálogo actual (todo entradas) no cambia. Es el primer paso de unificar el
 * catálogo; la absorción de `event_packages` como `type=pack` se hará en la Capa 2.
 * Ver docs/PLAN-COMPRA-PRODUCTOS.md (§2,§3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->string('type')->default('entry')->after('is_sellable')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
