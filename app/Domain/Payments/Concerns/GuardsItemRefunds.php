<?php

namespace App\Domain\Payments\Concerns;

use App\Domain\Booking\Models\OrderItem;

/**
 * Guardas de «¿se puede REEMBOLSAR este item suelto?» — la mitad de dinero de lo que era
 * `App\Domain\Booking\Concerns\HasItemActionGuards`, partida en el paso 5 de la modularización
 * (`docs/specs/modulos-dominio.md` §4/§5.5). Métodos, firmas y orden de comprobaciones
 * IDÉNTICOS: es una mudanza, no un rediseño.
 *
 * Por qué se parte aquí: editar y cancelar un item son operaciones de BOOKING (liberan plaza,
 * no tocan la pasarela — decisión #157/#172), mientras que reembolsar es de PAYMENTS. Vivían
 * juntas por accidente histórico. El corte es limpio porque las dos mitades no comparten ningún
 * helper: `refundItemBlockedReason` NO llama a `editItemBlockedReason` (repite a propósito sus
 * dos comprobaciones, `not_in_order` e `item_is_addon`, con distinto criterio para el resto).
 *
 * Se apoya, vía `$this->`, en métodos que siguen en `Order` (`paidPayment`,
 * `refundableCapacityCents`, `itemRefundableRemainderCents`, `displayStatus`) y en sus
 * constantes de estado (`self::`) — igual que antes de la partición.
 *
 * ⚠️ Invariante `PAY-09` (`INVARIANTES.md` §1): el tope per-item bajo lock vive en
 * `Order::itemRefundableRemainderCents()`; aquí solo se decide si el botón se ofrece.
 */
trait GuardsItemRefunds
{
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
        // ⚠️⚠️ **Un pedido CANCELADO con deuda TAMBIÉN se reembolsa por línea** (`DECISIONES #152`,
        // owner). Aquí decía `!== STATUS_PAID` y eso, combinado con que el reembolso TOTAL se
        // bloquea sobre cancelados (`refundBlockedReason` → `already_cancelled`), dejaba al pedido
        // cancelado SIN NINGUNA vía de panel: el cliente leía «tenemos pendiente devolverte X €»
        // para siempre (medido en `#150`). Cancelar cancela el PRODUCTO; el dinero cobrado sigue
        // debiéndose, y esta es la vía para devolverlo. Los topes de PAY-09 no cambian: lo que
        // queda por debajo (capacidad, remanente por línea) sigue mandando bajo lock.
        if (! in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED], true)) {
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
