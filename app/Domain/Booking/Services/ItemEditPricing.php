<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Carbon;

/**
 * La TARIFICACIÓN de una edición de ítem del panel — cómputo PURO, sin
 * efectos (extracción 4b del desmontaje de `ViewOrder`,
 * `docs/specs/desmontar-view-order.md` §9.6 · sub-paso A). Lo comparten la
 * vista previa reactiva del modal «Gestionar» (`PresentsOrderActions`) y el
 * guardado (`OrderItemEditor`): lo que ve el operador y lo que se cobra o
 * acredita salen de la MISMA aritmética.
 *
 * Solo lectura: no toma locks ni muta nada, y por eso NO pertenece al
 * `CRITICAL_RE` — es su control negativo declarado en `CriticalPathGateTest`.
 *
 * ⚠️ El ancla de fecha de RESPALDO (`Carbon::today()`, UTC) cuando no llega
 * fecha se conserva TAL CUAL de la página (spec §9.4: «los dos fallbacks de
 * fecha de TARIFICACIÓN siguen en UTC a propósito — son dinero, `PAY-18`»).
 * En la práctica es inalcanzable con un ítem que tenga franja (los llamantes
 * pasan siempre la fecha efectiva); unificar ese ancla con la de la oferta
 * (`AFORO-09`) es una decisión de producto del owner, no de esta mudanza.
 */
class ItemEditPricing
{
    public function __construct(private RateResolver $rates) {}

    /**
     * Precio unitario de CATÁLOGO del producto del item para una fecha, o `null` si ese día no tiene
     * precio para su tarifa. Es la misma fuente que usa la compra (`RateResolver`), así que el panel
     * y la web no pueden divergir sobre lo que cuesta un día.
     */
    public function catalogUnitPriceFor(OrderItem $item, ?string $dateStr): ?int
    {
        if ($dateStr === null || $dateStr === '' || $item->ticketType === null) {
            return null;
        }

        return $this->rates->priceCents($item->ticketType, Carbon::parse($dateStr));
    }

    /**
     * Cómputo PURO del importe de un edit (sub-fase 7.2e.3, #167) — sin
     * efectos: compartido por `priceDiffPreview` (display reactivo) y
     * `executeItemEdit` (guardado), garantizando que lo que ve el operador y
     * lo que se cobra/reembolsa coinciden.
     *
     * Criterio de tarifa:
     *  - Producto SIN cambio → conserva el `unit_price` HISTÓRICO del item
     *    (extiende la reserva a la tarifa que pagó el cliente, sin sorpresas).
     *  - Producto CAMBIADO → tarifa de catálogo (`RateResolver`) en la fecha
     *    efectiva. Si no hay precio ese día, `new`/`diff` = null (bloqueante).
     *
     * @return array{old:int, unit:int, new:?int, diff:?int}
     */
    public function computeEditPricing(OrderItem $item, int $newTypeId, int $newQty, ?string $dateStr): array
    {
        $newQty = max(1, $newQty);
        $oldTotal = $item->chargedSubtotalCents();
        $productChanged = $newTypeId !== (int) $item->ticket_type_id;

        if (! $productChanged) {
            // ⚠️⚠️ **Si la FECHA lleva a un día de otro precio, manda el catálogo de ESE día**
            // (`DECISIONES #127(d)`): «pagas el precio del día que elijas». Antes se conservaba
            // siempre la tarifa pagada, y eso convertía el cambio de fecha en un arbitraje —comprar
            // el día barato y pedir el cambio al caro salía gratis—.
            // El LÍMITE está en el llamante: `dateStr` solo trae un día DISTINTO cuando la fecha
            // cambia de verdad, así que una subida de cantidad sin mover el día sigue conservando la
            // tarifa histórica del ítem, como siempre.
            $catalogo = $this->catalogUnitPriceFor($item, $dateStr);
            $movedDay = $dateStr !== null && $dateStr !== ''
                && $item->slot?->date?->toDateString() !== $dateStr;

            $unit = ($movedDay && $catalogo !== null) ? $catalogo : (int) $item->unit_price;

            // ⚠️⚠️ `#324` — EXCEPCIÓN para los productos con TRAMOS DE CANTIDAD, y es la única forma
            // coherente (`docs/specs/precio-por-tramo.md`).
            //
            // La regla de arriba —«subir la cantidad sin mover el día conserva la tarifa histórica»—
            // se escribió cuando el precio NO dependía de la cantidad. En un producto cuyo precio
            // está DECLARADO como función de la cantidad, conservarla contradice al propio producto:
            // un colegio que pasa de 70 a 100 niños seguiría pagando el tramo de 70 y **no recibiría
            // el descuento que su propia tabla de precios le promete**.
            //
            // ▶ El límite es estricto: solo se re-tarifica si el producto declara tramos. Sin ellos,
            // la conducta es EXACTAMENTE la de siempre — que es lo que hace segura esta excepción.
            if ($item->ticketType?->priceTiers()->exists()) {
                $tierDate = $movedDay || $dateStr === null || $dateStr === ''
                    ? ($dateStr ?: $item->slot?->date?->toDateString())
                    : $dateStr;
                $tiered = $tierDate !== null
                    ? $this->rates->priceCents($item->ticketType, Carbon::parse($tierDate), $newQty)
                    : null;
                if ($tiered !== null) {
                    $unit = (int) $tiered;
                }
            }
        } else {
            $newType = TicketType::find($newTypeId);
            $date = $dateStr !== null && $dateStr !== '' ? Carbon::parse($dateStr) : Carbon::today();
            // `#324`: al CAMBIAR de producto se tarifica el nuevo con la cantidad nueva, que es lo
            // que el catálogo del destino dice que cuesta comprar esa cantidad.
            $resolved = $newType !== null ? $this->rates->priceCents($newType, $date, $newQty) : null;
            if ($resolved === null) {
                return ['old' => $oldTotal, 'unit' => 0, 'new' => null, 'diff' => null];
            }
            $unit = (int) $resolved;
        }

        $newTotal = $unit * $newQty;

        return ['old' => $oldTotal, 'unit' => $unit, 'new' => $newTotal, 'diff' => $newTotal - $oldTotal];
    }

