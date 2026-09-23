<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **EL SELLO DE ORIGEN DEL PEDIDO** (`docs/specs/analitica.md` §4.1; `DECISIONES #678`).
 *
 * De dónde vino la compra, escrito AL NACER el pedido —en el `creating` de `Order`, dentro del mismo
 * INSERT— como el sello de edades de `PAY-19`: un hecho que nada mueve después. Las sesiones del libro se
 * podan a los 25 meses; el pedido se conserva por deber fiscal, y por eso el origen viaja con él.
 *
 * ⚠️ **Dos capas** (spec §7.1, rgpd-5): las columnas PLANAS son la capa de CAMPAÑA —dato del propio
 * contrato, se queda con el pedido y es lo que el panel agrupa, por eso van indexadas y no dentro del
 * JSON (en MariaDB un `json` es `LONGTEXT` sin índice funcional, rendimiento-8)—; el JSON lleva el
 * resto y, SOLO con consentimiento, los identificadores (`visitor_id`, `click_ids`), que `anonymize()`
 * vacía en el acto y la poda a los 25 meses del `created_at`.
 *
 * ⚠️ Un pedido anterior a esta migración queda con todo a `null`: el panel lo rotula «anterior a la
 * medición», nunca «directo» (faltas-8). Aditiva y reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // `web` · `app` · `panel` · `system`.
            $table->string('attribution_channel', 12)->nullable()->after('currency');
            $table->string('attribution_source', 120)->nullable()->after('attribution_channel');
            $table->string('attribution_medium', 120)->nullable()->after('attribution_source');
            $table->string('attribution_campaign', 160)->nullable()->after('attribution_medium');
            $table->json('attribution')->nullable()->after('attribution_campaign');

            $table->index(['attribution_source', 'created_at'], 'orders_attribution_source_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_attribution_source_created_idx');
            $table->dropColumn(['attribution_channel', 'attribution_source', 'attribution_medium', 'attribution_campaign', 'attribution']);
        });
    }
};
