<?php

namespace App\Models\Concerns;

use App\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Builder;

/**
 * Flags de "¿este pedido tiene reembolso?" (#139/#142/#146/#158) + el scope de query
 * simétrico. Extraído de Order como concern cohesivo (auditoría de organización): mismas
 * firmas, sin cambio de comportamiento. Modelo nuevo: `refunded_at` + `refund_amount_cents`
 * son la fuente de verdad, ortogonales al `status` (la constante `STATUS_REFUNDED` queda
 * solo para data legacy). Las constantes de estado siguen en Order (`self::` resuelve a ella).
 */
trait OrderRefundFlags
{
    /**
     * ¿La Order ha recibido devolución (parcial o total)? Modelo nuevo (#139):
     * `refunded_at` es la fuente de verdad — independiente del `status`. La
     * constante legacy `STATUS_REFUNDED` también marca este flag para data antigua.
     */
    public function isRefunded(): bool
    {
        return $this->refunded_at !== null || $this->status === self::STATUS_REFUNDED;
    }

    /**
     * ¿Existe AL MENOS UN reembolso completado sobre esta Order? Cubre tanto el
     * reembolso total (#142, deja `refunded_at` y `refund_amount_cents`) como
     * reembolsos parciales por item de la sub-fase futura (que NO actualizan
     * necesariamente las columnas a nivel Order). La fuente de verdad rigurosa
     * es la presencia de filas `payment_refunds.succeeded` apuntando a Payments
     * de esta Order.
     *
     * **Cubre AMBOS modos** (#146): el filtro mira solo `status=succeeded`, sin
     * importar `mode`. Tanto el reembolso REST (Redsys 0900) como el modo manual
     * (operador ya devolvió desde el portal y aquí solo registra,
     * `gateway_response_code=MANUAL`) terminan con `status=succeeded`. La
     * dimensión "hubo devolución" es independiente de "cómo se procesó".
     *
     * Usado en (#146):
     *  - Badge "Reembolsado" en el heading del Order (admin) y en la card de
     *    mi-cuenta (cliente).
     *  - Filtro "Con reembolso" en la tabla de pedidos del admin.
     *
     * Lectura eficiente: para llamadas desde Blade sin N+1, eager-load
     * `with('payments.refunds')` en el controller.
     */
    public function hasAnyRefund(): bool
    {
        // Fuente primaria: `Order.refunded_at` SIEMPRE se setea cuando un
        // reembolso se aplica con éxito (#138 acción inline, #142 orquestador,
        // y previsiblemente las sub-fases per-item futuras que actualicen las
        // columnas Order al sumar). Es la señal más robusta: si está set,
        // hubo devolución registrada, INDEPENDIENTEMENTE del modo (REST/manual)
        // o de si existe fila `payment_refunds` asociada — los reembolsos
        // anotados antes de #142 no la tienen (caso JJ-9OXNJW empírico).
        if ($this->refunded_at !== null) {
            return true;
        }

        // Fallback legacy: data muy antigua con `status=refunded` sin
        // `refunded_at`. Mantiene la coherencia del badge con data histórica.
        if ($this->status === self::STATUS_REFUNDED) {
            return true;
        }

        // Tercera vía defensiva: payment_refunds.succeeded sin que las columnas
        // Order se hayan actualizado (corrupción de data, import externo, etc.).
        // No debería ocurrir en flujo normal pero el helper queda robusto.
        foreach ($this->payments as $payment) {
            foreach ($payment->refunds as $refund) {
                if ($refund->status === PaymentRefund::STATUS_SUCCEEDED) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ¿La Order está reembolsada en su totalidad? (sub-fase 7.2e.1bis5, #158)
     *
     * Distingue **refund total** de **refund parcial** — el badge "Reembolsado"
     * del Order (heading admin, columna tabla, "Mis pedidos" cliente) solo debe
     * aparecer cuando el pedido ENTERO se devolvió. Un refund de un solo
     * complemento NO equivale a un "pedido reembolsado": el resto del servicio
     * sigue activo y el cliente recibirá su producto principal.
     *
     * Implementación: `refund_amount_cents >= total` (con $total > 0 para no
     * marcar como full-refunded un Order con total 0 — caso teórico no esperado).
     *
     * Caveat legacy: data muy antigua con `status=refunded` sin
     * `refund_amount_cents` se considera full-refunded por simetría con
     * `hasAnyRefund()`. Documentado en #146 (badge resiliente a partial pre-#142).
     */
    public function isFullyRefunded(): bool
    {
        if ($this->status === self::STATUS_REFUNDED) {
            return true;
        }

        if ($this->total <= 0) {
            return false;
        }

        return (int) ($this->refund_amount_cents ?? 0) >= (int) $this->total;
    }

    /**
     * Scope para query builder: filtra Orders con al menos un reembolso
     * succeeded. Usado por el filtro "Con reembolso" de la tabla admin (#146).
     *
     * Implementación SQL: subconsulta EXISTS sobre `payment_refunds` enlazada
     * vía `payments.id`. Robusta frente a partial refunds futuros (no depende
     * de `refunded_at` que podría no estar set en partial). Incluye también el
     * fallback legacy (`status=refunded`) por simetría con `hasAnyRefund()`.
     */
    public function scopeWhereHasAnyRefund(Builder $query): Builder
    {
        // Refleja exactamente las 3 vías de `hasAnyRefund()` con OR para que el
        // filtro de la tabla del panel devuelva el mismo subconjunto que pintaría
        // el badge en el detalle.
        return $query->where(function (Builder $q): void {
            $q->whereNotNull('refunded_at')
                ->orWhere('status', self::STATUS_REFUNDED)
                ->orWhereHas('payments.refunds', fn ($r) => $r->where('status', PaymentRefund::STATUS_SUCCEEDED));
        });
    }
}
