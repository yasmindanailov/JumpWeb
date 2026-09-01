<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * **EL JUSTIFICANTE CUELGA DE LA RESERVA, NO DEL PEDIDO**
 * (`docs/specs/waiver-por-reserva.md` §13; `[DECIDIDO owner, 2026-09-02]`).
 *
 * ❗❗ **Lo encontró el owner con un pedido real delante** (`R-LUKFD2`): dos reservas en días
 * distintos —03/09 y 07/09— dentro del mismo pedido, y la pantalla que firma el padre decía
 * *«Días de la visita: 03/09/2026 · 07/09/2026»*. Un padre no autoriza un PEDIDO: autoriza que su
 * hijo entre **a una visita concreta**.
 *
 * Y de esa sola raíz salían cuatro síntomas que parecían independientes:
 *
 *  1. **Dos fechas** en la hoja del padre, y ningún dato de a qué va.
 *  2. **Un solo correo** para un pedido con dos reservas marcadas (era uno por pedido).
 *  3. **La capacidad sumaba las dos líneas** (80 + 1 = 81 plazas para un justificante de una).
 *  4. **«Un niño, un papel» era por PEDIDO**, así que el mismo niño no podía tener dos
 *     justificantes para dos visitas distintas del mismo pedido — que es justo lo que hace falta.
 *
 * ⚠️ **`order_id` se SUSTITUYE, no se acompaña.** Tener las dos columnas serían dos fuentes de la
 * misma verdad, y el pedido se deriva de la línea con una consulta. Quien necesite agrupar por
 * pedido lo hace en la capa de entrega, que es la que ve los dos módulos.
 *
 * ⚠️ **El relleno es «la primera línea principal VIVA» del pedido**, y es lo único que se puede
 * hacer con la información que hay: la fila vieja no sabe a cuál de las reservas pertenecía. Medido
 * antes de escribirlo: **3 filas en local, 0 en producción** (la compra online está cerrada), y las
 * tres son de pedidos de UNA sola línea, así que el relleno es exacto para todas.
 *
 * ⚠️⚠️ **Una fila sin línea viva a la que colgarse se BORRA con su firma**, y va dicho: es una
 * autorización de un pedido íntegramente cancelado, o sea la prueba de una visita que no existe. Se
 * borra la firma primero por la FK RESTRICT de `subject_authorization_id` — el mismo orden que
 * `PurgeCustomerData` aprendió en §4.2.1.
 */
return new class extends Migration
{
    /**
     * ⚠️ **IDEMPOTENTE a propósito, y no por gusto**: la primera pasada de esta migración se quedó a
     * medias en local —columna añadida y rellenada, `UNIQUE` retirado, el resto sin hacer— y como no
     * llegó a registrarse, el siguiente `migrate` la reintentó desde arriba y se estrelló con
     * *«Duplicate column name»*. Una migración que cambia varias cosas en varios `ALTER` tiene que
     * poder repetirse: el estado intermedio existe.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('guardian_authorizations', 'order_item_id')) {
            Schema::table('guardian_authorizations', function (Blueprint $table) {
                $table->unsignedBigInteger('order_item_id')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('guardian_authorizations', 'order_id')) {
            return; // ya migrada.
        }

        // Relleno: la primera línea PRINCIPAL VIVA de su pedido. La misma cuenta que define la
        // capacidad, para que una fila rellenada no caiga en un complemento ni en una cancelada.
        foreach (DB::table('guardian_authorizations')->select('id', 'order_id')->get() as $row) {
            $itemId = DB::table('order_items')
                ->where('order_id', $row->order_id)
                ->whereNull('parent_item_id')
                ->whereNull('cancelled_at')
                ->orderBy('id')
                ->value('id');

            if ($itemId === null) {
                // Sin reserva viva no hay visita que autorizar. La firma va primero: la FK de
                // `subject_authorization_id` es RESTRICT y abortaría la transacción entera.
                DB::table('waiver_signatures')->where('subject_authorization_id', $row->id)->delete();
                DB::table('guardian_authorizations')->where('id', $row->id)->delete();

                continue;
            }

            DB::table('guardian_authorizations')->where('id', $row->id)->update(['order_item_id' => $itemId]);
        }

        // ⚠️⚠️ **El ORDEN es el que MySQL impone, y solo se ve al pisarlo: la FK PRIMERO.** Mientras
        // existe la clave foránea hace falta *algún* índice que la respalde; con el `UNIQUE` ya
        // retirado, el índice simple es el único que queda y MySQL se niega a soltarlo —
        // *«Cannot drop index …: needed in a foreign key constraint»*—. Soltar la FK antes deja el
        // índice libre.
        //
        // ⚠️ Cada paso con su guarda: la primera pasada de esta migración se quedó a medias y el
        // reintento tiene que poder continuar desde donde estuviera.
        // ⚠️ `Schema::getIndexes()` y **NO `SHOW INDEX`**: aquélla es portable y ésta es de MySQL —
        // la suite corre en SQLite y una migración que solo sabe hablar con un motor rompe los 3.900
        // casos en el primer `RefreshDatabase`. Lo dijo la suite en cuanto se intentó.
        $indexes = collect(Schema::getIndexes('guardian_authorizations'))->pluck('name');

        Schema::table('guardian_authorizations', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('guardian_authorizations_order_id_minor_key_unique')) {
                $table->dropUnique(['order_id', 'minor_key']);
            }
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('guardian_authorizations_order_id_index')) {
                $table->dropIndex(['order_id']);
            }
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id')->nullable(false)->change();
            // RESTRICT como la tenía el pedido: una autorización es la mitad de una prueba legal, y
            // borrar la reserva por debajo la dejaría huérfana. `PurgeCustomerData` ya sabe el orden.
            $table->foreign('order_item_id')->references('id')->on('order_items')->restrictOnDelete();
            // «Un niño, un papel» pasa a ser **por VISITA**: el mismo menor puede tener dos
            // justificantes en el mismo pedido si va a dos días distintos, que es lo correcto.
            $table->unique(['order_item_id', 'minor_key']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('id');
        });

        foreach (DB::table('guardian_authorizations')->select('id', 'order_item_id')->get() as $row) {
            DB::table('guardian_authorizations')->where('id', $row->id)->update([
                'order_id' => DB::table('order_items')->where('id', $row->order_item_id)->value('order_id'),
            ]);
        }

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->dropUnique(['order_item_id', 'minor_key']);
            $table->dropIndex(['order_item_id']);
            $table->dropForeign(['order_item_id']);
            $table->dropColumn('order_item_id');
        });

        Schema::table('guardian_authorizations', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->unique(['order_id', 'minor_key']);
            $table->index('order_id');
        });
    }
};
