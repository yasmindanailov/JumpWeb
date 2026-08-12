<?php

namespace App\Models\Concerns;

use App\Models\OrderItem;

/**
 * Guardas de "¿se puede editar / cancelar ESTE item suelto?" (sub-fases 7.2e,
 * decisiones #127/#141/#157/#172/#193). Separan la RAZÓN estructurada del bloqueo (string)
 * de la decisión booleana — la UI/handler revalidan en capas independientes (defense in depth).
 *
 * Extraído de Order como concern cohesivo (auditoría de organización): mismas firmas, sin
 * cambio de comportamiento. Sigue apoyándose, vía `$this->`, en métodos que permanecen en
 * Order (`paidPayment`, `isOperationalForItemActions`) y en las constantes de estado (`self::`).
 *
 * ⚠️ **PARTIDO en el paso 5 de la modularización** (`docs/specs/modulos-dominio.md` §5.5): la
 * mitad de REEMBOLSO se fue a `App\Domain\Payments\Concerns\GuardsItemRefunds`. Aquí queda
 * lo de BOOKING: editar y cancelar un item liberan plaza y NO tocan la pasarela (#157/#172).
 * El corte fue limpio porque las dos mitades no compartían ningún helper.
 */
trait HasItemActionGuards
{
    /**
     * ¿Razón por la que el item NO se puede editar (modal Gestionar)? `null` = sí puede.
     * Aplicará en 7.2e.2+ cuando el modal cobre vida.
     *
     * Bloqueos:
     *  - `order_not_operational` — Order no permite tocar items (pending, cancelled,
     *    refunded legacy, expired, ya finalizado). Hereda de `isOperationalForItemActions`.
     *  - `item_cancelled`        — item ya soft-cancelado (terminal).
     *  - `item_finished`         — slot del item ya pasó.
     *  - `item_is_addon`         — los addons se gestionan vía Tab 2 del modal del parent,
     *                              NO directamente. Coherencia con la decisión #127.
     *  - `not_in_order`          — defensa anti-IDOR: el item no pertenece a este Order.
     */
    public function editItemBlockedReason(OrderItem $item): ?string
    {
        if ((int) $item->order_id !== (int) $this->id) {
            return 'not_in_order';
        }
        // Checks específicos al item PRIMERO: dan al operador la razón más
        // informativa cuando el item por sí mismo es bloqueante. Sin este orden,
        // un Order con UN solo item finished caería en `order_not_operational`
        // (porque OPERATIVE_STATUS_FINISHED desactiva isOperationalForItemActions) en lugar
        // del más preciso `item_finished`.
        if ($item->isCancelled()) {
            return 'item_cancelled';
        }
        if ($item->isFinishedInPractice()) {
            return 'item_finished';
        }
        if ($item->parent_item_id !== null) {
            return 'item_is_addon';
        }
        // Operatividad del Order como último resort: cubre el caso "Order entera
        // bloqueada por status (pending/cancelled/refunded/expired/all_finished)"
        // cuando el item específico está activo.
        if (! $this->isOperationalForItemActions()) {
            return 'order_not_operational';
        }

        return null;
    }

    public function canEditItem(OrderItem $item): bool
    {
        return $this->editItemBlockedReason($item) === null;
    }

    /**
     * ¿Razón por la que el item NO se puede cancelar suelto? `null` = sí puede.
     *
     * **CANCELAR ≠ REEMBOLSAR** (decisión #157, reforzada en #172): cancelar un
     * item es SOLO un soft-cancel que libera la plaza (`executeItemCancellation`
     * NO toca Redsys); el reembolso es una acción INDEPENDIENTE que el operador
     * decide aparte, para dar flexibilidad en parque físico y online. Por eso
     * NO se exige capacidad reembolsable para cancelar: un item cuyo importe
     * superó lo cobrado online —p. ej. creció con cargos de puerta `extra_due`—
     * puede cancelarse igualmente; el badge "Pendiente reembolso" (7.2e.1bis)
     * avisa de lo que quede por devolver.
     *
     * Requiere Order `paid` con `Payment paid` (el item-cancel aplica a reservas
     * confirmadas; un Order pending se cancela entero). Bloqueos:
     *  - heredados de `editItemBlockedReason` (item cancelado/finalizado, addon,
     *    Order no operativo);
     *  - `order_not_paid` / `no_paid_payment`.
     */
    public function cancelItemBlockedReason(OrderItem $item): ?string
    {
        $editReason = $this->editItemBlockedReason($item);
        if ($editReason !== null) {
            return $editReason;
        }

        if ($this->status !== self::STATUS_PAID) {
            return 'order_not_paid';
        }

        if ($this->paidPayment() === null) {
            return 'no_paid_payment';
        }

        return null;
    }

    public function canCancelItem(OrderItem $item): bool
    {
        return $this->cancelItemBlockedReason($item) === null;
    }
}
