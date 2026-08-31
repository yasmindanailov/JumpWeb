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
 * Sub-fase 7.2e cimientos — Email tras reembolso PARCIAL ligado a un OrderItem.
 *
 * Disparado por `Order::executePartialRefund` solo POST-éxito (REST 0900 o
 * mode=manual). Caso típico:
 *  - Operador cancela un item del pedido → refund REST automático del importe
 *    del item + `alsoCancelledItem=true` → este email con la línea de cancelación.
 *  - Operador reembolsa importe de cortesía sobre un item → refund REST por
 *    importe libre + `alsoCancelledItem=false` → este email sin cancelación.
 *
 * Texto explícito sobre importe + nombre del item + plazo bancario — única vía
 * documental para el cliente de qué se devolvió y por qué item. Diferencia
 * deliberada con `OrderRefunded` (que es a nivel pedido completo): aquí citamos
 * el producto concreto.
 */
class OrderItemRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  bool  $manualRefund  T5 (`cumple-mixto.md` §25.5, Q3): el reembolso se registró como
     *                              `PaymentRefund::MODE_MANUAL` — dinero devuelto FUERA de la
     *                              pasarela (el circuito de parque de §20.5). Hasta el 2026-08-31
     *                              este correo prometía «lo verás en tu tarjeta (3-5 días)» también
     *                              por dinero entregado en mano: el panel distinguía el modo y el
     *                              correo no podía.
     */
    public function __construct(
        public Order $order,
        public OrderItem $item,
        public int $refundedAmountCents,
        public bool $alsoCancelledItem = false,
        public bool $manualRefund = false,
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
        // Nombre humano del producto: usa `tr()` para resolver el JSON
        // multi-locale `ticket_types.name` al idioma del cliente; fallback al
        // ID interno si la relación está rota (defensivo).
        $productName = $this->item->ticketType?->tr('name')
            ?? __('emails.order_item_refunded.product_fallback', ['id' => $this->item->id]);

        $amount = number_format($this->refundedAmountCents / 100, 2, ',', '.');

        $message = (new MailMessage)
            ->subject(__('emails.order_item_refunded.subject', ['code' => $this->order->code]))
            ->greeting(__('emails.order_item_refunded.greeting'))
            ->line(__('emails.order_item_refunded.intro', ['code' => $this->order->code]))
            ->line(EmailProductCard::forItem($this->item))
            ->line(__('emails.order_item_refunded.amount', [
                'product' => $productName,
                'amount' => $amount,
            ]));

        if ($this->alsoCancelledItem) {
            $message->line(__('emails.order_item_refunded.also_cancelled', [
                'product' => $productName,
            ]));
        }

        // La línea del CANAL sigue al modo real del registro: prometer la tarjeta por dinero
        // devuelto en el parque era la sobre-promesa nº 2 de la T5 (§25.2).
        return $message
            ->line(__($this->manualRefund ? 'emails.order_item_refunded.when_manual' : 'emails.order_item_refunded.when'))
            ->action(__('emails.order_item_refunded.action'), route('account.orders'))
            ->line(__('emails.order_item_refunded.contact'));
    }
}
