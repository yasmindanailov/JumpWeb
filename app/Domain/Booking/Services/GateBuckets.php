<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;

/**
 * **Los cubos de puerta de UNA línea, DERIVADOS de sus hechos** (T1 del libro,
 * `specs/desglose-libro.md` §6·T1 · `DECISIONES #305`).
 *
 * ## Por qué existe (y por qué es TEMPORAL)
 *
 * Hasta la T1, una bajada se ESCRIBÍA ya repartida en cubos: un crédito contra el cubo de
 * ediciones (`extra_due` negativo), otro contra el resto de la señal (`deposit_remainder`
 * negativo) y un marcador de 0 € cuando ninguno la absorbía — o NADA para el resto de una bajada
 * cubierta a medias. Desde la T1 cada gestión escribe **UN hecho con su delta entero** (`edit`),
 * y el reparto entre cubos que el modelo de dos ejes sigue leyendo (`OrderFinancialSummary`,
 * `ReservationFinancials`, `Order::pendingAtGateLines`…) se **replica aquí en lectura**, fila a
 * fila y en el MISMO orden en que ocurrieron: es la cascada de `OrderItemEditor::creditReduction`
 * de antes, aplicada al leer en vez de al escribir. Así la T1 cambia el hecho sin mover ninguna
 * cifra de las que se pintan (la foto puente de `OrderFinancialInvariantsTest` lo vigila).
 *
 * ▶ **Muere en la T3**, cuando el libro (`OrderBook`) sustituye al modelo de dos ejes y la
 * liquidación se deriva del SALDO (spec §4.1) sin cubos. No añadas consumidores nuevos.
 *
 * ## Lo que devuelve, por línea
 *
 * - `extraDue` · `depositRemainder`: los dos cubos NETOS tal como los leía el modelo de dos ejes.
 * - `depositSplit`: el reparto de señal al nacer (Σ `deposit_split`).
 * - `uncovered`: la parte de las bajadas que ningún cubo absorbió — lo que antes era «el marcador»
 *   y aflora como pendiente de devolución.
 * - `editDelta`: `Δ(i)` de la spec — Σ de `edit` y `mixed`, con signo. De aquí sale **el valor con
 *   el que nació la línea** (`birthValue = fila − Δ`) y **lo que aportó al cobro online**
 *   (`onlineAtBirth = birthValue − depositSplit`), las dos SIN consultar el catálogo.
 *
 * ⚠️ El orden de replay es `(created_at, id)`: dos gestiones pueden caer en el mismo segundo y el
 * orden de una relación sin `orderBy` no es un contrato.
 */
final readonly class GateBuckets
{
    public function __construct(
        public int $charged,
        public int $extraDue,
        public int $depositRemainder,
        public int $depositSplit,
        public int $uncovered,
        public int $editDelta,
    ) {}

    /** Sobre la relación `adjustments` YA CARGADA del pedido: cero consultas. */
    public static function forItem(Order $order, OrderItem $item): self
    {
        return self::fromRows($order->adjustments, $item);
    }

    /**
     * Sobre una colección de filas cualquiera (p. ej. una consulta FRESCA bajo lock, como hace
     * `MixedPartySurcharge::gateCoverageCents`).
     *
     * @param  iterable<int, OrderAdjustment>  $rows
     */
    public static function fromRows(iterable $rows, OrderItem $item): self
    {
        $own = [];
        foreach ($rows as $row) {
            if ((int) $row->order_item_id === (int) $item->id) {
                $own[] = $row;
            }
        }
        usort($own, static function (OrderAdjustment $a, OrderAdjustment $b): int {
            $ta = $a->created_at?->getTimestamp() ?? 0;
            $tb = $b->created_at?->getTimestamp() ?? 0;

            return $ta <=> $tb ?: (int) $a->id <=> (int) $b->id;
        });

        $extraDue = 0;
        $depositRemainder = 0;
        $depositSplit = 0;
        $uncovered = 0;
        $editDelta = 0;

        foreach ($own as $row) {
            $amount = (int) $row->amount_cents;

            if ($row->isDepositSplit()) {
                $depositRemainder += $amount;
                $depositSplit += $amount;

                continue;
            }
            if ($row->isMixed()) {
                // El gemelo de la línea mixta va entero al cubo de ediciones, con su signo: es lo
                // que el modelo de dos ejes leía (`itemExtraDueCents` sumaba `extra_due` con signo).
                $extraDue += $amount;
                $editDelta += $amount;

                continue;
            }
            if (! $row->isEdit()) {
                continue; // `courtesy` no es un cubo de puerta: es una línea de valor del libro.
            }

            $editDelta += $amount;
            if ($amount >= 0) {
                $extraDue += $amount;

                continue;
            }

            // La CASCADA de una bajada (la de `creditReduction` hasta la T1): primero contra el
            // cubo de ediciones, después contra el resto de la señal; lo que sobra no lo absorbe
            // ningún cubo y aflora como pendiente de devolución.
            $reduction = -$amount;
            $extraCredit = min($reduction, max(0, $extraDue));
            $extraDue -= $extraCredit;
            $depositCredit = min($reduction - $extraCredit, max(0, $depositRemainder));
            $depositRemainder -= $depositCredit;
            $uncovered += $reduction - $extraCredit - $depositCredit;
        }

        return new self(
            charged: $item->chargedSubtotalCents(),
            extraDue: $extraDue,
            depositRemainder: $depositRemainder,
            depositSplit: $depositSplit,
            uncovered: $uncovered,
            editDelta: $editDelta,
        );
    }

    /** El valor con el que NACIÓ la línea: lo que vale hoy menos lo que la movieron las gestiones. */
    public function birthValue(): int
    {
        return $this->charged - $this->editDelta;
    }

    /**
     * Lo que esta línea aportó al COBRO ONLINE al nacer: su valor de nacimiento menos el reparto de
     * señal. Un hecho, no una reconstrucción: no consulta `TicketType::depositCents()`.
     *
     * ⚠️ `max(0, …)` es cinturón, no soporte: por construcción no puede ser negativo, y la identidad
     * de nacimiento (`OrderLedger::cierra`) marca el pedido si alguna vez lo fuera.
     */
    public function onlineAtBirth(): int
    {
        return max(0, $this->birthValue() - $this->depositSplit);
    }

    /** Lo que los dos cubos podrían absorber de una bajada AHORA (la cobertura de la cascada). */
    public function coverage(): int
    {
        return max(0, $this->extraDue) + max(0, $this->depositRemainder);
    }
}
