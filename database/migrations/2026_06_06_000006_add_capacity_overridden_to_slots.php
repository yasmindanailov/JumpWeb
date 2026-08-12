<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.7 iter.3 — Marca de "aforo ajustado a mano" en una franja concreta.
 *
 * Cuando el operador edita el aforo online de una franja desde el panel (excepción puntual),
 * se marca `capacity_overridden = true` para que «Regenerar franjas» NO le reescriba el aforo
 * desde la plantilla (`slot_templates`). Sin esta marca, cada regeneración pisaría el ajuste
 * manual (el comando copia `capacity`/`online_capacity` de la plantilla). Default `false`:
 * todas las franjas existentes siguen gestionadas por la plantilla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->boolean('capacity_overridden')->default(false)->after('online_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropColumn('capacity_overridden');
        });
    }
};
