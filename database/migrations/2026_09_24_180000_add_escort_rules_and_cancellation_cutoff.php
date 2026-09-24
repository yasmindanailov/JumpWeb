<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Las reglas de «con un adulto» de una zona y el plazo de cambio y cancelación de un producto** (T4a·1 de
 * `docs/specs/isla-y-landing-nueva.md` §4.12; `DECISIONES #699` y `#761`).
 *
 * Tres columnas, las tres NULAS y sin valor por defecto: **vacío es una respuesta** («esta zona no tiene esa
 * excepción», «este producto no publica plazo»), no una falta. Los valores de cada instalación los pone su panel;
 * aquí no se escribe ninguno.
 *
 * - `zones.escort_under_age_from_cm`: por DEBAJO de la edad mínima de la zona se entra con un adulto desde esta
 *   altura (Kids de PlayJump: los menores de 4, desde 90 cm).
 * - `zones.escort_below_cm`: por DEBAJO de esta altura se entra con un adulto (Jump de PlayJump: con menos de
 *   1,30 m). ⚠️ No es `height_min_cm`: aquél es «no se entra por debajo», éste «se entra, acompañado». Son dos
 *   columnas porque la misma cifra dice cosas opuestas, igual que `height_min_cm` y `height_max_cm` (`#676`).
 * - `ticket_types.cancellation_cutoff_hours`: hasta cuántas horas antes se cambia o se cancela (24 en las
 *   entradas; 72 en los cumpleaños, los «3 días naturales» de las condiciones). Hoy lo INFORMA, no lo aplica:
 *   los cambios y las cancelaciones los hace el personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table): void {
            if (! Schema::hasColumn('zones', 'escort_under_age_from_cm')) {
                $table->unsignedSmallInteger('escort_under_age_from_cm')->nullable()->after('height_max_cm');
            }
            if (! Schema::hasColumn('zones', 'escort_below_cm')) {
                $table->unsignedSmallInteger('escort_below_cm')->nullable()->after('escort_under_age_from_cm');
            }
        });

        Schema::table('ticket_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_types', 'cancellation_cutoff_hours')) {
                $table->unsignedSmallInteger('cancellation_cutoff_hours')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_types', 'cancellation_cutoff_hours')) {
                $table->dropColumn('cancellation_cutoff_hours');
            }
        });

        Schema::table('zones', function (Blueprint $table): void {
            foreach (['escort_below_cm', 'escort_under_age_from_cm'] as $columna) {
                if (Schema::hasColumn('zones', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
