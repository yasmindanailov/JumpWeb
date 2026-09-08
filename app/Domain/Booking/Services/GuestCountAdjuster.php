<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\GuestCountChange;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Platform\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * **El cliente cambia cuántos invitados tiene su reserva, desde su post-formulario**
 * (`specs/invitados-en-post-form.md`, `DECISIONES #444`).
 *
 * ❗❗❗ **Es la primera puerta por la que el CLIENTE mueve AFORO**, y todo lo de aquí está escrito
 * alrededor de ese hecho. `complementos-post-reserva.md` §4.3·5 dejó fuera *a propósito* todo lo que
 * ocupa —«esta feature no toca aforo, y esa es la mitad de su coste»—; aquí no hay escapatoria: subir
 * invitados sube `seats`, consume `packs.max_guests_per_slot` y, por `#151`, plazas de entrada de la
 * zona.
 *
 * ## Por qué es un servicio propio y no `OrderItemEditor::edit()`
 *
 * Aquel método exige `orders.edit_item` **y además mueve producto, fecha y complementos**. Dárselo al
 * titular sería `SEC-04` por la puerta de atrás: el alcance de una puerta no lo decide quien la cruza.
 * Éste hace **una sola cosa**, y por eso puede ser del cliente.
 *
 * ## ❗❗ El orden de LOCKS es el del EDITOR, no el de `PostFormAddons`
 *
 * `PostFormAddons` toma `orders` primero, y eligió ese orden tras REPRODUCIR un interbloqueo (`#413`
 * §4.5.4). **Aquí no vale**: `AFORO-01` exige que el `SELECT … FOR UPDATE` de las franjas sea la
 * PRIMERA sentencia de la transacción, con zonas y fechas resueltas FUERA — cualquier lectura previa
 * fija el snapshot antes del lock y el recuento de aforo lee datos pre-commit del rival.
 *
 * ▶ Por tanto **`slots` → `order_items`**, y la secuencia financiera POST-COMMIT, como el editor.
 * ⚠️ Medido (`#444`): eso **no añade ninguna arista** al grafo de bloqueos del post-form. Las que hay
 * son `slots→items`, `items→orders` (`MixedPartySurcharge`) y `orders→items` (`PostFormAddons`), y el
 * único ciclo es el par preexistente de esas dos — ficha propia en `DEUDA.md`, con su forma
 * reproducida sobre InnoDB (4 interbloqueos de 8 procesos).
 *
 * ## Lo que se re-valida BAJO el lock (`SEC-04` aplicado al tiempo)
 *
 * Entre pintar el formulario y guardarlo puede pasar la fiesta, una cancelación, el plazo **o otra
 * reserva que llene la sala**. Todo se re-comprueba dentro, y el motivo que sale distingue los casos
 * porque el remedio de cada uno es distinto.
 */
final class GuestCountAdjuster
{
    public function __construct(
        private ItemEditPricing $pricing,
        private PackAvailability $packAvailability,
        private ZoneDaySlotLock $zoneDayLock,
        private GuestCountPolicy $policy,
    ) {}

