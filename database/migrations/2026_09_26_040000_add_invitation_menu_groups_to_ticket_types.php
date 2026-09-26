<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1b de `specs/fiesta-sistema-nuevo.md` (§1.4, `#743`): LA MERIENDA DE LA INVITACIÓN POR GRUPOS. El diseño la lee
 * de un vistazo en tres grupos con su icono —«Para beber», «Para comer», «Y para terminar»— y eso es DATO del
 * producto: tres listas traducibles (`{es: […], en: […], fr: […]}`, una cosa por línea, como `features`) en el
 * complemento que el pack enseña en la invitación (`product_addons.show_in_invitation`). Sin ellas, el complemento
 * sigue saliendo como hasta ahora: su nombre y sus ventajas, con el icono neutro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->json('menu_drink')->nullable()->after('features');
            $table->json('menu_food')->nullable()->after('menu_drink');
            $table->json('menu_sweet')->nullable()->after('menu_food');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn(['menu_drink', 'menu_food', 'menu_sweet']);
        });
    }
};
