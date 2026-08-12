<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 (Capa 3, rediseño #87) — Complementos AGRUPADOS bajo su producto: cada línea de
 * complemento (`type=addon`) enlaza con la línea de entrada/pack a la que pertenece mediante
 * `order_items.parent_item_id` (auto-referencia). Así el carrito/pedido los muestra bajo su
 * producto (p. ej. "Cumpleaños Jump — Lucía" → "Tarta", "Monitor") y se mantienen como un bloque.
 *
 * Sin FK a nivel BD (integridad a nivel app, cross-DB y evita rebuilds de SQLite): padre e hijos
 * viven en el MISMO pedido, que ya cae en cascada al borrar el pedido. Índice para agrupar rápido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_item_id')->nullable()->after('id');
            $table->index('parent_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['parent_item_id']);
            $table->dropColumn('parent_item_id');
        });
    }
};
