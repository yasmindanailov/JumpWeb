<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complementos incluidos / obligatorios / excluyentes (feature de complementos avanzados).
 *
 * Toda la configuración de CÓMO se ofrece un complemento dentro de un producto vive en el
 * PIVOTE `product_addons` (decisión de la clienta: config por enganche, no global) → el mismo
 * complemento "Tarta" puede venir gratis-incluido en el pack de cumpleaños y ser de pago en una
 * entrada normal, sin duplicarlo. Columnas nuevas (todas con default retro-compatible: un enganche
 * existente queda como complemento opcional de pago, comportamiento idéntico al de hoy):
 *
 *  - `is_included`        → viene incluido: las primeras unidades son GRATIS (etiqueta "INCLUIDO"
 *                           dentro de un pack). Las que excedan lo incluido se cobran a precio normal.
 *  - `included_quantity`  → cuántas unidades van gratis cuando `is_included` y `quantity_mode=fixed`
 *                           (p. ej. "la primera tarta"). En `per_guest` se ignora (todas las de los
 *                           invitados van incluidas).
 *  - `is_mandatory`       → siempre activo en la compra: no se puede quitar ni bajar del mínimo
 *                           incluido (el menú/tarta del pack). El servidor lo auto-inyecta si falta.
 *  - `quantity_mode`      → `fixed` (cantidad propia + extras opcionales, como la tarta) |
 *                           `per_guest` (una unidad por invitado del pack, como el menú: la cantidad
 *                           sigue al nº de invitados y no la toca el cliente).
 *  - `choice_group`       → clave de grupo de elección EXCLUYENTE (Menú 1 ⊻ Menú 2): los complementos
 *                           del mismo producto que comparten esta clave forman un radio (solo uno
 *                           activo a la vez); el incluido es el por defecto y el de pago lo sustituye.
 *  - `allow_extra`        → si `quantity_mode=fixed`, permite añadir más unidades por encima de lo
 *                           incluido (a precio normal). En `per_guest` o sin incluir no aplica.
 *
 * Y en `order_items`, una columna HISTÓRICA (como `unit_price`: se fija al crear el pedido y no se
 * recalcula si luego cambia el pivote → no afecta a pedidos pasados):
 *
 *  - `free_quantity`      → de las `quantity` unidades de esta línea, cuántas fueron GRATIS (incluidas).
 *                           El cobro real de la línea es `(quantity − free_quantity) × unit_price`,
 *                           centralizado en `OrderItem::chargedSubtotalCents()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->boolean('is_included')->default(false)->after('position');
            $table->unsignedInteger('included_quantity')->default(1)->after('is_included');
            $table->boolean('is_mandatory')->default(false)->after('included_quantity');
            $table->string('quantity_mode')->default('fixed')->after('is_mandatory'); // fixed | per_guest
            $table->boolean('allow_extra')->default(true)->after('quantity_mode');
            $table->string('choice_group')->nullable()->after('allow_extra');

            // Acelera la reconciliación de grupos excluyentes por producto al crear el pedido.
            $table->index(['product_id', 'choice_group']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('free_quantity')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'choice_group']);
            $table->dropColumn([
                'is_included',
                'included_quantity',
                'is_mandatory',
                'quantity_mode',
                'allow_extra',
                'choice_group',
            ]);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('free_quantity');
        });
    }
};