    /**
     * Cómputo PURO del importe de los cambios de complementos (sub-fase 7.2e.4,
     * #170). Modelo financiero (decisión #170): SUBIDAS (añadir / subir
     * cantidad) → cobro en puerta; BAJADAS (quitar, cantidad 0) → SIN
     * movimiento (refund manual aparte). Compartido por el guardado y la
     * preview reactiva (lo que ve el operador == lo que se cobra).
     *
     * ▶ **`add_quantity_modes` es el SELLO DEL MODO** (`specs/hora-extra.md` §12.6.1, `#448`), y sale
     * de AQUÍ y no de `$offeredAddons` a propósito: en una sola llamada a `OrderItemEditor::edit()`
     * hay TRES lecturas distintas de `product_addons` y solo una está dentro de la transacción.
     * Sellar desde otra ataría el sello a una lectura *autocommit* separada de la que define la
     * cantidad por trabajo de validación y por una espera de lock de hasta 50 s. Sale del **MISMO
     * `$pivot`** que ya produce `add_quantities` y `add_free_quantities`: cero consultas nuevas, y el
     * sello queda atado por construcción al número que describe.
     *
     * ⚠️ `null` cuando no hay pivote (el complemento no está enganchado al producto nuevo): no lo
     * gobierna ningún enganche, y eso es SILENCIO, no `fixed`.
     *
     * @param  array<int, array{child_id:int, quantity:int}>  $edits
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $adds
     * @return array{upcharge:int, changes:array<string,mixed>, add_unit_prices:array<int,int>, add_quantities:array<int,int>, add_free_quantities:array<int,int>, add_quantity_modes:array<int,?string>, charges:array<int, array{child_id:?int, type_id:?int, amount:int, context:array<string,mixed>}>, error:?string}
     */
    public function computeAddonPricing(OrderItem $item, TicketType $newType, array $edits, array $adds, ?string $dateStr): array
    {
        $childById = $item->children->keyBy('id');
        $upcharge = 0;
        $added = [];
        $removed = [];
        $updated = [];
        $addUnitPrices = [];
        $addQuantities = [];
        $addFreeQuantities = [];
        $addQuantityModes = [];
        // Cargos por complemento, cada uno ATADO a su child (no al principal): así, al
        // cancelar un complemento, su cargo se anula solo (el libro retira el valor del child).
        $charges = [];

        foreach ($edits as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null) {
                continue;
            }
            $q = (int) $edit['quantity'];
            $oldQ = (int) $child->quantity;
            $name = $child->ticketType?->tr('name') ?? ('#'.$child->id);
            if ($q === 0) {
                $removed[] = $name;
            } elseif ($q > $oldQ) {
                // Subir cantidad añade unidades de PAGO: las gratis (free_quantity) se conservan
                // intactas, así que el delta es (q − oldQ) × unit_price.
                $delta = ($q - $oldQ) * (int) $child->unit_price;
                $upcharge += $delta;
                $updated[] = ['name' => $name, 'old' => $oldQ, 'new' => $q];
                if ($delta > 0) {
                    $charges[] = [
                        'child_id' => (int) $child->id,
                        'type_id' => null,
                        'amount' => $delta,
                        'context' => ['addon_change' => ['added' => [], 'removed' => [], 'updated' => [['name' => $name, 'old' => $oldQ, 'new' => $q]]]],
                    ];
                }
            }
        }

