<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-fase 7.2e cimientos — soft-cancel a nivel OrderItem.
 *
 * Cancelar un item suelto (decisión #150 + sesión 7.2e cerrada) conserva la fila
 * como histórico inmutable y la marca como cancelada. Razones:
 *  - Audit log (7.2d) sigue resolviendo `target_id` del item sin filas huérfanas.
 *  - Operador puede consultar "qué se canceló y cuándo" desde la card del Order.
 *  - Cliente en "Mis pedidos" puede ver "item cancelado el DD/MM" como contexto.
 *  - `payment_refunds.order_item_id` (FK preparada en #142) sigue resolviendo.
 *
 * `cancelled_at` es la fuente de verdad para `OrderItem::isCancelled()`. Una vez
 * marcado, el item NO vuelve a estado activo (soft-cancel terminal): cualquier
 * reactivación tendría que crear un item nuevo. Esto cierra la dimensión sin
 * ambigüedad y casa con la regla operativa (refund REST automático se ejecuta
 * en la misma transacción que el soft-cancel — revertir querría revertir refund).
 *
 * `cancelled_by` nullable on delete: si el usuario staff que canceló se anonimiza
 * o se borra a futuro, la marca persiste sin atribución (mismo patrón que
 * `prepared_by` de la migración 7.1b #127).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('prepared_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancelled_at', 'cancelled_by']);
        });
    }
};
