<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `#324` — PRECIO POR TRAMO DE CANTIDAD (`docs/specs/precio-por-tramo.md`).
 *
 * «Cuantos más vienen, menos cuesta cada uno.» Lo motivan las excursiones de colegio: 30 personas a
 * 15 €, 70 a 13 €, 100 a 12 € (y su columna de finde/festivo, que es la tarifa que ya existe).
 *
 * **Tabla PROPIA y no una columna más en `prices`, a propósito** (spec §3·A): `prices` es
 * `(priceable, rate_type) → importe` con único en esa pareja, y **media docena de agregados la leen
 * suponiendo UNA fila por tarifa**. Meterle filas cambiaría lo que miden **sin que falle nada**:
 * `TicketType::displayPriceCents()` hace `first()` sobre las de tarifa normal —con tres tramos, no
 * determinista— y `priceVaries()`, que hoy significa «el precio varía según el DÍA» y decide que la
 * web diga «desde X €», pasaría a ser cierto por variar según la CANTIDAD.
 *
 * ▶ Con tabla propia, **un producto sin tramos no tiene filas** y ningún agregado existente cambia de
 * significado: la misma propiedad que hizo segura la tanda A del horario por zona (`null` = hereda).
 *
 * **No hay `max_qty`**: el tramo llega hasta que empieza el siguiente. Un máximo explícito permite
 * huecos («31–69 sin precio») y solapes, que es la familia de defectos que `#299` tuvo que cerrar
 * para los tramos de EDAD con un guardián en el dominio. Aquí no puede existir por construcción.
 *
 * **Solo `ticket_types`**, no `morphs`: los complementos no se venden por volumen, y un morph
 * invitaría a que alguien lo intentara. Si algún día hace falta, la migración es aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_qty');          // desde cuántas unidades aplica (inclusive)
            $table->unsignedInteger('amount_cents');     // precio POR UNIDAD en este tramo
            $table->timestamps();

            $table->unique(['ticket_type_id', 'rate_type_id', 'min_qty'], 'price_tiers_product_rate_qty_unique');
            // La resolución busca «el mayor min_qty ≤ cantidad» dentro de (producto, tarifa).
            $table->index(['ticket_type_id', 'rate_type_id', 'min_qty'], 'price_tiers_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};
