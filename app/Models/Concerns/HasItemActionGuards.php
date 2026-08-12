<?php

namespace App\Models\Concerns;

use App\Models\OrderItem;

/**
 * Guardas de "¿se puede editar / cancelar / reembolsar ESTE item suelto?" (sub-fases 7.2e,
 * decisiones #127/#141/#157/#172/#193). Separan la RAZÓN estructurada del bloqueo (string)
 * de la decisión booleana — la UI/handler revalidan en capas independientes (defense in depth).
 *
 * Extraído de Order como concern cohesivo (auditoría de organización): mismas firmas, sin
 * cambio de comportamiento. Sigue apoyándose, vía `$this->`, en métodos que permanecen en
 * Order (`paidPayment`, `refundableCapacityCents`, `itemRefundableRemainderCents`,
 * `isOperationalForItemActions`, `displayStatus`) y en las constantes de estado (`self::`).
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

    /**
     * ¿Razón por la que el item NO se puede reembolsar suelto? `null` = sí puede.
     *
     * Reembolsar item (icono ↩️) = refund parcial REST por un importe libre que
     * elige el operador (free-form hasta el remanente del item), con opción
     * "También cancelar este item" (réplica del flujo #142 aplicado a item).
     *
     * Bloqueos similares a `cancelItemBlockedReason` pero sin requerir que el
     * item NO esté finalizado: un refund "de cortesía" tras el servicio es
     * caso legítimo (algo falló durante la sesión).
     */
    public function refundItemBlockedReason(OrderItem $item): ?string
    {
        if ((int) $item->order_id !== (int) $this->id) {
            return 'not_in_order';
        }
        // Sub-fase 7.2e.1bis: items cancelados SÍ pueden refundarse — el flujo
        // operativo es "cancelar item (libera plaza) + refundar después
        // cuando el operador lo decide" (decisión clienta tras feedback 7.2e.1).
        // Sin embargo, items que ya tienen TODO su importe refundado quedan
        // bloqueados (no se puede devolver más de lo cobrado del item).
        if ($item->parent_item_id !== null) {
            return 'item_is_addon';
        }
        if ($this->status !== self::STATUS_PAID) {
            return 'order_not_paid';
        }
        if ($this->displayStatus() === self::STATUS_EXPIRED) {
            return 'expired';
        }
        if ($this->paidPayment() === null) {
            return 'no_paid_payment';
        }
        if ($this->refundableCapacityCents() <= 0) {
            return 'already_fully_refunded';
        }
        // Si NI el item principal NI ninguno de sus children tienen importe
        // refundable restante, no hay nada que ofrecer en el modal de refund
        // → ocultamos el botón. Sub-fase 7.2e.1bis fix tras feedback empírico:
        // un pack con principal full-refunded pero children vivos DEBE
        // mantener el botón ↩️ para permitir refund de los complementos
        // sueltos. El check anterior (solo mirando principal) ocultaba el
        // botón erróneamente en ese caso (los children podían quedar sin vía
        // operativa de refund desde el panel).
        if (! $this->hasAnyRefundableItemOrChild($item)) {
            return 'item_already_fully_refunded';
        }

        return null;
    }

    /**
     * ¿El item o cualquiera de sus children tiene importe refundable restante?
     * Helper de visibilidad del icono ↩️ Reembolsar en sub-card.
     */
    private function hasAnyRefundableItemOrChild(OrderItem $item): bool
    {
        if ($this->itemRefundableRemainderCents($item) > 0) {
            return true;
        }
        foreach ($item->children as $child) {
            if ($this->itemRefundableRemainderCents($child) > 0) {
                return true;
            }
        }

        return false;
    }

    public function canRefundItem(OrderItem $item): bool
    {
        return $this->refundItemBlockedReason($item) === null;
    }
}
