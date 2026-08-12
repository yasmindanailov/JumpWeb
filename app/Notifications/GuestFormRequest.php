<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailProductCard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email que pide al cliente rellenar el POST-FORM de datos por invitado de UNA reserva de cumpleaños
 * (#217, INDIVIDUALIZADO POR RESERVA). Se envía —uno por reserva— cuando un pedido con uno o varios
 * packs queda PAGADO (online o manual): un pedido con dos cumpleaños manda dos emails, cada uno con
 * el enlace a SU post-form.
 *
 * El botón lleva a un enlace FIRMADO que da acceso al formulario SIN iniciar sesión — la firma HMAC
 * prueba la titularidad de ESA reserva y blinda contra IDOR. El enlace CADUCA en la fecha de la franja
 * de la reserva + 14 días (auditoría Fase 1, A7; ver `OrderItem::guestFormLinkExpiresAt`). El cliente
 * también puede rellenarlo desde "Mis pedidos" (autenticado). Llega en el idioma del usuario.
 */
class GuestFormRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrderItem $reservation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = (string) ($this->reservation->ticketType?->tr('name') ?? '');
        $code = (string) ($this->reservation->order?->code ?? '');

        return (new MailMessage)
            ->subject(__('emails.guest_form.subject', ['product' => $product, 'code' => $code]))
            ->greeting(__('emails.guest_form.greeting'))
            ->line(__('emails.guest_form.intro', ['product' => $product, 'code' => $code]))
            ->line(EmailProductCard::forItem($this->reservation))
            ->line(__('emails.guest_form.body'))
            ->action(
                __('emails.guest_form.action'),
                // Fuente ÚNICA del enlace firmado (compartida con el botón «Copiar enlace» del panel).
                $this->reservation->guestFormSignedUrl(),
            )
            ->line(__('emails.guest_form.outro'));
    }
}