    /**
     * Deja la reserva con `$desired` invitados, o devuelve el motivo por el que no se pudo.
     *
     * @param  string  $via  por dónde entró quien guarda (`signed_link` | `account` | `panel`)
     * @param  string|null  $expectedVersion  el TOKEN OPTIMISTA que el cliente vio; `null` = sin comprobar
     */
    public function adjust(OrderItem $principal, int $desired, string $via, ?string $expectedVersion = null): GuestCountChange
    {
        $order = $principal->order;
        $slot = $principal->slot;

        if ($order === null || $slot === null || $desired < 1) {
            return GuestCountChange::blocked(GuestCountChange::REASON_CLOSED, (int) $principal->quantity, $desired);
        }
        if ($desired === (int) $principal->quantity) {
            return GuestCountChange::noop();
        }

        // ⚠️⚠️ **Zona y fecha se resuelven FUERA de la transacción, y como LITERALES** (`AFORO-01`):
        // una subconsulta dentro del propio `FOR UPDATE` fija el snapshot ANTES de que el lock
        // serialice, y el recuento de aforo posterior lee un estado anterior al rival → sobreventa.
        $zoneId = (int) $slot->zone_id;
        $date = $slot->date->toDateString();

        $change = DB::transaction(function () use ($principal, $desired, $zoneId, $date, $expectedVersion): GuestCountChange {
            $lockedSlots = $this->zoneDayLock->acquire([$zoneId], [$date]);

            /** @var OrderItem|null $item */
            $item = OrderItem::query()
                ->with(['ticketType', 'slot', 'order', 'children'])
                ->whereKey($principal->getKey())
                ->lockForUpdate()
                ->first();

            // El TOKEN OPTIMISTA (la convención de las CINCO puertas del operador): el lock SERIALIZA
            // las dos escrituras, pero **no decide cuál gana**. Sin esto, el operador sube los
            // invitados por teléfono y el cliente guarda con la pantalla cargada antes, devolviéndolos
            // al número viejo **con un movimiento de dinero** y sin que nadie se entere.
            if ($item !== null && $expectedVersion !== null && $expectedVersion !== PostFormAddons::versionOf($item)) {
                return GuestCountChange::blocked(GuestCountChange::REASON_STALE, (int) $item->quantity, $desired);
            }

            return $this->apply($item, $desired, $lockedSlots);
        });

        if (! $change->applied) {
            return $change;
        }

        // ── POST-COMMIT: el dinero, como en el editor ────────────────────────────────────────────
        // Una SUBIDA y una BAJADA son el MISMO hecho con su delta entero y su signo (T1 del libro,
        // `specs/desglose-libro.md` §4.2): qué parte absorbe la puerta y qué parte aflora como «a
        // devolver en el parque» lo dice el LIBRO al leer, no una cascada al escribir. Y **no se
        // auto-reembolsa nada** (`#157`: cancelar ≠ reembolsar).
        $actor = $order->user;
        $fresh = $principal->fresh();
        if ($actor !== null && $fresh !== null && $change->deltaCents !== 0) {
            $order->recordEdit(
                $fresh,
                $change->deltaCents,
                $actor,
                $change->deltaCents > 0 ? 'guest_count_increase' : 'guest_count_decrease',
                ['changes' => ['quantity_change' => ['old' => $change->from, 'new' => $change->to]]],
            );
        }

        // El dinero de los complementos re-escalados, **uno por hija** (`#449`, `#170`): atarlo a su
        // línea es lo que permite al libro decir de qué reserva sale cada euro, y lo que hace que al
        // cancelar ese complemento su cargo se anule solo. Un movimiento único sobre el principal
        // sería más corto de escribir y dejaría el desglose mudo.
        if ($actor !== null) {
            foreach ($change->addonRescales as $childId => $rescale) {
                if ($rescale['delta'] === 0) {
                    continue;
                }
                $child = OrderItem::find($childId);
                if ($child === null) {
                    continue;
                }
                $order->recordEdit(
                    $child,
                    $rescale['delta'],
                    $actor,
                    $rescale['delta'] > 0 ? 'addon_per_guest_rescale' : 'addon_per_guest_rescale_reduction',
                    ['changes' => ['addon_change' => ['added' => [], 'removed' => [], 'updated' => [[
                        'name' => $rescale['name'], 'old' => $rescale['old_qty'], 'new' => $rescale['new_qty'],
                    ]]]]],
                );
            }
        }

        // `RGPD-02`: el rastro NO lleva PII — ni un nombre ni una edad. Solo qué reserva, por dónde
        // entró quien la tocó y cuánto se movió.
        AuditLogger::log('orders.guest_count_changed', $order, [
            'order_code' => $order->code,
            'order_item_id' => $principal->getKey(),
            'via' => $via,
            'from' => $change->from,
            'to' => $change->to,
            'delta_cents' => $change->deltaCents,
            'discarded_forms' => $change->discardedForms,
        ]);

        return $change;
    }

