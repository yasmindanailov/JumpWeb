<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Contracts\ItemRefundRequest;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;

/**
 * El REEMBOLSO por líneas de un ítem y sus complementos desde el panel —
 * extracción 4b del desmontaje de `ViewOrder` (`docs/specs/desmontar-view-order.md`
 * §9.6 · sub-paso E). Sub-fase 7.2e.1bis del origen: el operador marca en
 * una lista de casillas qué líneas devolver; cada una es un reembolso
 * parcial de su remanente (o de un importe a medida, `#146` D5).
 *
 * Este servicio NO abre transacción ni llama a la pasarela: valida la
 * petición con sus guardas y delega en `Order::executePartialRefundBatch`,
 * que ya es el dominio del dinero — cada línea con su propio patrón de dos
 * transacciones, los topes re-validados bajo lock (`PAY-09`) y la
 * atomicidad PARCIAL (un fallo REST aborta las pendientes, no deshace las
 * hechas). Por eso no pertenece al `CRITICAL_RE`: los locks viven en `Order`.
 *
 * Defense in depth, en este orden (la de la página, sin cambios):
 *  1. Permiso `orders.refund_item` — re-exigido AQUÍ (`SEC-04`); desde la
 *     página es inalcanzable porque la acción filtra por `visible()`.
 *  2. `refundItemBlockedReason` del ítem PRINCIPAL con fila fresca.
 *  3. Optimistic lock contra `updated_at`.
 *  3 bis. Centinela de CAPACIDAD: si otro reembolso concurrente sobre otro
 *     ítem del mismo pedido bajó lo reembolsable desde que se pintó el
 *     modal, se rechaza (`capacity_changed`).
 *  4. La selección: no vacía; «marcar el principal auto-marca sus
 *     complementos» con remanente; y anti-IDOR — cada id es el principal o
 *     uno de sus hijos.
 *  5. El importe a medida (D5 de `#146`): exige UNA línea, positivo y no
 *     mayor que el remanente de esa línea; el dominio re-valida los topes
 *     bajo lock igualmente.
 *
 * ⚠️ Este camino **NO cancela la línea** (`alsoCancelItems: false`), a
 * propósito: reembolsar y cancelar son independientes (`DECISIONES #127(c)`,
 * owner). Por eso el motivo (`intent`) es obligatorio aquí y lo pide la
 * página: la reserva sigue viva y el dinero vuelve.
 *
 * Devuelve `done(['batch' => …, 'mode' => …])` para que la página lo
 * renderice, o `blocked(reason, extra)`; la página audita el rechazo
 * (`orders.item_refund_blocked`, con `extra` como siempre) salvo
 * `no_items_selected`, que nunca dejó rastro.
 */
class OrderItemRefunder
{
    public function refund(Order $order, OrderItem $primaryItem, ItemRefundRequest $request, ?User $by): ItemActionOutcome
    {
        if (! ($by?->hasPermission('orders.refund_item') ?? false)) {
            return ItemActionOutcome::blocked('permission_denied');
        }

        // Capa 4 (numeración del origen): revalidar bloqueos del item PRINCIPAL con fila fresca.
        $reason = $order->refundItemBlockedReason($primaryItem);
        if ($reason !== null) {
            return ItemActionOutcome::blocked($reason);
        }

        // Capa 3: optimistic lock vs item.updated_at.
        $currentToken = (string) ($primaryItem->updated_at?->getTimestamp() ?? '');
        if ($request->optimisticToken === '' || $request->optimisticToken !== $currentToken) {
            return ItemActionOutcome::blocked('stale_item_version');
        }

        // Capa 3 bis: capacity sentinel.
        $expectedCapacity = $request->expectedCapacityCents;
        $currentCapacity = $order->refundableCapacityCents();
        if ($expectedCapacity < 0 || $currentCapacity < $expectedCapacity) {
            return ItemActionOutcome::blocked('capacity_changed', [
                'expected_capacity_cents' => $expectedCapacity,
                'current_capacity_cents' => $currentCapacity,
            ]);
        }

        // Selección del operador: lista de item_ids marcados en el CheckboxList.
        $selectedIds = array_values(array_unique(array_map('intval', $request->selectedIds)));

        if ($selectedIds === []) {
            return ItemActionOutcome::blocked('no_items_selected');
        }

        // "Marcar el pack auto-marca complementos": si el principal está
        // seleccionado, añadir TODOS sus children (idempotente vía array_unique).
        if (in_array($primaryItem->id, $selectedIds, true)) {
            foreach ($primaryItem->children as $child) {
                if (! in_array($child->id, $selectedIds, true)
                    && $order->itemRefundableRemainderCents($child) > 0) {
                    $selectedIds[] = $child->id;
                }
            }
        }

        // Validación de pertenencia: cada selected_id debe ser el principal
        // o uno de sus children. Defensa anti-IDOR ante manipulación del form.
        $allowedIds = array_merge([$primaryItem->id], $primaryItem->children->pluck('id')->all());
        foreach ($selectedIds as $sid) {
            if (! in_array($sid, $allowedIds, true)) {
                return ItemActionOutcome::blocked('invalid_item_selection', [
                    'submitted_item_id' => $sid,
                ]);
            }
        }

        // D5 (`#146`): importe elegido por el operador. Server-side entero — el form es UI, no
        // defensa. Exige UNA línea (la atribución por línea es lo que el eje de caja explota para
        // explicar), un valor positivo y no exceder el remanente de esa línea; el dominio re-valida
        // los topes bajo lock igualmente (`PAY-09`). Cada bloqueo deja su audit en la página.
        $amountOverrideCents = null;
        if ($request->amountMode === 'custom') {
            if (count($selectedIds) !== 1) {
                return ItemActionOutcome::blocked('custom_amount_requires_single_item', [
                    'selected_count' => count($selectedIds),
                ]);
            }
            $amountOverrideCents = (int) round(((float) ($request->customAmount ?? 0)) * 100);
            if ($amountOverrideCents <= 0) {
                return ItemActionOutcome::blocked('invalid_custom_amount', [
                    'submitted_amount' => (string) ($request->customAmount ?? ''),
                ]);
            }
            $selectedItem = OrderItem::find($selectedIds[0]);
            $selectedRemainder = $selectedItem !== null ? $order->itemRefundableRemainderCents($selectedItem) : 0;
            if ($amountOverrideCents > $selectedRemainder) {
                return ItemActionOutcome::blocked('exceeds_item_refundable', [
                    'amount_cents' => $amountOverrideCents,
                    'item_remainder_cents' => $selectedRemainder,
                ]);
            }
        }

        // Ejecutar refund batch: el dominio devuelve resultados por item. Fallos parciales abortan
        // los pendientes (atomicidad parcial — ver docstring de executePartialRefundBatch).
        //
        // Sub-fase 7.2e.1bis4 (decisión #157): `alsoCancelItems=false` explícito. Refund y
        // cancellation son dimensiones independientes: el operador refunda dinero sin que se
        // cancele el servicio; si quiere cancelar, usa el botón 🗑️ por separado.
        $batchResult = $order->executePartialRefundBatch(
            itemIds: $selectedIds,
            by: $by,
            mode: $request->mode,
            alsoCancelItems: false,
            intent: $request->intent,
            amountCentsOverride: $amountOverrideCents,
        );

        return ItemActionOutcome::done(['batch' => $batchResult, 'mode' => $request->mode]);
    }
}
