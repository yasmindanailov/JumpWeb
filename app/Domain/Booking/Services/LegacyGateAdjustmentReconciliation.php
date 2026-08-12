<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Reparación de datos (robustez del desglose): NETEA los cargos de puerta
 * `extra_due` FANTASMA que el código PREVIO dejaba al subir y bajar la cantidad
 * de un item.
 *
 * Por qué existían: cada subida creaba una fila `extra_due` append-only y cada
 * bajada intentaba un reembolso ONLINE (en vez de ACREDITAR el cargo de puerta).
 * El cargo de puerta quedaba inflado con todas las subidas históricas aunque la
 * cantidad volviera a bajar → "A cobrar en el parque" no fidedigno (bug
 * JJ-WIMWJW: tres "+1 Jump · Ilimitada" cuando el neto real era 0).
 *
 * Detecta el invariante roto `Σ extra_due(item) > chargedSubtotal(item)` (no se
 * puede deber EN PUERTA más de lo que el item cuesta hoy) y appendea UNA fila de
 * CRÉDITO (`extra_due` negativo) que baja el neto a su valor correcto:
 *  - **qty-only** (sin cambio de producto en el histórico): reconstruye el
 *    importe pagado online del item desde el `quantity_change.old` del PRIMER
 *    ajuste → `correctNet = max(0, chargedSubtotal − qtyOriginal × unitPrice)`.
 *    EXACTO.
 *  - **con cambio de producto** (baseline online ambiguo): `correctNet =
 *    chargedSubtotal` (cota conservadora: nunca afirma más pendiente que el
 *    precio actual) + flag `ambiguous`.
 *
 * CONSERVADOR (no toca items sanos) · IDEMPOTENTE (re-ejecuta → invariante ya
 * satisfecho → no-op) · NO-OP en BD limpia. El crédito se atribuye al operador
 * de uno de los cargos del propio item (`applied_by` es NOT NULL).
 */
class LegacyGateAdjustmentReconciliation
{
    /**
     * @return array{credited:int, exact:int, ambiguous:int}
     */
    public static function run(): array
    {
        $credited = 0;
        $exact = 0;
        $ambiguous = 0;

        $orders = Order::query()
            ->whereHas('adjustments', fn ($q) => $q->where('type', OrderAdjustment::TYPE_EXTRA_DUE))
            ->with(['items.ticketType', 'adjustments'])
            ->get();

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                // Un item cancelado tiene su extra_due anulado en los cálculos
                // (OrderFinancialSummary lo excluye); no hay nada que netear.
                if ($item->isCancelled()) {
                    continue;
                }

                /** @var Collection<int,OrderAdjustment> $itemAdjustments */
                $itemAdjustments = $order->adjustments
                    ->where('type', OrderAdjustment::TYPE_EXTRA_DUE)
                    ->where('order_item_id', $item->id)
                    ->values();

                if ($itemAdjustments->isEmpty()) {
                    continue;
                }

                $net = (int) $itemAdjustments->sum('amount_cents');
                $charged = $item->chargedSubtotalCents();

                // Invariante sano: el cargo de puerta neto nunca supera el precio
                // actual del item. Si se respeta, no hay fantasma → no-op.
                if ($net <= $charged) {
                    continue;
                }

                [$correctNet, $isAmbiguous] = self::correctNet($item, $itemAdjustments, $charged);
                $creditAmount = $net - $correctNet; // > 0 por el invariante roto

                OrderAdjustment::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'type' => OrderAdjustment::TYPE_EXTRA_DUE,
                    'amount_cents' => -$creditAmount,
                    'currency' => $order->currency ?? 'EUR',
                    'reason' => 'legacy_gate_reconciliation',
                    'context' => ['legacy_gate_reconciliation' => [
                        'previous_net' => $net,
                        'corrected_net' => $correctNet,
                        'charged_subtotal' => $charged,
                        'ambiguous' => $isAmbiguous,
                    ]],
                    'applied_by' => (int) $itemAdjustments->first()->applied_by,
                ]);

                $credited++;
                $isAmbiguous ? $ambiguous++ : $exact++;
            }
        }

        return ['credited' => $credited, 'exact' => $exact, 'ambiguous' => $ambiguous];
    }

    /**
     * Calcula el cargo de puerta NETO correcto del item y si la estimación es
     * ambigua (cota conservadora) o exacta.
     *
     * @param  Collection<int,OrderAdjustment>  $itemAdjustments
     * @return array{0:int, 1:bool} [correctNet, ambiguous]
     */
    private static function correctNet(OrderItem $item, Collection $itemAdjustments, int $charged): array
    {
        // ¿Hubo cambio de producto en el histórico? → el importe pagado online
        // original es ambiguo (precio de un producto distinto). Cota conservadora.
        $hasProductChange = $itemAdjustments->contains(function (OrderAdjustment $adj): bool {
            $ctx = is_array($adj->context) ? $adj->context : [];

            return isset($ctx['changes']['product_change']);
        });

        if ($hasProductChange) {
            return [$charged, true];
        }

        // qty-only: baseline online = qty ORIGINAL × precio unitario actual.
        // qty original = el `old` del PRIMER ajuste de cantidad (cronológico).
        $first = $itemAdjustments
            ->filter(function (OrderAdjustment $adj): bool {
                $ctx = is_array($adj->context) ? $adj->context : [];

                return isset($ctx['changes']['quantity_change']['old']);
            })
            ->sortBy('created_at')
            ->first();

        if ($first === null) {
            // Sin quantity_change reconstruible (p. ej. cargos de complemento) →
            // cota conservadora.
            return [$charged, true];
        }

        $originalQty = (int) $first->context['changes']['quantity_change']['old'];
        $baselineOnline = $originalQty * (int) $item->unit_price;

        return [max(0, $charged - $baselineOnline), false];
    }
}