    /**
     * El cambio, ya con la reserva bloqueada y fresca.
     *
     * @param  Collection<array-key, Slot>  $lockedSlots  las franjas de la zona/día, BLOQUEADAS
     */
    private function apply(?OrderItem $item, int $desired, Collection $lockedSlots): GuestCountChange
    {
        $from = (int) ($item?->quantity ?? 0);

        if ($item === null || ! $this->policy->isOpenFor($item)) {
            return GuestCountChange::blocked(GuestCountChange::REASON_CLOSED, $from, $desired);
        }
        if (! $this->policy->isWithinWindow($item)) {
            return GuestCountChange::blocked(GuestCountChange::REASON_CUTOFF, $from, $desired);
        }

        $type = $item->ticketType;
        if ($type === null) {
            return GuestCountChange::blocked(GuestCountChange::REASON_CLOSED, $from, $desired);
        }

        // El TECHO: el máximo del producto (`[DECIDIDO owner, 2026-09-07]`).
        $max = $this->policy->maxFor($item);
        if ($max !== null && $desired > $max) {
            return GuestCountChange::blocked(GuestCountChange::REASON_ABOVE_MAX, $from, $desired);
        }

        // El SUELO son DOS preguntas con DOS motivos, y fundirlas deja al cliente sin saber qué
        // hacer: el mínimo del pack se resuelve llamando al parque; lo que ya tiene dueño, quitando
        // a alguien de la lista (§4.4).
        if ($desired < $this->policy->contractableFloorFor($item)) {
            return GuestCountChange::blocked(GuestCountChange::REASON_BELOW_MIN, $from, $desired);
        }
        if ($desired < $this->policy->assignedFloorFor($item)) {
            return GuestCountChange::blocked(GuestCountChange::REASON_BELOW_ASSIGNED, $from, $desired);
        }

        $slot = $lockedSlots->first(fn (Slot $s): bool => (int) $s->id === (int) $item->slot_id);
        if ($slot === null) {
            return GuestCountChange::blocked(GuestCountChange::REASON_CLOSED, $from, $desired);
        }

        $newSeats = $desired * (int) ($type->seats_per_unit ?? 1);

        // ⚠️⚠️ **La huella propia se EXCLUYE y los minutos extra ENTRAN.** Sin lo primero, crecer en
        // su propia franja se contaría a sí mismo y se bloquearía; sin lo segundo, una fiesta con
        // hora extra se revalidaría con una ventana más corta de la que ocupa — que es lo que `#425`
        // enseñó al editor.
        $available = $this->packAvailability->availableGuestsFor(
            $slot->fresh('zone') ?? $slot,
            $type,
            [],
            (int) $item->id,
            (int) ($item->extra_minutes ?? 0),
        );
        if ($available < $newSeats) {
            return GuestCountChange::blocked(GuestCountChange::REASON_SOLD_OUT, $from, $desired);
        }

        // El PRECIO sale de la MISMA aritmética que el panel (`ItemEditPricing`), con el mismo
        // producto y la misma fecha: subir la cantidad conserva la tarifa histórica de la línea
        // (`PAY-19`, «14,00 € y no 24,00 €») salvo en un producto con TRAMOS, donde `#324` manda
        // re-tarificar porque su propia tabla de precios promete el descuento por volumen.
        $pricing = $this->pricing->computeEditPricing($item, (int) $item->ticket_type_id, $desired, $slot->date->toDateString());
        if ($pricing['new'] === null) {
            return GuestCountChange::blocked(GuestCountChange::REASON_CLOSED, $from, $desired);
        }

        // Cuántas fichas RELLENAS se pierden al bajar. Se cuenta ANTES de escribir, y se cuentan las
        // rellenas y no las filas: decirle «se perderán 5» de cinco fichas vacías es ruido.
        $discarded = $this->filledFormsBeyond($item, $desired);

        // ⚠️ **El SELLO no se toca** (`PAY-19`): solo re-sella un cambio de PRODUCTO o de DÍA. Un
        // invitado añadido entra con las condiciones que se le comunicaron al comprar.
        $item->forceFill([
            'quantity' => $desired,
            'unit_price' => (int) $pricing['unit'],
            'seats' => $newSeats,
        ])->save();

        return new GuestCountChange(
            applied: true,
            from: $from,
            to: $desired,
            deltaCents: (int) $pricing['diff'],
            discardedForms: $discarded,
            addonRescales: $this->rescalePerGuestChildren($item, $desired),
        );
    }

