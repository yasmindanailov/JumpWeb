<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El SELLO DEL MODO de un complemento (`docs/specs/hora-extra.md` §12, `DECISIONES #448`).
 *
 * `order_items.quantity` de una línea hija significa dos cosas distintas —BLOQUES de tiempo o
 * PERSONAS— y quién lo decide es una columna de la CONFIGURACIÓN (`product_addons.quantity_mode`),
 * no un hecho de la línea. Así que cambiar el enganche **reinterpreta datos ya vendidos**: medido en
 * producción, poner la hora extra de sala en «por invitado» convertía 5,00 € en 40,00 € y 4,00 € en
 * 60,00 € en la primera edición de cantidad de esas fiestas.
 *
 * ▶ La comparación que lo explica entero, y es del owner: *el PRECIO ya está sellado, y lo está
 * porque vive en la línea* (`unit_price`); **el MODO no se guarda en ninguna parte de la línea, y
 * por eso se escapa**. Esta columna cierra esa asimetría, que es `PAY-19` con el molde de `#288`.
 *
 * ⚠️ **NULLABLE Y SIN DEFAULT, y no es preferencia.** `null` significa **SILENCIO** —«esta línea no
 * declara su unidad, léela del catálogo como se hacía»— y hay dos poblaciones legítimas: los
 * PORTADORES de fiesta mixta (su producto sale de un `Setting` y no tiene fila en `product_addons`,
 * así que no la gobierna ningún enganche) y las líneas anteriores a este mecanismo. Un
 * `default('fixed')` **afirmaría un modo que nadie midió**, y encima por el lado que multiplica los
 * minutos de sala.
 *
 * ⚠️ **`varchar(20)`**, como `product_addons.stage` — y no `string` a secas, que es lo que tiene
 * `quantity_mode` (`varchar(255)`, sin lista cerrada).
 *
 * ⚠️ **Autocontenida a propósito**: literales `'fixed'` / `'per_guest'` en vez de las constantes de
 * `ProductAddon`, para que renombrarlas mañana no reescriba lo que esta migración ya hizo. Y sin
 * `UPDATE … JOIN`: **la suite corre en SQLite** y esa forma no es portable.
 *
 * ⚠️ **Idempotente**: los dos rellenos solo tocan filas con `addon_quantity_mode IS NULL`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ La guarda de la columna no es decorativa: hace la migración **re-ejecutable**, y eso es
        // lo que permite que sus dos rellenos tengan caso propio (`AddonQuantityModeBackfillTest`).
        // Un relleno sin red es un `UPDATE` a ciegas sobre datos de un cliente.
        if (! Schema::hasColumn('order_items', 'addon_quantity_mode')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->string('addon_quantity_mode', 20)->nullable()->after('free_quantity');
            });
        }

        $this->backfillStayExtensions();
        $this->backfillLinesThatCannotHaveBeenBornThisWay();
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('addon_quantity_mode');
        });
    }

    /**
     * RELLENO A · los EXTENSORES de estancia, y solo ellos.
     *
     * ⚠️⚠️ **Su prueba NO es el candado de `#443`** —que nació el 2026-09-07, después de que en
     * producción ya se hubiera cambiado un modo (2026-09-06)— **sino el RASTRO**: las únicas filas
     * `catalog.addon_configured` de producción tocan los enganches del Menú 2 y de los Calcetines,
     * **nunca los de las dos horas extra de sala**. A los extensores no les ha cambiado el modo
     * nadie, así que `fixed` es un hecho COPIADO, no adivinado.
     *
     * ⚠️ **Con su límite dicho**: los eventos de Eloquent no ven `Query\Builder::update()`, y
     * `ProductionSeeder` escribe el pivote exactamente por esa puerta — el rastro cubre el panel, no
     * el seeder. Se acepta a sabiendas: el seeder no cambia modos de un enganche vendido.
     *
     * ▶ Y NO se rellena nada más. Rellenar todo el corpus desde el pivote de hoy convertiría un
     * error de configuración **hoy autocorregible** (los tres lectores miran el catálogo, así que
     * arreglar el enganche arregla las líneas) en un **hecho inmutable** — y sobre la línea del
     * relleno B escribiría justo lo contrario de lo que se vendió.
     */
    private function backfillStayExtensions(): void
    {
        $extenderIds = DB::table('ticket_types')
            ->where('extends_parent_stay', true)
            ->pluck('id')
            ->all();

        if ($extenderIds === []) {
            return;
        }

        $touched = DB::table('order_items')
            ->whereNotNull('parent_item_id')
            ->whereNull('addon_quantity_mode')
            ->whereIn('ticket_type_id', $extenderIds)
            ->update(['addon_quantity_mode' => 'fixed']);

        info('[#448] sello del modo · relleno A (extensores): '.$touched.' líneas selladas como `fixed`.');
    }

    /**
     * RELLENO B · la línea que NO PUDO NACER ASÍ, que es una corrección de dato con nombre.
     *
     * ❗❗ **El daño que el sello previene YA OCURRIÓ en producción, y el rastro lo fecha entero**: la
     * línea «Menú 2» del pedido `R-BOMAZH` (Pack Cumpleaños Jump, 17 invitados, fiesta del
     * 2026-09-21) se vendió el **2026-09-01** como `qty = 1` a 2,00 €, y su enganche pasó a
     * `per_guest` el **2026-09-06**. En la primera edición de cantidad de esa fiesta el re-escalado
     * la pondría en 17 × 2,00 € = **34,00 €**: +32,00 € que nadie vendió.
     *
     * ▶ **El criterio es DERIVADO, no un id clavado** —un id es de esta instalación y esta migración
     * corre en todas—: `AddonResolver::effectiveQuantity()` con `per_guest` devuelve **siempre**
     * `max(0, $lineQuantity)`, la cantidad del padre, sin topes ni mínimos que la muevan. Por tanto
     * **una hija viva bajo un enganche `per_guest` cuya cantidad no es la del padre no pudo nacer
     * así**: se vendió con la otra unidad. Medido en producción: **1 fila de 27 hijas vivas**.
     *
     * ⚠️⚠️ **Un borde declarado, y se elige a sabiendas.** Este criterio alcanzaría también a una
     * línea que SÍ se vendió `per_guest` y divergió después por el defecto abierto de
     * `GuestCountAdjuster` (no re-escala las hijas por-invitado, ficha en `DEUDA.md`). Sellarla
     * `fixed` es **el lado que no cobra de más**: la deja como está en vez de multiplicarla por los
     * invitados. Entre congelar una línea y cobrarle a un cliente 32,00 € que no compró, se congela.
     *
     * ⚠️ **Falla hacia no hacer nada**: sin filas que cumplan el criterio, no toca nada.
     */
    private function backfillLinesThatCannotHaveBeenBornThisWay(): void
    {
        $ids = DB::table('order_items as c')
            ->join('order_items as p', 'p.id', '=', 'c.parent_item_id')
            ->join('product_addons as pa', function ($join): void {
                $join->on('pa.product_id', '=', 'p.ticket_type_id')
                    ->on('pa.addon_id', '=', 'c.ticket_type_id');
            })
            ->whereNull('c.cancelled_at')
            ->whereNull('c.addon_quantity_mode')
            ->where('pa.quantity_mode', '=', 'per_guest')
            ->whereColumn('c.quantity', '!=', 'p.quantity')
            ->pluck('c.id')
            ->all();

        if ($ids === []) {
            return;
        }

        DB::table('order_items')->whereIn('id', $ids)->update(['addon_quantity_mode' => 'fixed']);
        info('[#448] sello del modo · relleno B (no pudieron nacer `per_guest`): '
            .count($ids).' líneas selladas como `fixed` — ids '.implode(', ', $ids).'.');
    }
};
