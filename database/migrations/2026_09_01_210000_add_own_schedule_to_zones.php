<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `#322` — HORARIO POR ZONA (`docs/specs/horario-por-zona.md` §4.1).
 *
 * Una zona puede operar fuera del horario del recinto. El caso que lo motiva son las **excursiones
 * de colegio**: vienen entre semana y por la mañana, que es cuando hay colegio, y a esa hora el
 * parque puede estar cerrado (medido con los datos del cliente: abre 10:00–21:00 y el martes cierra,
 * así que una excursión a las 9:00 o un martes **no tenía ni una franja**).
 *
 * **Las tres columnas son NULABLES y `null` = HEREDA el recinto**, que es el contrato exacto que
 * esta misma tabla ya usa tres veces (`max_per_slot`, `max_guests_per_slot`, `prep_blocks_cupo`,
 * leídas por `PackAvailability` antes de caer al ajuste global). No se inventa una forma nueva: se
 * copia la que la tabla ya tiene.
 *
 * ▶ Por eso esta migración **no cambia la conducta de ninguna instalación**: con las tres a `null`
 * y `ignores_venue_closure` a `false`, el horario efectivo de toda zona sigue siendo el del recinto.
 *
 * ⚠️ `ignores_venue_closure` se llama así y no `ignores_weekly_closure` a propósito
 * (`[DECIDIDO owner, 2026-09-01]`, spec §4.2): la zona ignora el cierre del recinto **entero**, sea
 * el descanso semanal o una excepción de día. La consecuencia está declarada en la spec —un cierre
 * por obras no cerrará la zona solo— y su salida es cerrar esas franjas a mano, que `SlotGenerator`
 * respeta para siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->time('opens_at')->nullable()->after('prep_blocks_cupo');
            $table->time('closes_at')->nullable()->after('opens_at');
            $table->boolean('ignores_venue_closure')->default(false)->after('closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['opens_at', 'closes_at', 'ignores_venue_closure']);
        });
    }
};
