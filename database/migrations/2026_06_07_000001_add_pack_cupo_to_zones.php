<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.9 (adelanto) — Cupo de packs POR ZONA (override del ajuste global).
 *
 * Hasta ahora el cupo de cumpleaños (fiestas/franja, niños/franja, montaje-bloquea-cupo) era
 * un ajuste GLOBAL (`settings: packs.*`), igual para todo el parque. Para poder tener otra
 * zona-pack con su propio aforo/condiciones, cada zona gana un override OPCIONAL: `null` =
 * usa el ajuste global (comportamiento actual intacto); un valor = manda sobre el global.
 * `PackAvailability` resuelve override-de-zona → ajuste global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->unsignedInteger('max_per_slot')->nullable()->after('rides_count');
            $table->unsignedInteger('max_guests_per_slot')->nullable()->after('max_per_slot');
            $table->boolean('prep_blocks_cupo')->nullable()->after('max_guests_per_slot');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['max_per_slot', 'max_guests_per_slot', 'prep_blocks_cupo']);
        });
    }
};
