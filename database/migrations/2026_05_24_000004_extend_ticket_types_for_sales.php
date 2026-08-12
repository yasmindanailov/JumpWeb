<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5.0 — Convierte `ticket_types` (catálogo de display de la Fase 3) en el
 * producto vendible: zona + duración + features + flags de venta (#58, #60, [DECIDIDO-A]).
 * - Renombra `items` → `features` (estandariza con event_packages).
 * - Elimina `price_cents`: el precio vive solo en `prices` (única fuente de verdad, #61).
 * - `zone_id` sin FK a nivel BD (SQLite no permite añadir FK en ALTER); la relación
 *   se valida en el modelo. Ver docs/04-MODELO-DATOS.md (§4).
 */
return new class extends Migration
{
    public function up(): void
    {
        // El renombrado va aislado (en SQLite reconstruye la tabla).
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->renameColumn('items', 'features');
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->json('description')->nullable()->after('name');
            $table->unsignedBigInteger('zone_id')->nullable()->after('description')->index();
            $table->unsignedInteger('duration_min')->nullable()->after('zone_id'); // null = ilimitada
            $table->decimal('tax_rate', 5, 2)->nullable()->after('duration_min');   // IVA %
            $table->string('wristband_color')->nullable()->after('tax_rate');
            $table->json('conditions')->nullable()->after('wristband_color');
            $table->boolean('is_sellable')->default(false)->after('conditions');
            $table->unsignedInteger('seats_per_unit')->default(1)->after('is_sellable');
            $table->dropColumn('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->default(0)->after('period_label');
            $table->dropColumn([
                'description', 'zone_id', 'duration_min', 'tax_rate',
                'wristband_color', 'conditions', 'is_sellable', 'seats_per_unit',
            ]);
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->renameColumn('features', 'items');
        });
    }
};
