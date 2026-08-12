<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Audit #114 (G5) + sub-fase 7.2b ampliada (#139) — Email tras cancelación.
 *
 * Cancelar y reembolsar son operaciones INDEPENDIENTES (#139): este correo solo
 * confirma la cancelación del servicio. Si la cancelación va acompañada de
 * devolución, el cliente recibe además `OrderRefunded` con importe + plazo
 * bancario. Si la cancelación se acordó con el equipo en persona (canje por
 * entradas físicas, reagendar, etc.), el email NO promete reembolso —
 * justamente para no dar información incorrecta al cliente.
 *
 * Texto en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('emails.order_cancelled.subject', ['code' => $this->order->code]))
            ->greeting(__('emails.order_cancelled.greeting'))
            ->line(__('emails.order_cancelled.intro', ['code' => $this->order->code]))
            ->line(__('emails.order_cancelled.next_steps'))
            ->action(__('emails.order_cancelled.action'), route('account.orders'))
            ->line(__('emails.order_cancelled.contact'));
    }
}