        $date = $dateStr !== null && $dateStr !== '' ? Carbon::parse($dateStr) : Carbon::today();
        foreach ($adds as $add) {
            $typeId = (int) $add['ticket_type_id'];
            $addonType = TicketType::find($typeId);
            if ($addonType === null) {
                return ['upcharge' => 0, 'changes' => [], 'add_unit_prices' => [], 'add_quantities' => [], 'add_free_quantities' => [], 'add_quantity_modes' => [], 'charges' => [], 'error' => 'invalid_product'];
            }

            // Config del pivote (incluido / por-invitado / extras): MISMA autoridad que la compra
            // pública vía AddonResolver, para no cobrar lo que viene incluido (#170 + complementos
            // avanzados). Un incluido SIN precio se trata como gratis (unit 0), igual que el resolver.
            $pivot = $newType->addons()->where('ticket_types.id', $typeId)->first()?->pivot;
            $resolved = $this->rates->priceCents($addonType, $date);
            if ($resolved === null && ! ($pivot?->is_included)) {
                return ['upcharge' => 0, 'changes' => [], 'add_unit_prices' => [], 'add_quantities' => [], 'add_free_quantities' => [], 'add_quantity_modes' => [], 'charges' => [], 'error' => 'addon_unavailable_on_date'];
            }
            $unit = (int) ($resolved ?? 0);

            $reqQty = (int) $add['quantity'];
            $effQty = $pivot ? AddonResolver::effectiveQuantity($pivot, $reqQty, (int) $item->quantity) : max(1, $reqQty);
            $free = $pivot ? AddonResolver::freeUnits($pivot, $effQty) : 0;

            $thisUpcharge = max(0, $effQty - $free) * $unit;
            $upcharge += $thisUpcharge;
            $addUnitPrices[$typeId] = $unit;
            $addQuantities[$typeId] = $effQty;
            $addFreeQuantities[$typeId] = $free;
            // El SELLO DEL MODO (`#448`), del MISMO `$pivot` del que salen las dos líneas de
            // arriba: la unidad y el número que describe no pueden venir de lecturas distintas.
            $addQuantityModes[$typeId] = $pivot?->quantityUnit();
            $addonName = $addonType->tr('name');
            $added[] = ['name' => $addonName, 'qty' => $effQty];
            if ($thisUpcharge > 0) {
                $charges[] = [
                    'child_id' => null,            // se resuelve al crear el child en la txn
                    'type_id' => $typeId,
                    'amount' => $thisUpcharge,
                    'context' => ['addon_change' => ['added' => [['name' => $addonName, 'qty' => $effQty]], 'removed' => [], 'updated' => []]],
                ];
            }
        }

        $changes = [];
        if ($added !== [] || $removed !== [] || $updated !== []) {
            $changes['addon_change'] = ['added' => $added, 'removed' => $removed, 'updated' => $updated];
        }

        return [
            'upcharge' => $upcharge,
            'changes' => $changes,
            'add_unit_prices' => $addUnitPrices,
            'add_quantities' => $addQuantities,
            'add_free_quantities' => $addFreeQuantities,
            'add_quantity_modes' => $addQuantityModes,
            'charges' => $charges,
            'error' => null,
        ];
    }
}
