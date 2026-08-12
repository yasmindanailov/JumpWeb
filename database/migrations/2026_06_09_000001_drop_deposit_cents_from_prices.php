<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #225 (cimientos de la señal/depósito): elimina la columna MUERTA `prices.deposit_cents`.
 *
 * Era un segundo modelado de la señal (por tarifa) que NUNCA se llegó a usar: nadie la lee ni
 * la escribe (`RateResolver` solo consulta `amount_cents`; sin seeders/factories que la pueblen).
 * La fuente única de la señal pasa a ser `ticket_types.deposit_type/deposit_value` (la única con
 * calculador, `TicketType::depositCents`). `down()` la reañade nullable para reversibilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prices', 'deposit_cents')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->dropColumn('deposit_cents');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prices', 'deposit_cents')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->unsignedInteger('deposit_cents')->nullable()->after('amount_cents');
            });
        }
    }
};
