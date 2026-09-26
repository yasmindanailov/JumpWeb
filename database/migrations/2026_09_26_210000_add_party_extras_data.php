<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5a de `specs/fiesta-sistema-nuevo.md` (§4.11, `[DECIDIDO owner]` `#749`): LO QUE LA ZONA 4 DE LA LISTA NECESITA SABER
 * Y EL CATÁLOGO NO DECÍA. Todo dato del panel; nada de una instalación en el producto.
 *
 *  - `ticket_types.serves`: «para cuántas personas» es un complemento (`#749`, opcional). La chapa «Para 6 adultos», el
 *    «De 12 raciones» de la tarta y las sugerencias («sois 14, es para 12: añade otra»).
 *  - `ticket_types.family`: la FAMILIA del complemento en la lista (i18n, opcional: «Combos», «Cubos de bebidas»).
 *  - `product_addons.postform_block`: en qué bloque de la lista va un enganche de venta POSTERIOR (`cake`: la pregunta de
 *    la tarta · `adults`: para los padres · nulo: la rejilla de siempre). Es del ENGANCHE, como `show_in_invitation`: el
 *    mismo complemento puede ir en un bloque en un pack y en otro no.
 *  - `order_items.cake_declined_at`: «Sin tarta», decidido y guardado (el aviso de la tarta se va).
 *  - `order_items.guest_form_saved_at`: la última vez que el TITULAR guardó su lista («Guardado hoy a las 16:05»). Se
 *    escribe SIN mover `updated_at` (el testigo de la página y el token de las puertas del operador).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->unsignedSmallInteger('serves')->nullable()->after('features');
            $table->json('family')->nullable()->after('serves');
        });
        Schema::table('product_addons', function (Blueprint $table): void {
            $table->string('postform_block', 20)->nullable()->after('postform_cutoff_hours');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->timestamp('guest_form_saved_at')->nullable()->after('guest_form_completed_at');
            $table->timestamp('cake_declined_at')->nullable()->after('guest_form_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['guest_form_saved_at', 'cake_declined_at']);
        });
        Schema::table('product_addons', function (Blueprint $table): void {
            $table->dropColumn('postform_block');
        });
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn(['serves', 'family']);
        });
    }
};
