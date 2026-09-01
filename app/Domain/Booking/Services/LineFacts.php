<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;

/**
 * **Los HECHOS de una línea, sumados** (`specs/desglose-libro.md` §4.1 y §6.3.6 · `DECISIONES #305`).
 *
 * Una línea del pedido tiene tres hechos de dinero además de su fila: el reparto de la SEÑAL al
 * nacer (`deposit_split`), los DELTAS de las gestiones que la movieron (`edit` y `mixed`, con signo)
 * y la CORTESÍA que se le atribuyó al devolver (`courtesy`, ≤ 0). De ahí salen, sin consultar el
 * catálogo ni la base de datos, las dos cifras que el libro necesita de cada línea:
 *
 *     nac(i)         = fila(i) − Δ(i)                 el valor con el que NACIÓ (I1: Σ nac == Order.total)
 *     online_nac(i)  = max(0, nac(i) − reparto(i))    lo que APORTÓ al cobro online (I2: Σ == cobrado)
 *
 * y una tercera para el checkout: `online_now = max(0, fila − reparto)`, lo que la línea cobraría
 * online HOY (`Order::onlineDueCents`, que solo se usa al nacer: D-T3·23).
 *
 * ▶ Sustituye a `GateBuckets` (T1 → T3·4). Aquél REPLICABA en lectura la cascada del modelo de dos
 * ejes —un crédito contra el cubo de ediciones, otro contra el resto de la señal, «lo que sobra»
 * como pendiente de devolución— para que las cifras pintadas no se movieran mientras convivían los
 * dos modelos. Con el libro no hay cubos: la liquidación se DERIVA del saldo (§4.1), y una suma
 * con signo no necesita orden de replay.
 *
 * Lectura pura sobre la relación `adjustments` YA CARGADA del pedido (cero consultas), o sobre una
 * colección de filas cualquiera.
 */
final readonly class LineFacts
{
    public function __construct(
        /** `fila(i)`: `chargedSubtotalCents()`, con signo (una línea de crédito resta). */
        public int $charged,
        /** `reparto(i)`: Σ `deposit_split` (≥ 0). */
        public int $depositSplit,
        /** `Δ(i)`: Σ `edit` + `mixed`, con signo. */
        public int $editDelta,
        /** `cortesía(i)`: Σ `courtesy` (≤ 0). */
        public int $courtesy,
    ) {}

    public static function forItem(Order $order, OrderItem $item): self
    {
        return self::fromRows($order->adjustments, $item);
    }

    /** @param  iterable<int, OrderAdjustment>  $rows */
    public static function fromRows(iterable $rows, OrderItem $item): self
    {
        $depositSplit = 0;
        $editDelta = 0;
        $courtesy = 0;

        foreach ($rows as $row) {
            if ((int) $row->order_item_id !== (int) $item->id) {
                continue;
            }
            $amount = (int) $row->amount_cents;
            if ($row->isDepositSplit()) {
                $depositSplit += $amount;
            } elseif ($row->isValueDelta()) {
                $editDelta += $amount;
            } elseif ($row->isCourtesy()) {
                $courtesy += $amount;
            }
        }

        return new self(
            charged: $item->chargedSubtotalCents(),
            depositSplit: $depositSplit,
            editDelta: $editDelta,
            courtesy: $courtesy,
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
     * de nacimiento (`I1`) marca el pedido «en revisión» si alguna vez lo fuera.
     */
    public function onlineAtBirth(): int
    {
        return max(0, $this->birthValue() - $this->depositSplit);
    }

    /** Lo que la línea cobraría online HOY: su fila menos el reparto de señal (D-T3·23). */
    public function onlineNow(): int
    {
        return max(0, $this->charged - $this->depositSplit);
    }
}
