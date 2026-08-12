<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P9 — Máximo de cantidad por complemento (config por ENGANCHE, como el resto del pivote). Permite
 * topar un complemento de pago a, p. ej., 1 unidad por reserva (una taquilla, una tirolina…). Solo
 * aplica a `quantity_mode = fixed` (en `per_guest` la cantidad la fija el aforo; en grupo, la elección).
 *
 * `null` (default) = sin límite → comportamiento idéntico al de hoy (retro-compatible; el cap duro
 * global de 20 sigue como red de seguridad).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->unsignedInteger('max_qty')->nullable()->after('choice_group');
        });
    }

    public function down(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropColumn('max_qty');
        });
    }
};
