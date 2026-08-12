<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dependencia «requiere» entre complementos (config por ENGANCHE, como el resto del pivote).
 * Permite que un complemento solo sea seleccionable/vendible cuando OTRO complemento del mismo
 * producto ya está elegido. Caso real: «Segunda tarta» requiere «Tarta» (no tiene sentido pedir
 * la 2.ª sin la 1.ª). Data-driven: la clienta lo configura desde el panel; el cliente no puede
 * saltárselo (autoridad de servidor en `AddonResolver`).
 *
 * `requires_addon_id` apunta al `ticket_types.id` del complemento REQUERIDO (no a la fila del
 * pivote). `null` (default) = sin dependencia → comportamiento idéntico al de hoy (retro-compatible).
 * `nullOnDelete`: si se borra el complemento requerido, la dependencia se anula sola (el dependiente
 * vuelve a ser libre, no se rompe nada). Aplica solo a `quantity_mode = fixed` (los per-invitado y
 * los de grupo no se «requieren» entre sí; la clienta lo deja vacío para ellos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            // Tabla EXPLÍCITA: `requires_addon_id` no infiere `ticket_types` por convención.
            $table->foreignId('requires_addon_id')->nullable()->after('max_qty')
                ->constrained(table: 'ticket_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requires_addon_id');
        });
    }
};
