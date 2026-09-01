<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **EL INTERRUPTOR del justificante de un menor invitado** (`docs/specs/waiver-por-reserva.md` §12.2,
 * T5; `[DECIDIDO owner, 2026-09-01]`).
 *
 * Hasta hoy el subsistema entero existía y **no había puerta por la que entrar**: el enlace tenía tres
 * consumidores en todo el repo y ninguno lo OFRECÍA. El owner lo encontró probándolo, y su diseño es
 * data-driven —el principio nº 1 del proyecto—: **lo decide el PRODUCTO**.
 *
 *  · `none`     el defecto. Ni casilla, ni aviso, ni correo. Es lo que son casi todas las entradas.
 *  · `optional` sale una casilla en el embudo: «viene un menor que no está a mi cargo».
 *  · `required` no hay casilla: hace falta siempre. Es la excursión de colegio.
 *
 * ⚠️ **`order_items.guardian_authorization` es un HECHO, no una consulta al catálogo.** Guarda lo que
 * se acordó al comprar, igual que `age_family_seal` guarda los tramos con los que se vendió: cambiar
 * el interruptor del producto mañana **no puede reescribir lo que este cliente marcó ayer**.
 *
 * ⚠️⚠️ **Los dos defectos van a `false`/`none`, así que esta migración NO cambia la conducta de
 * ninguna instalación**: sin tocar el panel, ningún producto ofrece justificante y ninguna línea nace
 * marcada. Lo único que cambia hoy es que existe el sitio donde decirlo.
 *
 * ⚠️ Es `string` y no un `enum` de MySQL por la misma razón que el resto de la tabla (`type`,
 * `deposit_type`, `min_advance_unit`): un `enum` de motor obliga a una migración para añadir un valor
 * y no es portable a SQLite, que es donde corre la suite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            // Junto a `guest_fields`, que es el interruptor hermano: el que decide si un producto
            // tiene post-form. Los dos responden a «¿qué papeles pide este producto?».
            $table->string('guardian_authorization', 10)->default('none')->after('guest_fields');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Junto a `guest_form_completed_at` por el mismo motivo: son el rastro de los papeles de
            // esta reserva. No lleva fecha — cuándo se firmó lo dice la firma, que es la prueba.
            $table->boolean('guardian_authorization')->default(false)->after('guest_form_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('guardian_authorization');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('guardian_authorization');
        });
    }
};
