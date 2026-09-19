<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **LA MARCA DEL AVISO DE LA VÍSPERA** (T7·2b,
 * `docs/specs/celebracion-e-invitacion.md` §4.9 y §10.17; `DECISIONES #714`, `#717`).
 *
 * El aviso sale a las 18:00 del día ANTES de la fiesta y **solo si queda algo por hacer**. Como el
 * comando corre varias veces —a partir de esa hora y hasta medianoche, para que una hora de cron
 * caído no se lleve el aviso por delante—, hace falta una marca por reserva, o el titular recibiría
 * el mismo correo seis veces.
 *
 * ⚠️⚠️ **NO son `party_invitations.reminded_at` / `reminded_count`.** Aquellas nacieron en la T4·1
 * con un comentario que decía justamente esto, pero §4.7 se las asignó al **recordatorio que escribe
 * el anfitrión** y la T6·6 (`DECISIONES #713`) las escribe desde el 19-09. Son dos gestos distintos:
 * aquél lo hace el cliente y no envía nada; éste lo manda el parque. Compartir la columna haría que
 * copiar un recordatorio apagara el aviso de la víspera **sin que nadie lo notara**.
 *
 * ⚠️ **Va en `order_items` y no en una tabla aparte** porque la idempotencia es **por reserva**
 * (§4.9) y ésa es su fila: un pedido con dos fiestas manda dos avisos y cada uno se marca solo. Una
 * tabla de avisos sería un registro que nadie ha pedido y otra poda que mantener.
 *
 * ⚠️⚠️ **Quien la escribe usa el CONSTRUCTOR DE CONSULTAS, no el modelo**: `order_items.updated_at`
 * es el testigo optimista del post-form (§1.3·2), y marcar un aviso con `save()` dejaría obsoleta la
 * página que el cliente tenga abierta —y con ella su compra de extras— **por haberle mandado un
 * correo**. Es la misma razón por la que personalizar, descartar y el recordatorio tienen su propio
 * POST.
 *
 * **RGPD**: no añade dato personal nuevo —es una fecha de envío— y muere con su fila, así que la
 * supresión por tabla de `PurgeCustomerData` y `User::anonymize()` la cubren sin tocar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Nullable y sin respaldo: `null` es «nunca se avisó», que es el estado de las 40 000
            // reservas que ya existen. Nada que rellenar hacia atrás — un aviso de una fiesta pasada
            // no tiene sentido, y el lector de §10.16 ya devuelve «nada pendiente» para ésas.
            $table->timestamp('eve_notice_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('eve_notice_at');
        });
    }
};