    /**
     * **Los complementos POR-INVITADO siguen al número de invitados** (`#449`), con la MISMA regla y
     * la misma aritmética que el editor del panel: su cantidad efectiva ES la cantidad de la reserva.
     *
     * ❗❗❗ **Esto FALTABA, y su spec afirmaba en tres sitios que estaba** (`#444`,
     * `specs/invitados-en-post-form.md` §4.1 y su tabla de pasos). El servicio cargaba `children` y
     * no las tocaba: su única escritura era el principal. *Una doc que describe una conducta que el
     * código no tiene es peor que no tenerla, porque el siguiente construye encima.*
     *
     * ⚠️⚠️ **No mordía porque no existía ningún enganche `per_guest` con hijas vivas — y se activa
     * EXACTAMENTE con el cambio que el owner quiere hacer**: con la hora extra «por invitado», un
     * cliente que suba de 15 a 20 desde el post-form se quedaría con la hora extra cobrada a 15, y el
     * desfase lo absorbería el siguiente `recordEdit` del operador **como si fuera suyo**. El panel sí
     * re-escalaba (`OrderItemEditor`): eran dos puertas al mismo hecho y solo una lo mantenía.
     *
     * ⚠️ **La unidad sale del SELLO, no del catálogo** (`#448`): esta línea sigue a los invitados si
     * se VENDIÓ así, no si el enganche lo dice hoy. Y la cantidad se calcula con **esa misma
     * unidad** — decidir con el sello y calcular con el pivote deja la línea en CERO.
     *
     * ⚠️ **El AFORO no se revalida y es correcto**: un extensor por-invitado ocupa **1 bloque** pase
     * lo que pase con la cantidad (`AddonOccupancy::blocksForUnit`), así que re-escalarlo no mueve
     * `extra_minutes` ni un minuto. Lo que cambia es el PRECIO.
     *
     * @return array<int, array{delta:int, old_qty:int, new_qty:int, name:string}>
     */
    private function rescalePerGuestChildren(OrderItem $item, int $desired): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }

        $pivotByAddonId = $type->addons()->get()->keyBy('id');
        $rescales = [];

        foreach ($item->children()->whereNull('cancelled_at')->get() as $child) {
            $pivot = $pivotByAddonId->get($child->ticket_type_id)?->pivot;
            if ($pivot === null || ! AddonResolver::wasSoldPerGuest($child, $pivot)) {
                continue;
            }

            $unidad = AddonResolver::soldQuantityUnit($child, $pivot);
            $oldQty = (int) $child->quantity;
            $oldFree = (int) $child->free_quantity;
            $newQty = AddonResolver::effectiveQuantityForUnit($pivot, $unidad, 0, $desired);
            $newFree = AddonResolver::freeUnitsForUnit($pivot, $unidad, $newQty);
            if ($newQty === $oldQty && $newFree === $oldFree) {
                continue;
            }

            $oldCharged = max(0, $oldQty - $oldFree) * (int) $child->unit_price;
            $newCharged = max(0, $newQty - $newFree) * (int) $child->unit_price;
            $child->forceFill(['quantity' => $newQty, 'free_quantity' => $newFree])->save();

            $rescales[(int) $child->id] = [
                'delta' => $newCharged - $oldCharged,
                'old_qty' => $oldQty,
                'new_qty' => $newQty,
                'name' => $child->ticketType?->tr('name') ?? ('#'.$child->id),
            ];
        }

        return $rescales;
    }

    /** Fichas CON algún dato por encima de la cantidad nueva: las que el cliente reconocería como suyas. */
    private function filledFormsBeyond(OrderItem $item, int $desired): int
    {
        $rows = array_values($item->guestData());
        $lost = 0;

        for ($i = $desired, $n = count($rows); $i < $n; $i++) {
            $row = is_array($rows[$i] ?? null) ? $rows[$i] : [];
            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    $lost++;
                    break;
                }
            }
        }

        return $lost;
    }
}
