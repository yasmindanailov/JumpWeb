<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **El ICONO y el NIVEL de cada norma** (`DECISIONES #842`, T6e de `isla-y-landing-nueva.md` §4.21): la página de Normas
 * pinta las normas del panel en tarjetas (`RuleCard` del diseño), cada una con su icono y su nivel —Obligatorio,
 * Seguridad…—, y los decide quien opera el parque, como el momento y el porqué (`#533`).
 *
 * ⚠️ **NULLABLES, y `null` significa «sin elegir»**, no un valor por defecto: una norma sin icono o sin nivel se sigue
 * publicando, y quien pinta pone el suyo. Las listas las gobierna el PRODUCTO (`VenueRule::ICONS`, `LEVELS`), no el
 * esquema: cadenas y no `enum`, como el momento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('park_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('park_rules', 'icon')) {
                $table->string('icon', 40)->nullable()->after('reason');
            }
            if (! Schema::hasColumn('park_rules', 'level')) {
                $table->string('level', 20)->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('park_rules', function (Blueprint $table): void {
            foreach (['icon', 'level'] as $columna) {
                if (Schema::hasColumn('park_rules', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
