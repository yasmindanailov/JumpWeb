<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sub-fase 7.2e.1bis — Email tras CANCELACIÓN de un OrderItem (sin refund).
 *
 * Disparado por `cancelItemAction` POST-éxito. La cancelación es ortogonal al
 * reembolso (decisión #154 — alineación con el patrón del Order completo
 * 7.2b/#139): cancelar libera plaza y marca el item como terminal, pero NO
 * mueve dinero. Si procede devolución, el operador dispara la acción
 * `Reembolsar` por separado (que sí emite `OrderItemRefunded`).
 *
 * Texto NEUTRO sobre el reembolso ("si procede recibirás un correo aparte"),
 * análogo al de `OrderCancelled` para Order completo. Cita el producto
 * concreto para que el cliente sepa exactamente qué se ha cancelado del
 * pedido (importante en pedidos con múltiples items).
 */
class OrderItemCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public OrderItem $item,
        /**
         * Número de complementos del item que también quedaron cancelled
         * por cascada (sub-fase 7.2e.1bis4, decisión #157). El email
         * menciona la cascada cuando > 0 para que el cliente sepa que TODO
         * el producto (principal + extras) ha sido cancelado y no solo
         * el principal aislado.
         */
        public int $cascadedChildrenCount = 0,
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
        // Usar `tr('name')` para resolver el JSON multi-locale (bug fix de
        // 7.2e.1: `->name` devuelve el array crudo y rompe `__()` con mb_substr).
        $productName = $this->item->ticketType?->tr('name')
            ?? __('emails.order_item_cancelled.product_fallback', ['id' => $this->item->id]);

        $message = (new MailMessage)
            ->subject(__('emails.order_item_cancelled.subject', ['code' => $this->order->code, 'product' => $productName]))
            ->greeting(__('emails.order_item_cancelled.greeting'))
            ->line(__('emails.order_item_cancelled.intro', [
                'product' => $productName,
                'code' => $this->order->code,
            ]));

        // Si la cancelación arrastró complementos (cascada), avisamos
        // explícitamente al cliente para que sepa que TODO el producto
        // (principal + extras) queda cancelado, no solo el principal.
        if ($this->cascadedChildrenCount > 0) {
            $message->line(__('emails.order_item_cancelled.cascaded_addons', [
                'count' => $this->cascadedChildrenCount,
            ]));
        }

        return $message
            ->line(__('emails.order_item_cancelled.next_steps'))
            ->action(__('emails.order_item_cancelled.action'), route('account.orders'))
            ->line(__('emails.order_item_cancelled.contact'));
    }
}
