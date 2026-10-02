<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA MARCA DEL CORREO «FALTA ELEGIR…»** (P4 de `docs/specs/fiesta-sistema-nuevo.md` §4.20; `[DECIDIDO owner]` `DECISIONES
 * #913`: «el día antes del plazo», un correo).
 *
 * Sale el día antes de que se cierre la lista de una fiesta a la que le falta contestar un grupo de opciones con «hay que
 * elegir» (`#914`). El comando corre CADA HORA —una hora de cron caído no se lo lleva—, así que hace falta una marca por
 * reserva, como la de la víspera (`eve_notice_at`, de la que calca la forma y los porqués):
 *  - en `order_items` y no en una tabla aparte, porque la idempotencia es POR RESERVA;
 *  - ⚠️⚠️ escrita por el CONSTRUCTOR DE CONSULTAS (`toBase()`), nunca por el modelo: `updated_at` es el testigo optimista del
 *    post-form, y mandar un correo no puede dejar obsoleta la página que el cliente tenga abierta;
 *  - sin dato personal (una fecha de envío) y muere con su fila: la supresión RGPD la cubre sin tocar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_items', 'choice_reminder_at')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            // `null` = nunca se avisó, el estado de todas las reservas que ya existen: nada que rellenar hacia atrás.
            $table->timestamp('choice_reminder_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_items', 'choice_reminder_at')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('choice_reminder_at');
        });
    }
};
