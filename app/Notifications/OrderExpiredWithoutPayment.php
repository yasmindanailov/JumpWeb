<?php

namespace App\Notifications;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\EmailSlip;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Audit #114 (2026-05-28, G2) — Email cuando una Order caduca SIN haber completado el pago.
 *
 * Trigger: el comando `orders:expire` detecta Order `pending` con `expires_at` cruzado
 * AND con un Payment `pending` asociado (= el cliente había intentado pagar pero la
 * notificación de Redsys nunca llegó). Caso típico:
 *  - Cliente paga, el banco captura, Redsys envía notificación pero la URL pública estaba
 *    caída → notificación perdida → orders:expire la caduca silenciosamente.
 *  - Cliente entra a Redsys pero cierra antes de pulsar pagar → Payment pending huérfano.
 *
 * Sin este email, el cliente NO sabe que su reserva ya no es válida. Si pagó realmente,
 * además necesita un canal para reclamar.
 *
 * NO se envía si:
 *  - La Order no tenía Payment (abandono limpio antes de la pasarela): no merece email.
 *  - La Order tenía Payment `failed` (ya recibió `OrderPaymentDeclined`): doble email es ruido.
 *
 * No bloqueante: el envío va en try/catch en `ExpireOrders` (un fallo SMTP no debe bloquear
 * el resto del comando).
 *
 * Texto en el idioma del usuario (User implementa HasLocalePreference).
 */
class OrderExpiredWithoutPayment extends Notification implements ShouldQueue
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
        return (new BrandedMailMessage($this))
            ->subject(__('emails.order_expired_without_payment.subject', ['code' => $this->order->code]))
            ->hero('emails.order_expired_without_payment', 'warn', EmailSlip::forOrder($this->order))
            ->line(__('emails.order_expired_without_payment.intro', ['code' => $this->order->code]))
            ->line(__('emails.order_expired_without_payment.no_charge'))
            ->line(__('emails.order_expired_without_payment.retry'))
            ->action(__('emails.order_expired_without_payment.action'), route('home'))
            // ❗ UNO DE LOS DOS ÚNICOS CORREOS QUE VENDEN (`#503`, el mapa del naranja): su botón
            // va en relleno de ACCIÓN y los otros diecinueve en tinta. `level` es la única palanca
            // que Laravel da aquí, porque `action()` no acepta color. Lo vigila `MailMoldTest::test_exactly_two_mails_carry_the_selling_button`.
            ->level('sell')
            ->line(__('emails.order_expired_without_payment.contact'));
    }
}
