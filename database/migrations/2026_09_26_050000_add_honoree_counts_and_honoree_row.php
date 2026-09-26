<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3a de `specs/fiesta-sistema-nuevo.md` (§4.8, `[DECIDIDO owner]` `#747`): QUIEN CUMPLE, LA PRIMERA FILA DE LA LISTA.
 *
 *  - `ticket_types.honoree_counts`: el AJUSTE DEL PACK «quien cumple cuenta como uno de los niños» (data-driven: cada
 *    instalación decide; PlayJump, sí). Apagado por defecto: sin él, todo sigue como antes.
 *  - `order_items.honoree_row`: el SELLO de la reserva, que `OrderCreator` copia del pack al crearla. Con él, la ficha 0
 *    de `guest_data` es la de quien cumple. ⚠️ `false` por defecto a propósito: las reservas que ya existen siguen con
 *    sus N fichas de invitados (`#747`·3, «solo a las nuevas»), y ninguna pierde una plaza ni paga más.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->boolean('honoree_counts')->default(false)->after('guest_invitation');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->boolean('honoree_row')->default(false)->after('guardian_authorization');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('honoree_row');
        });
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('honoree_counts');
        });
    }
};
