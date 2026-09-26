<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **Lo que Mi cuenta DICE de un producto reservado** (T5b de `docs/specs/isla-y-landing-nueva.md` §4.13,
 * `DECISIONES #775`, `[DECIDIDO owner]`): dos frases que el diseño escribía como política de PlayJump y que en el
 * producto son DATOS de cada instalación.
 *
 * - `ticket_types.reservation_note` (i18n, nulo): el aviso de un COMPLEMENTO en la reserva del cliente, con `:n`
 *   por la cantidad («Tenéis :n pares de calcetines comprados; os los damos en la puerta.»). Vacío: la reserva lo
 *   nombra con su cantidad, sin prometer nada.
 * - `ticket_types.deposit_refundable_in_time` (sí/no, por defecto no): si al cancelar dentro del plazo se devuelve
 *   la SEÑAL. Mi cuenta lo dice al pedir un cambio («…y te devolvemos la señal») solo si está encendido. Lo INFORMA,
 *   no lo aplica: la devolución la hace el personal, como el cambio (`CancellationCutoffRule`).
 *
 * Ningún valor se escribe aquí: los pone el panel de cada instalación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_types', 'reservation_note')) {
                $table->json('reservation_note')->nullable();
            }
            if (! Schema::hasColumn('ticket_types', 'deposit_refundable_in_time')) {
                $table->boolean('deposit_refundable_in_time')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            foreach (['deposit_refundable_in_time', 'reservation_note'] as $columna) {
                if (Schema::hasColumn('ticket_types', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
