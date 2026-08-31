<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;

/**
 * Las FICHAS POR INVITADO de un pack ya comprado (nombre, edad…), guardadas desde el panel —
 * T3 · F de reservas mixtas (`docs/specs/cumple-mixto.md` §23.3), calcado de
 * `OrderItemEventDataWriter`.
 *
 * **El problema que resuelve**: hasta la T3, `guest_data` tenía UN solo escritor (el post-form del
 * cliente) y al panel solo le llegaba «copiar enlace». El operador podía abrir el enlace del
 * cliente y corregir una edad, pero el rastro decía que lo hizo EL CLIENTE (`via: signed_link`) —
 * y la edad mueve el suplemento de fiesta mixta, que es dinero (`PAY-19`).
 *
 * **No escribe él: valida y entra por la MISMA puerta que el cliente**
 * (`OrderItem::submitGuestForm`), con `general = null` —el panel edita fichas, no los datos
 * generales del post-form— y el operador en `$by`. Esa puerta única es la que garantiza que el
 * saneo, el sello de completado, el audit (`orders.guest_form_submitted`, `via: panel`, sin PII —
 * `RGPD-02`) y la reconciliación del suplemento (`REASON_PANEL_GUEST_FORM`, actor operador,
 * correo con la voz de «el parque») no puedan divergir de los del cliente.
 *
 * Defense in depth, en este orden (la misma escalera que el escritor de `event_data`):
 *  1. Permiso `orders.edit_guest_data` — re-exigido AQUÍ, en el punto de ejecución (`SEC-04`).
 *  2. Semántica: el ítem es un pack con `guest_fields` no vacíos y no está cancelado.
 *  3. Optimistic lock contra `updated_at` (el token que el modal envió).
 *  4. Diff de lo SANEADO contra lo saneado vigente: sin cambio → `unchanged()` sin rastro —
 *     `submitGuestForm` no llega a llamarse, así que ni audit ni reconciliación ni correo.
 *
 * ⚠️ No toma el lock de zona/día ni escribe dinero: el suplemento lo mueve `reconcile()`, que ya
 * está en el gate del `pre-push` y relee la fila bloqueada. Por eso este fichero queda FUERA del
 * `CRITICAL_RE` y declarado como control negativo en `CriticalPathGateTest` — el mismo criterio
 * que sus vecinos `OrderItemEventDataWriter` y los dos controladores del post-form.
 */
class OrderItemGuestDataWriter
{
    /**
     * @param  array<int,mixed>  $rawRows  las fichas tal cual llegaron del formulario (una por invitado)
     */
    public function save(Order $order, OrderItem $item, array $rawRows, string $optimisticToken, ?User $by): ItemActionOutcome
    {
        if (! ($by?->hasPermission('orders.edit_guest_data') ?? false)) {
            return ItemActionOutcome::blocked('permission_denied');
        }

        $ticketType = $item->ticketType;
        if ($ticketType === null || ! $ticketType->isPack()) {
            return ItemActionOutcome::blocked('not_pack');
        }
        if ($ticketType->guestFields() === []) {
            return ItemActionOutcome::blocked('no_guest_fields');
        }
        // Una reserva cancelada no tiene fichas que corregir: su suplemento ya está fuera de todos
        // los desgloses y su PII camina hacia la purga, no hacia más ediciones.
        if ($item->isCancelled()) {
            return ItemActionOutcome::blocked('item_cancelled');
        }

        // Optimistic lock: el `updated_at` que se envió al fillForm debe coincidir con el actual;
        // si otro operador (o el cliente, por el post-form) editó entre el render del modal y el
        // submit, se rechaza sin modificar.
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($optimisticToken === '' || $optimisticToken !== $currentToken) {
            return ItemActionOutcome::blocked('stale_version');
        }

        // El diff se hace sobre lo SANEADO en los dos lados: es lo que `submitGuestForm` va a
        // persistir, y comparar contra el crudo llamaría «cambio» a una fila con claves que el
        // esquema vigente descarta. Sin cambio real, no se llama a la puerta: ni fila de audit,
        // ni pasada de reconciliación, ni correo.
        $quantity = (int) $item->quantity;
        $sanitized = $ticketType->sanitizeGuestData($rawRows, $quantity);
        if ($sanitized === $ticketType->sanitizeGuestData($item->guestData(), $quantity)) {
            return ItemActionOutcome::unchanged();
        }

        $item->submitGuestForm($rawRows, null, 'panel', $by);

        return ItemActionOutcome::done();
    }

    /**
     * Audit estructurado de un intento RECHAZADO (`order_items.guest_data_blocked`), sin PII: solo
     * el pedido, el producto y la razón. Lo escribe quien traduce el rechazo (la página).
     *
     * @param  array<string,mixed>  $extra
     */
    public function auditBlocked(Order $order, OrderItem $item, string $reason, array $extra = []): void
    {
        AuditLogger::log(
            action: 'order_items.guest_data_blocked',
            target: $item,
            payload: array_merge([
                'order_code' => $order->code,
                'order_status' => $order->displayStatus(),
                'ticket_type_id' => $item->ticket_type_id,
                'reason' => $reason,
            ], $extra),
        );
    }
}
