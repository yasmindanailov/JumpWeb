<?php

namespace App\Notifications;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Services\EmailProductCard;
use App\Domain\Booking\Services\PostFormAddons;
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

        $message = (new MailMessage)
            ->subject(__('emails.guest_form.subject', ['product' => $product, 'code' => $code]))
            ->greeting(__('emails.guest_form.greeting'))
            ->line(__('emails.guest_form.intro', ['product' => $product, 'code' => $code]))
            ->line(EmailProductCard::forItem($this->reservation))
            ->line(__('emails.guest_form.body'));

        // D15 · **el único correo que lleva a este formulario tiene que nombrar lo que ahí se puede
        // pedir**, o la feature se construye entera y no se vende un solo cubo de refrescos. No se
        // añade un envío nuevo: se añade una frase al que ya sale.
        //
        // ⚠️ **Condicionada a que ESTA reserva tenga extras abiertos**, nunca al catálogo: una
        // instalación sin enganches `postform` —el caso por defecto, y hoy los 29— prometería algo
        // que no existe, y el cliente buscaría en su formulario una sección que no está.
        if (app(PostFormAddons::class)->offerableFor($this->reservation)->isNotEmpty()) {
            $message->line(__('emails.guest_form.extras'));
        }

        return $message
            ->action(
                __('emails.guest_form.action'),
                // Fuente ÚNICA del enlace firmado (compartida con el botón «Copiar enlace» del panel).
                $this->reservation->guestFormSignedUrl(),
            )
            ->line(__('emails.guest_form.outro'));
    }
}
