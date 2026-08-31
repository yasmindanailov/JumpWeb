<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailProductCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sub-fase 7.2e cimientos — Email tras modificación de un OrderItem que NO
 * implica reembolso parcial completo (los reembolsos parciales por sí solos
 * van con `OrderItemRefunded`).
 *
 * Polivalente: cubre cambios de fecha/hora, cantidad, producto, addons, datos
 * del evento. El caller (orquestador `executeItemEdit` de 7.2e.3+) pasa un
 * array `$changes` con keys descriptivas y, opcionalmente, importes:
 *
 *  - `$changes['slot_change']`         → ['old' => '12/06 16:00', 'new' => '15/06 18:00']
 *  - `$changes['quantity_change']`     → ['old' => 3, 'new' => 5]
 *  - `$changes['product_change']`      → ['old' => 'Cumple Tortugas 1h', 'new' => 'Cumple Castores 2h']
 *  - `$changes['event_data_change']`   → true (booleano simple; el detalle no se cita)
 *  - `$changes['addon_change']`        → ['added' => [...], 'removed' => [...], 'updated' => [...]]
 *
 * `$extraDueCents` opcional: si la edición generó cobro extra en puerta, se incluye una línea
 * explícita. Evita la sorpresa al cliente al llegar. (El `$refundedCents` que hubo aquí se RETIRÓ
 * en la T5 —`cumple-mixto.md` §25.5—: llevaba cableado a `null` desde que la edición dejó de
 * auto-reembolsar (D8 del desglose) y su línea prometía la tarjeta. Un reembolso real manda su
 * propio correo, `OrderItemRefunded`.)
 *
 * Defensivo: si `$changes` está vacío y no hay importes, no debería enviarse
 * (el orquestador comprueba antes de notify). Para defensa en profundidad, el
 * email igual se renderiza con un mensaje genérico de actualización.
 *
 * NOTA: este Notification SOLO existe como esqueleto en 7.2e.0 — su uso real
 * llega en 7.2e.2+ cuando las acciones del modal Gestionar se implementan. Los
 * textos definitivos se afinan empíricamente con la clienta entonces. Por ahora
 * cubre el contrato + se valida con tests unitarios del envío.
 */
class OrderItemModified extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string,mixed>  $changes
     */
    public function __construct(
        public Order $order,
        public OrderItem $item,
        public array $changes = [],
        public ?int $extraDueCents = null,
        // ⚠️⚠️ **La BAJADA también es dinero, y este email no la contaba** (`#155`; medido en
        // `#149`: tras mover la fecha a un día más barato, el cliente al que se le debían 10,00 €
        // recibía un email sin un solo importe). Dos términos porque una bajada tiene dos destinos
        // (`#150`): lo que se le debe (aflora como «pendiente de devolución») y lo que se absorbe
        // contra lo que iba a pagar en el parque (packs con señal).
        public ?int $pendingRefundCents = null,
        public ?int $gateCreditedCents = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $productName = $this->item->ticketType?->tr('name')
            ?? __('emails.order_item_modified.product_fallback', ['id' => $this->item->id]);

        $message = (new MailMessage)
            ->subject(__('emails.order_item_modified.subject', ['code' => $this->order->code, 'product' => $productName]))
            ->greeting(__('emails.order_item_modified.greeting'))
            ->line(__('emails.order_item_modified.intro', [
                'code' => $this->order->code,
                'product' => $productName,
            ]));

        // Subcard de la reserva afectada (#251): refleja el estado ACTUAL del item (tras el cambio).
        $message->line(EmailProductCard::forItem($this->item));

        // Cambios descriptivos. Cada tipo tiene su línea con datos.
        if (isset($this->changes['slot_change'])) {
            $message->line(__('emails.order_item_modified.slot_change', [
                'old' => $this->changes['slot_change']['old'] ?? '—',
                'new' => $this->changes['slot_change']['new'] ?? '—',
            ]));
        }
        if (isset($this->changes['quantity_change'])) {
            $message->line(__('emails.order_item_modified.quantity_change', [
                'old' => $this->changes['quantity_change']['old'] ?? '—',
                'new' => $this->changes['quantity_change']['new'] ?? '—',
            ]));
        }
        if (isset($this->changes['product_change'])) {
            $message->line(__('emails.order_item_modified.product_change', [
                'old' => $this->changes['product_change']['old'] ?? '—',
                'new' => $this->changes['product_change']['new'] ?? '—',
            ]));
        }
        if (! empty($this->changes['event_data_change'])) {
            $message->line(__('emails.order_item_modified.event_data_change'));
        }
        if (! empty($this->changes['addon_change'])) {
            $message->line(__('emails.order_item_modified.addon_change'));
        }

        // Líneas financieras condicionales. Mantienen al cliente informado de lo
        // que verá al llegar a puerta o en el extracto.
        if ($this->extraDueCents !== null && $this->extraDueCents > 0) {
            $message->line(__('emails.order_item_modified.extra_due', [
                'amount' => number_format($this->extraDueCents / 100, 2, ',', '.'),
            ]));
        }
        // `#155`: la bajada, contada — el mismo vocabulario que la pantalla («pendiente de
        // devolverte»). T5 (§25.5): la frase describe el estado y las DOS salidas (tarjeta o
        // parque) SIN prometer canal ni correo — el registro del reembolso manual es OPCIONAL
        // (§20.5), y prometer un aviso que puede no existir era la sobre-promesa de `#285`. El
        // puntero de pantalla es «Mis pedidos»: «Mis reservas» no enseña importes desde `#130`.
        if ($this->pendingRefundCents !== null && $this->pendingRefundCents > 0) {
            $message->line(__('emails.order_item_modified.reduction_pending_refund', [
                'amount' => number_format($this->pendingRefundCents / 100, 2, ',', '.'),
            ]));
        }
        if ($this->gateCreditedCents !== null && $this->gateCreditedCents > 0) {
            $message->line(__('emails.order_item_modified.reduction_gate_credit', [
                'amount' => number_format($this->gateCreditedCents / 100, 2, ',', '.'),
            ]));
        }

        return $message
            ->action(__('emails.order_item_modified.action'), route('account.orders'))
            ->line(__('emails.order_item_modified.contact'));
    }
}
