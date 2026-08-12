<?php

namespace App\Models\Concerns;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Estado operativo del pedido: el estado FÍSICO del servicio (activo / en curso /
 * finalizado), ortogonal al estado de pago de la transacción ({@see Order::displayStatus()}).
 * Deriva ÚNICAMENTE de si la franja de cada item ya pasó
 * ({@see OrderItem::isFinishedInPractice()}). Las constantes `OPERATIVE_STATUS_*`
 * viven en Order y se referencian aquí con `self::` (que en un trait resuelve a la clase
 * que lo usa). (El antiguo estado `ready` y `preparedSummary` se retiraron junto con el
 * sistema "preparado/no preparado".)
 */
trait OrderOperativeStatus
{
    /**
     * Estado operativo del pedido para el staff (#127, podado tras retirar "preparado"):
     *  - `finished`    → todos los items principales están finalizados (slot pasado).
     *  - `in_progress` → alguno finalizado pero no todos.
     *  - `active`      → ninguno finalizado (la reserva aún no ha empezado/terminado).
     *
     * Es ortogonal a `displayStatus()`: este último refleja el estado de pago/caducidad
     * de la transacción; `displayOperativeStatus()` refleja el estado físico del servicio.
     */
    public function displayOperativeStatus(): string
    {
        // Sub-fase 7.2e: items cancelados (soft-cancel) son terminales y NO
        // entran en el cómputo operativo — equivalen a "no existen" para
        // efectos de finalización.
        $principals = $this->items()
            ->whereNull('parent_item_id')
            ->whereNull('cancelled_at')
            ->get();

        if ($principals->isEmpty()) {
            return self::OPERATIVE_STATUS_ACTIVE;
        }

        $finished = $principals->filter(
            fn (OrderItem $item) => $item->isFinishedInPractice(),
        )->count();

        if ($finished === $principals->count()) {
            return self::OPERATIVE_STATUS_FINISHED;
        }

        if ($finished > 0) {
            return self::OPERATIVE_STATUS_IN_PROGRESS;
        }

        return self::OPERATIVE_STATUS_ACTIVE;
    }

    /**
     * ¿El pedido está operativo para acciones por-item (editar / cancelar item)?
     *
     * Solo los Orders `paid` no completamente finalizados lo permiten. Estados
     * terminales / erróneos (`pending`/`cancelled`/`refunded`/`expired` — éste último
     * vía `displayStatus()` para reflejar la caducidad efectiva aunque el `status` en BD
     * siga `pending`, #116) lo bloquean. Es la base de {@see HasItemActionGuards}.
     *
     * (Antes se llamaba `canToggleItems` y gobernaba el toggle de "preparado"; al retirar
     * ese sistema conserva su lógica —pedido operativo— pero renombrada, porque sigue
     * siendo la guarda raíz de editar/cancelar item.)
     */
    public function isOperationalForItemActions(): bool
    {
        if ($this->displayStatus() !== self::STATUS_PAID) {
            return false;
        }

        return $this->displayOperativeStatus() !== self::OPERATIVE_STATUS_FINISHED;
    }
}
