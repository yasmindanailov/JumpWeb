<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\RedsysResponseCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Audit #114 (2026-05-28, G1) — Email cuando un pago Redsys es DENEGADO.
 *
 * Trigger: `RedsysReturnHandler` recibe `Ds_Response ≥ 0100` (vuelta firmada del navegador
 * o notificación on-line) sobre un Payment todavía pending. Se envía UNA SOLA VEZ por
 * Payment (idempotencia simétrica al `STATUS_PAID`, ver `RedsysReturnHandler` §rama Denied).
 *
 * Por qué este email:
 *  - El cliente puede haber cerrado la pestaña antes de ver el paso 10 ("pago denegado"):
 *    sin email no sabe qué pasó. Pierde confianza y duplica intentos.
 *  - El motivo (`Ds_Response`) se traduce a un texto humano vía `RedsysResponseCode`
 *    (manual §4, Anexo 2) — el cliente sabe si tiene que cambiar tarjeta, llamar al banco
 *    o simplemente reintentar.
 *
 * No bloqueante: se envía FUERA de la transacción del handler (un fallo SMTP no debe
 * deshacer una transición de estado Payment paid).
 *
 * Texto en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderPaymentDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public ?string $dsResponse = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reasonText = RedsysResponseCode::reasonText($this->dsResponse);

        return (new MailMessage)
            ->subject(__('emails.order_declined.subject', ['code' => $this->order->code]))
            ->greeting(__('emails.order_declined.greeting'))
            ->line(__('emails.order_declined.intro', ['code' => $this->order->code]))
            ->line(__('emails.order_declined.no_charge'))
            ->line(__('emails.order_declined.reason_prefix').' '.$reasonText)
            ->action(__('emails.order_declined.action'), route('account.orders'))
            ->line(__('emails.order_declined.contact'));
    }
}
