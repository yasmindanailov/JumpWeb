<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **EL EJE DE LA HORA EXTRA EN UN PACK** (`docs/specs/hora-extra.md` §10; `DECISIONES #421`/`#422`,
 * `[DECIDIDO owner, 2026-09-06]`: lo que se vende es que **la fiesta sigue en su sala**).
 *
 * Son DOS columnas y una sola idea, y por eso viajan juntas: sin la primera no hay nada que vender y
 * sin la segunda el aforo no sabe contarlo — separarlas abriría exactamente la ventana de sobreventa
 * que la revisión adversarial cazó (`#423` · A2).
 *
 *  - **`ticket_types.extends_parent_stay`** — el interruptor, hermano de `occupies_after_parent` y
 *    **excluyente** con él. No es un modo del que ya hay: los dos «prolongan la estancia», pero su
 *    UNIDAD es distinta —el ocupante se vende por PERSONA y el extensor por BLOQUE DE TIEMPO—, y
 *    reinterpretar el mismo complemento según el tipo del padre haría que su precio cambiara de
 *    unidad sin que nada lo diga (`prices` es una tabla sola).
 *
 *  - **`order_items.extra_minutes`** — cuánto se alargó ESA reserva. Es un HECHO de la venta y el
 *    hermano exacto de `order_items.seats`, que ya es un derivado materializado en la línea
 *    (`cantidad × seats_per_unit`) que los mapas de ocupación leen sin recalcular. ⚠️⚠️ Derivarlo en
 *    su lugar costaría **una consulta más por llamada** —una hija extensora no tiene `slot_id`, así
 *    que el `join slots` de los dos mapas no la trae— y `SlotOffer::offerableTimes()` pregunta el
 *    cupo **una vez por franja** (18 el sábado con la rejilla de `#420`).
 *
 * ⚠️ **Con los dos valores por defecto (false / 0) nada cambia de conducta**: ningún complemento
 * declara extender y ninguna línea existente lleva minutos extra, así que los dos mapas de ocupación
 * siguen dando exactamente lo mismo sobre los datos de hoy.
 *
 * La combinación imposible (extender sin decir cuánto, extender Y ocupar, o colgar de algo que no es
 * un pack) la rechazan los guards de `TicketType::booted()` y `ProductAddon::booted()`, **en las dos
 * direcciones** (la lección de `#324`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            // Junto a su hermano: los dos leen `duration_min` como «cuánto», y verlos seguidos es lo
            // que hace evidente que son excluyentes.
            $table->boolean('extends_parent_stay')->default(false)->after('occupies_after_parent');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Junto a `seats`, del que es hermano: los dos son hechos de la venta que el aforo lee.
            $table->unsignedInteger('extra_minutes')->default(0)->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('extends_parent_stay');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('extra_minutes');
        });
    }
};
