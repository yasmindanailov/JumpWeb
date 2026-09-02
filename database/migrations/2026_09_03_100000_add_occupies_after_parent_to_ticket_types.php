<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **EL INTERRUPTOR de la HORA EXTRA** (`docs/specs/hora-extra.md` §4.1; `[DECIDIDO owner, 2026-09-02]`).
 *
 * Un complemento con `occupies_after_parent = true` es un OCUPANTE: su línea hija nace con la franja
 * siguiente al tramo de su padre (`slot_id`), sus plazas (`seats`) y la duración de su producto — y
 * con eso `SlotAvailability::occupancyMap()` la cuenta sin tocar una línea de su código, porque el
 * mapa no filtra por tipo (§1.2 de la spec).
 *
 * ⚠️⚠️ **Son DOS datos a propósito** (§4.1): este bool dice QUE ocupa; CUÁNTO ocupa vive en
 * `ticket_types.duration_min`, la columna que ya existe y la única que el aforo lee. Un campo nuevo
 * de duración habría nacido huérfano (la lección de `#329`: el dato que decide no sería el que se
 * enseña).
 *
 * ⚠️ **El interruptor es EXPLÍCITO, no derivado de la duración**: «ocupa si tiene `duration_min`»
 * haría que ponerle duración a una camiseta se comiera aforo en silencio. Con `false` por defecto,
 * **los siete complementos actuales siguen igual por construcción** (medido: todos con
 * `duration_min` nulo y ninguna línea hija con franja o plazas) y esta migración no cambia la
 * conducta de ninguna instalación.
 *
 * La combinación imposible (`occupies` sin duración, o con `seats_per_unit < 1`) la rechaza el guard
 * del MODELO (`TicketType::booted()`), y su cinturón —para lo que entre por `Query\Builder::update()`,
 * que los eventos no ven— vive en el punto de composición: sin duración **ni se ofrece ni se vende**,
 * jamás «ocupa hasta el cierre» (§4.1 + §4.11·2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            // Junto a `duration_min`, que es su otra mitad: los dos juntos son «ocupa X minutos
            // detrás de su padre».
            $table->boolean('occupies_after_parent')->default(false)->after('duration_min');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('occupies_after_parent');
        });
    }
};
