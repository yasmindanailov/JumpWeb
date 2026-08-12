<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7.1b — Estado "preparado" a nivel `order_item` (decisión #127).
 *
 * El plan original (#18) ponía el flujo de preparación a nivel `tickets`. La
 * revisión operativa con la clienta movió la marca a `order_items` (el "producto"
 * que ve el empleado). Las columnas equivalentes de `tickets` quedan dormidas
 * (no se borran para no romper código que las lea).
 *
 * Granularidad por producto: el empleado puede tener N órdenes con M productos
 * cada una y mezclar estados (1 entrada preparada + 1 cumpleaños sin preparar).
 * Los addons heredan el estado del parent en el accesor del modelo.
 *
 * `prepared_at = null` → "sin preparar" (default).
 * `prepared_at != null` → "preparado".
 * `prepared_by` traza el staff que lo marcó (FK nullable on delete; si el staff
 * se anonimiza/borra a futuro el historial del item no se rompe).
 *
 * "Finalizado" NO se persiste: se calcula al vuelo desde `slot.end_time < now`
 * en `OrderItem::isFinishedInPractice()` (mismo patrón que `Order::displayStatus()` #116).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('prepared_at')->nullable()->after('event_data');
            $table->foreignId('prepared_by')->nullable()->after('prepared_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn('prepared_at');
        });
    }
};
