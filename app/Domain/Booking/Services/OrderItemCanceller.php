<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Notifications\OrderItemCancelled;
use Illuminate\Support\Facades\DB;

/**
 * La CANCELACIÓN de un ítem suelto desde el panel — extracción 4b del
 * desmontaje de `ViewOrder` (`docs/specs/desmontar-view-order.md` §9.6 ·
 * sub-paso D). Sub-fase 7.2e.1bis del origen (decisión #154): cancelar
 * SOLO cancela — no toca Redsys. Si procede devolución, el operador la
 * dispara aparte con «Reembolsar» (que acepta ítems cancelados).
 *
 * LIBERA aforo, no lo consume: la plaza vuelve sola porque los contadores
 * excluyen `cancelled_at !== null`. Por eso no toma el lock de zona/día ni
 * pertenece al `CRITICAL_RE` — es su control negativo declarado en
 * `CriticalPathGateTest`. Sí serializa contra otro operador que cancele el
 * mismo ítem (lock del pedido y del ítem) y es idempotente
 * (`markCancelled` conserva el timestamp original).
 *
 * Defense in depth, en este orden:
 *  1. Permiso `orders.cancel_item` — re-exigido AQUÍ, en el punto de
 *     ejecución (`SEC-04`); desde la página es inalcanzable porque la
 *     acción ya filtra por `visible()` en cada petición.
 *  2. `cancelItemBlockedReason` con fila fresca.
 *  3. Optimistic lock contra `updated_at` (el token que el modal envió).
 *  4. Transacción: lock del pedido Y del ítem, re-check dentro del lock,
 *     `markCancelled`, y la CASCADA a los complementos vivos (decisión
 *     #157 del origen: no tiene sentido «cancelar la entrada de cumple pero
 *     mantener la tarta»). ⚠️ El audit de ÉXITO `orders.item_cancelled` va
 *     DENTRO de la transacción a propósito (spec §4.3 / §8.4): si la
 *     cancelación no commitea, no hubo cancelación que auditar.
 *
 * Después del commit: el email al cliente (`OrderItemCancelled`, texto
 * NEUTRO sobre el reembolso, con el nº de complementos arrastrados).
 * ⚠️ Se envía también si el ítem resultó ya cancelado entre la capa 2 y el
 * lock —la transacción sale sin escribir y el desenlace es «hecho»—: es la
 * conducta que tenía la página y esta mudanza no la cambia.
 */
class OrderItemCanceller
{
    public function cancel(Order $order, OrderItem $item, string $optimisticToken, ?User $by): ItemActionOutcome
    {
        if (! ($by?->hasPermission('orders.cancel_item') ?? false)) {
            return ItemActionOutcome::blocked('permission_denied');
        }

        // Capa 4 (numeración del origen): revalidar bloqueos con fila fresca.
        $reason = $order->cancelItemBlockedReason($item);
        if ($reason !== null) {
            return ItemActionOutcome::blocked($reason);
        }

        // Capa 3: optimistic lock.
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($optimisticToken === '' || $optimisticToken !== $currentToken) {
            return ItemActionOutcome::blocked('stale_item_version');
        }

        // Capa 5: cancelación atómica + audit. NO Redsys.
        $previousStatus = $item->displayStatusForCustomer();
        $cascadedChildIds = [];
        DB::transaction(function () use ($item, $by, $order, $previousStatus, &$cascadedChildIds): void {
            // lockForUpdate sobre Order Y item — serializa contra ediciones
            // concurrentes (otro operador cancelando el mismo item).
            Order::query()->lockForUpdate()->find($order->id);
            /** @var OrderItem $itemLocked */
            $itemLocked = OrderItem::query()->with('children')->lockForUpdate()->findOrFail($item->id);

            // Re-check dentro del lock: el bloqueo de capa 4 pasó con fresh,
            // pero entre el fresh y el lock alguien podría haber cancelado
            // el item. `markCancelled` es idempotente (preserva timestamp
            // original) — no rompe, pero evitamos audit duplicado.
            if ($itemLocked->isCancelled()) {
                return;
            }
            $itemLocked->markCancelled($by);

            // Cascada a children: cada complemento vivo se marca cancelled
            // por el mismo user. Los ya cancelled (caso teórico — alguien
            // los canceló antes individualmente) NO se tocan (idempotente).
            foreach ($itemLocked->children as $child) {
                if (! $child->isCancelled()) {
                    /** @var OrderItem $childLocked */
                    $childLocked = OrderItem::query()->lockForUpdate()->findOrFail($child->id);
                    if (! $childLocked->isCancelled()) {
                        $childLocked->markCancelled($by);
                        $cascadedChildIds[] = $childLocked->id;
                    }
                }
            }

            AuditLogger::log(
                action: 'orders.item_cancelled',
                target: $order,
                payload: [
                    'order_code' => $order->code,
                    'order_item_id' => $itemLocked->id,
                    'ticket_type_id' => $itemLocked->ticket_type_id,
                    'previous_status' => $previousStatus,
                    'cascaded_child_ids' => $cascadedChildIds,
                ],
            );
        });

        // Post-commit: notify al cliente. Pasamos contador de complementos
        // cascaded para que el email los mencione explícitamente si los hay.
        $order->notifyCustomer(new OrderItemCancelled(
            order: $order->fresh(),
            item: $item->fresh(),
            cascadedChildrenCount: count($cascadedChildIds),
        ));

        return ItemActionOutcome::done(['cascaded_child_ids' => $cascadedChildIds]);
    }
}
